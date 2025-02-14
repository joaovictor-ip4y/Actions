<?php

namespace App\Http\Controllers;

use App\Models\RegisterRisk;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRiskController extends Controller
{
    protected function show(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerRisk              = new RegisterRisk();
        $registerRisk->id          = $request->id;
        $registerRisk->only_active = $request->only_active;
        return response()->json($registerRisk->get());
    }

}
