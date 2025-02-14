<?php

namespace App\Http\Controllers;

use App\Models\PayrollConfig;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayrollConfigController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [211];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payroll_config             = new PayrollConfig();
        $payroll_config->account_id = $checkAccount->account_id;
        $payroll_config->master_id  = $checkAccount->master_id;
        $payroll_config->onlyActive = $request->onlyActive;
        return response()->json($payroll_config->get());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [211];
            $checkAccount = $accountCheckService->checkAccount();
            if(!$checkAccount->success){
                return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

         // ----------------- Validate received data ----------------- //
         $validator = Validator::make($request->all(), [
            'description'=> ['required', 'string'],
        ],[
            'description.required' => 'Informe o nome do setor.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if($payroll_config = PayrollConfig::where('description','=',$request->description)->where('account_id','=',$checkAccount->account_id)->whereNull('deleted_at')->first()){
            return response()->json(array("error" => "Setor já cadastrado"));
        }else{
           if($payroll_config = PayrollConfig::where('description','=',$request->description)->where('account_id','=',$checkAccount->account_id)->whereNotNull('deleted_at')->first()){
                $payroll_config->description = $request->description;
                $payroll_config->identify_id = $request->identify_id;
                $payroll_config->account_id  = $checkAccount->account_id;
                $payroll_config->master_id   = $checkAccount->master_id;
                $payroll_config->deleted_at  = null;
                if(!$payroll_config->save()){
                    return response()->json(["error"=>"Ocorreu um erro ao cadastrar o setor, por favor tente novamente mais tarde"]);
                }else{
                    return response()->json(["success"=>"Setor cadastrado com sucesso"]);
                }
            }else{
                if(!PayrollConfig::create([
                    'description' => $request->description,
                    'identify_id' => $request->identify_id,
                    'account_id'  => $checkAccount->account_id,
                    'master_id'   => $checkAccount->master_id,
                ])){
                    return response()->json(["error"=>"Ocorreu um erro ao cadastrar o setor, por favor tente novamente mais tarde"]);
                }else{
                    return response()->json(["success"=>"Setor cadastrado com sucesso"]);
                }
            }
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [213];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
        ],[
            'id.required' => 'Informe o id do setor.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //


        $PayrollConfig = PayrollConfig::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->first();
        $PayrollConfig->deleted_at = \Carbon\Carbon::now();
        if($PayrollConfig->save()){
            return response()->json(array("success" => "Setor excluído com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o setor, por favor tente novamente mais tarde"));
        }
    }
}
