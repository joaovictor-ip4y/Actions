<?php

namespace App\Http\Controllers;

use App\Models\PayerDetail;
use App\Models\Account;
use App\Libraries\Facilites;
use App\Models\Payer;
use App\Services\Charge\PayerService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDF;

class PayerDetailController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [236, 296, 382];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerDetail = new PayerDetail();
        $payerDetail->register_master_id = $request->header('registerId');
        $payerDetail->registerOnlyActive = $request->registerOnlyActive;
        $payerDetail->payer_cpf_cnpj     = $request->payer_cpf_cnpj;
        $payerDetail->payer_name         = $request->payer_name;

        return response()->json($payerDetail->getPayers());
    }

    protected function exportChargePayer(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [236, 296, 382];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerDetail = new PayerDetail();
        $payerDetail->register_master_id = $request->header('registerId');
        $payerDetail->registerOnlyActive = $request->registerOnlyActive;
        $payerDetail->payer_cpf_cnpj     = $request->payer_cpf_cnpj;
        $payerDetail->payer_name         = $request->payer_name;
        $items = [];

        foreach($payerDetail->getPayers() as $movementData ){
            array_push($items, (object) [
                'payer_cpf_cnpj' => Facilites::mask_cpf_cnpj($movementData->payer_cpf_cnpj),
                'payer_name'     => $movementData->payer_name,
                'created_at'     => \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y'),
            ]);
        }

        $data = (object) array(
            "movement_data" => $items,
        );

        $file_name = "Cadastro_Sacado.pdf";
        $pdf       = PDF::loadView('reports/charge_payer', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function searchPayer(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerDetail                     = new PayerDetail();
        $payerDetail->master_id          = $checkAccount->master_id;
        $payerDetail->register_master_id = $request->header('registerId');
        $payerDetail->search             = $request->search;

        $ret['results'] = $payerDetail->searchPayer();

        return response()->json($ret);
    }


    protected function searchPayerV2(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $payerDetail                     = new PayerDetail();
        $payerDetail->master_id          = $checkAccount->master_id;
        $payerDetail->register_master_id = $request->header('registerId');
        $payerDetail->search             = $request->search;
        $payerDetail->limit              = $request->limit;

        $ret['results'] = $payerDetail->searchPayer();

        return response()->json($ret);
    }


    protected function searchPayerWithPayerDetailId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerDetail                     = new PayerDetail();
        $payerDetail->master_id          = $checkAccount->master_id;
        $payerDetail->register_master_id = $request->header('registerId');
        $payerDetail->search             = $request->search;

        $ret['results'] = $payerDetail->searchPayerWithPayerDetailId();

        return response()->json($ret);
    }


    protected function checkPayer(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerService = new PayerService();
        $payerService->payerData = (object) [
            "cpf_cnpj" => $request->cpf_cnpj,
            "master_id" => $checkAccount->master_id,
            "register_master_id" => $request->header('registerId')

        ];
        $payerCheck = $payerService->payerCheck();
        if($payerCheck->success){
            return response()->json(array("success" => $payerCheck->message, "payer_detail_data" => $payerCheck->payer_detail_data));
        } else {
            return response()->json(array("error" => $payerCheck->message, "payer_detail_data" => null));
        }
    }

    protected function createPayerDetail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [237, 297, 383];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerService = new PayerService();
        $payerService->payerData = (object) [
            "cpf_cnpj"             => $request->cpf_cnpj,
            "name"                 => $request->name,
            "master_id"            => $checkAccount->master_id,
            "register_master_id"   => $request->header('registerId'),
            "fantasy_name"         => $request->fantasy_name,
            "state_registration"   => $request->state_registration,
            "observation"          => "Informado pelo usuário",
            "address_state_id"     => $request->address_state_id,
            "address_public_place" => $request->address_public_place,
            "address"              => $request->address,
            "address_number"       => $request->address_number,
            "address_complement"   => $request->address_complement,
            "address_district"     => $request->address_district,
            "address_city"         => $request->address_city,
            "address_zip_code"     => $request->address_zip_code,
            "address_observation"  => "Informado pelo usuário",
            "phone_number"         => $request->phone_number,
            "phone_observation"    => "Informado pelo usuário",
            "email_address"        => $request->email_address,
            "email_observation"    => "Informado pelo usuário"
        ];

        $createReturnPayerDetail = $payerService->createReturnPayerDetail();

        if($createReturnPayerDetail->success){
            return response()->json(array("success" => $createReturnPayerDetail->message, "payer_detail_data" => $createReturnPayerDetail->payer_detail_data));
        } else {
            return response()->json(array("error" => $createReturnPayerDetail->message, "payer_detail_data" => null));
        }
    }

    public function getByPayerId(int $id, Request $request): JsonResponse
    {
        try {
            $payer = Payer::with('detail')
                ->when($request->register_master_id, function ($query) use ($request) {
                    return $query
                        ->join('payer_details as pd', 'pd.payer_id', '=', 'payers.id')
                        ->where('pd.register_master_id', '=', $request->register_master_id);
                })
                ->findOrFail($id);
            return response()->json(['data' => $payer]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
