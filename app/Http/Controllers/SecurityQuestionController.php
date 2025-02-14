<?php

namespace App\Http\Controllers;

use App\Models\SecurityQuestion;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class SecurityQuestionController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $securityQuestion = new SecurityQuestion();

        return response()->json($securityQuestion->get());
    }
}
