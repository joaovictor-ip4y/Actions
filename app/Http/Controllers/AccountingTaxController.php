<?php

namespace App\Http\Controllers;

use App\Models\AccountingTax;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class AccountingTaxController extends Controller
{
    protected function get(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id = [4];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //
         
        $account_tax = new AccountingTax();
        return response()->json($account_tax->get());
    }
}
