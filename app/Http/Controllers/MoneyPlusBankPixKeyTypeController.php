<?php

namespace App\Http\Controllers;

use App\Models\MoneyPlusBankPixKeyType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class MoneyPlusBankPixKeyTypeController extends Controller
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
     * @param  \App\Models\MoneyPlusBankPixKeyType  $moneyPlusBankPixKeyType
     * @return \Illuminate\Http\Response
     */
    public function show(MoneyPlusBankPixKeyType $moneyPlusBankPixKeyType, Request $request)
    {
        $moneyPlusBankPixKeyType->onlyForPf = $request->onlyForPf;
        $moneyPlusBankPixKeyType->onlyForPj = $request->onlyForPj;
        return response()->json($moneyPlusBankPixKeyType->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\MoneyPlusBankPixKeyType  $moneyPlusBankPixKeyType
     * @return \Illuminate\Http\Response
     */
    public function edit(MoneyPlusBankPixKeyType $moneyPlusBankPixKeyType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MoneyPlusBankPixKeyType  $moneyPlusBankPixKeyType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MoneyPlusBankPixKeyType $moneyPlusBankPixKeyType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MoneyPlusBankPixKeyType  $moneyPlusBankPixKeyType
     * @return \Illuminate\Http\Response
     */
    public function destroy(MoneyPlusBankPixKeyType $moneyPlusBankPixKeyType)
    {
        //
    }
}
