<?php

namespace App\Http\Controllers;

use App\Models\TitleStatementRendimentoBank;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class TitleStatementRendimentoBankController extends Controller
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

       $titleStatement = new TitleStatementRendimentoBank();
       $titleStatement->master_id         = $checkAccount->master_id;
       $titleStatement->created_at_start  = $request->start_dt;
       $titleStatement->created_at_end    = $request->end_dt;
       $titleStatement->onlyActive        = $request->onlyActive;
       return response()->json($titleStatement->getTitleStatement());
    }
}
