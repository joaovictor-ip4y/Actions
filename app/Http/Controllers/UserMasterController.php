<?php

namespace App\Http\Controllers;

use App\Models\UserMaster;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class UserMasterController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [128];
        $checkAccount                        = $accountCheckService->checkAccount(); 
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $userMaster             = new UserMaster();
        $userMaster->master_id  = $checkAccount->master_id;
        $userMaster->onlyActive = $request->onlyActive;

        $cpf_cnpj = preg_replace( '/[^0-9]/is', '', $request->cpf_cnpj );
        $userMaster->cpf_cnpj = $cpf_cnpj;
        
        return response()->json($userMaster->getUserMaster());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [132];
        $checkAccount                        = $accountCheckService->checkAccount(); 
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $userMaster = UserMaster::where('id','=',$request->user_id)->where('master_id','=',$checkAccount->master_id)->first();
        if($userMaster->count() > 0){
            if( $userMaster->user_name != $request->user_name){
                $userMaster->user_name = $request->user_name;
                if($userMaster->save()){
                    return response()->json(array("success" => "Usuário atualizado com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao atualizar o usuário, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("success" => "Usuário atualizado com sucesso"));
            }
                
        } else {
            return response()->json(array("error" => "Usuário não localizado"));
        }
    }

}
