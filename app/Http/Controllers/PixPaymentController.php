<?php

namespace App\Http\Controllers;


use App\Models\PixPayment;
use App\Models\SendSms;
use App\Models\User;
use App\Models\PixPaymentStatus;
use App\Models\PixKeyType;
use App\Models\PixParticipant;
use App\Models\PixAccountType;
use App\Models\PixType;
use App\Models\Account;
use App\Models\AccountMovement;
use App\Models\ApiConfig;
use App\Models\AuthorizationToken;
use App\Models\SystemFunctionMaster;
use App\Libraries\ApiCelCoin;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Classes\Banking\PixClass;
use App\Models\AccntAddMoneyPix;
use App\Models\PixCharge;
use App\Models\PixReceivePayment;
use App\Models\PixStaticReceive;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\PixPayment\PixPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


use Illuminate\Support\Facades\Log;

use PDF;
use File;

class PixPaymentController extends Controller
{
    /*
    public function test(PixClass $pixClass, Request $request)
    {

        $KeyVariable = $request->key;

        $pixClass->payload = (object) ['emv' => $KeyVariable];

        $checkDict = $pixClass->checkEmv();

        return $checkDict;

        if ( ! $checkDict->success ){
            return response()->json(array("error" => $checkDict->message_pt_br));
        }

        return $checkDict;
    }

    public function checkServiceAvailable(Request $request)
    {
        if( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível realizar pix."));
        } else {
            return response()->json(array("success" => ""));
        }
    }

    protected function pay(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [176, 257];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível realizar pix."));
        }

        $pixPaymentService          = new pixPaymentService();
        $pixPaymentService->pixData = (object) [
            'master_id'          => $request->header('masterId'),
            'accountPJId'        => $request->header('accountPJId'),
            'accountPFId'        => $request->header('accountPFId'),
            'accountUniqueId'    => $request->header('accountUniqueId'),
            'pix_id'             => $request->id,
            'pix_unique_id'      => $request->unique_id,
            'token'              => $request->token,
        ];

        $pixPayment = $pixPaymentService->approve();

        if($pixPayment->success){
            return response()->json(array(
                'success'       => $pixPayment->message,
                'pix_id'        => $pixPayment->pix_id,
                'file_name'     => $pixPayment->file_name,
                'pix_data'      => $pixPayment->pix_data
            ));
        } else {
            return response()->json(array(
                "error" => $pixPayment->message
            ));
        }
    }

    protected function newOld(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [176, 257];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        if ( $checkAccount->account_id <> 1){
            if( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
                return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
            }
        }


        $apiConfig              = new ApiConfig();
        $apiConfig->master_id   = $checkAccount->master_id;
        $apiConfig->onlyActive  = 1;

        $apiConfig->api_id      = 8;
        $api_cel_coin           = $apiConfig->getApiConfig()[0];

        $apiConfig->api_id      = 9;
        $api_cel_coin_pix       = $apiConfig->getApiConfig()[0];

        $apiCelCoin                         = new ApiCelCoin();
        $apiCelCoin->api_address_request    = Crypt::decryptString($api_cel_coin_pix->api_address);
        $apiCelCoin->api_address            = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id              = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type             = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret          = Crypt::decryptString($api_cel_coin->api_authentication);
        $apiCelCoin->payer_id               = '11491029000130';

        $key = $request->emv;
        $value = null;
        $url = null;
        $transaction_identification = null;
        $endtoendid = null;


        if($request->pix_key_type == 2){
            $validate = new Facilites();
            $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->emv);
            $validate->cpf_cnpj = $cpf_cnpj;
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json(['error'=>'CNPJ inválido']);
            }
        }

        if($request->pix_key_type == 3){
            $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->emv);
            if(strlen($cpf_cnpj) == 11){
                $validate = new Facilites();

                $validate->cpf_cnpj = $cpf_cnpj;
                if( !$validate->validateCPF($cpf_cnpj) ){
                    return response()->json(['error'=>'CPF inválido']);
                }
            }
        }

        if($request->pix_key_type == 4){
            if(strlen($request->emv) < 13){
                return response()->json(['error'=>'Celular inválido']);
            }
        }

        if($request->pix_key_type == 5){

            if(strlen($request->emv) < 32){
                return response()->json(['error'=>'Não foi possível localizar sua chave, por favor reveja os dados informados e tente novamente']);
            }
            //check emv
            $apiCelCoin->emv = $key;
            $emv_data = $apiCelCoin->pixEmv();
            if(!$emv_data->success){
                return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde! (err 1)"));
            }
            $url = $emv_data->data->merchantAccountInformation->url;

            if(strlen($key) <> 32  and strlen($key) <> 36){
                $transaction_identification = $checkAccount->account_id.date('Ymd').time().rand(1,999);//$emv_data->data->transactionIdentification;
            }


            if($emv_data->data->merchantAccountInformation->key == null or $emv_data->data->merchantAccountInformation->key == ''){
                if($url != null and $url != ''){
                    $apiCelCoin->url = preg_replace('#^https?://#', '', rtrim($url,'/'));
                    $payload_data = $apiCelCoin->pixPayload();
                    if(!$payload_data->success){
                        return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde! (err 2)"));
                    }
                    if(isset($payload_data->data->body->chave)){
                        $key = $payload_data->data->body->chave;

                    } else {
                        return response()->json(array("error" => "Poxa, não foi possível identificar sua chave, por favor verifique a chave informada e tente novamente (err 3)"));
                    }
                } else {
                    return response()->json(array("error" => "Poxa, não foi possível identificar sua chave, por favor verifique a chave informada e tente novamente (err 4)"));
                }
            } else {
                $key = $emv_data->data->merchantAccountInformation->key;
            }
            if($emv_data->data->transactionAmount > 0){
                $value = $emv_data->data->transactionAmount;
            }
        }

        //check dict
        $apiCelCoin->dict_key = $key;
        //$dict_data = $apiCelCoin->pixDict();
        $dict_data = $apiCelCoin->pixDictV2();
        if(!$dict_data->success){
            if(isset($dict_data->code)){
                if($dict_data->code == '909'){
                    return response()->json(array("error" => $dict_data->description));
                }

                if($dict_data->code == 'PBE363'){
                    return response()->json(array("error" => $dict_data->description));
                }
            }

            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde! (err 5)"));
        }

        if(!isset($dict_data->data->endtoendid)){
            return response()->json(array("error" => "Poxa, não foi possível identificar sua chave, por favor verifique a chave informada e tente novamente"));
        }

       // if($request->pix_key_type == 5){
            $endtoendid = $dict_data->data->endtoendid;
       // }

        $creationDate = null;
        if($dict_data->data->account->openingDate != '0001-01-01T00:00:00Z'){
            $creationDate = $dict_data->data->account->openingDate;
        }


        $tax = 0;
        $getTax = Account::getTax($checkAccount->account_id, 21, $checkAccount->master_id);
        if($getTax->value > 0){
            $tax = $getTax->value;
        } else if($getTax->percentage > 0){
            $tax = round(( ($getTax->percentage/100) * $emv_data->data->transactionAmount),2);
        }

        if( $pixPaymentInclude = PixPayment::create([
            'account_id'                 => $checkAccount->account_id,
            'master_id'                  => $checkAccount->master_id,
            'status_id'                  => 38,
            'value'                      => $value,
            'end_to_end'                 => $endtoendid,//'E'.date('Ymd').time().substr( (date('Ymd').time()),0,13 ), //$endtoendid
            'key'                        => $dict_data->data->key,
            'bank'                       => $dict_data->data->account->participant,
            'agency_credit'              => $dict_data->data->account->branch,
            'account_credit'             => $dict_data->data->account->accountNumber,
            'account_type_credit'        => $dict_data->data->account->accountType,
            'cpf_cnpj_credit'            => $dict_data->data->owner->taxIdNumber,
            'name_credit'                => $dict_data->data->owner->name,
            'emv'                        => $request->emv,
            'url'                        => $url,
            'dict'                       => $key,
            'creation_date'              => $creationDate,
            //'expiration'                 => $payload_data->data->body->calendario->expiracao,
            //'expiration_date'            => \Carbon\Carbon::parse($payload_data->data->body->calendario->criacao)->addSeconds($payload_data->data->body->calendario->expiracao)->format('Y-m-d H:i:s'),
            //'pix_status_id'              => PixPaymentStatus::returnPixStatusId($payload_data->data->body->status),
            'pix_key_type_id'            => PixKeyType::returnPixKeyTypeId($dict_data->data->keyType),
            'pix_participant_id'         => PixParticipant::returnPixParticipantId($dict_data->data->account->participant),
            'pix_account_type_id'        => PixAccountType::returnPixAccountTypeId($dict_data->data->account->accountType),
            'tax_value'                  => $tax,
            'unique_id'                  => md5(date('Ymd').time()).$dict_data->data->endtoendid.$checkAccount->account_id,
            'transaction_identification' => $transaction_identification,
            'created_at'                 => \Carbon\Carbon::now(),
        ])){
            $pixPayment     = new PixPayment();
            $pixPayment->id = $pixPaymentInclude->id;
            if($value == 0 or $value == null or $value = ''){
                return response()->json(array("success" => "Informe o valor que deseja enviar para ".$dict_data->data->owner->name, "value_required" => 1, "pix_data" => $pixPayment->getPixPayment()[0]));
            } else {
                return response()->json(array("success" => "Pix incluído com sucesso, realize a aprovação", "value_required" => 0, "pix_data" => $pixPayment->getPixPayment()[0]));
            }
        } else {
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Tente novamente mais tarde (err 6)"));
        }
    }

    protected function setPixValue(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->value <= 0){
            return response()->json(array("error" => "Valor deve ser maior que zero"));
        }

        if($request->value > 100000){
            return response()->json(array("error" => "Por medidas de segurança, o valor máximo permitido por pix é de R$ 100.000,00. Se necessário, realize outros pix até atingir o valor desejado."));
        }

        $pixPayment = PixPayment::where('id', '=', $request->id)->where('unique_id','=',$request->unique_id)->where('account_id', '=', $checkAccount->account_id)->whereNull('payment_date')->whereNull('deleted_at')->whereNull('value')->first();
        $pixPayment->value = $request->value;
        if($pixPayment->save()){
            $pixPaymentInclude     = new PixPayment();
            $pixPaymentInclude->id = $request->id;
            return response()->json(array("success" => "Pix incluído com sucesso, realize a aprovação", "value_required" => 0, "pix_data" => $pixPaymentInclude->getPixPayment()[0]));
        } else {
            return response()->json(array("error" => "Poxa, temos uma instabilidade no sistema, por favor tente novamente mais tarde"));
        }
    }

    protected function sendToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();

            if( Hash::check(base64_decode($request->password), $usr->password) ){

                $pixPayment = PixPayment::where('id', '=', $request->id)->where('unique_id','=',$request->unique_id)->where('account_id', '=', $checkAccount->account_id)->whereNull('payment_date')->whereNull('deleted_at')->first();

                if($pixPayment->value < 0 or $pixPayment->value == null){
                    return response()->json(array("error" => "Valor não definido para o pagamento, por favor insira o PIX novamente"));
                }

                if($pixPayment->value > 100000){
                    return response()->json(array("error" => "Por medidas de segurança, o valor máximo permitido por pix é de R$ 100.000,00. Se necessário, realize outros pix até atingir o valor desejado."));
                }

                $accountMovement             = new AccountMovement();
                $accountMovement->account_id = $pixPayment->account_id;
                $accountMovement->master_id  = $checkAccount->master_id;
                $accountMovement->start_date = \Carbon\Carbon::now();
                $accountBalance = 0;
                if(isset( $accountMovement->getAccountBalance()->balance )){
                    $accountBalance = $accountMovement->getAccountBalance()->balance;
                }

                //check free tax
                $taxValue = 0;
                $freePixTaxLimit = Account::getLimitFreePix($pixPayment->account_id, $checkAccount->master_id );
                $checkFreePixTax = PixPayment::checkFreePixTax($pixPayment->account_id);
                if($freePixTaxLimit->value == 0){
                    $taxValue = $pixPayment->tax_value;
                }
                if($checkFreePixTax->tax_value < $freePixTaxLimit->value){
                    $taxValue = 0;
                } else {
                    $taxValue = $pixPayment->tax_value;
                }

                if( $accountBalance < ($pixPayment->value + $taxValue) ){
                    return response()->json(array("error" => "Saldo insuficiente para realizar o pix <br>
                    Saldo disponível: <strong>R$ ".number_format($accountBalance, 2, ',','.')."</strong> <br>
                    Valor do pix: <strong>R$ ".number_format($pixPayment->value, 2, ',','.')."</strong> <br>
                    Valor da Tarifa : <strong>R$ ".number_format($taxValue, 2, ',','.')."</strong>") );
                }
                if($pixPayment != ''){
                    $token = new Facilites();
                    $authorizationToken = AuthorizationToken::create([
                        'token_phone'       => $token->createApprovalToken(),
                        'token_email'       => $token->createApprovalToken(),
                        'type_id'           => 5,
                        'origin_id'         => $pixPayment->id,
                        'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
                        'token_expired'     => 0,
                        'created_at'        => \Carbon\Carbon::now()
                    ]);
                    $pixPayment->approval_token            = $authorizationToken->token_phone;
                    $pixPayment->approval_token_expiration = $authorizationToken->token_expiration;
                    if($pixPayment->save()){
                        $getPixPayment              = new PixPayment();
                        $getPixPayment->id          = $pixPayment->id;
                        $getPixPayment->onlyPending = 1;
                        $pixPaymentData             = $getPixPayment->getPixPayment()[0];

                        $sendSMS = SendSms::create([
                            'external_id' => ("13".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$pixPayment->id),
                            'to'          => "55".$usr->phone,
                            'message'     => "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para aprovar um pix no valor de R$ ".number_format($pixPaymentData->value, 2, ',','.').", para ".str_replace('.',' ',$pixPaymentData->name_credit),
                            'type_id'     => 13,
                            'origin_id'   => $pixPayment->id,
                            'created_at'  => \Carbon\Carbon::now()
                        ]);

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
                        if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$request->header('masterId'))->first())->available == 1 ){
                            $apiZenviaWhats            = new ApiZenviaWhatsapp();
                            $apiZenviaWhats->to_number = $sendSMS->to;
                            $apiZenviaWhats->token     = "*".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4)."*";
                            if(isset( $apiZenviaWhats->sendToken()->success ) ){
                                return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove o pix novamente para gerar outro token"));
                            }
                        }

                        if(isset( $apiZenviaSMS->sendShortSMS()->success ) ){
                            return response()->json(array("success" => "Token enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove o pix novamente para gerar outro token"));
                        } else {
                            return response()->json(array("error" => "Não foi possível enviar o token de aprovação, por favor tente novamente"));
                        }
                    } else {
                        return response()->json(array("error" => "Não foi possível gerar o token de aprovação, por favor tente novamente"));
                    }
                } else {
                    return response()->json(array("error" => "Ocorreu um erro, pix já realizado ou não localizado"));
                }
            } else {
                return response()->json(array("error" => "Senha Inválida"));
            }
        } else {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
        }
    }

    protected function getPixPaymentData(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixPayment                  = new PixPayment();
        $pixPayment->master_id       = $checkAccount->master_id;
        $pixPayment->account_id      = $checkAccount->account_id;
        $pixPayment->status_id       = $request->status_id;
        $pixPayment->onlyActive      = 1;
        $pixPayment->onlyEffective   = 1;

        if($request->payment_date_start != ''){
            $pixPayment->payment_date_start = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $pixPayment->payment_date_end = $request->payment_date_end." 23:59:59.998";
        }


        return response()->json($pixPayment->getPixReceipt());
    } */

    protected function getDetailed(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pix_payment                = new PixPayment();
        $pix_payment->master_id     = $checkAccount->master_id;
        $pix_payment->account_id    = $checkAccount->account_id;
        $pix_payment->status_id     = $request->status_id;
        $pix_payment->type_id        = $request->type_id;

        if($request->payment_date_start != ''){
            $pix_payment->payment_date_start = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $pix_payment->payment_date_end = $request->payment_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $pix_payment->created_at_start = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $pix_payment->created_at_end = $request->created_at_end." 23:59:59.998";
        }
        if($request->occurrence_date_start != ''){
            $pix_payment->occurrence_date_start = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $pix_payment->occurrence_date_end = $request->occurrence_date_end." 23:59:59.998";
        }
        return response()->json($pix_payment->getPixPaymentsDetailed());
    }

    public function getReceipt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $facilites = new Facilites();

        if ($pix_payment = PixPayment::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('account_id','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->first()) {

            $value = $pix_payment->getPixPayment()[0];

            $data = (object) array(
                'id'                        =>$value->id,
                'register_name'             =>$value->from_name,
                'register_cpf_cnpj'         =>$facilites->mask_cpf_cnpj($value->from_cpf_cnpj),
                'agency'                    =>'0001',
                'account_number'            =>$facilites->mask_account($value->from_account_number),
                'name_credit'               =>$value->name_credit,
                'cpf_cnpj_credit'           =>$facilites->mask_cpf_cnpj($value->cpf_cnpj_credit),
                'participant_description'   =>$value->bank,
                'agency_credit'             =>$value->agency_credit,
                'account_credit'            =>$facilites->mask_account($value->account_credit),
                'key'                       =>$value->key,
                'value'                     =>number_format($value->value,2,',','.'),
                'payment_date'              =>$value->payment_date ? \Carbon\Carbon::parse($value->payment_date)->format('d/m/Y H:i:s') : null,
                'master_name'               =>$value->master_name,
                'slip_auth'                 =>$value->slip_auth,
                'transaction_id'            =>$value->transaction_id,
                'end_to_end'                =>$value->end_to_end,
            );

            $file_name = "Comprovante_Pagamento_Pix.pdf";
            $pdf = PDF::loadView('reports/receipt_transfer_pix',compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
            return response()->json(array(
                "success"   => "true",
                "file_name" => $file_name,
                "mime_type" => "application/pdf",
                "base64"    => base64_encode($pdf)
            ));
        }

        return response()->json(array("error" => "Comprovante não localizado"));
    }

    /*protected function getPixKey(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pix_payment                = new PixPayment();
        $pix_payment->master_id     = $checkAccount->master_id;
        $pix_payment->account_id    = $checkAccount->account_id;

        return response()->json($pix_payment->getKey());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [176, 257];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if ( $checkAccount->account_id <> 1){
            if ( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ) {
                return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
            }
        }

        $apiConfig              = new ApiConfig();
        $apiConfig->master_id   = $checkAccount->master_id;
        $apiConfig->onlyActive  = 1;

        $apiConfig->api_id      = 8;
        $api_cel_coin           = $apiConfig->getApiConfig()[0];

        $apiConfig->api_id      = 9;
        $api_cel_coin_pix       = $apiConfig->getApiConfig()[0];

        $apiCelCoin                         = new ApiCelCoin();
        $apiCelCoin->api_address_request    = Crypt::decryptString($api_cel_coin_pix->api_address);
        $apiCelCoin->api_address            = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id              = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type             = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret          = Crypt::decryptString($api_cel_coin->api_authentication);
        $apiCelCoin->payer_id               = '11491029000130';
        
        $value = null;
        $url = null;
        $endtoendid = null;
        $key = $request->emv;
        $transaction_identification = null;

        switch($request->pix_key_type){
            case 1: //E-MAIL
                if( ! Facilites::validateEmail($key) ){
                    return response()->json(array("error" => "E-Mail."));
                }
                $pix_type_id = 3;
            break;
            case 2: //CNPJ
                if( ! Facilites::validateCpfCnpj($key) ) {
                    return response()->json(array("error" => "CNPJ inválido."));
                }
                $pix_type_id = 3;
            break;
            case 3: //CPF
                if( ! Facilites::validateCpfCnpj($key) ) {
                    return response()->json(array("error" => "CPF inválido."));
                }
                $pix_type_id = 3;
            break;
            case 4: //CELULAR            
                if( ! Facilites::validatePhone(str_replace('+55', '', $key)) ){
                    return response()->json(array("error" => "Celular inválido."));
                }
                $pix_type_id = 3;
            break;
            case 5: //QR CODE

                if ($pixCharge = PixCharge::where('emvqrcps', '=', $request->emv)->first()) {
                    $pixChargeData = $pixCharge->get()[0];
                    return response()->json(
                        array(
                            "error" => "A chave informada pertence a uma conta iP4y, por favor realize uma transferência.",
                            "pix_account_data"  =>
                                array(
                                    "account_id"        => $pixChargeData->account_id,
                                    "account_number"    => $pixChargeData->account_number,
                                    "register_name"     => $pixChargeData->register_name,
                                    "register_cpf_cnpj" => $pixChargeData->register_cpf_cnpj,
                                    "key_type"          => "pix_charge"
                                ),
                        )
                    );
                }
    
                if ($pixReceivePayment = PixReceivePayment::where('emvqrcps', '=', $request->emv)->first()) {
                    $pixReceivePaymentData = $pixReceivePayment->get()[0];
                    return response()->json(
                        array(
                            "error" => "A chave informada pertence a uma conta iP4y, por favor realize uma transferência.",
                            "pix_account_data"  =>
                            array(
                                "account_id"        => $pixReceivePaymentData->account_id,
                                "account_number"    => $pixReceivePaymentData->account_number,
                                "register_name"     => $pixReceivePaymentData->register_name,
                                "register_cpf_cnpj" => $pixReceivePaymentData->register_cpf_cnpj,
                                "key_type"          => "pix_receive"
                            ),
                        )
                    );
                }
    
                if ($pixStaticReceive = PixStaticReceive::where('emvqrps', '=', $request->emv)->first()) {
                    $getPixStaticReceiveData = new PixStaticReceive();
    
                    $getPixStaticReceiveData->id = $pixStaticReceive->id;
                    $getPixStaticReceiveData->onlyForAccount = $pixStaticReceive->only_for_account;
                    $pixStaticReceiveData = $getPixStaticReceiveData->get()[0];
                    
                    return response()->json(
                        array(
                            "error"             => "A chave informada pertence a uma conta iP4y, por favor realize uma transferência.",
                            "pix_account_data"  =>
                                array(
                                    "account_id"        => $pixStaticReceiveData->account_id,
                                    "account_number"    => $pixStaticReceiveData->account_number,
                                    "register_name"     => $pixStaticReceiveData->register_name,
                                    "register_cpf_cnpj" => $pixStaticReceiveData->register_cpf_cnpj,
                                    "key_type"          => "pix_static_receive"
                                ),
                        )
                    );
                }
    
                if ($accntAddMoneyPix = AccntAddMoneyPix::where('emvqrcps', '=', $request->emv)->first()) {
                    $accntAddMoneyPixData = $accntAddMoneyPix->get()[0];
                    return response()->json(
                        array(
                            "error" => "A chave informada pertence a uma conta iP4y, por favor realize uma transferência.",
                            "pix_account_data"  =>
                            array(
                                "account_id"        => $accntAddMoneyPixData->account_id,
                                "account_number"    => $accntAddMoneyPixData->account_number,
                                "register_name"     => $accntAddMoneyPixData->register_name,
                                "register_cpf_cnpj" => $accntAddMoneyPixData->register_cpf_cnpj,
                                "key_type"          => "pix_add_money"
                            ),
                        )
                    );
                }

                $pixClass = new PixClass();

                $pixClass->payload = (object) ['emv' => $key];

                $checkPixData = $pixClass->checkEmv();
        
                if ( ! $checkPixData->success ){
                    return response()->json(array("error" => $checkPixData->message_pt_br));
                }
        
                
                $key = $checkPixData->data->key;
                $value =  $checkPixData->data->value;
                $endtoendid = $checkPixData->data->end_to_end;
                $url = $checkPixData->data->qr_code_url;

                if($checkPixData->data->qr_code_type_id == 1 ){
                    $pix_type_id = 1;

                    $transaction_identification = $checkAccount->account_id.date('Ymd').time().rand(1,999);
                    if($checkPixData->data->qr_code_transaction_identification != '***'){
                        $transaction_identification = $checkPixData->data->qr_code_transaction_identification;
                    }

                    
                } else {
                    $transaction_identification = $checkPixData->data->qr_code_transaction_identification;
                    $pix_type_id = 2;
                }

                

            break;
            case 6: //ALEATORIA
                if(strlen($key) != 36 ){
                    return response()->json(array("error" => "Chave aleatória inválida. A chave aleatória deve conter 36 caracteres, incluindo letras, números e traços. Por favaor verifique e tente novamente."));
                }
                $pix_type_id = 3;
            break;
        }

        if( $pix_type_id == 3 ){
            $pixClass = new PixClass();

            $pixClass->payload = (object) ['key' => $key];

            $checkPixData = $pixClass->checkDict();
    
            if ( ! $checkPixData->success ){
                return response()->json(array("error" => $checkPixData->message_pt_br));
            }

            $endtoendid = $checkPixData->data->end_to_end;
        }


        $tax = 0;
        $getTax = Account::getTax($checkAccount->account_id, 21, $checkAccount->master_id);

        if ($getTax->value > 0) {
            $tax = $getTax->value;
        } else if ($getTax->percentage > 0) {
            $tax = round(( ($getTax->percentage/100) * $value),2);
        }

        if ( $pixPaymentInclude = PixPayment::create([
            'account_id'                 => $checkAccount->account_id,
            'master_id'                  => $checkAccount->master_id,
            'status_id'                  => 38,
            'value'                      => $value,
            'end_to_end'                 => $checkPixData->data->end_to_end,
            'key'                        => $checkPixData->data->key,
            'bank'                       => $checkPixData->data->participant_ispb,
            'agency_credit'              => $checkPixData->data->agency,
            'account_credit'             => $checkPixData->data->account,
            'account_type_credit'        => $checkPixData->data->account_type,
            'cpf_cnpj_credit'            => $checkPixData->data->cpf_cnpj,
            'name_credit'                => $checkPixData->data->name,
            'emv'                        => $request->emv,
            'url'                        => $url,
            'dict'                       => $key,
            'pix_key_type_id'            => PixKeyType::returnPixKeyTypeId($checkPixData->data->key_type),
            'pix_participant_id'         => PixParticipant::returnPixParticipantId($checkPixData->data->participant_ispb),
            'pix_account_type_id'        => PixAccountType::returnPixAccountTypeId($checkPixData->data->account_type),
            'tax_value'                  => $tax,
            'unique_id'                  => Str::orderedUuid(),
            'transaction_identification' => $transaction_identification,
            'created_at'                 => \Carbon\Carbon::now(),
            'pix_type_id'                => $pix_type_id
        ])) {
            $pixPayment     = new PixPayment();
            $pixPayment->id = $pixPaymentInclude->id;

            if ($value == 0 or $value == null or $value = '') {
                return response()->json(array("success" => "Informe o valor que deseja enviar para ".$checkPixData->data->name, "value_required" => 1, "pix_data" => $pixPayment->getPixPayment()[0]));
            } else {
                return response()->json(array("success" => "Pix incluído com sucesso, realize a aprovação", "value_required" => 0, "pix_data" => $pixPayment->getPixPayment()[0]));
            }
        } else {
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Tente novamente mais tarde (err 6)"));
        }
    }

    protected function receiptEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixPayment              = new PixPayment();
        $pixPayment->id          = $request->id;
        $pixPayment->unique_id   = $request->unique_id;
        $pixPayment->account_id  = $checkAccount->account_id;
        $receiptData             = $pixPayment->getPixPayment()[0];

        $facilities = new Facilites();

        $data = (object) array(
            'id'                        => $receiptData->id,
            'register_name'             => $receiptData->from_name,
            'register_cpf_cnpj'         => $facilities->mask_cpf_cnpj($receiptData->from_cpf_cnpj),
            'agency'                    => '0001',
            'account_number'            => $facilities->mask_account($receiptData->from_account_number),
            'name_credit'               => $receiptData->name_credit,
            'cpf_cnpj_credit'           => $facilities->mask_cpf_cnpj($receiptData->cpf_cnpj_credit),
            'participant_description'   => $receiptData->bank,
            'agency_credit'             => $receiptData->agency_credit,
            'account_credit'            => $facilities->mask_account($receiptData->account_credit),
            'key'                       => $receiptData->key,
            'value'                     => number_format($receiptData->value,2,',','.'),
            'payment_date'              => $receiptData->payment_date ? \Carbon\Carbon::parse($receiptData->payment_date)->format('d/m/Y H:i:s') : null,
            'master_name'               => $receiptData->master_name,
            'slip_auth'                 => $receiptData->slip_auth,
            'transaction_id'            => $receiptData->transaction_id,
            'end_to_end'                => $receiptData->end_to_end,
        );

        $pdfFilePath  = '../storage/app/email_receipt/';
        $file_name    = 'Pix_'.$receiptData->from_cpf_cnpj.'_'.$receiptData->id.'.pdf';

        if (PDF::loadView('reports/receipt_transfer_pix', compact('data'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name) ){
            $message = "Olá, <br>
            Segue em anexo o comprovante do pix realizado por <b>$data->register_name - ".$facilities->mask_cpf_cnpj($data->register_cpf_cnpj)."</b>, para <b>$data->name_credit - ".$facilities->mask_cpf_cnpj($data->cpf_cnpj_credit)."</b><br><br>
            <b>Favorecido:</b> $data->name_credit<br>
            <b>Valor:</b> R$ ".$data->value."<br>
            <b>Pago Em:</b> ".$data->payment_date;
            $user = User::where('id','=',$request->header('userId'))->first();
            $sendMail = new sendMail();
            $sendMail->to_mail      = $request->email;
            $sendMail->to_name      = $request->email;
            $sendMail->send_cc      = 1;
            $sendMail->to_cc_mail   = $user->email;
            $sendMail->to_cc_name   = $user->name;
            $sendMail->send_cco     = 0;
            $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
            $sendMail->to_cco_name  = 'Ragazzi';
            $sendMail->attach_pdf   = 1;
            $sendMail->attach_path  = $pdfFilePath.$file_name;
            $sendMail->attach_file  = $file_name;
            $sendMail->subject      = 'Comprovante do Pix Realizado por '.$data->register_name;
            $sendMail->email_layout = 'emails/confirmEmailAccount';
            $sendMail->bodyMessage  = $message;

            if ($sendMail->send()) {
                File::delete($pdfFilePath.$file_name);
                return response()->json(array("success" => "E-Mail enviado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao enviar o e-mail, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Ocorreu uma falha ao gerar o anexo em PDF, por favor tente novamente") );
        }
    }

    public function sendPixFromCelcoinToRendimento()
    {
        $apiConfig              = new ApiConfig();
        $apiConfig->master_id   = 1;
        $apiConfig->onlyActive  = 1;

        $apiConfig->api_id      = 8;
        $api_cel_coin           = $apiConfig->getApiConfig()[0];

        $apiConfig->api_id      = 9;
        $api_cel_coin_pix       = $apiConfig->getApiConfig()[0];

        $apiCelCoin                         = new ApiCelCoin();
        $apiCelCoin->api_address_request    = Crypt::decryptString($api_cel_coin_pix->api_address);
        $apiCelCoin->api_address            = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id              = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type             = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret          = Crypt::decryptString($api_cel_coin->api_authentication);
        $apiCelCoin->payer_id               = '11491029000130';

        $key = '11491029000130';

        //check dict
        $apiCelCoin->dict_key = $key;
        //$dict_data = $apiCelCoin->pixDict();
        $dict_data = $apiCelCoin->pixDictV2();

        $apiCelCoin->dict_key                    = $dict_data->data->key;

        //debit part
        $apiCelCoin->debit_account               = '100538032';
        $apiCelCoin->debit_branch                = "0001";
        $apiCelCoin->debit_taxId                 = '11491029000130';
        $apiCelCoin->debit_accountType           = "CACC";
        $apiCelCoin->debit_name                  = 'Dinaripay Securitizadora S.A.';
        //credit part

        $apiCelCoin->credit_bank                 = 68900810;
        $apiCelCoin->credit_account              = $dict_data->data->account->accountNumber;
        $apiCelCoin->credit_branch               = $dict_data->data->account->branch;
        $apiCelCoin->credit_taxId                = $dict_data->data->owner->taxIdNumber;
        $apiCelCoin->credit_accountType          = $dict_data->data->account->accountType;
        $apiCelCoin->credit_name                 = $dict_data->data->owner->name;
        $apiCelCoin->amount                      = 50000;
        $apiCelCoin->clientCode                  = Str::orderedUuid();
        $apiCelCoin->endToEndId                  = $dict_data->data->endtoendid;

        $apiCelCoin->transactionIdentification   = "";
        $apiCelCoin->key                         = "";
        $apiCelCoin->initiationType              = "";
        $apiCelCoin->taxIdPaymentInitiator       = "";
        $apiCelCoin->remittanceInformation       = "";

        $apiCelCoin->key                         = $dict_data->data->key;
        $apiCelCoin->initiationType              = 'DICT';

        $data_api_celcoin_payment                = $apiCelCoin->pixPaymentNew();

    }

    protected function resendUniqueTokenByWhatsApp(Request $request)
    {
        $transfer = new PixClass();
        $transfer->data = $request;

        $resendUniqueTokenByWhatsApp = $transfer->resendUniqueTokenByWhatsApp();

        Log::debug([
            'logError' => $resendUniqueTokenByWhatsApp
        ]);

        

        if(!$resendUniqueTokenByWhatsApp->success){
            return response()->json(array("error" => $resendUniqueTokenByWhatsApp->message_pt_br, "data" => $resendUniqueTokenByWhatsApp));
        }

        return response()->json(array("success" => $resendUniqueTokenByWhatsApp->message_pt_br, "data" => $resendUniqueTokenByWhatsApp));
    } */

}
