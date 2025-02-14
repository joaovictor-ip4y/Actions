<?php

namespace App\Http\Controllers;

use App\Models\RegisterPhone;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class RegisterPhoneController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [7];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerPhone = new RegisterPhone();
        $registerPhone->register_master_id = $request->register_master_id;
        $registerPhone->master_id          = null;
        $registerPhone->onlyActive         = $request->onlyActive;
        return response()->json($registerPhone->getRegisterPhone());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [10];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $main = 0;
        if($request->phone_main == 1){
            RegisterPhone::removeAllMainPhone($request->register_master_id, null);
            $main = 1;
        }

        if(RegisterPhone::where('register_master_id','=',$request->register_master_id)->count() == 0){
            $main = 1;
        }

        if($registerPhone = RegisterPhone::Create([
            'register_master_id' => $request->register_master_id,
            'contact_type_id'    => $request->phone_contact_type_id,
            'phone_type_id'      => $request->phone_type_id,
            'number'             => preg_replace('/[^0-9]/', '', $request->phone_number),
            'main'               => $main,
            'observation'        => $request->phone_observation,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            return response()->json(array(
                "success" => "Telefone cadastrado com sucesso",
                "register_phone_id" =>  $registerPhone->id
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o telefone"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [9];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerPhone = RegisterPhone::where('register_master_id','=',$request->register_master_id)->where('id','=',$request->phone_id)->first();
        if($request->phone_main == 1){
            RegisterPhone::removeAllMainPhone($request->register_master_id, $registerPhone->id);
        }
        $registerPhone->contact_type_id = $request->phone_contact_type_id;
        $registerPhone->phone_type_id   = $request->phone_type_id;
        $registerPhone->number          = preg_replace('/[^0-9]/', '', $request->phone_number);
        $registerPhone->main            = $request->phone_main;
        $registerPhone->observation     = $request->phone_observation;
        if($registerPhone->save()){
            return response()->json(array("success" => "Telefone atualizado com sucesso", "register_phone_id" =>  $registerPhone->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o telefone"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [12];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerPhone = RegisterPhone::where('register_master_id','=',$request->register_master_id)->where('id','=',$request->register_phone_id)->first();
        if($registerPhone->main == 1){
            return response()->json(array("error" => "Não é possível excluir um telefone definido como principal"));
        }
        $registerPhone->deleted_at = \Carbon\Carbon::now();
        if($registerPhone->save()){
            return response()->json(array("success" => "Telefone excluido com sucesso", "register_phone_id" =>  $registerPhone->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o telefone"));
        }
    }

    protected function updatePhone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [9];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        if($register_phone = RegisterPhone::where('register_master_id','=',$request->register_master_id)->first()){
            $register_phone->number =  preg_replace('/[^0-9]/', '', $request->number);
            if($register_phone->save()){
                return response()->json(array("success" => "Telefone alterado com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar o telefone, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }
}
