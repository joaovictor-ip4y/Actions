<?php

namespace App\Http\Controllers;

use App\Models\CelcoinAccount;
use App\Models\Account;

use App\Classes\Celcoin\CelcoinClass;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class CelcoinAccountController extends Controller
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
    public function store(Request $request, CelcoinClass $celcoinClass)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $celcoinClass->account_id = $checkAccount->account_id;
        //$celcoinClass->unique_id = Account::where('id', '=', $checkAccount->account_id)->first()->unique_id;

        $createAccount = $celcoinClass->baasCreateAccount();

        if ( ! $createAccount->success ) {

            if ( isset( $createAccount->data->data->error->message) ) {
                return response()->json(array("error" => "INFORMAÇÃO CELCOIN: ".$createAccount->data->data->error->message ));
            }


            return response()->json(array("error" =>  $createAccount->message_pt_br ));
        }

        return response()->json(array("success" => $createAccount->message_pt_br, "data" => $createAccount->data));
    }

    /**
     * Block celcoin account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function block(Request $request, CelcoinClass $celcoinClass)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $celcoinClass->account_id = $request->account_id;
        return response()->json($celcoinClass->bassBlockAccount());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CelcoinAccount  $celcoinAccount
     * @return \Illuminate\Http\Response
     */
    public function show(CelcoinAccount $celcoinAccount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CelcoinAccount  $celcoinAccount
     * @return \Illuminate\Http\Response
     */
    public function edit(CelcoinAccount $celcoinAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CelcoinAccount  $celcoinAccount
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CelcoinAccount $celcoinAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CelcoinAccount  $celcoinAccount
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, CelcoinClass $celcoinClass)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $celcoinClass->account_id = $request->account_id;

        $deleteAccount = $celcoinClass->bassCloseAccount();

        if ( ! $deleteAccount->success ) {

            if ( isset( $deleteAccount->data->data->error->message) ) {
                return response()->json(array("error" => "INFORMAÇÃO CELCOIN: ".$deleteAccount->data->data->error->message ));
            }


            return response()->json(array("error" =>  $deleteAccount->message_pt_br ));
        }

        return response()->json(array("success" => $deleteAccount->message_pt_br, "data" => $deleteAccount->data));

    }


    public function createWebhook(Request $request, CelcoinClass $celcoinClass)
    {
        $celcoinClass->cpf_cnpj = "11491029000130";
        return $celcoinClass->bassSearchAccountByCpfCnpj();   
    }

    public function sendAccountKyc(Request $request, CelcoinClass $celcoinClass)
    {
        return $celcoinClass->baasSendKyc();
    }
    
    public function recheckCreationKey(Request $request, CelcoinClass $celcoinClass)
    {
        return $celcoinClass->recheckCreationKey();
    }

}
