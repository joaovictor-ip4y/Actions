<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\SimpleChrgRecurrence;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class SimpleChrgRecurrenceController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simple_chrg_recurrence = new SimpleChrgRecurrence();
        $simple_chrg_recurrence->master_id           = $checkAccount->master_id;
        $simple_chrg_recurrence->account_id          = $request->account_id;
        $simple_chrg_recurrence->payer_detail_id     = $request->payer_detail_id;
        $simple_chrg_recurrence->status_id           = $request->status_id;
        $simple_chrg_recurrence->invoice             = $request->invoice;
        $simple_chrg_recurrence->issue_date          = $request->issue_date;
        $simple_chrg_recurrence->document            = $request->document;
        $simple_chrg_recurrence->value               = $request->value;
        $simple_chrg_recurrence->release_day         = $request->release_day;
        $simple_chrg_recurrence->next_release_date   = $request->next_release_date;
        $simple_chrg_recurrence->until_date          = $request->until_date;
        $simple_chrg_recurrence->created_at          = $request->created_at;
        return response()->json($simple_chrg_recurrence->getSimpleChrgRecurrence());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(SimpleChrgRecurrence::create([
           'master_id'          => $checkAccount->master_id,
           'register_master_id' => $request->header('registerId'),
           'account_id'         => $checkAccount->account_id,
           'payer_detail_id'    => $request->payer_detail_id,
           'status_id'          => $request->status_id,
           'invoice'            => $request->invoice,
           'issue_date'         => $request->issue_date,
           'document'           => $request->document,
           'value'              => $request->value,
           'release_day'        => $request->release_day,
           'next_release_date'  => $request->next_release_date,
           'until_date'         => $request->until_date,
        ])){
            return response()->json(['success' => 'Cobrança cadastrada com sucesso']);
        } else {
            return response()->json(['error' => 'Ocorreu uma falha ao cadatrar a Cobrança, por favor tente mais tarde']);
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $simple_chrg_recurrence = SimpleChrgRecurrence::where('id','=',$request->id)->where('account_id ','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $simple_chrg_recurrence->status_id           = $request->status_id;
            $simple_chrg_recurrence->invoice             = $request->invoice;
            $simple_chrg_recurrence->issue_date          = $request->issue_date;
            $simple_chrg_recurrence->document            = $request->document;
            $simple_chrg_recurrence->value               = $request->value;
            $simple_chrg_recurrence->release_day         = $request->release_day;
            $simple_chrg_recurrence->next_release_date   = $request->next_release_date;
            $simple_chrg_recurrence->until_date          = $request->until_date;
            $simple_chrg_recurrence->deleted_at          = null;
            if($simple_chrg_recurrence->save()){
                return response()->json(array("success" => "Cobrança atualizada com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao atualizar a Cobrança"));
            }
        }else{
            return response()->json(array("error" => "Nenhuma Cobrança foi localizado"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $arrayError = [];
        foreach($request->id as $id){
            if(SimpleChrgRecurrence::where('id','=',$id)->count() > 0){
                $simple_chrg_recurrence = SimpleChrgRecurrence::where('id','=',$id)->first();
                $simple_chrg_recurrence->deleted_at = \Carbon\Carbon::now();
                if(!$simple_chrg_recurrence->save()){
                    array_push($arrayError, 'id '.$id.' Falha ao atualizar');
                }
            } else {
                array_push($arrayError, 'id '.$id.' Não localizado');
            }
        }
        if(sizeof($arrayError) == 0){
            return response()->json(array("success" => "Cobrança(s) excluída(s) com sucesso")); 
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir a(s) Cobrança(s), por favor tente novamente mais tarde", "errorList" => $arrayError));
        }  
    }

}
