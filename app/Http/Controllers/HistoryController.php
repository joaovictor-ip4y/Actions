<?php

namespace App\Http\Controllers;

use App\Models\History;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class HistoryController extends Controller
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

        $history = new History();
        return response()->json($history->getHistory());
    }

    public function new($historyData)
    {
        History::create([
            'description'          => $historyData->description,
            'new_value'            => $historyData->new_value,
            'old_value'            => $historyData->old_value,
            'ip_request'           => $historyData->ip_request,
            'coordinates_request'  => $historyData->coordinates_request,
            'master_id'            => $historyData->master_id,
            'user_id'              => $historyData->user_id,
            'user_relationship_id' => $historyData->user_relationship_id,
            'action_id'            => $historyData->action_id,
            'table_field_id'       => $historyData->table_field_id,
            'origin_id'            => $historyData->origin_id,
            'created_at'           => \Carbon\Carbon::now()
        ]);  
    }
}
