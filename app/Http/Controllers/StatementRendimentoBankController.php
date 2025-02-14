<?php

namespace App\Http\Controllers;

use App\Models\StatementRendimentoBank;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class StatementRendimentoBankController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [85];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $statement = new StatementRendimentoBank();
        $statement->master_id         = $checkAccount->master_id;
        $statement->created_at_start  = $request->start_dt;
        $statement->created_at_end    = $request->end_dt;
        $statement->onlyActive        = $request->onlyActive;
        return response()->json($statement->getStatement());
    }
}
