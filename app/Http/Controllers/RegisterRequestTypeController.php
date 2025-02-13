<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequestType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRequestTypeController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerRequestType = new RegisterRequestType();
        $registerRequestType->id          = $request->id;
        $registerRequestType->uuid        = $request->uuid;
        $registerRequestType->description = $request->description;
        return response()->json($registerRequestType->get());
    }
}
