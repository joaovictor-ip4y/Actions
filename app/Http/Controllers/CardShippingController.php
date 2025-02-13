<?php

namespace App\Http\Controllers;

use App\Models\CardShipping;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CardShippingController extends Controller
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
     * @param  \App\Models\CardShipping  $cardShipping
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, CardShipping $cardShipping)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $cardShipping->card_id = $request->card_id;
        return response()->json($cardShipping->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CardShipping  $cardShipping
     * @return \Illuminate\Http\Response
     */
    public function edit(CardShipping $cardShipping)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CardShipping  $cardShipping
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CardShipping $cardShipping)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CardShipping  $cardShipping
     * @return \Illuminate\Http\Response
     */
    public function destroy(CardShipping $cardShipping)
    {
        //
    }
}
