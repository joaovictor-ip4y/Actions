<?php

namespace App\Http\Controllers;

use App\Models\LemitCnae;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LemitCnaeController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [36];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $lemit_cnae                  = new LemitCnae();
        $lemit_cnae->lemit_id        = $request->lemit_id;
        $lemit_cnae->lemit_unique_id = $request->lemit_unique_id;
        return response()->json($lemit_cnae->getLemitCnae());
    }
   
}
