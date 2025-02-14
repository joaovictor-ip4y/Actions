<?php

namespace App\Http\Controllers;

use App\Models\TaxConfig;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class TaxConfigController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [167];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $tax_config = new TaxConfig();
        $tax_config->master_id = $checkAccount->master_id;
        return response()->json($tax_config->get());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [170];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        if(TaxConfig::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->count() > 0){
            $taxConfig = TaxConfig::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first();
            $taxConfig->percentage = $request->percentage;
            if($taxConfig->save()){
                return response()->json(array("success" => "Configuração de imposto alterada com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao alterar a configuração de imposto, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" => "Configuração de imposto não localizada"));
        }
    }
}
