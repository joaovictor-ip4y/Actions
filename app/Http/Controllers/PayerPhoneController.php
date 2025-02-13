<?php

namespace App\Http\Controllers;

use App\Models\PayerPhone;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PayerPhoneController extends Controller
{
    
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [238, 298, 384];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerPhone                       = new PayerPhone();
        $payerPhone->payer_id             = $request->payer_id;
        if($request->header('registerId') == ''){
            $payerPhone->register_master_id  = $request->register_master_id;
        } else {
            $payerPhone->register_master_id  = $request->header('registerId');
            $payerPhone->registerOnlyActive  = $request->register_only_active;
        }
        $payerPhone->onlyActive   = $request->only_active;
        return response()->json($payerPhone->getPayerPhone());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [238, 298, 384];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        //verificar se sacado pertence ao registro
        $main = 0;
        if($request->phone_main == 1){
            PayerPhone::removeAllMainPhone($request->payer_id);
            $main = 1;
        }

        if(PayerPhone::where('payer_detail_id','=',$request->payer_id)->count() == 0){
            $main = 1;
        }

        if($payerPhone = PayerPhone::Create([
            'payer_detail_id'    => $request->payer_id,
            'contact_type_id'    => $request->phone_contact_type_id,
            'phone_type_id'      => $request->phone_type_id,
            'number'             => $request->phone_number,
            'main'               => $main,
            'observation'        => $request->phone_observation,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            return response()->json(array(
                "success" => "Telefone cadastrado com sucesso",
                "payer_phone_id" =>  $payerPhone->id
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o telefone"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [239, 299, 385];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerPhone = PayerPhone::where('payer_detail_id','=',$request->payer_detail_id)->where('id','=',$request->payer_phone_id)->first();
        if($payerPhone->main == 1){
            return response()->json(array("error" => "Não é possível excluir um telefone principal, cadastre um novo telefone principal para excluir este"));
        }
        if($request->header('registerId') == ''){
            $payerPhone->deleted_at = \Carbon\Carbon::now();
        } else {
            $payerPhone->register_deleted_at = \Carbon\Carbon::now();
        }
        if($payerPhone->save()){
            return response()->json(array("success" => "Telefone excluído com sucesso", "payer_phone_id" =>  $payerPhone->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o telefone"));
        }
    }
}
