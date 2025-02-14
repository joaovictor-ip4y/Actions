<?php

namespace App\Http\Controllers;

use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\Facilites;
use App\Models\AccntMvmntManualInsert;
use App\Models\AccountMovement;
use App\Models\ApiConfig;
use App\Models\AuthorizationToken;
use App\Models\MovementType;
use App\Models\Master;
use App\Models\SendSms;
use App\Models\SystemFunctionMaster;
use App\Models\User;
use App\Models\UserRelationship;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccntMvmntManualInsertController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [56];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accntMvmntManualInsert                         = new AccntMvmntManualInsert();
        $accntMvmntManualInsert->id                     = $request->id;
        $accntMvmntManualInsert->uuid                   = $request->uuid;
        $accntMvmntManualInsert->master_id              = $request->master_id;
        $accntMvmntManualInsert->account_id             = $request->account_id;
        $accntMvmntManualInsert->user_relationship_id   = $request->user_relationship_id;
        $accntMvmntManualInsert->mvmnt_type_id          = $request->mvmnt_type_id;
        $accntMvmntManualInsert->value_start            = $request->value_start;
        $accntMvmntManualInsert->value_end              = $request->value_end;
        return response()->json($accntMvmntManualInsert->get());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [56];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'integer'],
            'type_id' => ['required', 'integer'],
            'description' => ['required', 'string'],
            'password' => ['required', 'string'],
            'value'=> ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if (!Auth::check()) {
            return response()->json(array("error" => "Realize o login para continuar."));
        }

        $user = Auth::user();
        $usr  = User::where('id','=',$user->id)->first();
        if (!Hash::check(base64_decode($request->password), $usr->password)) {
            return response()->json(array("error" => "Senha inválida."));
        }   

        if($request->value <= 0) {
            return response()->json(array("error" => "Informe um valor maior que 0 para continuar."));
        }

        $accntMvmntManualInsert                         = new AccntMvmntManualInsert();
        $accntMvmntManualInsert->user_relationship_id   = $checkAccount->user_relationship_id;
        $accntMvmntManualInsert->start_date             = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accntMvmntManualInsert->end_date               = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        $accntMvmntManualInsertTotal                    = $accntMvmntManualInsert->sumMovementDay();

        if (UserRelationship::where('id', '=', $checkAccount->user_relationship_id)->first()->manual_entry_daily_limit < ($accntMvmntManualInsertTotal->total_value + $request->value) ) {
            return response()->json(array("error" => "Poxa, seu vínculo não possui limite diário para incluir esse lançamento, por favor entre em contato com o administrador do sistema"));
        }

        $positive = [9, 10, 11, 12, 13, 14, 15, 20, 41, 42, 44, 46, 48];
        $negative = [2, 4, 5, 6, 7, 8, 16, 17, 21, 22, 23, 24, 30, 31, 32, 33, 34, 35, 36, 39, 43, 45, 47];

        if( ( ! in_array($request->type_id, $positive) ) and ( ! in_array($request->type_id, $negative) ) ){
            return response()->json(array("error" => "Tipo de lançamento não permitido"));
        }

        if (!$accntMvmntManualInsert = AccntMvmntManualInsert::create([
            'uuid'                      => Str::orderedUuid(),
            'master_id'                 => $checkAccount->master_id,
            'user_relationship_id'      => $checkAccount->user_relationship_id,
            'account_id'                => $request->account_id,
            'mvmnt_type_id'             => $request->type_id,
            'description'               => $request->description,
            'value'                     => $request->value > 0 ? $request->value : 0,
            'created_at'                => \Carbon\Carbon::now(),
        ])) {
            return response()->json(array("error" => "Poxa, não foi possível efetivar o lançamento, por favor entre em contato com o administrador do sistema"));
        }

        $token = new Facilites();

        if (!$authorizationToken = AuthorizationToken::create([
            'token_phone'       => $token->createApprovalToken(),
            'token_email'       => $token->createApprovalToken(),
            'type_id'           => 2,
            'origin_id'         => $accntMvmntManualInsert->id,
            'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
            'token_expired'     => 0,
            'created_at'        => \Carbon\Carbon::now()
        ])) {
            return response()->json(array("error" => "Poxa, não foi possível salvar os parametros de autorização do token no banco de dados, por favor entre em contato com o administrador do sistema"));
        }

        if (!$sendSMS = SendSms::create([
            'external_id' => ("2".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$accntMvmntManualInsert->id),
            'to'          => "55".$checkAccount->user_phone,
            'message'     => "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para aprovar o lançamento de R$ ".number_format($accntMvmntManualInsert->value, 2, ',','.'),
            'type_id'     => 2,
            'origin_id'   => $accntMvmntManualInsert->id,
            'created_at'  => \Carbon\Carbon::now()
        ])) {
            return response()->json(array("error" => "Poxa, não foi possível salvar os parametros da mensagem no banco de dados, por favor entre em contato com o administrador do sistema"));
        }

        $accntMvmntManualInsert->approval_token            = $authorizationToken->token_phone;
        $accntMvmntManualInsert->approval_token_expiration = $authorizationToken->token_expiration;

        if (!$accntMvmntManualInsert->save()) {
            return response()->json(array("error" => "Poxa, não foi possível salvar os parametros referente ao token no banco de dados, por favor entre em contato com o administrador do sistema"));
        }

        $apiConfig                     = new ApiConfig();
        $apiConfig->master_id          = $request->header('masterId');
        $apiConfig->api_id             = 3;
        $apiConfig->onlyActive         = 1;
        $apiData                       = $apiConfig->getApiConfig()[0];
        $apiZenviaSMS                  = new ApiZenviaSMS();
        $apiZenviaSMS->api_address     = Crypt::decryptString($apiData->api_address);
        $apiZenviaSMS->authorization   = Crypt::decryptString($apiData->api_authentication);
        $apiZenviaSMS->id              = $sendSMS->external_id;
        $apiZenviaSMS->aggregateId     = "001";
        $apiZenviaSMS->to              = $sendSMS->to;
        $apiZenviaSMS->msg             = $sendSMS->message;
        $apiZenviaSMS->callbackOption  = "NONE";

        //Check if should send token by whatsapp
        if ( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$request->header('masterId'))->first())->available == 1 ){
            $apiZenviaWhats            = new ApiZenviaWhatsapp();
            $apiZenviaWhats->to_number = $sendSMS->to;
            $apiZenviaWhats->token     = "*".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4)."*";
            if (isset( $apiZenviaWhats->sendToken()->success ) ){
                return response()->json(
                    array(
                    "success" => "Token para aprovação do lançamento no valor de R$ ".number_format($accntMvmntManualInsert->value, 2, ',','.')." enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo",
                    "data" => array(
                        "id"   => $accntMvmntManualInsert->id,
                        "uuid" => $accntMvmntManualInsert->uuid
                        )
                    )
                );
            }
        }

        if (isset( $apiZenviaSMS->sendShortSMS()->success ) ){
            return response()->json(
                array(
                    "success" => "Token para aprovação do lançamento no valor de R$ ".number_format($accntMvmntManualInsert->value, 2, ',','.')." enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo",
                    "data" => array(
                        "id"   => $accntMvmntManualInsert->id,
                        "uuid" => $accntMvmntManualInsert->uuid
                    )
                )
            );
        } else {
            return response()->json(array("error" => "Não foi possível enviar o token de lançamento, por favor tente novamente"));
        }
    }

    protected function approve(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [56];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'uuid' => ['required', 'string'],
            'approval_token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if (!$accntMvmntManualInsert = AccntMvmntManualInsert::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Não foi possível localizar o lançamento, por favor tente novamente"));
        }

        if ($accntMvmntManualInsert->approved == 1) {
            return response()->json(array("error" => "Lançamento já liberado"));
        }

        if($request->approval_token == null or $accntMvmntManualInsert->approval_token == ''){
            return response()->json(array("error" => "Token inválido"));
        }

        if($request->approval_token != $accntMvmntManualInsert->approval_token){
            return response()->json(array("error" => "Token inválido"));
        }

        if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse($accntMvmntManualInsert->approval_token_expiration )->format('Y-m-d H:i:s')) ){
            $authorizationToken = AuthorizationToken::where('origin_id','=',$accntMvmntManualInsert->id)->where('type_id','=',2)->where('token_phone','=',$accntMvmntManualInsert->approval_token)->first();
            $authorizationToken->token_expired = 1;
            $authorizationToken->save();
            return response()->json(array("error" => "Token inválido, token gerado a mais de 5 minutos, cancele e faça novamente o processo de lançamento"));
        }

        $accntMvmntManualInsertCheck                         = new AccntMvmntManualInsert();
        $accntMvmntManualInsertCheck->user_relationship_id   = $checkAccount->user_relationship_id;
        $accntMvmntManualInsertCheck->start_date             = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accntMvmntManualInsertCheck->end_date               = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        $accntMvmntManualInsertTotal                         = $accntMvmntManualInsertCheck->sumMovementDay();

        if (UserRelationship::where('id', '=', $checkAccount->user_relationship_id)->first()->manual_entry_daily_limit < ($accntMvmntManualInsertTotal->total_value + $accntMvmntManualInsert->value) ) {
            return response()->json(array("error" => "Poxa, seu vínculo não possui limite diário para aprovar esse lançamento, por favor entre em contato com o administrador do sistema"));
        }

        $positive = [9, 10, 11, 12, 13, 14, 15, 20, 41, 42, 44, 46, 48];
        $negative = [2, 4, 5, 6, 7, 8, 16, 17, 21, 22, 23, 24, 30, 31, 32, 33, 34, 35, 36, 39, 43, 45, 47];

        if( in_array($accntMvmntManualInsert->mvmnt_type_id, $positive) ){
            $value = $accntMvmntManualInsert->value;
        } else if( in_array($accntMvmntManualInsert->mvmnt_type_id, $negative) ){
            $value = ($accntMvmntManualInsert->value * -1);
        } else {
            return response()->json(array("error" => "Tipo de lançamento não permitido"));
        }

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $accntMvmntManualInsert->account_id;
        $accountMovement->master_id  = $accntMvmntManualInsert->master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();
        $accountBalance              = 0;
        $accountMasterBalance        = 0;

        // Exceto conta CDB
        if ($accntMvmntManualInsert->account_id != 430) {
            if (isset( $accountMovement->getAccountBalance()->balance )){
                $accountBalance = $accountMovement->getAccountBalance()->balance;
            }
            if (isset( $accountMovement->getMasterAccountBalance()->master_balance )){
                $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
            }

            if( $value < 0 ) {
                if($accountBalance + $value < 0 ) {
                    return response()->json(array("error" => "Não é permitido lançar valores que deixarão a conta negativa"));
                }
            }
        }

        $accntMvmntManualInsert->approved = 1;
        $accntMvmntManualInsert->save();

        $movementType = MovementType::where('id', '=', $accntMvmntManualInsert->mvmnt_type_id)->first();

        $description = $movementType->description;

        if($accntMvmntManualInsert->description != '' and $accntMvmntManualInsert->description != null){
            $description .= ' | '.$accntMvmntManualInsert->description;
        }

        $originId = ((\Carbon\Carbon::now()->format('ymdHi')).$accntMvmntManualInsert->mvmnt_type_id.$accntMvmntManualInsert->id);

        if (AccountMovement::create([
            'account_id'     => $accntMvmntManualInsert->account_id,
            'master_id'      => $accntMvmntManualInsert->master_id,
            'mvmnt_type_id'  => $accntMvmntManualInsert->mvmnt_type_id,
            'origin_id'      => $originId,
            'date'           => \Carbon\Carbon::now(),
            'value'          => $value,
            'balance'        => ($accountBalance + $value),
            'master_balance' => ($accountMasterBalance + $value),
            'description'    => $description,
            'created_at'     => \Carbon\Carbon::now(),
            'uuid'           => Str::orderedUuid()
        ])) {

            $movementType = MovementType::where('id', '=', $accntMvmntManualInsert->mvmnt_type_id)->first();
            if($movementType->is_fee == 1){

                $master = Master::where('id', '=', $accntMvmntManualInsert->master_id)->first();

                if($master->margin_accnt_id != $accntMvmntManualInsert->account_id){
                    
                    if($master->margin_accnt_id != ''){

                        $marginAccountMovement             = new AccountMovement();
                        $marginAccountMovement->account_id = $master->margin_accnt_id;
                        $marginAccountMovement->master_id  = $accntMvmntManualInsert->master_id;
                        $marginAccountMovement->start_date = \Carbon\Carbon::now();
                        $marginAccountBalance              = 0;
                        $marginAccountMasterBalance        = 0;
                        if(isset( $marginAccountMovement->getAccountBalance()->balance )){
                            $marginAccountBalance = $marginAccountMovement->getAccountBalance()->balance;
                        }
                        if(isset( $marginAccountMovement->getMasterAccountBalance()->master_balance )){
                            $marginAccountMasterBalance = $marginAccountMovement->getMasterAccountBalance()->master_balance;
                        }

                        AccountMovement::create([
                            'account_id'      => $master->margin_accnt_id,
                            'master_id'       => $accntMvmntManualInsert->master_id,
                            'accnt_origin_id' => $accntMvmntManualInsert->account_id,
                            'mvmnt_type_id'   => $accntMvmntManualInsert->mvmnt_type_id,
                            'origin_id'       => $originId,
                            'date'            => \Carbon\Carbon::now(),
                            'value'           => ($value * -1),
                            'balance'         => ($marginAccountBalance + ($value * -1)),
                            'master_balance'  => ($marginAccountMasterBalance + ($value * -1)),
                            'description'     => $description.' | Lcto manual conta id '.$accntMvmntManualInsert->account_id,
                            'created_at'      => \Carbon\Carbon::now(),
                            'uuid'            => Str::orderedUuid()
                        ]);
                    }
                }
            }
            return response()->json(array("success" => "Lançamento inserido com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao inserir o lançamento"));
        }
    }
}
