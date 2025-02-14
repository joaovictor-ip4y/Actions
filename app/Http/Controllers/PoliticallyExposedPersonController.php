<?php

namespace App\Http\Controllers;

use App\Classes\Register\RegisterClass;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PoliticallyExposedPersonController extends Controller
{
    protected function updatePoliticallyExposedPersonBase(Request $request)
    {

         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [6];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $politically              = new RegisterClass();
        $politically->data        = $request;
        $politically_data         = $politically->updatePoliticallyExposedPersonBase();

        if (!$politically_data->success) {
            return response()->json(array("error" => $politically_data->message_pt_br, "data" => $politically_data));
        }
        return response()->json(array("success" => $politically_data->message_pt_br, "data" => $politically_data));
    }
}
