<?php

namespace App\Http\Controllers;

use App\Models\SimpleChargeHistory;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class SimpleChargeHistoryController extends Controller
{

    protected function getSimpleChargeHistory(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleChargeHistory                 = new SimpleChargeHistory();
        $simpleChargeHistory->accountId      = $checkAccount->account_id;
        $simpleChargeHistory->masterId       = $checkAccount->master_id;
        $simpleChargeHistory->simpleChargeId = $request->simple_charge_id;

        return response()->json($simpleChargeHistory->getSimpleChargeHistory());

    }

}
