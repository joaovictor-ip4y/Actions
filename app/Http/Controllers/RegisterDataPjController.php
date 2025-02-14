<?php

namespace App\Http\Controllers;

use App\Models\RegisterDataPj;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class RegisterDataPjController extends Controller
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

        $registerDataPj = new RegisterDataPj();
        return response()->json($registerDataPj->getRegisterDataPj());
    }

    protected function updateStateRegistration(Request $request)
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

        if($register_data_pj = RegisterDataPj::where('register_master_id','=',$request->register_master_id)->first()){
            $register_data_pj->state_registration = $request->state_registration;
            if($register_data_pj->save()){
                return response()->json(array("success" => "Inscrição estadual alterada com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar a inscrição estadual, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }

    protected function updateMunicipalRegistration(Request $request)
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
        if($register_data_pj = RegisterDataPj::where('register_master_id','=',$request->register_master_id)->first()){
            $register_data_pj->municipal_registration = $request->municipal_registration;
            if($register_data_pj->save()){
                return response()->json(array("success" => "Inscrição municipal alterada com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar a inscrição municipal, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }

    protected function updateComercialBoard(Request $request)
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
        if($register_data_pj = RegisterDataPj::where('register_master_id','=',$request->register_master_id)->first()){
            $register_data_pj->commercial_board_registration = $request->commercial_board_registration;
            if($register_data_pj->save()){
                return response()->json(array("success" => "Registro junta comercial alterada com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar o registro junta comercial, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }

    protected function updateBranchActivity(Request $request)
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
        if($register_data_pj = RegisterDataPj::where('register_master_id','=',$request->register_master_id)->first()){
            $register_data_pj->branch_activity = $request->branch_activity;
            if($register_data_pj->save()){
                return response()->json(array("success" => "Ramo de atividade alterado com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar o ramo de atividade, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }
}
