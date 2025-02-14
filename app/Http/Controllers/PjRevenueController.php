<?php

namespace App\Http\Controllers;

use App\Models\PjRevenue;
use App\Models\RegisterDataPj;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PjRevenueController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if ( RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->count() > 0 ){
            $registerDataPj                 = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
            $pjRevenue                      = new PjRevenue();
            $pjRevenue->register_data_pj_id = $registerDataPj->id;
            $pjRevenue->onlyActive          = $request->onlyActive;
            return response()->json($pjRevenue->getPjRevenue());
        } else {
            return response()->json();
        }        
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $dataPj      = RegisterDataPj::where('register_master_id' , '=', $request->register_master_id)->first();
        if($pjRevenue = PjRevenue::Create([
            'register_data_pj_id' => $dataPj->id,
            'value'               => $request->revenue_value,
            'start_date'          => $request->revenue_start_date,
            'end_date'            => $request->revenue_end_date,
            'created_at'          => \Carbon\Carbon::now()
        ])){
            return response()->json(array(
                "success" => "Faturamento cadastrado com sucesso", 
                "pj_revenue_id" =>  $pjRevenue->id
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o faturamento"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDataPj        = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
        $pjRevenue             = PjRevenue::where('register_data_pj_id','=', $registerDataPj->id)->where('id','=',$request->pj_revenue_id)->first();
        $pjRevenue->value      = $request->revenue_value;
        $pjRevenue->start_date = $request->revenue_start_date;
        $pjRevenue->end_date   = $request->revenue_end_date;
        if( $pjRevenue->save() ){
            return response()->json(array("success" => "Faturamento atualizado com sucesso", "pj_revenue_id" => $pjRevenue->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o faturamento"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDataPj  = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
        $pjRevenue = PjRevenue::where('register_data_pj_id','=', $registerDataPj->id)->where('id','=',$request->pj_revenue_id)->first();
        $pjRevenue->deleted_at = \Carbon\Carbon::now();
        if( $pjRevenue->save() ){
            return response()->json(array("success" => "Faturamento excluído com sucesso", "pj_revenue_id" => $pjRevenue->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o faturamento"));
        }
    }
}
