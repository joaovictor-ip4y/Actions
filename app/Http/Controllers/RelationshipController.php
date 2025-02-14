<?php

namespace App\Http\Controllers;

use App\Models\Relationship;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class RelationshipController extends Controller
{
    protected function get(Request $request){
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [133];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $relationship              = new Relationship();
        $relationship->onlyAccount = $request->onlyAccount;
        return response()->json($relationship->getRelationship());
    }
}
