<?php

namespace App\Http\Controllers;

use App\Models\LmtGrpItm;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LmtGrpItmController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [159];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $lmtGrpItm = new LmtGrpItm();
        $lmtGrpItm->limit_group_id = $request->limit_group_id;
        $lmtGrpItm->onlyActive     = $request->onlyActive;
        $lmtGrpItm->master_id      = null;
        return response()->json($lmtGrpItm->getLmtGrpItm());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [162];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $request->default_value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
                
        $lmtGrpItm                     = LmtGrpItm::where('id','=',$request->id)->where('limit_group_id',$request->limit_group_id)->first();
        $lmtGrpItm->default_value      = $request->default_value;
        if( $lmtGrpItm->save() ){
            return response()->json(array("success" => "Limite do grupo de limite atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o limite do grupo de limite"));
        }
    }
}
