<?php

namespace App\Http\Controllers;

use App\Classes\ImportCSVClass;
use App\Models\Register;
use App\Models\RegisterDetail;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RegisterDetailController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDetail = new RegisterDetail();
        $registerDetail->register_master_id = $request->register_master_id;
        $registerDetail->master_id          = $checkAccount->master_id;
        $registerDetail->name               = $request->name;
        $registerDetail->cpf_cnpj           = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $registers                          = $registerDetail->getRegisterDetailed();

        if($request->onlyActive == 1){
            return response()->json(array("success" => "", "register" => $registers));
        } else {
            switch(sizeof($registers)){
                case 1:
                    return response()->json(array("success" => "", "register" => $registers[0]));
                break;
                default:
                    return response()->json(array("success" => "", "register" => $registers));
                break;
            }
        }
    }

    protected function getForSelect(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $registerDetail = new RegisterDetail();
        $registerDetail->master_id = $checkAccount->master_id;
        return $registerDetail->getRegisterForSelect();
    }

    protected function getRegisterData(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDetail                     = new RegisterDetail();
        $registerDetail->master_id          = $checkAccount->master_id;
        $registerDetail->register_master_id = $request->register_master_id;
        $registerDetail->register_id        = $request->register_id;
        $registerDetail->register_detail_id = $request->register_detail_id;
        $registerDetail->user_master_id     = $request->user_master_id;
        $registerDetail->user_id            = $request->user_id;
        $registerDetail->status_id          = $request->status_id;
        $registerDetail->cpf_cnpj           = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $registerDetail->name               = $request->name;
        $registerDetail->managers_ids       = $request->managers_ids;
        $registerDetail->account_numbers    = $request->account_numbers;
        $registerDetail->phone              = preg_replace( '/[^0-9]/', '', $request->phone);
        $registerDetail->email              = $request->email;
        return response()->json( array("success" => "", "data" => $registerDetail->getRegisterData()));
    }

    protected function updatePEP(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $fileName = 'PEP_072021.csv';

        if (!Storage::disk('public')->put('/'.'CSV_file'.'/'.$fileName, base64_decode($request->file64))) {
            return response()->json(array("error"=>"Ocorreu uma falha ao fazer o upload do arquivo, por favor, tente mais tarde"));
        }

        $path = Storage::disk('public')->path('CSV_file'.'\\'.$fileName);

        $import_csv = new ImportCSVClass();

        $import_csv->file = "$path";

        $csv = $import_csv->import();

        if (!$csv->success) {
            return (object) [
                "success" => false,
                "data" => null
            ];
        }

        $value = mb_convert_encoding($csv->data, 'UTF-8', 'UTF-8');

        $csv_data = [];

        array_shift($value);

        $registerDetail = new RegisterDetail();

        foreach ($value as $data) {

            if ($register = Register::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '', $data[0]))->first()){

                $registerDetail->cpf_cnpj   = $register->cpf_cnpj;
                $registers_detail           = $registerDetail->getRegisterDetailed()[0];

                array_push($csv_data, (object)[
                    "register_master_id"        => $registers_detail->register_master_id,
                    "cpf_cnpj"                  => $registers_detail->cpf_cnpj,
                    "name"                      => $registers_detail->name,
                    "created_at"                => $registers_detail->created_at ? (\Carbon\Carbon::parse($registers_detail->created_at)->format('Y-m-d')) : "",
                    "pep_function_description"  => $data[3],
                    "pep_function_level"        => $data[4],
                    "pep_public_agency"         => $data[5],
                    "pep_start"                 => $data[6] ? \Carbon\Carbon::createFromFormat('d/m/Y', $data[6])->format('Y-m-d') : "",
                    "pep_end"                   => $data[7] ? \Carbon\Carbon::createFromFormat('d/m/Y', $data[7])->format('Y-m-d') : "",
                    "pep_grace_period"          => $data[8] ? \Carbon\Carbon::createFromFormat('d/m/Y', $data[8])->format('Y-m-d') : "",
                ]);
            }
        }

        if (Storage::disk('public')->delete('/'.'CSV_file'.'/'.$fileName)) {
            return response()->json(array("success" => "Planilha importada com sucesso", "data" => $csv_data));
        } else {
            return response()->json(array("error" => "Ocorreu uma falha ao excluir o arquivo do servidor, por favor, tente mais tarde"));
        }
    }

    protected function setRisk(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'risk_id'              => ['required', 'integer']
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'risk_id.required'     => 'É obrigatório informar o risk_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if(!$registerDetail = RegisterDetail::where('id', '=', $request->id)
        ->first()) {
            return response()->json(["error" => "Cadastro não localizado, por favor verifique os dados informados e tente novamente."]);
        }

        $registerDetail->risk_id = $request->risk_id;

        if($registerDetail->save()) {
            return response()->json(["success" => "Risco definido com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar o risco."]);
    }

    protected function searchRegister(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDetail = new RegisterDetail();
        $registerDetail->master_id = $checkAccount->master_id;
        $registerDetail->register_master_id = $request->header('registerId');
        $registerDetail->search = $request->search;

        $ret['results'] = $registerDetail->searchRegister();

        return response()->json($ret);
    }
}
