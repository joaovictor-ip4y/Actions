<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequestBillingPlan;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRequestBillingPlanController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\RegisterRequestBillingPlan  $registerRequestBillingPlan
     * @return \Illuminate\Http\Response
     */
    public function get(RegisterRequestBillingPlan $registerRequestBillingPlan, Request $request)
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

        $registerRequestBillingPlan = new RegisterRequestBillingPlan();
        return response()->json($registerRequestBillingPlan->get());
    }
}
