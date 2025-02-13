<?php

namespace App\Http\Controllers;

use App\Models\EdenredCardMovement;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class EdenredCardMovementController extends Controller
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
     * @param  \App\Models\EdenredCardMovement  $edenredCardMovement
     * @return \Illuminate\Http\Response
     */
    public function show(EdenredCardMovement $edenredCardMovement, Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $edenredCardMovement->account_id = $checkAccount->account_id;
        $edenredCardMovement->master_id = $checkAccount->master_id;
        $edenredCardMovement->id = $request->id;
        $edenredCardMovement->card_id = $request->card_id;
        $edenredCardMovement->card_user_detail_id = $request->card_user_detail_id;
        $edenredCardMovement->register_detail_id = $request->register_detail_id;
        $edenredCardMovement->start_value = $request->start_value;
        $edenredCardMovement->end_value = $request->end_value;
        $edenredCardMovement->start_date = $request->start_date;
        $edenredCardMovement->end_date = $request->end_date;
        return response()->json($edenredCardMovement->getTransactions());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\EdenredCardMovement  $edenredCardMovement
     * @return \Illuminate\Http\Response
     */
    public function edit(EdenredCardMovement $edenredCardMovement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\EdenredCardMovement  $edenredCardMovement
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, EdenredCardMovement $edenredCardMovement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\EdenredCardMovement  $edenredCardMovement
     * @return \Illuminate\Http\Response
     */
    public function destroy(EdenredCardMovement $edenredCardMovement)
    {
        //
    }
}
