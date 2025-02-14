<?php

namespace App\Http\Controllers;

use App\Models\LemitPhone;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LemitPhoneController extends Controller
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

        $lemit_phone                    =   new LemitPhone();
        $lemit_phone->lemit_id          =   $request->lemit_id;
        $lemit_phone->lemit_unique_id   =   $request->lemit_unique_id;
        return response()->json($lemit_phone->getLemitPhone());
    }
}
