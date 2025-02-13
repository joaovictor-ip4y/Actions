<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $permission = new Permission();
        $permission->id = $request->id;
        $permission->relationship_id = $request->relationship_id;
        $permission->usr_rltnshp_id  = $request->usr_rltnshp_id;
        $permission->prmsn_grp_id = $request->prmsn_grp_id;
        $permission->user_relationship_request_id = $request->user_relationship_request_id;
        $permission->onlyActive = $request->onlyActive;
        return response()->json($permission->getPermission());
    }
}
