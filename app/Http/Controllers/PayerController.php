<?php

namespace App\Http\Controllers;

use App\Models\Payer;
use App\Models\PayerDetail;
use App\Models\Account;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PayerController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [383, 237, 297];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerData = $this->returnPayer(
            $request->cpf_cnpj,
            $request->name,
            $request->fantasy_name,
            $request->state_registration,
            $request->observation,
            (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id
        );

        if($payerData->status == 0 ){
            return response()->json(array("error" => $payerData->error));
        } else {
            return response()->json(array("success" => "Cadastro realizado com sucesso", "payer_details" => $payerData->success));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [237, 297];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerDetail = PayerDetail::where('id','=',$request->payerId)->where('register_master_id','=',(Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id)->first();
        $payerDetail->name               = $request->payer_name;
        $payerDetail->fantasy_name       = $request->fantasy_name;
        $payerDetail->state_registration = $request->state_registration;
        $payerDetail->observation        = $request->observation;

        if($payerDetail->save()){
            return response()->json(array("success" => "Pagador atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o pagador"));
        }
    }

    protected function checkExists(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $reg_cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;
        //Verifica se é cpf e valida
        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return response()->json( array("error" => "CPF inválido") );
            }
        //Verifica se é cnpj e valida
        } else if(strlen($cpf_cnpj) == 14){
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json( array("error" => "CNPJ inválido") );
            }
        //Retorna erro se não for cpf ou cnpj
        } else {
            return response()->json( array("error" => "CPF ou CNPJ inválido") );
        }
        if( Payer::where('cpf_cnpj', '=', $cpf_cnpj)->count() > 0 ){
            $payer = Payer::where('cpf_cnpj', '=', $cpf_cnpj)->first();
            if(PayerDetail::where('register_master_id', '=', (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id)->where('payer_id', '=', $payer->id)->count() > 0 ){
                return response()->json(array("success" => "1"));
            } else {
                return response()->json(array("success" => "0"));
            }
        } else {
            return response()->json(array("success" => "0"));
        }
    }

    public function returnPayer($reg_cpf_cnpj, $name, $fantasy_name, $state_registration, $observation, $registerMasterId)
    {
        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $reg_cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;

        //Verifica se é cpf e valida
        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CPF inválido");
            }
        //Verifica se é cnpj e valida
        } else if(strlen($cpf_cnpj) == 14){
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CNPJ inválido");
            }
        //Retorna erro se não for cpf ou cnpj
        } else {
            return (object) array("status" => 0, "error" => "CPF ou CNPJ inválido");
        }
        //Verifica se cpf ou cnpj existe
        if( Payer::where('cpf_cnpj', '=', $cpf_cnpj)->count() == 0 ){
            if(
                $payer = Payer::Create([
                    'cpf_cnpj'   => $cpf_cnpj,
                    'created_at' => \Carbon\Carbon::now()
                ])
            ){
                if($payerDetail = PayerDetail::Create([
                    'payer_id'           => $payer->id,
                    'register_master_id' => $registerMasterId,
                    'name'               => $name,
                    'fantasy_name'       => $fantasy_name,
                    'state_registration' => $state_registration,
                    'observation'        => $observation,
                    'created_at'         => \Carbon\Carbon::now()
                ])){
                    return (object) array("status" => 1, "success" => $payerDetail);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao vincular o novo cadastro");
                }
            } else {
                return (object) array("status" => 0, "error" => "Ocorreu um erro ao realizar o novo cadastro");
            }
        } else {
            $payer = Payer::where('cpf_cnpj', '=', $cpf_cnpj)->first();
            //Verifica se cpf ou cnpj já está cadastrado com o registro
            if( PayerDetail::where('register_master_id', '=', $registerMasterId)->where('payer_id', '=', $payer->id)->count() == 0 ){
                if($payerDetail = PayerDetail::Create([
                    'payer_id'           => $payer->id,
                    'register_master_id' => $registerMasterId,
                    'name'               => $name,
                    'fantasy_name'       => $fantasy_name,
                    'state_registration' => $state_registration,
                    'observation'        => $observation,
                    'created_at'         => \Carbon\Carbon::now()
                ])){
                    return (object) array("status" => 1, "success" => $payerDetail);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao vincular o novo cadastro");
                }
            } else {
                $payerDetail = PayerDetail::where('register_master_id', '=', $registerMasterId)->where('payer_id', '=', $payer->id)->first();
                return (object) array("status" => 1, "success" => $payerDetail);
            }
        }
    }
}
