<?php

namespace App\Http\Controllers;

use App\Models\PixAccountType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PixAccountTypeController extends Controller
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
     * @param  \App\PixAccountType  $pixAccountType
     * @return \Illuminate\Http\Response
     */
    public function show(PixAccountType $pixAccountType)
    {
        return response()->json($pixAccountType->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PixAccountType  $pixAccountType
     * @return \Illuminate\Http\Response
     */
    public function edit(PixAccountType $pixAccountType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PixAccountType  $pixAccountType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PixAccountType $pixAccountType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PixAccountType  $pixAccountType
     * @return \Illuminate\Http\Response
     */
    public function destroy(PixAccountType $pixAccountType)
    {
        //
    }
}
