<?php

namespace App\Http\Controllers;

use App\Models\PixReverseReason;
use Illuminate\Http\Request;
use App\Classes\Banking\PixClass;
use App\Services\Account\AccountRelationshipCheckService;

class PixReverseReasonController extends Controller
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
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PixReverseReason  $pixReverseReason
     * @return \Illuminate\Http\Response
     */
    public function show(PixReverseReason $pixReverseReason)
    {
        return response()->json($pixReverseReason->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PixReverseReason  $pixReverseReason
     * @return \Illuminate\Http\Response
     */
    public function edit(PixReverseReason $pixReverseReason)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PixReverseReason  $pixReverseReason
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PixReverseReason $pixReverseReason)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PixReverseReason  $pixReverseReason
     * @return \Illuminate\Http\Response
     */
    public function destroy(PixReverseReason $pixReverseReason)
    {
        //
    }
}
