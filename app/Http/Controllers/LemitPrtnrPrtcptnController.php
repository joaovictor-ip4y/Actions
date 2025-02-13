<?php

namespace App\Http\Controllers;

use App\Models\LemitPrtnrPrtcptn;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LemitPrtnrPrtcptnController extends Controller
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

        $prtnr_prtcptn                    = new LemitPrtnrPrtcptn();
        $prtnr_prtcptn->lemit_id          = $request->lemit_id;
        $prtnr_prtcptn->lemit_unique_id   = $request->lemit_unique_id;
        return response()->json($prtnr_prtcptn->getLemitPrtnrPrtcptn());
    }
}
