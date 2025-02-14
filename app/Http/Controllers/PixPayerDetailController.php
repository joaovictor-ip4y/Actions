<?php

namespace App\Http\Controllers;

use App\Models\PixPayer;
use App\Models\PixPayerDetail;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PixPayerDetailController extends Controller
{

    public function get(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [358, 359];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $pix_payer_detail               = new PixPayerDetail();
        $pix_payer_detail->id           = $request->id;
        $pix_payer_detail->account_id   = $checkAccount->account_id;
        $pix_payer_detail->cpf_cnpj     = $request->cpf_cnpj;
        $pix_payer_detail->onlyActive   = $request->onlyActive;

        return response()->json($pix_payer_detail->getPixPayerDetail());
    }

    public function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;
        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return response()->json(['error'=>'CPF/CNPJ inválido']);
            }
        }else if(strlen($cpf_cnpj) == 14){
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json(['error'=>'CPF/CNPJ inválido']);
            }
        }else{
            return response()->json(['error'=>'CPF/CNPJ inválido']);
        }

        if($pix_payer = PixPayer::where('cpf_cnpj','=',$cpf_cnpj)->whereNull('deleted_at')->first()){
            if( PixPayerDetail::where('pix_payer_id','=',$pix_payer->id)->where('account_id','=',$checkAccount->account_id)->whereNull('deleted_at')->count() > 0 ){
                $pix_payer_detail                   = new PixPayerDetail();
                $pix_payer_detail->account_id       = $checkAccount->account_id;
                $pix_payer_detail->pix_payer_id     = $pix_payer->id;
                $data_pix_payer_detail              = $pix_payer_detail->getPixPayerDetail()[0];
                return response()->json(array("success" => "Pagador já cadastrado","pix_payer_detail"=>[
                            "cpf_cnpj"              =>$data_pix_payer_detail->cpf_cnpj,
                            "name"                  =>$data_pix_payer_detail->name,
                            "pix_payer_detail_id"   =>$data_pix_payer_detail->p_pyr_dtls_id,
                    ]));
            } else {
                if($pix_payer_detail = PixPayerDetail::create([
                            'pix_payer_id'  => $pix_payer->id,
                            'account_id'    => $checkAccount->account_id,
                            'name'          => $request->name,
                            'created_at'    => \Carbon\Carbon::now(),
                        ])){
                    return response()->json(array("success" => "Pagador cadastrado com sucesso","pix_payer_detail"=>[
                                    "cpf_cnpj"              => $pix_payer->cpf_cnpj,
                                    "name"                  => $pix_payer_detail->name,
                                    "pix_payer_detail_id"   => $pix_payer_detail->id
                    ]));
                        }else{
                            return response()->json(array("error" => "Ocorreu uma falha ao cadastrar detalhes do pagador, por favor tente novamente mais tarde"));
                        }
            }
        } else {
            if($pix_payer = PixPayer::create([
                        'cpf_cnpj'      => $cpf_cnpj,
                        'created_at'    => \Carbon\Carbon::now(),
            ])){
                    if($pix_payer_detail = PixPayerDetail::create([
                                'pix_payer_id'  => $pix_payer->id,
                                'account_id'    => $checkAccount->account_id,
                                'name'          => $request->name,
                                'created_at'    => \Carbon\Carbon::now(),
                ])){
                    return response()->json(array("success" => "Pagador cadastrado com sucesso","pix_payer_detail"=>[
                                    "cpf_cnpj"              => $pix_payer->cpf_cnpj,
                                    "name"                  => $pix_payer_detail->name,
                                    "pix_payer_detail_id"   => $pix_payer_detail->id
                    ]));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao cadastrar detalhes do pagador, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao cadastrar o pagador, por favor tente novamente mais tarde"));
            }
        }
    }

    public function alter(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;
        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return response()->json(['error'=>'CPF/CNPJ inválido']);
            }
        }else if(strlen($cpf_cnpj) == 14){
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json(['error'=>'CPF/CNPJ inválido']);
            }
        }else{
            return response()->json(['error'=>'CPF/CNPJ inválido']);
        }

        if($pix_payer_detail = PixPayerDetail::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->first()){
            if($pix_payer = PixPayer::where('cpf_cnpj','=',$cpf_cnpj)->whereNull('deleted_at')->first()){
                $pix_payer_detail->name = $request->name;
                $pix_payer_detail->pix_payer_id = $pix_payer->id;
                if($pix_payer_detail->save()){
                    return response()->json(array("success" => "Pagador alterado com sucesso"));
                }else{
                    return response()->json(array("error" => "Ocorreu uma falha ao alterar o pagador, por favor tente novamente mais tarde"));
                }
            }else{
                if($pix_payer = PixPayer::create([
                    'cpf_cnpj'      => $cpf_cnpj,
                    'created_at'    => \Carbon\Carbon::now(),
                ])){
                    $pix_payer_detail->name         = $request->name;
                    $pix_payer_detail->pix_payer_id = $pix_payer->id;
                    if($pix_payer_detail->save()){
                        return response()->json(array("success" => "Pagador alterado com sucesso"));
                    }else{
                        return response()->json(array("error" => "Ocorreu uma falha ao alterar o pagador, por favor tente novamente mais tarde"));
                    }
                }else{
                    return response()->json(array("error" => "Ocorreu uma falha ao cadastrar o CPF/CNPJ"));
                }
            }
        }else{
           return response()->json(array("error" => "Pagador não encontrado"));
        }
    }

    public function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($pix_payer_detail = PixPayerDetail::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->first()){
            $pix_payer_detail->deleted_at = \Carbon\Carbon::now();
            if($pix_payer_detail->save()){
                return response()->json(array("success" => "Pagador excluido com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao cadastrar ao excluir o Pagador"));
            }
        }else{
            return response()->json(array("error" => "Pagador não encontrado"));
        }
    }
}
