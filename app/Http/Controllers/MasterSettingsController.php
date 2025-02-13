<?php

namespace App\Http\Controllers;

use App\Models\MasterSettings;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class MasterSettingsController extends Controller
{
    protected function get()
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [138];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $masterSettings = new MasterSettings();
        return response()->json($masterSettings->getMasterSettings());
    }
}
