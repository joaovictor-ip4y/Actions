<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequestAccountType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRequestAccountTypeController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\RegisterRequestAccountType  $registerRequestAccountType
     * @return \Illuminate\Http\Response
     */
    public function get(RegisterRequestAccountType $registerRequestAccountType, Request $request)
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

        $registerRequestAccountType = new RegisterRequestAccountType();
        return response()->json($registerRequestAccountType->get());
    }
}
