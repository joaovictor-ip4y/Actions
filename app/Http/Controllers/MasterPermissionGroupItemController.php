<?php

namespace App\Http\Controllers;

use App\Models\MasterPermissionGroupItem;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class MasterPermissionGroupItemController extends Controller
{
    protected function get(Request $request)
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
        
        $masterPermissionGroupItem = new MasterPermissionGroupItem();
        return response()->json($masterPermissionGroupItem->getMasterPermissionGroupItem());
    }
}
