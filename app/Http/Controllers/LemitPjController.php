<?php

namespace App\Http\Controllers;

use App\Models\LemitPj;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LemitPjController extends Controller
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

        $lemit_pj                   = new LemitPj();
        $lemit_pj->lemit_id         = $request->lemit_id;
        $lemit_pj->lemit_unique_id  = $request->lemit_unique_id;
        return response()->json($lemit_pj->getLemitPj());
    }
}
