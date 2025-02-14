<?php

namespace App\Http\Controllers;

use App\Models\CountryRgstrResidence;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CountryRgstrResidenceController extends Controller
{
    protected function get()
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [4];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //
         
        $countryRgstrResidence = new CountryRgstrResidence();
        return response()->json($countryRgstrResidence->getCountryRgstrResidence());
    }
}
