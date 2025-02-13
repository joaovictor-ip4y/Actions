<?php

namespace App\Http\Controllers;

use App\Models\AntecipationChrgMvmnt;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class AntecipationChrgMvmntController extends Controller
{
    protected function getAccountMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [112];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AntecipationChrgMvmnt();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->onlyActive = 1;
        return response()->json( $accountMovement->getAccountMovement() );
    }
}
