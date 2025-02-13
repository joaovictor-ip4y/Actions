<?php

namespace App\Http\Controllers;

use App\Models\Lemit;
use App\Models\LemitPf;
use App\Models\LemitPj;
use App\Models\LemitPhone;
use App\Models\LemitEmail;
use App\Models\LemitAddress;
use App\Models\LemitCar;
use App\Models\LemitCnae;
use App\Models\LemitBond;
use App\Models\LemitCreditRisk;
use App\Models\LemitPrtnrPrtcptn;
use App\Models\Gender;
use App\Models\ApiConfig;
use App\Models\PjPartner;
use App\Models\Register;
use App\Models\RegisterMaster;
use App\Models\RegisterDetail;
use App\Models\RegisterDataPj;
use App\Models\RegisterDataPf;

use App\Models\RegisterPhone;
use App\Models\RegisterEmail;
use App\Models\RegisterAddress;
use App\Models\Account;


use App\Libraries\ApiLemit;
use App\Libraries\Facilites;
use App\Services\Register\RegisterService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LemitController extends Controller
{

    public $document, $consulted_by, $master_id, $register_master_id;

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [36];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $lemit                      = new Lemit();
        $lemit->cpf_cnpj            = $request->cpf_cnpj;
        $lemit->register_master_id  = $request->register_master_id;
        $lemit->master_id           = $checkAccount->master_id;//$request->master_id;
        return response()->json($lemit->getLemit());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [35];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $this->document           = $request->cpf_cnpj;
        $this->master_id          = $checkAccount->master_id;
        $this->consulted_by       = $request->header('userId');
        $this->register_master_id = $request->register_master_id;
        $lemit = $this->checkNewLemit();
        if($lemit->success){
            if($request->register_update == 1){
                $registerService = new RegisterService();
                $registerService->cpf_cnpj        = $request->cpf_cnpj;
                $registerService->master_id       = $checkAccount->master_id;
                $registerService->lemit_id        = $lemit->id;
                $registerService->lemit_unique_id = $lemit->unique_id;
                $registerService->lemitUpdate();
            }
            return response()->json(array("success" => $lemit->message, "id" => $lemit->id, "unique_id" => $lemit->unique_id));
        } else {
            return response()->json(array("error" => $lemit->message));
        }
    }

    public function checkAllRegisterOnLemit()
    {
        $registerDetailModel = new  Account;
        $registerDetails = $registerDetailModel->getAccounts();

        foreach($registerDetails as $registerDetail ){
            $this->document           = $registerDetail->cpf_cnpj;
            $this->master_id          = $registerDetail->master_id;
            $this->consulted_by       = null;
            $this->register_master_id = $registerDetail->register_master_id;
            $lemit = $this->checkNewLemit();
        }        
    }

    public function checkNewLemit()
    {
        $cpf_cnpj           = preg_replace( '/[^0-9]/', '', $this->document);
        $validate           = new Facilites();
        $validate->cpf_cnpj = $cpf_cnpj;

        $checkLastLemit = Lemit::where('cpf_cnpj', '=', $cpf_cnpj)->where('created_at','>=', (\Carbon\Carbon::yesterday())->format('Y-m-d') );

        if($checkLastLemit->count() == 0) {
            if(strlen($cpf_cnpj) == 11) {
                if( !$validate->validateCPF($cpf_cnpj) ){
                    return (object) ["success" => false, "message" => "CPF inválido"];
                } else {
                    $apiConfig               = new ApiConfig();
                    $apiConfig->master_id    = $this->master_id;
                    $apiConfig->api_id       = 4;
                    $apiConfig->onlyActive   = 1;
                    $apiData                 = $apiConfig->getApiConfig()[0];
                    $apiLemit                = new ApiLemit();
                    $apiLemit->api_address   = Crypt::decryptString($apiData->api_address);
                    $apiLemit->authorization = Crypt::decryptString($apiData->api_key);
                    $apiLemit->document      = $cpf_cnpj;
                    $lemit                   = $apiLemit->getDataPF();
                    return $this->createLemit($lemit,1);
                }
            } else if(strlen($cpf_cnpj) == 14){
                if( !$validate->validateCNPJ($cpf_cnpj) ){
                    return (object) ["success" => false, "message" => "CNPJ inválido"];
                } else {
                    $apiConfig               = new ApiConfig();
                    $apiConfig->master_id    = $this->master_id;
                    $apiConfig->api_id       = 4;
                    $apiConfig->onlyActive   = 1;
                    $apiData                 = $apiConfig->getApiConfig()[0];
                    $apiLemit                = new ApiLemit();
                    $apiLemit->api_address   = Crypt::decryptString($apiData->api_address);
                    $apiLemit->authorization = Crypt::decryptString($apiData->api_key);
                    $apiLemit->document      = $cpf_cnpj;
                    $lemit                   = $apiLemit->getDataPJ();
                    return $this->createLemit($lemit,2);
                }
            } else {
                return (object) ["success" => false, "message" => "CPF ou CNPJ inválido"];
            }
        } else {
            return (object) ["success" => false, "message" => "CPF ou CNPJ já consultado entre ontem e hoje"];
        }
    }

    protected function createLemit($lemitData,$type)
    {
        if(!$lemitData->success){
            return (object) ["success" => false, "message" => "Ocorreu uma falha ao consultar o Lemit, por favor tente novamente mais tarde"];
        }

        $mainRegisterMasterId = $this->register_master_id;

        if($type == 1){
            $cpf_cnpj = $lemitData->data->pessoa->cpf;
        } else {
            $cpf_cnpj = $lemitData->data->empresa->cnpj;
        }

        if($lemit = Lemit::create([
            'unique_id'          => md5(rand(1, 99999).$cpf_cnpj.date('Ymd').time()),
            'cpf_cnpj'           => $cpf_cnpj,
            'master_id'          => $this->master_id,
            'register_master_id' => $this->register_master_id,
            'consulted_by'       => $this->consulted_by,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            if($type == 1){

                $createLemit = $this->createLemitPF($lemitData, $lemit);
 
            } else {
                $createLemit = $this->createLemitPJ($lemitData, $lemit);
            }


            $registerPhone = null;
            $registerEmail = null;
            $registerAddress = null;
            $partnerPfData = null;

            if( isset(  $checkNewLemitToPartner->id ) ) {
                $registerPhone = LemitPhone::where('lemit_id', '=', $createLemit->id)->orderBy('ranking', 'asc')->first();
                $registerEmail = LemitEmail::where('lemit_id', '=', $createLemit->id)->orderBy('ranking', 'asc')->first();
                $registerAddress = LemitAddress::where('lemit_id', '=', $createLemit->id)->orderBy('ranking', 'asc')->first();

                if( strlen($checkNewLemitToPartner->cpf_cnpj) == 11 ) {
                    $partnerPfData = LemitPf::where('lemit_id', '=', $checkNewLemitToPartner->id)->first();
    
                }
            }

            if (isset( $registerPhone->number )) {
                $this->createRegisterPhone($mainRegisterMasterId, $registerPhone->number);
            }

            if (isset( $registerEmail->email )) {
                $this->createRegisterEmail($mainRegisterMasterId, $registerEmail->email);
            }

            if (isset( $registerAddress->address )) {
                $this->createRegisterAddress($mainRegisterMasterId, $registerAddress);
            }

            if (isset( $partnerPfData->name )) {
                $this->updatePartnerPfData($mainRegisterMasterId, $partnerPfData);
            }

            return $createLemit;

        } else {
            return (object) ["success" => false, "message" => "Não foi possível armazenar os dados da consulta, por favor tente novamente mais tarde"];
        }
    }

    protected function createLemitPF($lemitData, $newLemit)
    {
        if(!LemitPf::create([
            'lemit_id'        => $newLemit->id,
            'lemit_unique_id' => $newLemit->unique_id,
            'name'            => $lemitData->data->pessoa->nome,
            'birth_date'      => (\Carbon\Carbon::parse($lemitData->data->pessoa->data_nascimento))->format('Y-m-d'),
            'gender_id'       => Gender::returnGenderIdByLetter($lemitData->data->pessoa->sexo),
            'mother_name'     => $lemitData->data->pessoa->nome_mae,
            'deceased'        => $lemitData->data->pessoa->falecido,
            'income'          => $lemitData->data->pessoa->renda,
            'cpf_status'      => $lemitData->data->pessoa->situacao_cpf,
            'occupation'      => substr($lemitData->data->pessoa->ocupacao, 0, 100),
            'created_at'      => \Carbon\Carbon::now()
        ])){
            return (object) ["success" => false, "message" => "Não foi possível armazenar os dados PF"];
        } else {
            if(isset($lemitData->data->pessoa->celulares)){
                $this->createLemitCellPhone($lemitData->data->pessoa->celulares,                           $newLemit);
            }
            if(isset($lemitData->data->pessoa->fixos)){
                $this->createLemitPhone($lemitData->data->pessoa->fixos,                                   $newLemit);
            }
            if(isset($lemitData->data->pessoa->emails)){
                $this->createLemitEmail($lemitData->data->pessoa->emails,                                  $newLemit);
            }
            if(isset($lemitData->data->pessoa->enderecos)){
                $this->createLemitAddress($lemitData->data->pessoa->enderecos,                             $newLemit);
            }
            if(isset($lemitData->data->pessoa->carros)){
                $this->createLemitCars($lemitData->data->pessoa->carros,                                   $newLemit);
            }
            if(isset($lemitData->data->pessoa->risco_credito)){
                $this->createLemitCreditRisk($lemitData->data->pessoa->risco_credito,                      $newLemit);
            }
            if(isset($lemitData->data->pessoa->participacao_societaria, $newLemit)){
                $this->createLemitPartenerParticipation($lemitData->data->pessoa->participacao_societaria, $newLemit);
            }
            if(isset($lemitData->data->pessoa->vinculos, $newLemit)){
                $this->createLemitBond($lemitData->data->pessoa->vinculos, $newLemit);
            }
            return (object) ["success" => true, "message" => "Lemit consultado com sucesso", "id" => $newLemit->id, "unique_id" => $newLemit->unique_id];
        }
    }

    protected function createLemitPJ($lemitData, $newLemit)
    {
        if(!LemitPj::create([
            'lemit_id'        => $newLemit->id,
            'lemit_unique_id' => $newLemit->unique_id,
            'name'            => $lemitData->data->empresa->razao_social,
            'fantasy_name'    => $lemitData->data->empresa->nome_fantasia,
            'fundation_date'  => (\Carbon\Carbon::parse($lemitData->data->empresa->data_fundacao))->format('Y-m-d'),
            'type'            => $lemitData->data->empresa->tipo,
            'situation'       => $lemitData->data->empresa->situacao,
            'created_at'      => \Carbon\Carbon::now()
        ])){
            return (object) ["success" => false, "message" => "Não foi possível armazenar os dados PJ"];
        } else {
            if(isset($lemitData->data->empresa->celulares)){
                $this->createLemitCellPhone($lemitData->data->empresa->celulares,          $newLemit);
            }
            if(isset($lemitData->data->empresa->fixos)){
                $this->createLemitPhone($lemitData->data->empresa->fixos,                  $newLemit);
            }
            if(isset($lemitData->data->empresa->emails)){
                $this->createLemitEmail($lemitData->data->empresa->emails,                 $newLemit);
            }
            if(isset($lemitData->data->empresa->endereco)){
                $this->createLemitAddressPJ($lemitData->data->empresa->endereco,           $newLemit);
            }
            if(isset($lemitData->data->empresa->carros)){
                $this->createLemitCars($lemitData->data->empresa->carros,                  $newLemit);
            }
            if(isset($lemitData->data->empresa->cnae)){
                $this->createLemitCnae($lemitData->data->empresa->cnae,                    $newLemit);
            }
            if(isset($lemitData->data->empresa->socios)){
                $this->createLemitPartenerParticipation($lemitData->data->empresa->socios, $newLemit);
            }
            return (object) ["success" => true, "message" => "Lemit consultado com sucesso", "id" => $newLemit->id, "unique_id" => $newLemit->unique_id];
        }
    }

    protected function createLemitCellPhone($lemitCellPhone, $newLemit)
    {
        foreach($lemitCellPhone as $key => $value) {
            LemitPhone::create([
                'lemit_id'        => $newLemit->id,
                'lemit_unique_id' => $newLemit->unique_id,
                'number'          => $value->ddd.$value->numero,
                'plus'            => $value->plus,
                'ranking'         => $value->ranking,
                'whatsapp'        => $value->whatsapp,
                'phone_type_id'   => 2,
                'created_at'      => \Carbon\Carbon::now()
            ]);
        }
    }

    protected function createLemitPhone($lemitPhone, $newLemit)
    {
        foreach($lemitPhone as $key => $value) {
            LemitPhone::create([
                'lemit_id'        => $newLemit->id,
                'lemit_unique_id' => $newLemit->unique_id,
                'number'          => $value->ddd.$value->numero,
                'plus'            => $value->plus,
                'ranking'         => $value->ranking,
                'phone_type_id'   => 1,
                'created_at'      => \Carbon\Carbon::now()
            ]);
        }
    }

    protected function createLemitEmail($lemitEmail, $newLemit)
    {
        foreach($lemitEmail as $key => $value) {
            LemitEmail::create([
                'lemit_id'        => $newLemit->id,
                'lemit_unique_id' => $newLemit->unique_id,
                'email'           => $value->email,
                'ranking'         => $value->ranking,
                'created_at'      => \Carbon\Carbon::now()
            ]);
        }
    }

    protected function createLemitAddress($lemitAddress, $newLemit)
    {
        foreach($lemitAddress as $key => $value) {
            LemitAddress::create([
                'lemit_id'        => $newLemit->id,
                'lemit_unique_id' => $newLemit->unique_id,
                'public_place'    => $value->tipo_logradouro,
                'address'         => $value->logradouro,
                'number'          => $value->numero,
                'complement'      => $value->complemento,
                'district'        => $value->bairro,
                'city'            => $value->cidade,
                'state_id'        => Facilites::convertStateToInt($value->uf),
                'zip_code'        => $value->cep,
                'type'            => $value->tipo,
                'ranking'         => $value->ranking,
                'created_at'      => \Carbon\Carbon::now()
            ]);
        }
    }

    protected function createLemitAddressPJ($lemitAddress, $newLemit)
    {
        LemitAddress::create([
            'lemit_id'        => $newLemit->id,
            'lemit_unique_id' => $newLemit->unique_id,
            'public_place'    => $lemitAddress->tipo_logradouro,
            'address'         => $lemitAddress->logradouro,
            'number'          => $lemitAddress->numero,
            'complement'      => $lemitAddress->complemento,
            'district'        => $lemitAddress->bairro,
            'city'            => $lemitAddress->cidade,
            'state_id'        => Facilites::convertStateToInt($lemitAddress->uf),
            'zip_code'        => $lemitAddress->cep,
            'type'            => $lemitAddress->tipo,
            'ranking'         => $lemitAddress->ranking,
            'created_at'      => \Carbon\Carbon::now()
        ]);
    }

    protected function createLemitCars($lemitCar, $newLemit)
    {
        foreach($lemitCar as $key => $value) {
            LemitCar::create([
                'lemit_id'         => $newLemit->id,
                'lemit_unique_id'  => $newLemit->unique_id,
                'license_plate'    => $value->placa,
                'brand'            => $value->marca,
                'fabrication_year' => $value->ano_fabricacao,
                'model_year'       => $value->ano_modelo,
                'renavan'          => $value->renavan,
                'chassis'          => $value->chassi,
                'licensing_date'   => (\Carbon\Carbon::parse($value->data_licenciamento))->format('Y-m-d'),
                'ranking'          => $value->ranking,
                'created_at'       => \Carbon\Carbon::now()
            ]);
        }
    }

    protected function createLemitPartenerParticipation($lemitPrtnrPrtcptn, $newLemit)
    {
        foreach($lemitPrtnrPrtcptn as $key => $value) {
            if(strlen($newLemit->cpf_cnpj) == 11){
                $lemiitParticipation = LemitPrtnrPrtcptn::create([
                    'lemit_id'              => $newLemit->id,
                    'lemit_unique_id'       => $newLemit->unique_id,
                    'name'                  => $value->nome,
                    'cpf_cnpj'              => $value->cnpj,
                    'share_capital'         => $value->capital_social,
                    'partner_participation' => substr($value->participacao_socio,0,-1),
                    'fundation_date'        => (\Carbon\Carbon::parse($value->data_fundacao))->format('Y-m-d'),
                    'cadastral_situation'   => $value->situacao_cadastral,
                    'created_at'            => \Carbon\Carbon::now()
                ]);
            } else {
                $lemiitParticipation = LemitPrtnrPrtcptn::create([
                    'lemit_id'              => $newLemit->id,
                    'lemit_unique_id'       => $newLemit->unique_id,
                    'name'                  => $value->nome,
                    'cpf_cnpj'              => $value->cpf,
                    'share_capital'         => $value->capital_social,
                    'partner_participation' => $value->participacao,//substr($value->participacao,0,-1),
                    'created_at'            => \Carbon\Carbon::now()
                ]);

                $this->createParnerFromLemit($lemiitParticipation, $newLemit);
            }         
        }
    }

    protected function createLemitCnae($lemitCnae, $newLemit)
    {
        LemitCnae::create([
            'lemit_id'        => $newLemit->id,
            'lemit_unique_id' => $newLemit->unique_id,
            'number'          => $lemitCnae->numero,
            'type'            => $lemitCnae->tipo,
            'segment'         => $lemitCnae->segmento,
            'description'     => $lemitCnae->descricao,
            'created_at'      => \Carbon\Carbon::now()
        ]);
    }

    protected function createLemitCreditRisk($lemitCreditRisk, $newLemit)
    {
        LemitCreditRisk::create([
            'lemit_id'        => $newLemit->id,
            'lemit_unique_id' => $newLemit->unique_id,
            'cpf_cnpj'        => $lemitCreditRisk->cpf_cnpj,
            'score'           => $lemitCreditRisk->score_credito,
            'created_at'      => \Carbon\Carbon::now()
        ]);
    }

    protected function createLemitBond($lemitBond, $newLemit)
    {
        foreach($lemitBond as $key => $value) {
            LemitBond::create([
                'lemit_id'        => $newLemit->id,
                'lemit_unique_id' => $newLemit->unique_id,
                'cpf_cnpj'        => $value->cpf_vinculo,
                'name'            => $value->nome_vinculo,
                'type'            => $value->tipo_vinculo,
                'created_at'      => \Carbon\Carbon::now()
            ]);
        }
    }


    protected function createParnerFromLemit($lemiitParticipation, $newLemit)
    {

        $registerMasterId           = null;
        $registerDetail            = new RegisterDetail();
        $registerDetail->master_id = $newLemit->master_id;
        $registerDetail->cpf_cnpj  = preg_replace( '/[^0-9]/', '', $lemiitParticipation->cpf_cnpj);
        $registerData              = $registerDetail->getRegisterData();

        if(sizeof($registerData) > 0){

            $registerData = $registerData[0];

            //update register if exists
            $registerMasterId = $registerData->register_master_id;

        } else {
            //create register if not exists
            $registerPartner  = app('App\Http\Controllers\RegisterController')->returnRegister(preg_replace( '/[^0-9]/', '',  $lemiitParticipation->cpf_cnpj), $lemiitParticipation->name, $newLemit->master_id);
            if($registerPartner->status == 0 ){
                return response()->json(array("error" => $registerPartner->error));
            }

            $registerMasterId = $registerPartner->success->id;

        }

        $this->register_master_id = $registerMasterId;
        $checkNewLemitToPartner = $this->checkNewLemitToPartner($lemiitParticipation);

        $partnerPhone = null;
        $partnerEmail = null;
        $partnerAddress = null;

        $partnerData = null;

        if( isset(  $checkNewLemitToPartner->id ) ) {
            $partnerPhone = LemitPhone::where('lemit_id', '=', $checkNewLemitToPartner->id)->orderBy('ranking', 'asc')->first();
            $partnerEmail = LemitEmail::where('lemit_id', '=', $checkNewLemitToPartner->id)->orderBy('ranking', 'asc')->first();
            $partnerAddress = LemitAddress::where('lemit_id', '=', $checkNewLemitToPartner->id)->orderBy('ranking', 'asc')->first();

            if( strlen($lemiitParticipation->cpf_cnpj) == 11 ) {
                $partnerPfData = LemitPf::where('lemit_id', '=', $checkNewLemitToPartner->id)->first();

            }

        }

        if (isset( $partnerPhone->number )) {
            $this->createRegisterPhone($registerMasterId, $partnerPhone->number);
        }

        if (isset( $partnerEmail->email )) {
            $this->createRegisterEmail($registerMasterId, $partnerEmail->email);
        }

        if (isset( $partnerAddress->address )) {
            $this->createRegisterAddress($registerMasterId, $partnerAddress);
        }

        if (isset( $partnerPfData->name )) {
            $this->updatePartnerPfData($registerMasterId, $partnerPfData);
        }

        
        $registerDataPj = RegisterDataPj::where('register_master_id', '=', $newLemit->register_master_id)->first();

        if( PjPartner::where('register_data_pj_id', '=', $registerDataPj->id)->where('partner_type_id','=', 1)->where('register_master_id', '=', $registerMasterId)->whereNull('deleted_at')->count() == 0){
            //create Partner
            if($pjPartner = PjPartner::Create([
                'register_master_id'  => (int) $registerMasterId,
                'register_data_pj_id' => (int) $registerDataPj->id,
                'partner_type_id'     => (int) 1,
                'participation'       => (float) $lemiitParticipation->partner_participation,
                'uuid'                => Str::orderedUuid(),
                'created_at'          => \Carbon\Carbon::now()
            ]));
        }
    }


    protected function checkNewLemitToPartner($lemiitParticipation)
    {
        
            $cpf_cnpj           = preg_replace( '/[^0-9]/', '', $lemiitParticipation->cpf_cnpj);
            if($cpf_cnpj != (preg_replace( '/[^0-9]/', '', $this->document)) ) {
                $validate           = new Facilites();
                $validate->cpf_cnpj = $cpf_cnpj;
                if(strlen($cpf_cnpj) == 11) {
                    if( $validate->validateCPF($cpf_cnpj) ){
                        $apiConfig               = new ApiConfig();
                        $apiConfig->master_id    = $this->master_id;
                        $apiConfig->api_id       = 4;
                        $apiConfig->onlyActive   = 1;
                        $apiData                 = $apiConfig->getApiConfig()[0];
                        $apiLemit                = new ApiLemit();
                        $apiLemit->api_address   = Crypt::decryptString($apiData->api_address);
                        $apiLemit->authorization = Crypt::decryptString($apiData->api_key);
                        $apiLemit->document      = $cpf_cnpj;
                        $lemit                   = $apiLemit->getDataPF();
                        return $this->createLemit($lemit,1);
                    }
                } else if(strlen($cpf_cnpj) == 14){
                    if( $validate->validateCNPJ($cpf_cnpj) ){
                        $apiConfig               = new ApiConfig();
                        $apiConfig->master_id    = $this->master_id;
                        $apiConfig->api_id       = 4;
                        $apiConfig->onlyActive   = 1;
                        $apiData                 = $apiConfig->getApiConfig()[0];
                        $apiLemit                = new ApiLemit();
                        $apiLemit->api_address   = Crypt::decryptString($apiData->api_address);
                        $apiLemit->authorization = Crypt::decryptString($apiData->api_key);
                        $apiLemit->document      = $cpf_cnpj;
                        $lemit                   = $apiLemit->getDataPJ();
                        return $this->createLemit($lemit,2);
                    }
                }
            }
    }


    protected function createRegisterPhone($registerMasterId, $number)
    {
        if( $number != null and $number != '' ) {
            
            $main = 0;

            if(RegisterPhone::where('register_master_id','=',$registerMasterId)->whereNull('deleted_at')->count() == 0){
                $main = 1;
            }

            if(RegisterPhone::where('register_master_id','=',$registerMasterId)->where('number','=',preg_replace('/[^0-9]/', '', $number))->whereNull('deleted_at')->count() == 0){
                RegisterPhone::Create([
                    'register_master_id' => $registerMasterId,
                    'number'             => preg_replace('/[^0-9]/', '', $number),
                    'main'               => $main,
                    'observation'        => 'Incluído ao consultar Lemit',
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
        }
    }

    protected function createRegisterEmail($registerMasterId, $email)
    {
        if( $email != null and $email != '' ) {

            $main = 0;
            
            if(RegisterEmail::where('register_master_id','=',$registerMasterId)->whereNull('deleted_at')->count() == 0){
                $main = 1;
            }

            if(RegisterEmail::where('register_master_id','=',$registerMasterId)->where('email','=', $email)->whereNull('deleted_at')->count() == 0){
                RegisterEmail::Create([
                    'register_master_id' => $registerMasterId,
                    'email'              => $email,
                    'main'               => $main,
                    'observation'        => 'Incluído ao consultar Lemit',
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
        }
    }

    protected function createRegisterAddress($registerMasterId, $addressData)
    {

        if( $addressData->address != null and $addressData->address != '' ){
            $main = 0;

            if(RegisterAddress::where('register_master_id','=',$registerMasterId)->whereNull('deleted_at')->count() == 0){
                $main = 1;
            }

            if(RegisterAddress::where('register_master_id','=',$registerMasterId)->where('address', '=', $addressData->address)->where('number', '=', $addressData->number)->where('city', '=', $addressData->city)->where('zip_code', '=', $addressData->zip_code)->whereNull('deleted_at')->count() == 0){
                RegisterAddress::Create([
                    'register_master_id' => $registerMasterId,
                    'state_id'           => $addressData->state_id,
                    'public_place'       => $addressData->public_place,
                    'address'            => $addressData->address,
                    'number'             => $addressData->number,
                    'complement'         => $addressData->complement,
                    'district'           => $addressData->district,
                    'city'               => $addressData->city,
                    'zip_code'           => $addressData->zip_code,
                    'main'               => $main,
                    'observation'        => 'Incluído ao consultar Lemit',
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
        }
    }

    protected function updatePartnerPfData($registerMasterId, $partnerPfData)
    {
        if( $registerDataPf = RegisterDataPf::where('register_master_id', '=', $registerMasterId)->first() ){

            if ($partnerPfData->birth_date != null and $partnerPfData->birth_date != '') {
                if( $registerDataPf->date_birth == null or $registerDataPf->date_birth == '' ) {
                    $registerDataPf->date_birth = $partnerPfData->birth_date;
                    $registerDataPf->save();
                }
            }

            if ($partnerPfData->gender_id != null and $partnerPfData->gender_id != '') {
                if( $registerDataPf->gender_id == null or $registerDataPf->gender_id == '' ) {
                    $registerDataPf->gender_id = $partnerPfData->gender_id;
                    $registerDataPf->save();
                }
            }

            if ($partnerPfData->mother_name != null and $partnerPfData->mother_name != '') {
                if( $registerDataPf->mother_name == null or $registerDataPf->mother_name == '' ) {
                    $registerDataPf->mother_name = $partnerPfData->mother_name;
                    $registerDataPf->save();
                }
            }

            if ($partnerPfData->income != null and $partnerPfData->income != '') {
                if( $registerDataPf->income == null or $registerDataPf->income == '' ) {
                    $registerDataPf->income = $partnerPfData->income;
                    $registerDataPf->save();
                }
            }

            if ($partnerPfData->occupation != null and $partnerPfData->occupation != '') {
                if( $registerDataPf->professional_occupation == null or $registerDataPf->professional_occupation == '' ) {
                    $registerDataPf->professional_occupation = substr($partnerPfData->occupation, 0, 100);
                    $registerDataPf->save();
                }
            }

            
        }
    }

}
