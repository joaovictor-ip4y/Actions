<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequestBusinessLine;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRequestBusinessLineController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\RegisterRequestBusinessLine  $registerRequestBusinessLine
     * @return \Illuminate\Http\Response
     */
    public function get(RegisterRequestBusinessLine $registerRequestBusinessLine, Request $request)
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

        $registerRequestBusinessLine = new RegisterRequestBusinessLine();
        return response()->json($registerRequestBusinessLine->get());
    }
}
