<?php

namespace App\Http\Controllers;

use App\Models\RgstrLmtVlItm;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RgstrLmtVlItmController extends Controller
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


        $rgstrLmtVlItm = new RgstrLmtVlItm();
        $rgstrLmtVlItm->register_master_id = $request->register_master_id;
        return response()->json($rgstrLmtVlItm->getRgstrLmtVlItm());
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

        if( $request->limit_value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
        
        $rgstrLmtVlItm          = RgstrLmtVlItm::where('rgstr_id','=',$request->register_master_id)->where('id','=',$request->register_limit_id)->first();
        $rgstrLmtVlItm->value   = $request->limit_value;
        if( $rgstrLmtVlItm->save() ){
            return response()->json(array("success" => "Limite do cadastro atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o limite do cadastro"));
        }
    }
}
