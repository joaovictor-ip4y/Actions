<?php

namespace App\Http\Controllers;

use App\Models\PayerAddress;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PayerAddressController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [242, 302, 388];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerAddress                      = new PayerAddress();
        $payerAddress->payer_id            = $request->payer_id;
        if($request->header('registerId') == ''){
            $payerAddress->register_master_id  = $request->register_master_id;
        } else {
            $payerAddress->register_master_id  = $request->header('registerId');
            $payerAddress->registerOnlyActive  = $request->register_only_active;
        }
        $payerAddress->onlyActive = $request->onlyActive;
        return response()->json($payerAddress->getPayerAddress());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [242, 302, 388];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(strlen(preg_replace( '/[^0-9]/', '', $request->address_zip_code)) > 8 ){
            return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
        }

        $main = 0;
        //validar se sacado pertence ao registro
        if($request->address_main == 1){
            $main = 1;
            PayerAddress::removeAllMainAddress($request->payer_id);
        }

        if(PayerAddress::where('payer_detail_id','=',$request->payer_id)->count() == 0){
            $main = 1;
        }

        if($payerAddress = PayerAddress::Create([
            'payer_detail_id'    => $request->payer_id,
            'contact_type_id'    => $request->address_contact_type_id,
            'state_id'           => $request->address_state_id,
            'public_place'       => $request->address_public_place,
            'address'            => $request->address,
            'number'             => $request->address_number,
            'complement'         => $request->address_complement,
            'district'           => $request->address_district,
            'city'               => $request->address_city,
            'zip_code'           => $request->address_zip_code,
            'ibge_code'          => $request->address_ibge_code,
            'gia_code'           => $request->address_gia_code,
            'main'               => $main,
            'observation'        => $request->address_observation,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            return response()->json(array("success" => "Endereço cadastrado com sucesso", "payer_address_id" =>  $payerAddress->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o endereço"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [243, 303, 389];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payerAddress = PayerAddress::where('payer_detail_id','=',$request->payer_detail_id)->where('id','=',$request->payer_address_id)->first();
        if($payerAddress->main == 1){
            return response()->json(array("error" => "Não é possível excluir um endereço principal, cadastre um novo eendereço principal para excluir este"));
        }
        if($request->header('registerId') == ''){
            $payerAddress->deleted_at = \Carbon\Carbon::now();
        } else {
            $payerAddress->register_deleted_at = \Carbon\Carbon::now();
        }
        if($payerAddress->save()){
            return response()->json(array("success" => "Endereço excluído com sucesso", "payer_address_id" =>  $payerAddress->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o endereço"));
        }
    }
}
