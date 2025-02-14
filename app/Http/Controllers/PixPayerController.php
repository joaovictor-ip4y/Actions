<?php

namespace App\Http\Controllers;

use App\Models\PixPayer;
use App\Models\PixPayerDetail;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PixPayerController extends Controller
{
    protected function pixPayerCheck(Request $request)
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

        if($pix_payer = PixPayer::where('cpf_cnpj','=',$request->cpf_cnpj)->first()){
            $pix_payer_detail               = new PixPayerDetail();
            $pix_payer_detail->account_id   = $checkAccount->account_id;
            $pix_payer_detail->pix_payer_id = $pix_payer->id;
            $pix_payer_data = $pix_payer_detail->getPixPayerDetail()->first();

            if(isset($pix_payer_data->name)){
                return response()->json(array("success"=>"Pagador localizado","pix_payer_detail"=>[
                    "cpf_cnpj"              =>$pix_payer->cpf_cnpj,
                    "name"                  =>$pix_payer_data->name,
                    "pix_payer_detail_id"   =>$pix_payer_data->pix_payer_detail_id,
                ]));
            } else {
                return response()->json(array("error"=>"Pagador não localizado","pix_payer_detail"=>null));
            }
        }else{
            return response()->json(array("error"=>"Pagador não localizado","pix_payer_detail"=>null));

        }

    }
}
