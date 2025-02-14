<?php

namespace App\Http\Controllers;

use App\Models\MoneyPlusBankAccountPixKey;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\BancoMoneyPlus\BancoMoneyPlusClass;

use Illuminate\Http\Request;

class MoneyPlusBankAccountPixKeyController extends Controller
{
    protected function store(Request $request)
    {        
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        //$accountCheckService->permission_id = [176, 257];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //        

        $bancoMoneyPlusClass = new BancoMoneyPlusClass();

        $bancoMoneyPlusClass->payload = (object) [
            'account_id' => $checkAccount->account_id, 
            'pix_key_type_id' => $request->pix_key_type_id,
            "pix_key" => $request->pix_key
        ];

        $createBmpPixKey = $bancoMoneyPlusClass->createBmpAliasAccountPixKey();



        if( ! $createBmpPixKey->success ){
            return response()->json(array("error" => $createBmpPixKey->message_pt_br));
        }

        return response()->json(array("success" => $createBmpPixKey->message_pt_br));

    }

    protected function show(Request $request, MoneyPlusBankAccountPixKey $moneyPlusBankAccountPixKey)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        //$accountCheckService->permission_id = [176, 257];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }

        $moneyPlusBankAccountPixKey->account_id = $checkAccount->account_id;
        $moneyPlusBankAccountPixKey->onlyActive = 1;

        return response()->json($moneyPlusBankAccountPixKey->get());
    }

    protected function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        //$accountCheckService->permission_id = [176, 257];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        

        //$checkAccount  = (object) ["account_id" => 71];

        $bancoMoneyPlusClass = new BancoMoneyPlusClass();
        $bancoMoneyPlusClass->payload = (object) [
            'account_id' => $checkAccount->account_id, 
            'pix_key_id' => $request->pix_key_id,
            'pix_key_uuid' => $request->pix_key_uuid,
        ];

        $deleteBmpPixKey = $bancoMoneyPlusClass->deleteBmpAliasAccountPixKey();

        if( ! $deleteBmpPixKey->success ){
           return response()->json(array("error" => $deleteBmpPixKey->message_pt_br));
        }

        return response()->json(array("success" => $deleteBmpPixKey->message_pt_br));
    }

    public function listAccountKey(Request $request, BancoMoneyPlusClass $bancoMoneyPlusClass)
    {
        return $bancoMoneyPlusClass->getBmpPixKey();
    }
}
