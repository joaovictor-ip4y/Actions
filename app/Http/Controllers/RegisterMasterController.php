<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\RegisterMaster;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class RegisterMasterController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $resgisterMaster = new RegisterMaster();
        $resgisterMaster->master_id = $checkAccount->master_id;
        return response()->json($resgisterMaster->getResgisterMaster());
    }

    protected function updateStatus(Request $request)
    {  
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [8];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $register_master = RegisterMaster::where('id','=',$request->register_master_id)->where('master_id','=',$checkAccount->master_id)->first();
        $register_master->status_id = $request->register_status_id;
        $register_master->save();
        if($request->update_accounts == 1){
            Account::addStatusId($checkAccount->master_id,$request->register_master_id, $request->register_status_id);
            return response()->json(["success" => "Status do cadastro e da(s) conta(s) atualizado(s) com sucesso"]);
        }
        return response()->json(["success" => "Status do cadastro atualizado com sucesso"]);
    }
}
