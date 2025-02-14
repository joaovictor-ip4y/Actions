<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Classes\BancoRendimento\BancoRendimentoClass;
use App\Classes\BancoMoneyPlus\BancoMoneyPlusClass;
use App\Classes\Celcoin\CelcoinClass;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ApiTokenController extends Controller
{
    public function setBanoRendimentoAccessToken()
    {
        $bancoRendimentoClass = new BancoRendimentoClass();
        $bancoRendimentoClass->setApiToken();
    }

    public function setBanoMoneyPlusAccessToken()
    {
        $bancoMoneyPlusClass = new BancoMoneyPlusClass();
        $bancoMoneyPlusClass->setApiToken();
    }

    public function setCelcoinAccessToken()
    {
        $celcoinClass = new CelcoinClass();
        $celcoinClass->setApiToken();
    }





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
     * @param  \App\Models\ApiToken  $apiToken
     * @return \Illuminate\Http\Response
     */
    public function show(ApiToken $apiToken)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ApiToken  $apiToken
     * @return \Illuminate\Http\Response
     */
    public function edit(ApiToken $apiToken)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ApiToken  $apiToken
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ApiToken $apiToken)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ApiToken  $apiToken
     * @return \Illuminate\Http\Response
     */
    public function destroy(ApiToken $apiToken)
    {
        //
    }
}
