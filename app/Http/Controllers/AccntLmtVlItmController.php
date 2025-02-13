<?php

namespace App\Http\Controllers;

use App\Models\AccntLmtVlItm;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class AccntLmtVlItmController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [47, 362, 366];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accntLmtVlItms             = new AccntLmtVlItm();
        $accntLmtVlItms->accnt_id   = $checkAccount->account_id;
        $accntLmtVlItms->onlyActive = $request->onlyActive;
        $accntLmtVlItms->is_update_by_account = $request->is_update_by_account;
        return response()->json($accntLmtVlItms->getAccntLmtVlItms());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [50];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $request->value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }

        $accountLmtVlItm          = AccntLmtVlItm::where('accnt_id','=',$request->accnt_id)->where('id','=',$request->id)->first();
        $accountLmtVlItm->value   = $request->value;
        if( $accountLmtVlItm->save() ){
            return response()->json(array("success" => "Limite da conta atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o limite da conta"));
        }
    }
}