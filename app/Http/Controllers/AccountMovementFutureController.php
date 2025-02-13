<?php

namespace App\Http\Controllers;

use App\Models\AccountMovementFuture;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\Account\AccountMovementFutureClass;

class AccountMovementFutureController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService = new AccountRelationshipCheckService();
        $accountCheckService->request = $request;
        $accountCheckService->permission_id  = [86];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        $accountMovementFutureClass                = new AccountMovementFutureClass();
        $accountMovementFutureClass->account_id    = $checkAccount->account_id;
        $accountMovementFutureClass->master_id     = $checkAccount->master_id;
        $accountMovementFutureClass->description   = $request->description;
        $accountMovementFutureClass->mvmnt_type_id = $request->mvmnt_type_id;
        $accountMovementFutureClass->value         = $request->value;

        $create = $accountMovementFutureClass->create();

        if( $create->success ) {
            return response()->json(["success" => $create->message_pt_br]);
        }
        return response()->json(["error" => $create->message_pt_br]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AccountMovementFuture  $accountMovementFuture
     * @return \Illuminate\Http\Response
     */
    public function show(AccountMovementFuture $accountMovementFuture, Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService = new AccountRelationshipCheckService();
        $accountCheckService->request = $request;
        $accountCheckService->permission_id  = [86];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovementFuture->account_id = $checkAccount->account_id;
        $accountMovementFuture->master_id = $checkAccount->master_id;
        $accountMovementFuture->register_master_id = $request->register_master_id;
        $accountMovementFuture->mvmnt_type_id = $request->mvmnt_type_id;
        $accountMovementFuture->created_at_start = $request->created_at_start;
        $accountMovementFuture->created_at_end = $request->created_at_end;
        $accountMovementFuture->competence_start = $request->competence_start;
        $accountMovementFuture->competence_end = $request->competence_end;
        $accountMovementFuture->only_active = $request->only_active;
        $accountMovementFuture->only_paid = $request->only_paid;
        $accountMovementFuture->only_loss = $request->only_loss;
        $accountMovementFuture->only_pending = $request->only_pending;
        $accountMovementFuture->paid_at_start = $request->paid_at_start;
        $accountMovementFuture->paid_at_end = $request->paid_at_end;
        $accountMovementFuture->loss_at_start = $request->loss_at_start;
        $accountMovementFuture->loss_at_end = $request->loss_at_end;
        return $accountMovementFuture->get();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AccountMovementFuture  $accountMovementFuture
     * @return \Illuminate\Http\Response
     */
    public function edit(AccountMovementFuture $accountMovementFuture)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AccountMovementFuture  $accountMovementFuture
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AccountMovementFuture $accountMovementFuture)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AccountMovementFuture  $accountMovementFuture
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService = new AccountRelationshipCheckService();
        $accountCheckService->request = $request;
        $accountCheckService->permission_id  = [86];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        if( ! $accountMovementFuture = AccountMovementFuture::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first() ) {
            return response()->json(array("error" => "Lançamento não localizado ou pago/perdido."));
        }
        $accountMovementFuture->loss = 1;
        $accountMovementFuture->paid_or_loss_at = \Carbon\Carbon::now();
        $accountMovementFuture->save();

        return response()->json(array("success" => "Lançamento futuro definido como perdido com sucesso."));


    }

    public function tryPayMovementFuture()
    {
        $accountMovementFuture = new AccountMovementFutureClass(); 
        $accountMovementFuture->tryPayMovementFuture();
    }
}
