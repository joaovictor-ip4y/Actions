<?php

namespace App\Http\Controllers;

use App\Models\ExternalCharge;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalChargeController extends Controller
{
   
    public function get(Request $request)
    {


        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountId = null;
        $userMasterId = '';
        $accountPJId = $request->header('accountPJId');
        $accountPFId = $request->header('accountPFId');
        if($accountPJId != ''){
            $accountId = $accountPJId;
        } else if($accountPFId != ''){
            $accountId = $accountPFId;
        } else {
            $userMasterId = $request->header('userMasterId');
            if($userMasterId == ''){
                return response()->json(array("error" => "Falha ao verificar conta"));
            }
        }

        $external_charge = new ExternalCharge();
        $external_charge->id                  = $request->id;
        $external_charge->master_id           = $request->header('masterId');
        $external_charge->register_master_id  = $request->register_master_id;
        $external_charge->account_id          = $request->account_id;
        $external_charge->payer_detail_id     = $request->payer_detail_id;
        $external_charge->status_id           = $request->status_id;
        $external_charge->issue_date          = $request->issue_date;
        $external_charge->document            = $request->document;
        $external_charge->due_date            = $request->due_date;
        $external_charge->value               = $request->value;
        $external_charge->bank_code           = $request->bank_code;
        $external_charge->agency              = $request->agency;
        $external_charge->account             = $request->account;
        $external_charge->wallet_number       = $request->wallet_number;
        $external_charge->our_number          = $request->our_number;
        $external_charge->bar_code            = $request->bar_code;
        $external_charge->digitable_line      = $request->digitable_line;
        $external_charge->control_number      = $request->control_number;
        $external_charge->dda                 = $request->dda;
        $external_charge->down_date           = $request->down_date;
        $external_charge->payment_date        = $request->payment_date;
        $external_charge->payment_value       = $request->payment_value;
        $external_charge->review_number       = $request->review_number;

        return response()->json($external_charge->viewall());
       // return response()->json(CardSaleTerminal::latest()->get());
    }

    public function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( DB::table('external_charges')
            ->where('master_id',        $checkAccount->master_id)
            ->where('payer_detail_id',  $request->payer_detail_id)
            ->where('document',         $request->document)
            ->where('our_number',       $request->our_number)
            ->where('wallet_number',    $request->wallet_number)
            ->where('bank_code',        $request->bank_code)
            ->count() == 0
        )
        {
            if(ExternalCharge::create([
                'master_id'         => $checkAccount->master_id,
                'register_master_id'=> $request->register_master_id,
                'account_id'        => $request->account_id,
                'payer_detail_id'   => $request->payer_detail_id,
                'status_id'         => $request->status_id,
                'issue_date'        => $request->issue_date,
                'document'          => $request->document,
                'due_date'          => $request->due_date,
                'value'             => $request->value,
                'bank_code'         => $request->bank_code,
                'agency'            => $request->agency,
                'account'           => $request->account,
                'wallet_number'     => $request->wallet_number,
                'our_number'        => $request->our_number,
                'bar_code'          => $request->bar_code,
                'digitable_line'    => $request->digitable_line,
                'control_number'    => $request->control_number,
                'dda'               => $request->dda,
                'down_date'         => $request->down_date,
                'payment_date'      => $request->payment_date,
                'payment_value'     => $request->payment_value,
                'review_number'     => $request->review_number

            ])){
                return response()->json(['success' => 'Título Cadastrado com sucesso']);
            } else {
                return response()->json(['error' => 'Ocorreu uma falha ao cadatrar o tpitulo, por favor tente mais tarde']);
            }
        } else{
                return response()->json(['error'=>'Título já cadastrado']);
        }
    }

}
