<?php

namespace App\Http\Controllers;

use App\Models\RegisterEmail;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class RegisterEmailController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [11];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerEmail = new RegisterEmail();
        $registerEmail->register_master_id = $request->register_master_id;
        $registerEmail->master_id          = null;
        $registerEmail->onlyActive         = $request->onlyActive;
        return response()->json($registerEmail->getRegisterEmail());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [14];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $main = 0;
        if($request->email_main == 1){
            RegisterEmail::removeAllMainEmail($request->register_master_id, null);
            $main = 1;
        }

        if(RegisterEmail::where('register_master_id','=',$request->register_master_id)->count() == 0){
            $main = 1;
        }

        if($registerEmail = RegisterEmail::Create([
            'register_master_id' => $request->register_master_id,
            'email'              => $request->email_address,
            'main'               => $main,
            'observation'        => $request->email_observation,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            return response()->json(array(
                "success" => "E-Mail cadastrado com sucesso",
                "register_email_id" =>  $registerEmail->id
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o E-Mail"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [13];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerEmail = RegisterEmail::where('register_master_id','=',$request->register_master_id)->where('id','=',$request->register_email_id)->first();
        if($request->email_main == 1){
            RegisterEmail::removeAllMainEmail($request->register_master_id, $registerEmail->id);
        }
        $registerEmail->email       = $request->email_address;
        $registerEmail->main        = $request->email_main;
        $registerEmail->observation = $request->email_observation;
        if($registerEmail->save()){
            return response()->json(array("success" => "E-Mail alterado com sucesso", "register_email_id" =>  $registerEmail->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao alterar o E-Mail"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [16];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerEmail = RegisterEmail::where('register_master_id','=',$request->register_master_id)->where('id','=',$request->register_email_id)->first();
        if($registerEmail->main == 1){
            return response()->json(array("error" => "Não é possível excluir um e-mail definido como principal"));
        }
        $registerEmail->deleted_at = \Carbon\Carbon::now();
        if($registerEmail->save()){
            return response()->json(array("success" => "E-Mail excluido com sucesso", "register_email_id" =>  $registerEmail->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o E-Mail"));
        }
    }

    protected function updateEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [13];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($register_email = RegisterEmail::where('register_master_id','=',$request->register_master_id)->first()){
            $register_email->email = $request->email;
            if($register_email->save()){
                return response()->json(array("success" => "Email alterado com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar o email, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }
}
