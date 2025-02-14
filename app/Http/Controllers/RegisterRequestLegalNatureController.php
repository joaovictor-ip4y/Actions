<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequestLegalNature;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRequestLegalNatureController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\RegisterRequestLegalNature  $registerRequestLegalNature
     * @return \Illuminate\Http\Response
     */
    public function show(RegisterRequestLegalNature $registerRequestLegalNature)
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

        $registerRequestLegalNature = new RegisterRequestLegalNature();
        return response()->json($registerRequestLegalNature->get());
    }
}
