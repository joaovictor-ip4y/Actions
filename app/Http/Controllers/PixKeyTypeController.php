<?php

namespace App\Http\Controllers;

use App\Models\PixKeyType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PixKeyTypeController extends Controller
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
     * @param  \App\PixKeyType  $pixKeyType
     * @return \Illuminate\Http\Response
     */
    public function show(PixKeyType $pixKeyType, Request $request)
    {
        $pixKeyType->forCelcoin = $request->forCelcoin;
        return response()->json($pixKeyType->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PixKeyType  $pixKeyType
     * @return \Illuminate\Http\Response
     */
    public function edit(PixKeyType $pixKeyType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PixKeyType  $pixKeyType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PixKeyType $pixKeyType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PixKeyType  $pixKeyType
     * @return \Illuminate\Http\Response
     */
    public function destroy(PixKeyType $pixKeyType)
    {
        //
    }
}
