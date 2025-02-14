<?php

namespace App\Http\Controllers;

use App\Models\CelcoinPixKey;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CelcoinPixKeyController extends Controller
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CelcoinPixKey  $celcoinPixKey
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4, 358, 359];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $celcoinPixKey             = new CelcoinPixKey();
        $celcoinPixKey->account_id = $checkAccount->account_id;
        $celcoinPixKey->onlyActive = $request->onlyActive;
        return response()->json($celcoinPixKey->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CelcoinPixKey  $celcoinPixKey
     * @return \Illuminate\Http\Response
     */
    public function edit(CelcoinPixKey $celcoinPixKey)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CelcoinPixKey  $celcoinPixKey
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CelcoinPixKey $celcoinPixKey)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CelcoinPixKey  $celcoinPixKey
     * @return \Illuminate\Http\Response
     */
    public function destroy(CelcoinPixKey $celcoinPixKey)
    {
        //
    }
}
