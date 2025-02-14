<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\EdenredCardRequest;
use App\Models\RegisterDetail;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EdenredCardRequestController extends Controller
{
    public function show(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $edenredCardRequest             = new EdenredCardRequest();
        $edenredCardRequest->account_id = $checkAccount->account_id;
        $edenredCardRequest->onlyActive = $request->onlyActive;
        return response()->json($edenredCardRequest->get());
    }

    protected function store(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $validator = Validator::make($request->all(), [
            'account_id'               => ['required', 'integer'],
        ],[
            'account_id.required'      => 'É obrigatório informar o account_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $account = new Account();
        $account->id = $request->account_id;
        $account_data = $account->returnAccountData();

        if($request->card_type_id == 3 || $request->card_type_id == 4) {
            $register_detail = new RegisterDetail();
            $register_detail->register_master_id = $request->register_master_id;
            $register_data = $register_detail->getRegister();
        }
        
        switch($request->card_type_id) {
            case 1:
                
                if( $edenredCardRequest = EdenredCardRequest::create([
                    "created_at"                    => \Carbon\Carbon::now(),
                    "uuid"                          => Str::orderedUuid(),
                    "account_id"                    => $request->account_id,
                    "register_master_id"            => $account_data->register_master_id,
                    "status_id"                     => 1,
                    "card_type_id"                  => $request->card_type_id,

                    "cpf_cnpj"                      => $account_data->cpf_cnpj,
                    "name"                          => $account_data->name,
                    "birth_date"                    => $account_data->date_birth,
                    "phone"                         => $account_data->phone,
                    "email"                         => $account_data->email,

                    "address_zip_code"              => $account_data->address_zip_code,
                    "address_public_place"          => $account_data->address_public_place,
                    "address"                       => $account_data->address,
                    "address_number"                => $account_data->address_number,
                    "address_complement"            => $account_data->address_complement,
                    "address_district"              => $account_data->address_district,
                    "address_city"                  => $account_data->address_city,
                    "address_state_id"              => $account_data->address_state_id,
        
                    "owner_cpf_cnpj"                => $account_data->cpf_cnpj,
                    "owner_name"                    => $account_data->name,
                    "owner_birth_date"              => $account_data->date_birth,
                    "owner_phone"                   => $account_data->phone,
                    "owner_email"                   => $account_data->email,
                    "owner_mother_name"             => $account_data->mother_name,
                    "owner_rg_number"               => $account_data->rg_number,
                    "owner_gender_id"               => $account_data->gender_id,
                    "owner_nationality_id"          => $account_data->nationality_id,

                    "owner_address_zip_code"        => $account_data->address_zip_code,
                    "owner_address_public_place"    => $account_data->address_public_place,
                    "owner_address"                 => $account_data->address,
                    "owner_address_number"          => $account_data->address_number,
                    "owner_address_complement"      => $account_data->address_complement,
                    "owner_address_district"        => $account_data->address_district,
                    "owner_address_city"            => $account_data->address_city,
                    "owner_address_state_id"        => $account_data->address_state_id,
        
                    "delivery_address_zip_code"     => $account_data->address_zip_code,
                    "delivery_address_public_place" => $account_data->address_public_place,
                    "delivery_address"              => $account_data->address,
                    "delivery_address_number"       => $account_data->address_number,
                    "delivery_address_complement"   => $account_data->address_complement,
                    "delivery_address_district"     => $account_data->address_district,
                    "delivery_address_city"         => $account_data->address_city,
                    "delivery_address_state_id"     => $account_data->address_state_id
                ]) ) {
                    $edn = new EdenredCardRequest;
                    $edn->id = $edenredCardRequest->id;
                    $edn->uuid = $edenredCardRequest->uuid;
                    return response()->json(["success" => "Solicitação de cartão PF realizada com sucesso", "data" => $edn->getData()]);
                } 
        
                return response()->json(["error" => "Não foi possível realizar a solicitação de cartão PF, por favor tente novamente mais tarde"]);

                break;
            case 2:

                if( $edenredCardRequest = EdenredCardRequest::create([
                    "created_at"                    => \Carbon\Carbon::now(),
                    "uuid"                          => Str::orderedUuid(),
                    "account_id"                    => $request->account_id,
                    "register_master_id"            => $account_data->register_master_id,
                    "status_id"                     => 1,
                    "card_type_id"                  => $request->card_type_id,

                    "cpf_cnpj"                      => $account_data->cpf_cnpj,
                    "name"                          => $account_data->name,
                    "birth_date"                    => $account_data->date_birth,
                    "phone"                         => $account_data->phone,
                    "email"                         => $account_data->email,

                    "address_zip_code"              => $account_data->address_zip_code,
                    "address_public_place"          => $account_data->address_public_place,
                    "address"                       => $account_data->address,
                    "address_number"                => $account_data->address_number,
                    "address_complement"            => $account_data->address_complement,
                    "address_district"              => $account_data->address_district,
                    "address_city"                  => $account_data->address_city,
                    "address_state_id"              => $account_data->address_state_id,
        
                    "owner_cpf_cnpj"                => $account_data->cpf_cnpj,
                    "owner_name"                    => $account_data->name,
                    "owner_birth_date"              => $account_data->date_birth,
                    "owner_phone"                   => $account_data->phone,
                    "owner_email"                   => $account_data->email,
                    "owner_mother_name"             => $account_data->mother_name,
                    "owner_rg_number"               => $account_data->rg_number,
                    "owner_gender_id"               => $account_data->gender_id,
                    "owner_nationality_id"          => $account_data->nationality_id,
                    
                    "owner_address_zip_code"        => $account_data->address_zip_code,
                    "owner_address_public_place"    => $account_data->address_public_place,
                    "owner_address"                 => $account_data->address,
                    "owner_address_number"          => $account_data->address_number,
                    "owner_address_complement"      => $account_data->address_complement,
                    "owner_address_district"        => $account_data->address_district,
                    "owner_address_city"            => $account_data->address_city,
                    "owner_address_state_id"        => $account_data->address_state_id,
        
                    "delivery_address_zip_code"     => $account_data->address_zip_code,
                    "delivery_address_public_place" => $account_data->address_public_place,
                    "delivery_address"              => $account_data->address,
                    "delivery_address_number"       => $account_data->address_number,
                    "delivery_address_complement"   => $account_data->address_complement,
                    "delivery_address_district"     => $account_data->address_district,
                    "delivery_address_city"         => $account_data->address_city,
                    "delivery_address_state_id"     => $account_data->address_state_id
                ]) ) {
                    $edn = new EdenredCardRequest;
                    $edn->id = $edenredCardRequest->id;
                    $edn->uuid = $edenredCardRequest->uuid;
                    return response()->json(["success" => "Solicitação de cartão PF realizada com sucesso", "data" => $edn->getData()]);
                } 
        
                return response()->json(["error" => "Não foi possível realizar a solicitação de cartão PF, por favor tente novamente mais tarde"]);

                break;
            case 3:

                $validator = Validator::make($request->all(), [
                    'register_master_id'               => ['required', 'integer'],
                ],[
                    'register_master_id.required'      => 'É obrigatório informar o register_master_id',
                ]);
                if ($validator->fails()) {
                    return ["error" => $validator->errors()->first()];
                }

                if( $edenredCardRequest = EdenredCardRequest::create([
                    "created_at"                    => \Carbon\Carbon::now(),
                    "uuid"                          => Str::orderedUuid(),
                    "account_id"                    => $request->account_id,
                    "register_master_id"            => $request->register_master_id,
                    "status_id"                     => 1,
                    "card_type_id"                  => $request->card_type_id,

                    "cpf_cnpj"                      => $account_data->cpf_cnpj,
                    "name"                          => $account_data->name,
                    "birth_date"                    => $account_data->date_birth,
                    "phone"                         => $account_data->phone,
                    "email"                         => $account_data->email,

                    "address_zip_code"              => $account_data->address_zip_code,
                    "address_public_place"          => $account_data->address_public_place,
                    "address"                       => $account_data->address,
                    "address_number"                => $account_data->address_number,
                    "address_complement"            => $account_data->address_complement,
                    "address_district"              => $account_data->address_district,
                    "address_city"                  => $account_data->address_city,
                    "address_state_id"              => $account_data->address_state_id,

                    "owner_cpf_cnpj"                => $register_data->cpf_cnpj,
                    "owner_name"                    => $register_data->name,
                    "owner_birth_date"              => $register_data->date_birth,
                    "owner_phone"                   => $register_data->phone,
                    "owner_email"                   => $register_data->email,
                    "owner_mother_name"             => $register_data->mother_name,
                    "owner_rg_number"               => $register_data->rg_number,
                    "owner_gender_id"               => $register_data->gender_id,
                    "owner_nationality_id"          => $register_data->nationality_id,

                    "owner_address_zip_code"        => $register_data->address_zip_code,
                    "owner_address_public_place"    => $register_data->address_public_place,
                    "owner_address"                 => $register_data->address,
                    "owner_address_number"          => $register_data->address_number,
                    "owner_address_complement"      => $register_data->address_complement,
                    "owner_address_district"        => $register_data->address_district,
                    "owner_address_city"            => $register_data->address_city,
                    "owner_address_state_id"        => $register_data->address_state_id,

                    "delivery_address_zip_code"     => $account_data->address_zip_code,
                    "delivery_address_public_place" => $account_data->address_public_place,
                    "delivery_address"              => $account_data->address,
                    "delivery_address_number"       => $account_data->address_number,
                    "delivery_address_complement"   => $account_data->address_complement,
                    "delivery_address_district"     => $account_data->address_district,
                    "delivery_address_city"         => $account_data->address_city,
                    "delivery_address_state_id"     => $account_data->address_state_id
                ]) ) {
                    $edn = new EdenredCardRequest;
                    $edn->id = $edenredCardRequest->id;
                    $edn->uuid = $edenredCardRequest->uuid;
                    return response()->json(["success" => "Solicitação de cartão PJ realizada com sucesso", "data" => $edn->getData()]);
                } 

                return response()->json(["error" => "Não foi possível realizar a solicitação de cartão PJ, por favor tente novamente mais tarde"]);

                break;
            case 4:

                $validator = Validator::make($request->all(), [
                    'register_master_id'               => ['required', 'integer'],
                ],[
                    'register_master_id.required'      => 'É obrigatório informar o register_master_id',
                ]);
                if ($validator->fails()) {
                    return ["error" => $validator->errors()->first()];
                }

                if( $edenredCardRequest = EdenredCardRequest::create([
                    "created_at"                    => \Carbon\Carbon::now(),
                    "uuid"                          => Str::orderedUuid(),
                    "account_id"                    => $request->account_id,
                    "register_master_id"            => $request->register_master_id,
                    "status_id"                     => 1,
                    "card_type_id"                  => $request->card_type_id,

                    "cpf_cnpj"                      => $account_data->cpf_cnpj,
                    "name"                          => $account_data->name,
                    "birth_date"                    => $account_data->date_birth,
                    "phone"                         => $account_data->phone,
                    "email"                         => $account_data->email,

                    "address_zip_code"              => $account_data->address_zip_code,
                    "address_public_place"          => $account_data->address_public_place,
                    "address"                       => $account_data->address,
                    "address_number"                => $account_data->address_number,
                    "address_complement"            => $account_data->address_complement,
                    "address_district"              => $account_data->address_district,
                    "address_city"                  => $account_data->address_city,
                    "address_state_id"              => $account_data->address_state_id,

                    "owner_cpf_cnpj"                => $register_data->cpf_cnpj,
                    "owner_name"                    => $register_data->name,
                    "owner_birth_date"              => $register_data->date_birth,
                    "owner_phone"                   => $register_data->phone,
                    "owner_email"                   => $register_data->email,
                    "owner_mother_name"             => $register_data->mother_name,
                    "owner_rg_number"               => $register_data->rg_number,
                    "owner_gender_id"               => $register_data->gender_id,
                    "owner_nationality_id"          => $register_data->nationality_id,

                    "owner_address_zip_code"        => $register_data->address_zip_code,
                    "owner_address_public_place"    => $register_data->address_public_place,
                    "owner_address"                 => $register_data->address,
                    "owner_address_number"          => $register_data->address_number,
                    "owner_address_complement"      => $register_data->address_complement,
                    "owner_address_district"        => $register_data->address_district,
                    "owner_address_city"            => $register_data->address_city,
                    "owner_address_state_id"        => $register_data->address_state_id,

                    "delivery_address_zip_code"     => $account_data->address_zip_code,
                    "delivery_address_public_place" => $account_data->address_public_place,
                    "delivery_address"              => $account_data->address,
                    "delivery_address_number"       => $account_data->address_number,
                    "delivery_address_complement"   => $account_data->address_complement,
                    "delivery_address_district"     => $account_data->address_district,
                    "delivery_address_city"         => $account_data->address_city,
                    "delivery_address_state_id"     => $account_data->address_state_id
                ]) ) {
                    $edn = new EdenredCardRequest;
                    $edn->id = $edenredCardRequest->id;
                    $edn->uuid = $edenredCardRequest->uuid;
                    return response()->json(["success" => "Solicitação de cartão PJ realizada com sucesso", "data" => $edn->getData()]);
                } 

                return response()->json(["error" => "Não foi possível realizar a solicitação de cartão PJ, por favor tente novamente mais tarde"]);

                break;
            case 5:

                if( $edenredCardRequest = EdenredCardRequest::create([
                    "created_at"                    => \Carbon\Carbon::now(),
                    "uuid"                          => Str::orderedUuid(),
                    "account_id"                    => $request->account_id,
                    "register_master_id"            => $account_data->register_master_id,
                    "status_id"                     => 1,
                    "card_type_id"                  => $request->card_type_id,

                    "cpf_cnpj"                      => $account_data->cpf_cnpj,
                    "name"                          => $account_data->name,
                    "birth_date"                    => $account_data->date_birth,
                    "phone"                         => $account_data->phone,
                    "email"                         => $account_data->email,

                    "address_zip_code"              => $account_data->address_zip_code,
                    "address_public_place"          => $account_data->address_public_place,
                    "address"                       => $account_data->address,
                    "address_number"                => $account_data->address_number,
                    "address_complement"            => $account_data->address_complement,
                    "address_district"              => $account_data->address_district,
                    "address_city"                  => $account_data->address_city,
                    "address_state_id"              => $account_data->address_state_id,

                    "owner_cpf_cnpj"                => $account_data->cpf_cnpj,
                    "owner_name"                    => $account_data->name,
                    "owner_birth_date"              => $account_data->date_birth,
                    "owner_phone"                   => $account_data->phone,
                    "owner_email"                   => $account_data->email,

                    "owner_address_zip_code"        => $account_data->address_zip_code,
                    "owner_address_public_place"    => $account_data->address_public_place,
                    "owner_address"                 => $account_data->address,
                    "owner_address_number"          => $account_data->address_number,
                    "owner_address_complement"      => $account_data->address_complement,
                    "owner_address_district"        => $account_data->address_district,
                    "owner_address_city"            => $account_data->address_city,
                    "owner_address_state_id"        => $account_data->address_state_id,

                    "delivery_address_zip_code"     => $account_data->address_zip_code,
                    "delivery_address_public_place" => $account_data->address_public_place,
                    "delivery_address"              => $account_data->address,
                    "delivery_address_number"       => $account_data->address_number,
                    "delivery_address_complement"   => $account_data->address_complement,
                    "delivery_address_district"     => $account_data->address_district,
                    "delivery_address_city"         => $account_data->address_city,
                    "delivery_address_state_id"     => $account_data->address_state_id
                ]) ) {
                    $edn = new EdenredCardRequest;
                    $edn->id = $edenredCardRequest->id;
                    $edn->uuid = $edenredCardRequest->uuid;
                    return response()->json(["success" => "Solicitação de cartão PJ sem vínculo PF realizada com sucesso", "data" => $edn->getData()]);
                } 

                return response()->json(["error" => "Não foi possível realizar a solicitação de cartão PJ sem vínculo PF, por favor tente novamente mais tarde"]);

                break;
            case 6:
                
                if( $edenredCardRequest = EdenredCardRequest::create([
                    "created_at"                    => \Carbon\Carbon::now(),
                    "uuid"                          => Str::orderedUuid(),
                    "account_id"                    => $request->account_id,
                    "register_master_id"            => $account_data->register_master_id,
                    "status_id"                     => 1,
                    "card_type_id"                  => $request->card_type_id,

                    "cpf_cnpj"                      => $account_data->cpf_cnpj,
                    "name"                          => $account_data->name,
                    "birth_date"                    => $account_data->date_birth,
                    "phone"                         => $account_data->phone,
                    "email"                         => $account_data->email,

                    "address_zip_code"              => $account_data->address_zip_code,
                    "address_public_place"          => $account_data->address_public_place,
                    "address"                       => $account_data->address,
                    "address_number"                => $account_data->address_number,
                    "address_complement"            => $account_data->address_complement,
                    "address_district"              => $account_data->address_district,
                    "address_city"                  => $account_data->address_city,
                    "address_state_id"              => $account_data->address_state_id,

                    "owner_cpf_cnpj"                => $account_data->cpf_cnpj,
                    "owner_name"                    => $account_data->name,
                    "owner_birth_date"              => $account_data->date_birth,
                    "owner_phone"                   => $account_data->phone,
                    "owner_email"                   => $account_data->email,

                    "owner_address_zip_code"        => $account_data->address_zip_code,
                    "owner_address_public_place"    => $account_data->address_public_place,
                    "owner_address"                 => $account_data->address,
                    "owner_address_number"          => $account_data->address_number,
                    "owner_address_complement"      => $account_data->address_complement,
                    "owner_address_district"        => $account_data->address_district,
                    "owner_address_city"            => $account_data->address_city,
                    "owner_address_state_id"        => $account_data->address_state_id,

                    "delivery_address_zip_code"     => $account_data->address_zip_code,
                    "delivery_address_public_place" => $account_data->address_public_place,
                    "delivery_address"              => $account_data->address,
                    "delivery_address_number"       => $account_data->address_number,
                    "delivery_address_complement"   => $account_data->address_complement,
                    "delivery_address_district"     => $account_data->address_district,
                    "delivery_address_city"         => $account_data->address_city,
                    "delivery_address_state_id"     => $account_data->address_state_id
                ]) ) {
                    $edn = new EdenredCardRequest;
                    $edn->id = $edenredCardRequest->id;
                    $edn->uuid = $edenredCardRequest->uuid;
                    return response()->json(["success" => "Solicitação de cartão PJ sem vínculo PF realizada com sucesso", "data" => $edn->getData()]);
                } 

                return response()->json(["error" => "Não foi possível realizar a solicitação de cartão PJ sem vínculo PF, por favor tente novamente mais tarde"]);

                break;
            case 7:
                
                break;
            default:
                return response()->json(["error" => "Não foi possível localizar o cartão informado."]);
        }

        // return response()->json($response);

    }


    protected function setCardTypeId(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'card_type_id'           => ['required', 'integer'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'card_type_id.required'  => 'É obrigatório informar o card_type_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->card_type_id = $request->card_type_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Tipo do cartão alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o tipo do cartão, por favor tente novamente mais tarde"]);
    }

    protected function setAccountId(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        //$accountCheckService->permission_id = [122, 309, 351];
        $accountCheckService->permission_id = [122];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'account_id'             => ['required', 'integer'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'account_id.required'    => 'É obrigatório informar o account_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->account_id = $request->account_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Conta a ser vinculada no cartão alterada com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar a conta a ser vinculada no cartão, por favor tente novamente mais tarde"]);
    }

    protected function setRegisterMasterId(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122];
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'register_master_id'           => ['required', 'integer'],
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'register_master_id.required'  => 'É obrigatório informar o register_master_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }
        
        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->register_master_id = $request->register_master_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Cadastro a ser vinculado no cartão alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o cadastro a ser vinculado no cartão, por favor tente novamente mais tarde"]);
    }

    protected function setCpfCnpj(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'cpf_cnpj'           => ['required', 'string'],
        ],[
            'id.required'        => 'É obrigatório informar o id',
            'uuid.required'      => 'É obrigatório informar o uuid',
            'cpf_cnpj.required'  => 'É obrigatório informar o cpf_cnpj',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }
        
        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;
        $isCpf = false;

        if (strlen($cpf_cnpj) == 11) {
            $isCpf = true;
            if (!$facilites->validateCPF($cpf_cnpj) ) {
                return response()->json(array("error" => "CPF inválido."));
            }
        } else if (strlen($cpf_cnpj) == 14) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido."));
        }

        $edenredCardRequest->cpf_cnpj = $cpf_cnpj;

        if ($edenredCardRequest->save()) {
            if ($isCpf) {
                return response()->json(array("success" => "CPF alterado com sucesso"));
            }
            return response()->json(array("success" => "CNPJ alterado com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu um erro ao alterar o CPF/CNPJ, por favor tente novamente mais tarde"));
    }

    protected function setName(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                  => ['required', 'integer'],
            'uuid'                => ['required', 'string'],
            'name'                => ['required', 'string'],
        ],[
            'id.required'         => 'É obrigatório informar o id',
            'uuid.required'       => 'É obrigatório informar o uuid',
            'name.required'       => 'É obrigatório informar o name',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->name = $request->name;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Nome alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o nome, por favor tente novamente mais tarde"]);
    }

    protected function setBirthDate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'birth_date'                => ['nullable', 'date']
        ],[
            'id.required'               => 'É obrigatório informar o id',
            'uuid.required'             => 'É obrigatório informar o uuid',
            'birth_date.required'       => 'É obrigatório informar o birth_date',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->birth_date = $request->birth_date;

        if ($edenredCardRequest->save()) {
            return response()->json(array("success" => "Data de nascimento alterada com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu um erro ao alterar a data de nascimento, por favor tente novamente mais tarde"));
    }

    protected function setPhone(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                  => ['required', 'integer'],
            'uuid'                => ['required', 'string'],
            'phone'               => ['required', 'string'],
        ],[
            'id.required'         => 'É obrigatório informar o id',
            'uuid.required'       => 'É obrigatório informar o uuid',
            'phone.required'      => 'É obrigatório informar o phone',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->phone = $request->phone;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Telefone alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o telefone, por favor tente novamente mais tarde"]);
    }

    protected function setEmail(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                  => ['required', 'integer'],
            'uuid'                => ['required', 'string'],
            'email'               => ['required', 'string'],
        ],[
            'id.required'         => 'É obrigatório informar o id',
            'uuid.required'       => 'É obrigatório informar o uuid',
            'email.required'      => 'É obrigatório informar o email',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->email = mb_strtolower($request->email);

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "E-mail alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o e-mail, por favor tente novamente mais tarde"]);
    }

    protected function setAddressZipCode(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'address_zip_code'           => ['required', 'string'],
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'address_zip_code.required'  => 'É obrigatório informar o address_zip_code',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        if(strlen(preg_replace( '/[^0-9]/', '', $request->address_zip_code)) > 8 ){
            return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
        }

        $edenredCardRequest->address_zip_code = $request->address_zip_code;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "CEP alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o CEP, por favor tente novamente mais tarde"]);
    }

    protected function setAddressPublicPlace(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                             => ['required', 'integer'],
            'uuid'                           => ['required', 'string'],
            'address_public_place'           => ['required', 'string'],
        ],[
            'id.required'                    => 'É obrigatório informar o id',
            'uuid.required'                  => 'É obrigatório informar o uuid',
            'address_public_place.required'  => 'É obrigatório informar o address_public_place',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->address_public_place = $request->address_public_place;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Logradouro alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o logradouro, por favor tente novamente mais tarde"]);
    }

    protected function setAddress(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'address'               => ['required', 'string'],
        ],[
            'id.required'           => 'É obrigatório informar o id',
            'uuid.required'         => 'É obrigatório informar o uuid',
            'address.required'      => 'É obrigatório informar o address',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->address = $request->address;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Endereço alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o endereço, por favor tente novamente mais tarde"]);
    }

    protected function setAddressNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'address_number'             => ['required', 'string']
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'address_number.required'    => 'É obrigatório informar o address_number',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->address_number = $request->address_number;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Número alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o número, por favor tente novamente mais tarde"]); 
    }

    protected function setAddressComplement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                             => ['required', 'integer'],
            'uuid'                           => ['required', 'string'],
            'address_complement'             => ['required', 'string']
        ],[
            'id.required'                    => 'É obrigatório informar o id',
            'uuid.required'                  => 'É obrigatório informar o uuid',
            'address_complement.required'    => 'É obrigatório informar o address_complement',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }


        $edenredCardRequest->address_complement = $request->address_complement;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Complemento alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o complemento, por favor tente novamente mais tarde"]); 
    }

    protected function setAddressDistrict(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'address_district'            => ['required', 'string']
        ],[
            'id.required'                 => 'É obrigatório informar o id',
            'uuid.required'               => 'É obrigatório informar o uuid',
            'address_district.required'   => 'É obrigatório informar o address_district',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->address_district = $request->address_district;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Bairro alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o bairro, por favor tente novamente mais tarde"]); 
    }

    protected function setAddressCity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'address_city'           => ['required', 'string']
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'address_city.required'  => 'É obrigatório informar o address_city',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->address_city = $request->address_city;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Cidade alterada com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar a cidade, por favor tente novamente mais tarde"]); 
    }

    protected function setAddressStateId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'address_state_id'            => ['required', 'integer']
        ],[
            'id.required'                 => 'É obrigatório informar o id',
            'uuid.required'               => 'É obrigatório informar o uuid',
            'address_state_id.required'   => 'É obrigatório informar o address_state_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->address_state_id = $request->address_state_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Estado alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o estado, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerCpfCnpj(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'owner_cpf_cnpj'           => ['required', 'string'],
        ],[
            'id.required'              => 'É obrigatório informar o id',
            'uuid.required'            => 'É obrigatório informar o uuid',
            'owner_cpf_cnpj.required'  => 'É obrigatório informar o owner_cpf_cnpj',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }
        
        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->owner_cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;
        $isCpf = false;

        if (strlen($cpf_cnpj) == 11) {
            $isCpf = true;
            if (!$facilites->validateCPF($cpf_cnpj) ) {
                return response()->json(array("error" => "CPF inválido."));
            }
        } else if (strlen($cpf_cnpj) == 14) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido."));
        }

        $edenredCardRequest->owner_cpf_cnpj = $cpf_cnpj;

        if ($edenredCardRequest->save()) {
            if ($isCpf) {
                return response()->json(array("success" => "CPF alterado com sucesso"));
            }
            return response()->json(array("success" => "CNPJ alterado com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu um erro ao alterar o CPF/CNPJ, por favor tente novamente mais tarde"));
    }

    protected function setOwnerName(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string'],
            'owner_name'           => ['required', 'string'],
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'owner_name.required'  => 'É obrigatório informar o owner_name',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_name = $request->owner_name;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Nome alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o nome, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerEmail(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                      => ['required', 'integer'],
            'uuid'                    => ['required', 'string'],
            'owner_email'             => ['required', 'string'],
        ],[
            'id.required'             => 'É obrigatório informar o id',
            'uuid.required'           => 'É obrigatório informar o uuid',
            'owner_email.required'    => 'É obrigatório informar o owner_email',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_email = mb_strtolower($request->owner_email);

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "E-mail alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o e-mail, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerBirthDate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'owner_birth_date'            => ['nullable', 'date']
        ],[
            'id.required'                 => 'É obrigatório informar o id',
            'uuid.required'               => 'É obrigatório informar o uuid',
            'owner_birth_date.required'   => 'É obrigatório informar o owner_birth_date',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_birth_date = $request->owner_birth_date;

        if ($edenredCardRequest->save()) {
            return response()->json(array("success" => "Data de nascimento alterada com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu um erro ao alterar a data de nascimento, por favor tente novamente mais tarde"));
    }

    protected function setOwnerPhone(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'owner_phone'            => ['required', 'string'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'owner_phone.required'   => 'É obrigatório informar o owner_phone',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_phone = $request->owner_phone;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Telefone alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o telefone, por favor tente novamente mais tarde"]);
    }
    
    protected function setOwnerMotherName(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'owner_mother_name'            => ['required', 'string'],
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'owner_mother_name.required'   => 'É obrigatório informar o owner_mother_name',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_mother_name = $request->owner_mother_name;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Nome da mãe alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o nome da mãe, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerRgNumber(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'owner_rg_number'              => ['required', 'string'],
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'owner_rg_number.required'     => 'É obrigatório informar o owner_rg_number',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_rg_number = $request->owner_rg_number;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "RG alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o RG, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerGenderId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'owner_gender_id'              => ['required', 'integer']
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'owner_gender_id.required'     => 'É obrigatório informar o owner_gender_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_gender_id = $request->owner_gender_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Gênero alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o gênero, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerNationalityId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                             => ['required', 'integer'],
            'uuid'                           => ['required', 'string'],
            'owner_nationality_id'           => ['required', 'integer']
        ],[
            'id.required'                    => 'É obrigatório informar o id',
            'uuid.required'                  => 'É obrigatório informar o uuid',
            'owner_nationality_id.required'  => 'É obrigatório informar o owner_nationality_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_nationality_id = $request->owner_nationality_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Nacionalidade alterada com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar a nacionalidade, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerAddressZipCode(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'owner_address_zip_code'           => ['required', 'string'],
        ],[
            'id.required'                      => 'É obrigatório informar o id',
            'uuid.required'                    => 'É obrigatório informar o uuid',
            'owner_address_zip_code.required'  => 'É obrigatório informar o owner_address_zip_code',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        if(strlen(preg_replace( '/[^0-9]/', '', $request->owner_address_zip_code)) > 8 ){
            return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
        }

        $edenredCardRequest->owner_address_zip_code = $request->owner_address_zip_code;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "CEP alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o CEP, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerAddressPublicPlace(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                   => ['required', 'integer'],
            'uuid'                                 => ['required', 'string'],
            'owner_address_public_place'           => ['required', 'string'],
        ],[
            'id.required'                          => 'É obrigatório informar o id',
            'uuid.required'                        => 'É obrigatório informar o uuid',
            'owner_address_public_place.required'  => 'É obrigatório informar o owner_address_public_place',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_address_public_place = $request->owner_address_public_place;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Logradouro alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o logradouro, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerAddress(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'owner_address'            => ['required', 'string'],
        ],[
            'id.required'              => 'É obrigatório informar o id',
            'uuid.required'            => 'É obrigatório informar o uuid',
            'owner_address.required'   => 'É obrigatório informar o owner_address',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_address = $request->owner_address;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Endereço alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o endereço, por favor tente novamente mais tarde"]);
    }

    protected function setOwnerAddressNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'owner_address_number'             => ['required', 'string']
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'owner_address_number.required'    => 'É obrigatório informar o owner_address_number',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_address_number = $request->owner_address_number;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Número alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o número, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerAddressComplement(Request $request)
    {   
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                 => ['required', 'integer'],
            'uuid'                               => ['required', 'string'],
            'owner_address_complement'           => ['required', 'string']
        ],[
            'id.required'                        => 'É obrigatório informar o id',
            'uuid.required'                      => 'É obrigatório informar o uuid',
            'owner_address_complement.required'  => 'É obrigatório informar o owner_address_complement',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }


        $edenredCardRequest->owner_address_complement = $request->owner_address_complement;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Complemento alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o complemento, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerAddressDistrict(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
            'owner_address_district'            => ['required', 'string']
        ],[
            'id.required'                       => 'É obrigatório informar o id',
            'uuid.required'                     => 'É obrigatório informar o uuid',
            'owner_address_district.required'   => 'É obrigatório informar o owner_address_district',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_address_district = $request->owner_address_district;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Bairro alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o bairro, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerAddressCity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'owner_address_city'           => ['required', 'string']
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'owner_address_city.required'  => 'É obrigatório informar o owner_address_city',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_address_city = $request->owner_address_city;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Cidade alterada com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar a cidade, por favor tente novamente mais tarde"]); 
    }

    protected function setOwnerAddressStateId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
            'owner_address_state_id'            => ['required', 'integer']
        ],[
            'id.required'                       => 'É obrigatório informar o id',
            'uuid.required'                     => 'É obrigatório informar o uuid',
            'owner_address_state_id.required'   => 'É obrigatório informar o owner_address_state_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->owner_address_state_id = $request->owner_address_state_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Estado alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o estado, por favor tente novamente mais tarde"]); 
    }
    
    protected function setDeliveryAddressZipCode(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                  => ['required', 'integer'],
            'uuid'                                => ['required', 'string'],
            'delivery_address_zip_code'           => ['required', 'string'],
        ],[
            'id.required'                         => 'É obrigatório informar o id',
            'uuid.required'                       => 'É obrigatório informar o uuid',
            'delivery_address_zip_code.required'  => 'É obrigatório informar o delivery_address_zip_code',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        if(strlen(preg_replace( '/[^0-9]/', '', $request->delivery_address_zip_code)) > 8 ){
            return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
        }

        $edenredCardRequest->delivery_address_zip_code = $request->delivery_address_zip_code;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "CEP alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o CEP, por favor tente novamente mais tarde"]);
    }

    protected function setDeliveryAddressPublicPlace(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                      => ['required', 'integer'],
            'uuid'                                    => ['required', 'string'],
            'delivery_address_public_place'           => ['required', 'string'],
        ],[
            'id.required'                             => 'É obrigatório informar o id',
            'uuid.required'                           => 'É obrigatório informar o uuid',
            'delivery_address_public_place.required'  => 'É obrigatório informar o delivery_address_public_place',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->delivery_address_public_place = $request->delivery_address_public_place;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Logradouro alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o logradouro, por favor tente novamente mais tarde"]);
    }

    protected function setDeliveryAddress(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'delivery_address'           => ['required', 'string'],
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'delivery_address.required'  => 'É obrigatório informar o delivery_address',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->delivery_address = $request->delivery_address;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Endereço alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o endereço, por favor tente novamente mais tarde"]);
    }

    protected function setDeliveryAddressNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
            'delivery_address_number'           => ['required', 'string']
        ],[
            'id.required'                       => 'É obrigatório informar o id',
            'uuid.required'                     => 'É obrigatório informar o uuid',
            'delivery_address_number.required'  => 'É obrigatório informar o delivery_address_number',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->delivery_address_number = $request->delivery_address_number;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Número alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o número, por favor tente novamente mais tarde"]); 
    }

    protected function setDeliveryAddressComplement(Request $request)
    {   
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                   => ['required', 'integer'],
            'uuid'                                 => ['required', 'string'],
            'delivery_address_complement'          => ['required', 'string']
        ],[
            'id.required'                          => 'É obrigatório informar o id',
            'uuid.required'                        => 'É obrigatório informar o uuid',
            'delivery_address_complement.required' => 'É obrigatório informar o delivery_address_complement',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }


        $edenredCardRequest->delivery_address_complement = $request->delivery_address_complement;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Complemento alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o complemento, por favor tente novamente mais tarde"]); 
    }

    protected function setDeliveryAddressDistrict(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                   => ['required', 'integer'],
            'uuid'                                 => ['required', 'string'],
            'delivery_address_district'            => ['required', 'string']
        ],[
            'id.required'                          => 'É obrigatório informar o id',
            'uuid.required'                        => 'É obrigatório informar o uuid',
            'delivery_address_district.required'   => 'É obrigatório informar o delivery_address_district',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->delivery_address_district = $request->delivery_address_district;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Bairro alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o bairro, por favor tente novamente mais tarde"]); 
    }

    protected function setDeliveryAddressCity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                              => ['required', 'integer'],
            'uuid'                            => ['required', 'string'],
            'delivery_address_city'           => ['required', 'string']
        ],[
            'id.required'                     => 'É obrigatório informar o id',
            'uuid.required'                   => 'É obrigatório informar o uuid',
            'delivery_address_city.required'  => 'É obrigatório informar o delivery_address_city',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->delivery_address_city = $request->delivery_address_city;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Cidade alterada com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar a cidade, por favor tente novamente mais tarde"]); 
    }

    protected function setDeliveryAddressStateId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $validator = Validator::make($request->all(), [
            'id'                                 => ['required', 'integer'],
            'uuid'                               => ['required', 'string'],
            'delivery_address_state_id'          => ['required', 'integer']
        ],[
            'id.required'                        => 'É obrigatório informar o id',
            'uuid.required'                      => 'É obrigatório informar o uuid',
            'delivery_address_state_id.required' => 'É obrigatório informar o delivery_address_state_id',
        ]);
        if ($validator->fails()) {
            return ["error" => $validator->errors()->first()];
        }

        try {
            if ( ! $edenredCardRequest = EdenredCardRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde"));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $edenredCardRequest->delivery_address_state_id = $request->delivery_address_state_id;

        if( $edenredCardRequest->save() ) {
            return response()->json(["success" => "Estado alterado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao alterar o estado, por favor tente novamente mais tarde"]); 
    }

    
}
