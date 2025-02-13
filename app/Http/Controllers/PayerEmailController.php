<?php

namespace App\Http\Controllers;

use App\Models\PayerEmail;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PayerEmailController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [240, 300, 386];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerEmail                       = new PayerEmail();
        $payerEmail->payer_id             = $request->payer_id;
        if($request->header('registerId') == ''){
            $payerEmail->register_master_id  = $request->register_master_id;
        } else {
            $payerEmail->register_master_id  = $request->header('registerId');
            $payerEmail->registerOnlyActive  = $request->register_only_active;
        }
        $payerEmail->onlyActive = $request->only_active;
        return response()->json($payerEmail->getPayerEmail());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [240, 300, 386];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        //Verificar se sacado pertence ao registro
        $main = 0;
        if($request->email_main == 1){
            PayerEmail::removeAllMainEmail($request->payer_id);
            $main = 1;
        }

        if(PayerEmail::where('payer_detail_id','=',$request->payer_id)->count() == 0){
            $main = 1;
        }

        if($payerEmail = PayerEmail::Create([
            'payer_detail_id' => $request->payer_id,
            'email'           => $request->email_address,
            'main'            => $main,
            'observation'     => $request->email_observation,
            'created_at'      => \Carbon\Carbon::now()
        ])){
            return response()->json(array(
                "success" => "E-Mail cadastrado com sucesso",
                "payer_email_id" =>  $payerEmail->id
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o E-Mail"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [301, 241, 387];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $payerEmail = PayerEmail::where('payer_detail_id','=',$request->payer_detail_id)->where('id','=',$request->payer_email_id)->first();
        if($payerEmail->main == 1){
            return response()->json(array("error" => "Não é possível excluir um email principal, cadastre um novo e-mail principal para excluir este"));
        }
        if($request->header('registerId') == ''){
            $payerEmail->deleted_at = \Carbon\Carbon::now();
        } else {
            $payerEmail->register_deleted_at = \Carbon\Carbon::now();
        }
        if($payerEmail->save()){
            return response()->json(array("success" => "Email excluído com sucesso", "payer_email_id" =>  $payerEmail->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o email"));
        }
    }

}
