<?php

namespace App\Http\Controllers;

use App\Models\AuthAttempt;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class AuthAttemptController extends Controller
{
    public function get(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [128];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $authAttempt = new AuthAttempt();
        $authAttempt->user_id = $request->user_id;
        return response()->json($authAttempt->getUserAuthAttempt());
    }
}
