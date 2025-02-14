<?php

namespace App\Http\Controllers;

use App\Models\RegisterAddress;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class RegisterAddressController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [15];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerAddress = new RegisterAddress();
        $registerAddress->register_master_id = $request->register_master_id;
        $registerAddress->master_id          = null;
        $registerAddress->onlyActive         = $request->onlyActive;
        return response()->json($registerAddress->getRegisterAddress());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [18];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(strlen(preg_replace( '/[^0-9]/', '', $request->address_zip_code)) > 8 ){
            return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
        }

        $main = 0;
        if($request->address_main == 1){
            $main = 1;
            RegisterAddress::removeAllMainAddress($request->register_master_id, null);
        }

        if(RegisterAddress::where('register_master_id','=',$request->register_master_id)->count() == 0){
            $main = 1;
        }

        if($registerAddress = RegisterAddress::Create([
            'register_master_id' => $request->register_master_id,
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
            return response()->json(array("success" => "Endereço cadastrado com sucesso", "register_address_id" =>  $registerAddress->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar o endereço"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [17];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerAddress = RegisterAddress::where('register_master_id','=',$request->register_master_id)->where('id','=',$request->register_address_id)->first();
        if($request->address_main == 1){
            RegisterAddress::removeAllMainAddress($request->register_master_id, $registerAddress->id);
        }
        $registerAddress->contact_type_id    = $request->address_contact_type_id;
        $registerAddress->state_id           = $request->address_state_id;
        $registerAddress->public_place       = $request->address_public_place;
        $registerAddress->address            = $request->address;
        $registerAddress->number             = $request->address_number;
        $registerAddress->complement         = $request->address_complement;
        $registerAddress->district           = $request->address_district;
        $registerAddress->city               = $request->address_city;
        $registerAddress->zip_code           = $request->address_zip_code;
        $registerAddress->ibge_code          = $request->address_ibge_code;
        $registerAddress->gia_code           = $request->address_gia_code;
        $registerAddress->main               = $request->address_main;
        $registerAddress->observation        = $request->address_observation;
        if($registerAddress->save()){
            return response()->json(array("success" => "Endereço atualizado com sucesso", "register_address_id" =>  $registerAddress->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o endereço"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [20];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerAddress = RegisterAddress::where('register_master_id','=',$request->register_master_id)->where('id','=',$request->register_address_id)->first();
        if($registerAddress->main == 1){
            return response()->json(array("error" => "Não é possível excluir um endereço definido como principal"));
        }
        $registerAddress->deleted_at = \Carbon\Carbon::now();
        if($registerAddress->save()){
            return response()->json(array("success" => "Endereço excluído com sucesso", "register_address_id" =>  $registerAddress->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o endereço"));
        }
    }

    protected function updateAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [17];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        if($register_address = RegisterAddress::where('register_master_id','=',$request->register_master_id)->first()){
            $register_address->address = $request->address;
            if($register_address->save()){
                return response()->json(array("success" => "Endereço alterado com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar o endereço, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cadastro não localizado"));
        }
    }

}
