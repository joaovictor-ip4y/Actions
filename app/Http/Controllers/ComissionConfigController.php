<?php

namespace App\Http\Controllers;

use App\Models\ComissionConfig;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class ComissionConfigController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [83];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $comission_config = new ComissionConfig();
        $comission_config->master_id = $checkAccount->master_id;
        return response()->json($comission_config->get());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [83];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        if(ComissionConfig::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->count() > 0){
            $comissionConfig = ComissionConfig::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first();
            $comissionConfig->percentage = $request->percentage;
            if($comissionConfig->save()){
                return response()->json(array("success" => "Configuração de imposto da comissão alterada com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao alterar a configuração de imposto da comissão, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" => "Configuração de imposto da comissão não localizada"));
        }
    }
}
