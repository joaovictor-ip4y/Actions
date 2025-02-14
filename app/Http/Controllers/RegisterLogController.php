<?php

namespace App\Http\Controllers;

use App\Classes\Register\RegisterLogClass;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterLogController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $register_log                        = new RegisterLogClass();
        $register_log->id                    = $request->id;
        $register_log->uuid                  = $request->uuid;
        $register_log->user_id               = $request->user_id;
        $register_log->master_id             = $request->master_id;
        $register_log->register_master_id    = $request->register_master_id;
        $register_log->field_id              = $request->field_id;
        $register_log->old_value             = $request->old_value;
        $register_log->new_value             = $request->new_value;
        $register_log->created_at_start      = $request->created_at_start;
        $register_log->created_at_end        = $request->created_at_end;
        $register_log->data                  = $request;

        $register_data = $register_log->get();

        if (!$register_data->success) {
            return response()->json(array("error" => $register_data->message_pt_br, "data" => $register_data));
        }
        return response()->json(array("success" => $register_data->message_pt_br, "data" => $register_data));
    }

    protected function newRegisterLog(Request $request)
    {
        $register_log                     = new RegisterLogClass();
        $register_log->user_id            = $request->user_id;
        $register_log->master_id          = $request->master_id;
        $register_log->register_master_id = $request->register_master_id;
        $register_log->field_id           = $request->field_id;
        $register_log->old_value          = $request->old_value;
        $register_log->new_value          = $request->new_value;
        $register_log->data               = $request;

        $register_data = $register_log->new();

        if (!$register_data->success) {
            return response()->json(array("error" => $register_data->message_pt_br, "data" => $register_data));
        }
        return response()->json(array("success" => $register_data->message_pt_br, "data" => $register_data));
    }
}
