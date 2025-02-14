<?php

namespace App\Http\Controllers;

use App\Models\RgstrTxVlItm;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RgstrTxVlItmController extends Controller
{

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $rgstrTxVlItm = new RgstrTxVlItm();
        $rgstrTxVlItm->register_master_id = $request->register_master_id;
        return response()->json($rgstrTxVlItm->getRgstrTxVlItm());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $request->tax_value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
        
        if( $request->tax_percentage < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar porcentagem negativa."));
        }
        
        $rgstrTxVlItm             = RgstrTxVlItm::where('rgstr_id','=',$request->register_master_id)->where('id','=',$request->register_tax_id)->first();
        $rgstrTxVlItm->value      = $request->tax_value;
        $rgstrTxVlItm->percentage = $request->tax_percentage;
        if( $rgstrTxVlItm->save() ){
            return response()->json(array("success" => "Tarifa do cadastro atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a tarifa do cadastro"));
        }
    }
}
