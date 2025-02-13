<?php

namespace App\Http\Controllers;

use App\Models\PixStaticReceiveReverse;
use Illuminate\Http\Request;
use App\Models\PixStaticReceivePayment;
use App\Classes\Banking\PixClass;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;

class PixStaticReceiveReverseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [408];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixClass = new PixClass;
        $pixClass->payload = (object) [
            'id' => $request->id,
            'end_to_end' => $request->end_to_end,
            'pix_static_id' => $request->pix_static_id,
            'reason_id' => $request->reason_id,
            'value' => $request->value,
            'description' => $request->description,
            'password' => $request->password
        ];

        $reverse = $pixClass->reverseStaticPix();
        if( ! $reverse->success){
            return response()->json(array("error" => $reverse->message_pt_br));
        }

        return response()->json(array(
            "success" => $reverse->message_pt_br,
            "data" => $reverse->data
        ));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PixStaticReceiveReverse  $pixStaticReceiveReverse
     * @return \Illuminate\Http\Response
     */
    public function show(PixStaticReceiveReverse $pixStaticReceiveReverse)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PixStaticReceiveReverse  $pixStaticReceiveReverse
     * @return \Illuminate\Http\Response
     */
    public function edit(PixStaticReceiveReverse $pixStaticReceiveReverse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PixStaticReceiveReverse  $pixStaticReceiveReverse
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PixStaticReceiveReverse $pixStaticReceiveReverse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PixStaticReceiveReverse  $pixStaticReceiveReverse
     * @return \Illuminate\Http\Response
     */
    public function destroy(PixStaticReceiveReverse $pixStaticReceiveReverse)
    {
        //
    }

    public function getOriginalTransaction(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [408];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $originalPixTransaction = new PixStaticReceivePayment;
        $originalPixTransaction->end_to_end = $request->end_to_end;

        if($originalPixTransaction->count() == 0) {
            return response()->json([
                "error" => "Transação não localizada, verifique o end to end informado.",
                "data" => []
            ]);
        }

        return response()->json([
            "success" => "Transação localizada com sucesso",
            "data" => $originalPixTransaction->get()
        ]);
    }

    protected function approve(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [408];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'array'],
            'uuid' => ['required', 'string'],
            'token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }


        $pixClass = new PixClass;
        $pixClass->payload = (object) [
            'id' => $request->id,
            'uuid' => $request->uuid,
            'token' => $request->token
        ];

        $approveReverse = $pixClass->approveReverseStaticPix();
        if( ! $approveReverse->success){
            return response()->json(array("error" => $approveReverse->message_pt_br, "data" => $approveReverse->data));
        }

        return response()->json(array(
            "success" => $approveReverse->message_pt_br,
            "data" => $approveReverse->data
        ));
    }
}
