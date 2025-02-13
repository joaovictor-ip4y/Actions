<?php

namespace App\Http\Controllers;

use App\Models\AccntTxVlItms;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class AccntTxVlItmsController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [49];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accntTxVlItm = new AccntTxVlItms();
        $accntTxVlItm->accnt_id   = $request->account_id;
        $accntTxVlItm->onlyActive = $request->onlyActive;
        return response()->json($accntTxVlItm->getAccntTxVlItms());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [52];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        

        if( $request->value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }

        if( $request->percentage < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar porcentagem negativa."));
        }

        $accountTxVlItm             = AccntTxVlItms::where('accnt_id','=',$request->accnt_id)->where('id','=',$request->id)->first();
        $accountTxVlItm->value      = $request->value;
        $accountTxVlItm->percentage = $request->percentage;
        if( $accountTxVlItm->save() ){
            return response()->json(array("success" => "Tarifa da conta atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a tarifa da conta"));
        }
    }
}