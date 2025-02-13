<?php

namespace App\Http\Controllers;

use App\Models\AccountClassification;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class AccountClassificationController extends Controller
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
     * @param  \App\Models\AccountClassification  $accountClassification
     * @return \Illuminate\Http\Response
     */
    public function show(AccountClassification $accountClassification, Request $request)
    {
        return response()->json( $accountClassification->get() );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AccountClassification  $accountClassification
     * @return \Illuminate\Http\Response
     */
    public function edit(AccountClassification $accountClassification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AccountClassification  $accountClassification
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AccountClassification $accountClassification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AccountClassification  $accountClassification
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccountClassification $accountClassification)
    {
        //
    }
}
