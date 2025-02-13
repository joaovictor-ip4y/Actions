<?php

namespace App\Http\Controllers;

use App\Models\PjPartner;
use App\Models\Register;
use App\Models\RegisterMaster;
use App\Models\RegisterDetail;
use App\Models\RegisterDataPj;
use App\Models\RegisterPhone;
use App\Models\RegisterEmail;
use App\Models\RegisterAddress;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Register\RegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PjPartnerController extends Controller
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

        //to pj visualization
        if(isset($request->register_master_id)){
            if ( RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->count() > 0 ){
                $registerDataPj                 = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
                $pjPartner                      = new PjPartner();
                $pjPartner->register_data_pj_id = $registerDataPj->id;
                $pjPartner->onlyActive          = $request->onlyActive;
                return response()->json($pjPartner->getPjPartner());
            }
        }

        //to pf visualization
        if(isset($request->rgstr_mstr_id)){
            $pjPartner                = new PjPartner();
            $pjPartner->rgstr_mstr_id = $request->rgstr_mstr_id;
            $pjPartner->onlyActive    = $request->onlyActive;
            return response()->json($pjPartner->getPjPartner());
        }

        return response()->json([]);
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

        $registerMasterId           = null;
        $registerDetail            = new RegisterDetail();
        $registerDetail->master_id = $checkAccount->master_id;
        $registerDetail->cpf_cnpj  = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $registerData              = $registerDetail->getRegisterData();



        if(sizeof($registerData) > 0){

            $registerData = $registerData[0];

            //update register if exists
            $registerMasterId = $registerData->register_master_id;

            //check if create phone
            if($registerData->phone_main_id == null or $registerData->phone_main_id == ''){
                if($request->phone <> ''){
                    RegisterPhone::create([
                        'register_master_id' => $registerMasterId,
                        'number'             => $request->phone,
                        'main'               => 1,
                        'observation'        => 'Criado no cadastro de participante',
                        'uuid'               => Str::orderedUuid(),
                        'created_at'         => \Carbon\Carbon::now()
                    ]);
                }
            }

            //check if create email
            if($registerData->email_main_id == null or $registerData->email_main_id == ''){
                if($request->email <> ''){
                    RegisterEmail::Create([
                        'register_master_id' => $registerMasterId,
                        'email'              => $request->email,
                        'main'               => 1,
                        'observation'        => 'Criado no cadastro de participante',
                        'uuid'               => Str::orderedUuid(),
                        'created_at'         => \Carbon\Carbon::now()
                    ]);
                }
            }

            //check if create address
            if($registerData->address_main_id == null or $registerData->address_main_id == ''){
                if($request->address <> ''){
                    RegisterAddress::Create([
                        'register_master_id' => $registerMasterId,
                        'state_id'           => $request->address_state_id,
                        'public_place'       => $request->address_public_place,
                        'address'            => $request->address,
                        'number'             => $request->address_number,
                        'complement'         => $request->address_complement,
                        'district'           => $request->address_district,
                        'city'               => $request->address_city,
                        'zip_code'           => $request->address_zip_code,
                        'main'               => 1,
                        'observation'        => 'Criado no cadastro de participante',
                        'uuid'               => Str::orderedUuid(),
                        'created_at'         => \Carbon\Carbon::now()
                    ]);
                }
            }

            //check if create user (only for pf)
            if($registerData->user_master_id == null or $registerData->user_master_id == ''){
                if($request->user == 1){
                    if(strlen(preg_replace('/[^0-9]/', '', $request->cpf_cnpj)) == 11) {
                        $registerService = new RegisterService();
                        $registerService->register_master_id = $registerMasterId;
                        $createUser = $registerService->createUserByRegister();
                    }
                }
            }

        } else {
            //create register if not exists
            $registerPartner  = app('App\Http\Controllers\RegisterController')->returnRegister(preg_replace( '/[^0-9]/', '', $request->cpf_cnpj), $request->name, $request->header('masterId'));
            if($registerPartner->status == 0 ){
                return response()->json(array("error" => $registerPartner->error));
            }

            $registerMasterId = $registerPartner->success->id;

            //check if create phone
            if($request->phone <> ''){
                RegisterPhone::create([
                    'register_master_id' => $registerMasterId,
                    'number'             => $request->phone,
                    'main'               => 1,
                    'observation'        => 'Criado no cadastro de participante',
                    'uuid'               => Str::orderedUuid(),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }

            //check if create e-mail
            if($request->email <> ''){
                RegisterEmail::Create([
                    'register_master_id' => $registerMasterId,
                    'email'              => $request->email,
                    'main'               => 1,
                    'observation'        => 'Criado no cadastro de participante',
                    'uuid'               => Str::orderedUuid(),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }

            //check if create email
            if($request->address <> ''){
                RegisterAddress::Create([
                    'register_master_id' => $registerMasterId,
                    'state_id'           => $request->address_state_id,
                    'public_place'       => $request->address_public_place,
                    'address'            => $request->address,
                    'number'             => $request->address_number,
                    'complement'         => $request->address_complement,
                    'district'           => $request->address_district,
                    'city'               => $request->address_city,
                    'zip_code'           => $request->address_zip_code,
                    'main'               => 1,
                    'observation'        => 'Criado no cadastro de participante',
                    'uuid'               => Str::orderedUuid(),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }

            //check if create user (only for pf)
            if($request->user == 1){
                if(strlen(preg_replace('/[^0-9]/', '', $request->cpf_cnpj)) == 11) {
                    $registerService = new RegisterService();
                    $registerService->register_master_id = $registerMasterId;
                    $createUser = $registerService->createUserByRegister();
                }
            }
        }

        //create Partner
        $registerDataPj = RegisterDataPj::where('register_master_id', '=', $request->register_master_id)->first();

        if( PjPartner::where('register_data_pj_id', '=', $registerDataPj->id)->where('partner_type_id','=',$request->partner_type_id)->where('register_master_id', '=', $registerMasterId)->whereNull('deleted_at')->count() == 0){
            if($pjPartner = PjPartner::Create([
                'register_master_id'  => (int) $registerMasterId,
                'register_data_pj_id' => (int) $registerDataPj->id,
                'partner_type_id'     => (int) $request->partner_type_id,
                'participation'       => (float) $request->partner_participation,
                'subscriber'          => (int) $request->partner_subscriber,
                'mandatory'           => (int) $request->partner_mandatory,
                'uuid'                => Str::orderedUuid(),
                'created_at'          => \Carbon\Carbon::now()
            ])){
                return response()->json(array("success" => "Participante cadastrado com sucesso", "register_partner_id" => $pjPartner->id));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao cadastrar o participante, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" => "Participante já associado ao cadastro"));
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

        $registerDataPj  = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
        $pjPartner = PjPartner::where('register_data_pj_id','=',$registerDataPj->id)->where('id','=',$request->register_partner_id)->first();
        $pjPartner->participation = $request->participation;
        $pjPartner->subscriber    = $request->subscriber;
        $pjPartner->mandatory     = $request->mandatory;
        if( $pjPartner->save() ){
            return response()->json(array("success" => "Sócio atualizado com sucesso", "register_partner_id" =>  $pjPartner->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o sócio"));
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
        $pjPartner = PjPartner::where('register_data_pj_id','=',$registerDataPj->id)->where('id','=',$request->register_partner_id)->first();
        $pjPartner->deleted_at = \Carbon\Carbon::now();
        if( $pjPartner->save() ){
            return response()->json(array("success" => "Sócio excluído com sucesso", "register_partner_id" =>  $pjPartner->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o sócio"));
        }
    }

    protected function linkPF(Request $request)
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

        if (!$registerDataPj = RegisterDataPj::where('register_master_id', '=', $request->company_id)->first()){
            return response()->json(array("error" => "Empresa não cadastrada, realize o cadastro da empresa antes de realizar esse vínculo."));
        }

        if(PjPartner::where('register_master_id', '=', $request->register_master_id)
        ->where('register_data_pj_id', '=', $registerDataPj->id)
        ->where('partner_type_id', '=', $request->partner_type_id)
        ->whereNull('deleted_at')
        ->count() > 0) {
            return response()->json(array("error" => "A participação desse cadastro com este tipo e com esta empresa já existe."));
        }

        if (PjPartner::create([
            'uuid'                  => Str::orderedUuid(),
            'register_master_id'    => $request->register_master_id,
            'register_data_pj_id'   => $registerDataPj->id,
            'partner_type_id'       => $request->partner_type_id,
            'participation'         => $request->partner_participation,
            'subscriber'            => $request->partner_subscriber,
            'mandatory'             => $request->partner_mandatory,
            'created_at'            => \Carbon\Carbon::now()
        ])){
            return response()->json(array("success" => "Vínculo de cadastro de pessoa física em pessoa jurídica efetuado com sucesso."));
        }

        return response()->json(array("error" => "Poxa, não foi possível salvar o registro, por favor tente mais tarde."));
    }
}
