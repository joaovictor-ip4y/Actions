<?php

namespace App\Http\Controllers;

use App\Models\SystemFunctionMaster;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class SystemFunctionMasterController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $systemFunctionMaster            = new SystemFunctionMaster();
        $systemFunctionMaster->master_id = $checkAccount->master_id;
        return response()->json($systemFunctionMaster->get());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $systemFunctionMaster = SystemFunctionMaster::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first();
        $systemFunctionMaster->available = $request->available;
        if($systemFunctionMaster->save()){
            return response()->json(array("success" => "Função atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualiza a função, por favor tente novamente mais tarde"));
        }

    }

    protected function getChargeRegistrationAPI(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $systemFunctionMaster = new SystemFunctionMaster();
        return response()->json($systemFunctionMaster->getChargeRegistrationAPI());
    }

    protected function getChargeRegistrationAddMoney(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $systemFunctionMaster               = new SystemFunctionMaster();
        $systemFunctionMaster->master_id    = $checkAccount->master_id;
        return response()->json(array("success" => $systemFunctionMaster->getChargeRegistrationAddMoney()));
    }

    protected function editChargeRegistrationAddMoney(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$systemFunctionMaster = SystemFunctionMaster::where('system_function_id', '=', 2)->where('master_id', '=', $checkAccount->master_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar a função do sistema"));
        }

        $systemFunctionMaster->charge_registration_api_id = $request->charge_registration_api_id;

        if ($systemFunctionMaster->save()) {
            return response()->json(array("success" => "API padrão de emissão de boleto para depósito definida com sucesso"));
        }
        return response()->json(array("error" => "Não foi possível definir a API padrão de emissão de boleto para depósito"));
    }

    protected function getChargeRegistrationCharge(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $systemFunctionMaster               = new SystemFunctionMaster();
        $systemFunctionMaster->master_id    = $checkAccount->master_id;
        return response()->json(array("success" => $systemFunctionMaster->getChargeRegistrationCharge()));
    }

    protected function editChargeRegistrationCharge(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$systemFunctionMaster = SystemFunctionMaster::where('system_function_id', '=', 6)->where('master_id', '=', $checkAccount->master_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar a função do sistema"));
        }

        $systemFunctionMaster->charge_registration_api_id = $request->charge_registration_api_id;

        if ($systemFunctionMaster->save()) {
            return response()->json(array("success" => "API padrão de emissão de cobrança definida com sucesso"));
        }
        return response()->json(array("error" => "Não foi possível definir a API padrão de emissão de cobrança"));
    }

    protected function getEffectiveTransferAPI(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [171];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $systemFunctionMaster = new SystemFunctionMaster();
        return response()->json($systemFunctionMaster->getEffectiveTransferAPI());
    }
}
