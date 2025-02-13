<?php

namespace App\Http\Controllers;

use App\Models\PixReceivePayment;
use App\Models\ApiConfig;
use App\Models\PixPayer;
use App\Models\PixPayerDetail;
use App\Models\PixTag;
use App\Models\User;
use App\Models\Account;
use App\Models\SystemFunctionMaster;
use App\Models\CelcoinPixKey;
use App\Libraries\ApiCelCoin;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Libraries\QrCodeGenerator\QrCodeGenerator;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Account\MovementTaxService;
use Illuminate\Http\Request;
use PDF;
use File;
use Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Classes\Celcoin\CelcoinClass;
use App\Classes\BancoRendimento\IndirectPix\QrCode\CreateEstaticPixQrCodeClass;
use App\Classes\BancoRendimento\IndirectPix\QrCode\CreateDynamicPixQrCodeClass;

class PixReceivePaymentController extends Controller
{

    public function checkServiceAvailable(Request $request)
    {
        if( (SystemFunctionMaster::where('system_function_id','=',9)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível gerar pix."));
        } else {
            return response()->json(array("success" => ""));
        }
    }


    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pix_receive_payment                       = new PixReceivePayment();
        $pix_receive_payment->master_id            = $checkAccount->master_id;
        $pix_receive_payment->account_id           = $checkAccount->account_id;
        $pix_receive_payment->payer_cpf_cnpj       = $request->payer_cpf_cnpj;
        $pix_receive_payment->status_id            = $request->status_id;
        $pix_receive_payment->inclusion_date_start = $request->inclusion_date_start;
        $pix_receive_payment->inclusion_date_end   = $request->inclusion_date_end;
        $pix_receive_payment->value_start          = $request->value_start;
        $pix_receive_payment->value_end            = $request->value_end;
        $pix_receive_payment->payment_date_start   = $request->payment_date_start;
        $pix_receive_payment->payment_date_end     = $request->payment_date_end;
        $pix_receive_payment->payment_value_start  = $request->payment_value_start;
        $pix_receive_payment->payment_value_end    = $request->payment_value_end;
        $pix_receive_payment->onlyActive           = $request->onlyActive;


        return response()->json($pix_receive_payment->get());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( (SystemFunctionMaster::where('system_function_id','=',9)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        $validator = Validator::make($request->all(), [
            'pix_type_id' => ['nullable', 'integer'],
            'cpf_cnpj' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'value' => ['nullable', 'string'],
            'due_date' => ['nullable', 'string'],
            'additional_information' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

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
            if($PixPayerDetail = PixPayerDetail::where('pix_payer_id','=',$pix_payer->id)->where('account_id','=',$checkAccount->account_id)->first()){
                $PixPayerDetailId = $PixPayerDetail->id;
            }else{
                if($PixPayerDetail = PixPayerDetail::create([
                    'pix_payer_id'  => $pix_payer->id,
                    'account_id'    => $checkAccount->account_id,
                    'name'          => $request->name,
                    'created_at'    => \Carbon\Carbon::now(),
                ])){
                    $PixPayerDetailId = $PixPayerDetail->id; //
                }else{
                    return response()->json(array("error" => "Ocorreu uma falha ao cadastrar o pagador, por favor tente novamente mais tarde"));
                }
            }
        }else{
            if($pix_payer = PixPayer::create([
                'cpf_cnpj'      => $cpf_cnpj,
                'created_at'    => \Carbon\Carbon::now(),
                ])){
                    if($PixPayerDetail = PixPayerDetail::create([
                        'pix_payer_id'  => $pix_payer->id,
                        'account_id'    => $checkAccount->account_id,
                        'name'          => $request->name,
                        'created_at'    => \Carbon\Carbon::now(),
                    ])){
                        $PixPayerDetailId = $PixPayerDetail->id;
                    }else{
                        return response()->json(array("error" => "Ocorreu uma falha ao cadastrar o pagador, por favor tente novamente mais tarde"));
                    }
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao cadastrar o pagador, por favor tente novamente mais tarde"));
            }
        }


        $tax = 0;

        $getTax = Account::getTax($checkAccount->account_id, 25, $checkAccount->master_id);

        if($getTax->value > 0){
            $tax = $getTax->value;
        } else if($getTax->percentage > 0){
            $tax = round(( ($getTax->percentage/100) * $request->value),2);
        }

        //Get Account CPF/CNPJ
        $accountData = Account::where('id', '=', $checkAccount->account_id)->first();

        if ($accountData->pix_api_id == 18) { // Indireto Rendimento
            if ( ! $pix_payer_detail = PixPayerDetail::where('id','=',$PixPayerDetailId)->where('account_id','=',$checkAccount->account_id)->first()){
                return response()->json(array("error" => "Pagador não encontrado"));
            }

            $pix_payer = PixPayer::where('id','=',$pix_payer_detail->pix_payer_id)->first();

            if( ! $pixReceivePayment = PixReceivePayment::create( [
                'uuid' => Str::orderedUuid(),
                'master_id' => $checkAccount->master_id,
                'account_id' => $checkAccount->account_id,
                'pix_type_id' => $request->pix_type_id,
                'pix_payer_detail_id' => $pix_payer_detail->id,
                'payer_cpf_cnpj' => $pix_payer->cpf_cnpj,
                'payer_name' => $pix_payer_detail->name,
                'status_id' => 38,
                //'key'                       => $key,
                'additional_information' => $request->additional_information,
                'value' => $request->value,
                'due_date' => isset($request->due_date) ? $request->due_date.' 23:59:59' : \Carbon\Carbon::parse(\Carbon\Carbon::now()->addDays(30))->format('Y-m-d').' 23:59:59',
                //'expiration'                => 604800,
                'created_at' => \Carbon\Carbon::now(),
                'tax_value' => $tax,
                'pix_api_id' => 18
            ])){
                return response()->json(array("error" => "Poxa, ocorreu um erro ao gerar o QrCode, por favor tente novamente mais tarde"));
            }

            switch ($request->pix_type_id) {
                case 1: //QrCode Estático
                    $createQrCode = CreateEstaticPixQrCodeClass::execute(
                        $pixReceivePayment->account_id,
                        $pixReceivePayment->value,
                        $pixReceivePayment->additional_information
                    );
                break;
                case 2: //QrCode Dinamico
                    $createQrCode = CreateDynamicPixQrCodeClass::execute(
                        $pixReceivePayment->account_id,
                        $pixReceivePayment->payer_cpf_cnpj,
                        $pixReceivePayment->payer_name,
                        $pixReceivePayment->value,
                        $pixReceivePayment->due_date,
                        $pixReceivePayment->id,
                        $pixReceivePayment->additional_information,
                    );
                break;
                default:
                    return response()->json(array("error" => "Tipo de QrCode inválido"));
                break;
            }

            // Atualização do qrCode
            $updatePixQrCode = PixReceivePayment::where('id','=',$pixReceivePayment->id)->first();

            if (! $createQrCode["success"]) {
                $updatePixQrCode->deleted_at = \Carbon\Carbon::now();
                $updatePixQrCode->save();
                return response()->json(array("error" => $createQrCode["message"]));
            }

            $updatePixQrCode->status_id = 4;
            $updatePixQrCode->transaction_identification = $createQrCode["data"]->qr_code_tx_identification;
            $updatePixQrCode->emvqrcps = $createQrCode["data"]->qr_code_value;
            $updatePixQrCode->transaction_key =  $updatePixQrCode->id.'-'.$createQrCode["data"]->qr_code_transaction_identification;
            $updatePixQrCode->save();

            $qrCodeGenerator = new QrCodeGenerator();
            $qrCodeGenerator->data = $updatePixQrCode->emvqrcps;

            return response()->json(array(
                "success"=> "QrCode Pix gerado com sucesso",
                "pix_data" => $updatePixQrCode,
                "register_name" => $updatePixQrCode->register_name,
                "emvqrcps" =>  $updatePixQrCode->emvqrcps,
                "mime_type" => "image/jpeg",
                "base64" => substr($qrCodeGenerator->createQrCode()->base64, 22),
            ));

        }

        // QR CODE PELA CELCOIN
        $apiConfig              = new ApiConfig();
        $apiConfig->master_id   = $checkAccount->master_id;
        $apiConfig->onlyActive  = 1;

        $apiConfig->api_id      = 8;
        $api_cel_coin           = $apiConfig->getApiConfig()[0];

        $apiConfig->api_id      = 9;
        $api_cel_coin_pix       = $apiConfig->getApiConfig()[0];

        $api_address            = Crypt::decryptString($api_cel_coin->api_address);
        $api_address_receive    = Crypt::decryptString($api_cel_coin_pix->api_address);
        $client_id              = Crypt::decryptString($api_cel_coin->api_client_id);
        $grant_type             = Crypt::decryptString($api_cel_coin->api_key);
        $client_secret          = Crypt::decryptString($api_cel_coin->api_authentication);

        $key = null;
        $accountHasCelcoinKey = 0;
        
        // get celcoin accounnt key
        if ( ! $getCelcoinAccountPixKey = CelcoinPixKey::where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first() ){
            return response()->json(array("error" => "Conta não possui chave cadastrada na Celcoin para geração de QrCode"));
        }

        $key = $getCelcoinAccountPixKey->key;//'testepix@celcoin.com.br';//$getCelcoinAccountPixKey->key;
        $accountHasCelcoinKey = 1;

        $payerCNPJ           = '';
        $payerCPF            = '';
        
        if($pix_payer_detail = PixPayerDetail::where('id','=',$PixPayerDetailId)->where('account_id','=',$checkAccount->account_id)->first()){
            $pix_payer = PixPayer::where('id','=',$pix_payer_detail->pix_payer_id)->first();
            if($pix_receive_payment = PixReceivePayment::create([
                'uuid'                      => Str::orderedUuid(),
                'master_id'                 => $checkAccount->master_id,
                'account_id'                => $checkAccount->account_id,
                'pix_type_id'               => $request->pix_type_id,
                'pix_payer_detail_id'       => $pix_payer_detail->id,
                'payer_cpf_cnpj'            => $pix_payer->cpf_cnpj,
                'payer_name'                => $pix_payer_detail->name,
                'status_id'                 => 38,
                'key'                       => $key,
                'additional_information'    => $request->additional_information,
                'value'                     => $request->value,
                'expiration'                => 604800,
                'created_at'                => \Carbon\Carbon::now(),
                'tax_value'                 => $tax,
                'api_id'                    => 9
            ])){
                $tags = [];
                $taglines = $request->tag;
                if(!empty($taglines)){
                    foreach ($taglines as $tagline){
                        PixTag::create([
                            'pix_receive_payment_id' => $pix_receive_payment->id,
                            'description'            => $tagline,
                            'created_at'             => \Carbon\Carbon::now(),
                        ]);
                        array_push($tags,$tagline);
                    }
                }

                if(strlen($pix_payer->cpf_cnpj) == 11){
                    $payerCPF =  $pix_payer->cpf_cnpj;
                }else{
                    $payerCNPJ =  $pix_payer->cpf_cnpj;
                }

                $facilites = new Facilites();

                $apiCelCoin                          = new ApiCelCoin();
                $apiCelCoin->api_address             = $api_address;
                $apiCelCoin->client_id               = $client_id;
                $apiCelCoin->grant_type              = $grant_type;
                $apiCelCoin->client_secret           = $client_secret;

                $data_pix_receive_payment            = new PixReceivePayment();
                $data_pix_receive_payment->id        = $pix_receive_payment->id;
                $data_pix_receive_payment_get        = $data_pix_receive_payment->get()[0];
                $apiCelCoin->api_address_request     = $api_address_receive;
                $apiCelCoin->clientRequestId         = $pix_receive_payment->id;
                $apiCelCoin->key                     = $key;
                $apiCelCoin->amount                  = number_format($pix_receive_payment->value,2,'.','');
                $apiCelCoin->value                   = number_format($pix_receive_payment->value,2,'.','');
                $apiCelCoin->postalCode              = $data_pix_receive_payment_get->zip_code;
                $apiCelCoin->city                    = mb_substr($facilites->sanitizeString($data_pix_receive_payment_get->city),0,15);
                $apiCelCoin->additionalInformation   = $data_pix_receive_payment_get->additional_information;
                $apiCelCoin->name                    = mb_substr($facilites->sanitizeString($data_pix_receive_payment_get->register_name),0,25);
                $apiCelCoin->payerCPF                = $payerCPF;
                $apiCelCoin->payerCNPJ               = $payerCNPJ;
                $apiCelCoin->payerName               = $facilites->sanitizeString($data_pix_receive_payment_get->payer_name);
                $apiCelCoin->expiration              = $pix_receive_payment->expiration;
                $apiCelCoin->tags                    = $tags;
                $apiCelCoin->accnt_number            = $facilites->mask_account((int) $data_pix_receive_payment_get->account_number);

                if($pix_receive_payment->pix_type_id == 2){ //gera um pix dinamico

                    if ($accountHasCelcoinKey) {
                        $data_pix_receive_payment         = $apiCelCoin->createImmediateDynamicQrCode();
                    } else {
                        $data_pix_receive_payment         = $apiCelCoin->pixRequestDynamic();
                        $apiCelCoin->dict_key             = $data_pix_receive_payment->data->body->body->key;
                       // $data_pix_dict_key                = $apiCelCoin->pixDict();
                    }

                    if($data_pix_receive_payment->success == false){
                        $pix_receive_payment->deleted_at = \Carbon\Carbon::now();
                        if($pix_receive_payment->save()){
                            return response()->json(array("error" => "Ocorreu uma falha ao gerar o PIX, por favor tente novamente mais tarde"));
                        }
                    }else{
                        if($put_pix_receive_payment = PixReceivePayment::where('id','=',$pix_receive_payment->id)->first()){
                            
                            
                            if ($accountHasCelcoinKey) {
                                $put_pix_receive_payment->transaction_identification = $data_pix_receive_payment->data->transactionId;
                                $put_pix_receive_payment->transaction_key            = $data_pix_receive_payment->data->transactionIdentification;
                                $put_pix_receive_payment->transaction_currency       = 986;
                                //$put_pix_receive_payment->pactual_id                 = $data_pix_receive_payment->data->locationId;
                                $put_pix_receive_payment->pactual_id                 = $data_pix_receive_payment->data->location->locationId;
                                
                                $put_pix_receive_payment->emvqrcps                   = $data_pix_receive_payment->data->location->emv;
                                $put_pix_receive_payment->url                        = $data_pix_receive_payment->data->location->url;
                                $put_pix_receive_payment->due_date                   = (\Carbon\Carbon::parse( \Carbon\Carbon::now())->addSeconds($data_pix_receive_payment->data->calendar->expiration))->format('Y-m-d H:i:s');
                               
                            } else {
                            
                                $put_pix_receive_payment->transaction_identification = $data_pix_receive_payment->data->body->transactionId;
                                $put_pix_receive_payment->transaction_key            = $data_pix_receive_payment->data->body->transactionIdentification;
                                $put_pix_receive_payment->transaction_currency       = $data_pix_receive_payment->data->body->body->dynamicBRCodeData->transactionCurrency;
                                $put_pix_receive_payment->pactual_id                 = $data_pix_receive_payment->data->body->pactualId;
                                $put_pix_receive_payment->emvqrcps                   = $data_pix_receive_payment->data->body->body->dynamicBRCodeData->emvqrcps;
                                $put_pix_receive_payment->due_date                   = (\Carbon\Carbon::parse( $data_pix_receive_payment->data->body->body->calendar->dueDate))->format('Y-m-d H:i:s');
                                $put_pix_receive_payment->url                        = $data_pix_receive_payment->data->body->body->location;
                            }

                            $put_pix_receive_payment->status_id  = 4;
                            
                            if($put_pix_receive_payment->save()){
                                if(!$put_pix_receive_payment->transaction_identification == 0){


                                    $qrCodeGenerator = new QrCodeGenerator();
                                    $qrCodeGenerator->data = $put_pix_receive_payment->emvqrcps;

                                    /*if ($accountHasCelcoinKey) {
                                        $celcoinClass = new CelcoinClass();
                                        $celcoinClass->location_id = $data_pix_receive_payment->data->location->locationId;                                                                             
                                        $base64Image = $celcoinClass->getLocationQrCode()->data->base64image;
                                    } else {
                                        $apiCelCoin->api_address_request     = $api_address_receive;
                                        $apiCelCoin->transactionId           = $put_pix_receive_payment->transaction_identification;
                                        // $put_pix_receive_payment->end_to_end = $data_pix_dict_key->data->endtoendid;
                                        $put_pix_receive_payment->status_id  = 4;
                                        $base64Image = $apiCelCoin->pixQrCodeDynamic()->data->base64image;
                                    } */
                                   
                                   
                                   if($put_pix_receive_payment->save()){
                                        if($tax > 0){
                                            $movementTax = new MovementTaxService();
                                            $movementTax->movementData = (object) [
                                                'account_id'    => $checkAccount->account_id,
                                                'master_id'     => $checkAccount->master_id,
                                                'origin_id'     => $pix_receive_payment->id,
                                                'mvmnt_type_id' => 37,
                                                'value'         => $tax,
                                                'description'   => 'Tarifa de Pix Gerado | Transação '.$put_pix_receive_payment->transaction_identification
                                            ];
                                            $movementTax->create();
                                        }

                                        return response()->json(array(
                                            "success"=> "PIX gerado com sucesso", 
                                            "pix_data" => $put_pix_receive_payment, 
                                            "register_name" => $data_pix_receive_payment_get->register_name, 
                                            "emvqrcps" =>  $put_pix_receive_payment->emvqrcps, 
                                            "mime_type" => "image/jpeg", 
                                            "base64"=> substr($qrCodeGenerator->createQrCode()->base64, 22)
                                        ));
                                    }
                                } else {
                                    return response()->json(array("error" => "Ocorreu uma falha ao gerar o PIX, por favor tente novamente mais tarde"));
                                }
                            } else {
                                return response()->json(array("error" => "Ocorreu uma falha ao gerar o PIX, por favor tente novamente mais tarde"));
                            }
                        } else {
                            return response()->json(array("error" => "Ocorreu uma falha ao recuperar os dados do PIX, por favor tente novamente mais tarde"));
                        }
                    }
                } else if ($pix_receive_payment->pix_type_id == 1){ //para gerar o pix statico
                    if($put_pix_receive_payment = PixReceivePayment::where('id','=',$pix_receive_payment->id)->first()){
                        $data_pix_receive_payment_static = $apiCelCoin->pixRequestStatic();
                        if($data_pix_receive_payment_static->success == false){
                            $pix_receive_payment->deleted_at = \Carbon\Carbon::now();
                            if($pix_receive_payment->save()){
                                return response()->json(array("error" => "Erro ao gerar o PIX, tente mais tarde"));
                            }
                        }else{
                            $put_pix_receive_payment->transaction_identification    = $data_pix_receive_payment_static->data->transactionId;
                            $apiCelCoin->transactionId                              = $data_pix_receive_payment_static->data->transactionId;
                            $data_pix_receive_payment_br_code_static                = $apiCelCoin->pixDataBrCodeStatic();
                            $apiCelCoin->dict_key                                   = $data_pix_receive_payment_br_code_static->data->merchantAccountInformation->key;
                            $data_pix_dict_key                                      = $apiCelCoin->pixDict();
                            $put_pix_receive_payment->transaction_currency          = $data_pix_receive_payment_br_code_static->data->transactionCurrency;
                            $put_pix_receive_payment->emvqrcps                      = $data_pix_receive_payment_br_code_static->data->emvqrcps;
                            $put_pix_receive_payment->transaction_key               = $data_pix_receive_payment_static->data->transactionIdentification;
                            if($put_pix_receive_payment->save()){
                                if(!$put_pix_receive_payment->transaction_identification == 0){
                                    $apiCelCoin->api_address_request        =  $api_address_receive;
                                    $apiCelCoin->transactionId              =  $put_pix_receive_payment->transaction_identification;
                                    $put_pix_receive_payment->status_id     = 37;
                                    $put_pix_receive_payment->end_to_end    = $data_pix_dict_key->data->endtoendid;
                                    if($put_pix_receive_payment->save()){

                                        $qrCodeGenerator = new QrCodeGenerator();
                                        $qrCodeGenerator->data = $put_pix_receive_payment->emvqrcps;

                                        return response()->json(array(
                                            "success"=> "PIX gerado com sucesso", 
                                            "pix_data" => $put_pix_receive_payment, 
                                            "register_name" => $data_pix_receive_payment_get->register_name, 
                                            "emvqrcps" =>  $put_pix_receive_payment->emvqrcps, 
                                            "mime_type" => "image/jpeg", 
                                            "base64" => substr($qrCodeGenerator->createQrCode()->base64, 22)
                                        ));
                                    }
                                }else{
                                    return response()->json(array("error" => "Erro codigo de transação não encontrado"));
                                }
                            }else{
                                return response()->json(array("error" => "Erro ao cadastrar"));
                            }
                        }
                    }else{
                        return response()->json(array("error" => "Erro na indentificação Pagador"));
                    }
                }
            }else{
                return response()->json(array("error" => "Erro ao cadastrar"));
            }
        }else{
            return response()->json(array("error" => "Pagador não encontrado"));
        }
    }

    protected function getPixQrCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( ! $pix_receive_payment = PixReceivePayment::where('account_id','=',$checkAccount->account_id)->where('master_id', '=', $checkAccount->master_id)->where('id', '=', $request->id)->first()){
            return response()->json(array("error" => "QrCode não localizado"));
        }

        $qrCodeGenerator = new QrCodeGenerator();
        $qrCodeGenerator->data = $pix_receive_payment->emvqrcps;

        return response()->json(array(
            "success" => "QR Code gerado com sucesso", 
            "mime_type" => "image/jpeg", 
            "base64" => substr($qrCodeGenerator->createQrCode()->base64, 22),
        ));
    }

    protected function sendEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( ! $pix_receive_payment = PixReceivePayment::where('account_id','=',$checkAccount->account_id)->where('master_id', '=', $checkAccount->master_id)->where('id', '=', $request->id)->first()){
            return response()->json(array("error" => "QrCode não localizado"));
        }

        $qrCodeGenerator = new QrCodeGenerator();
        $qrCodeGenerator->data = $pix_receive_payment->emvqrcps;

        $imgBase64 = substr($qrCodeGenerator->createQrCode()->base64, 22);

        $data_register = $pix_receive_payment->get()[0];
        $facilities = new Facilites();
        $receipt = (object) array(
            "due_date"          => \Carbon\Carbon::parse($pix_receive_payment->due_date)->format('d/m/Y H:i:s'),
            "payer_name"        => $pix_receive_payment->payer_name,
            "register_name"     => $data_register->register_name,
            "value"             => number_format($pix_receive_payment->value,2,',','.'),
            "img64"             => $imgBase64,
            "emvqrcps"          => $pix_receive_payment->emvqrcps,
            "register_cpf_cnpj" => $facilities->mask_cpf_cnpj($data_register->register_cpf_cnpj),
            "px_pyrs_cpf_cnpj"  => $facilities->mask_cpf_cnpj($data_register->px_pyrs_cpf_cnpj),
        );

        $pdfFilePath  = '../storage/app/pix_receipt/';
        $file_name    = 'QRCode_Pix_'.$receipt->payer_name.'_'.$pix_receive_payment->id.'.pdf';

        if ( PDF::loadView('reports/receipt_pix_payment', compact('receipt'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name) ){
            $message = "Olá, $receipt->payer_name, <br> $receipt->register_name gerou uma cobrança PIX para você</b><br><br>
            Para realizar o pagamento, abra o PDF em anexo, escaneie a imagem ou copie e cole o código do QR Code.<br><br>
            <b>Valor:</b> R$".$receipt->value."<br>
            <b>Data de expiração:</b> ".$receipt->due_date."<br>
            <b>Código do QR Code:</b> ".$receipt->emvqrcps."<br>
            ";
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
            $sendMail->subject      = "PIX Gerado para você";
            $sendMail->email_layout = 'emails/confirmEmailAccount';
            $sendMail->bodyMessage  = $message;
            if($sendMail->send()){
               File::delete($pdfFilePath.$file_name);
               return response()->json(array("success" => "E-Mail enviado com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao enviar o e-mail, por favor tente novamente"));
            }
        } else {
            return response()->json(array('status' => 'error', 'message' => 'Ocorreu uma falha ao gerar o anexo em PDF, por favor tente novamente') );
        }
    }

    protected function pixDownload(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( ! $pix_receive_payment = PixReceivePayment::where('account_id','=',$checkAccount->account_id)->where('master_id', '=', $checkAccount->master_id)->where('id', '=', $request->id)->first()){
            return response()->json(array("error" => "QrCode não localizado"));
        }

        $qrCodeGenerator = new QrCodeGenerator();
        $qrCodeGenerator->data = $pix_receive_payment->emvqrcps;

        $imgBase64 = substr($qrCodeGenerator->createQrCode()->base64, 22);

        $data_register  = $pix_receive_payment->get()[0];
        $facilities     = new Facilites();
        $receipt = (object) array(
            "due_date"          => \Carbon\Carbon::parse($pix_receive_payment->due_date)->format('d/m/Y H:i:s'),
            "payer_name"        => $pix_receive_payment->payer_name,
            "register_name"     => $data_register->register_name,
            "value"             => number_format($pix_receive_payment->value,2,',','.'),
            "img64"             => $imgBase64,
            "emvqrcps"          => $pix_receive_payment->emvqrcps,
            "register_cpf_cnpj" => $facilities->mask_cpf_cnpj($data_register->register_cpf_cnpj),
            "px_pyrs_cpf_cnpj"  => $facilities->mask_cpf_cnpj($data_register->px_pyrs_cpf_cnpj),
        );

       $file_name    = 'Pix_'.$receipt->payer_name.'_'.$receipt->emvqrcps.'.pdf';
       $pdf  = PDF::loadView('reports/receipt_pix_payment', compact('receipt'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
       return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [178, 259];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$pix_receive_payment = PixReceivePayment::where('id','=',$request->id)->where('account_id','=', $checkAccount->account_id)->where('transaction_identification', '=', $request->transaction_identification)->whereNull('payment_date')->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Pix a receber não localizado"));
        }

        if ($pix_receive_payment->api_id != 18) {
            $apiConfig              = new ApiConfig();
            $apiConfig->master_id   = $checkAccount->master_id;
            $apiConfig->onlyActive  = 1;

            $apiConfig->api_id      = 8;
            $api_cel_coin           = $apiConfig->getApiConfig()[0];

            $apiConfig->api_id      = 9;
            $api_cel_coin_pix       = $apiConfig->getApiConfig()[0];

            $api_address            = Crypt::decryptString($api_cel_coin->api_address);
            $api_address_receive    = Crypt::decryptString($api_cel_coin_pix->api_address);
            $client_id              = Crypt::decryptString($api_cel_coin->api_client_id);
            $grant_type             = Crypt::decryptString($api_cel_coin->api_key);
            $client_secret          = Crypt::decryptString($api_cel_coin->api_authentication);

            $apiCelCoin                          = new ApiCelCoin();
            $apiCelCoin->api_address             = $api_address;
            $apiCelCoin->client_id               = $client_id;
            $apiCelCoin->grant_type              = $grant_type;
            $apiCelCoin->client_secret           = $client_secret;
            $apiCelCoin->api_address_request     = $api_address_receive;

            $apiCelCoin->transactionId = $pix_receive_payment->transaction_identification;
            $pix_delete = $apiCelCoin->pixDelete();
        }

        $pix_receive_payment->deleted_at = \Carbon\Carbon::now();
        $pix_receive_payment->down_date  = \Carbon\Carbon::now();
        $pix_receive_payment->status_id  = 28;
        
        if (!$pix_receive_payment->save()){
            return response()->json(array("error" => "Poxa, ocorreu uma falha ao cancelar o Pix a receber, por favor tente novamente mais tarde"));
        }

        return response()->json(array("success" =>  "Pix cancelado com sucesso"));
    }

    protected function getPixReceivePaymentData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_key' => ['required', 'string'],
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if ($pix_receive_payment = PixReceivePayment::where('id','=',$request->id)->where('transaction_key','=',$request->transaction_key)->first()) {
            if ($pix_receive_payment->status_id ===  29) {
                return response()->json(array("error" => "PIX com pagamento já realizado"));
            } else {
               $data = $pix_receive_payment->get()[0];
               $facilites = new Facilites();

                if (!strlen($data->register_cpf_cnpj > 11)) {
                    $cpf_cnpj_masked  = substr_replace(substr_replace($facilites->mask_cpf_cnpj($data->register_cpf_cnpj),"XXX",-15,3),"XX",-2);
                } else {
                    $cpf_cnpj_masked  = substr_replace(substr_replace($facilites->mask_cpf_cnpj($data->register_cpf_cnpj),"XX",-18,2),"XX",-2);
                }
                return response()->json(array("success" =>
                    array(
                        "register_name"     => $data->register_name,
                        "register_cpf_cnpj" => $cpf_cnpj_masked,
                        "master_name"       => 'IP4Y INSTITUIÇÃO DE PAGAMENTO LTDA',//$data->master_name,
                        "master_cpf_cnpj"   => $facilites->mask_cpf_cnpj($data->master_cpf_cnpj),
                        "value"             => $data->value,
                        "transaction_key"   => $data->transaction_key,
                        "due_date"          => $data->due_date,
                        "emvqrcps"          => $data->emvqrcps,
                        "status_id"         => $data->status_id,
                    )
                ));
            }
        } else {
            return response()->json(array("error" => "Ocorreu uma falha ao consultar o PIX, por favor tente mais tarde"));
        }
    }

    protected function getDetailed(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixReceive             = new PixReceivePayment();
        $pixReceive->master_id  = $checkAccount->master_id;
        $pixReceive->account_id = $checkAccount->account_id;
        $pixReceive->status_id  = $request->status_id;
        $pixReceive->onlyActive = $request->onlyActive;
        $pixReceive->type_id    = $request->type_id;
        $pixReceive->manager_id = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $pixReceive->occurrence_date_start = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $pixReceive->occurrence_date_end = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $pixReceive->created_at_start = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $pixReceive->created_at_end = $request->created_at_end." 23:59:59.998";
        }
        if($request->payment_date_start != ''){
            $pixReceive->payment_date_start = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $pixReceive->payment_date_end = $request->payment_date_end." 23:59:59.998";
        }
        if($request->down_date_start != ''){
            $pixReceive->down_date_start = $request->down_date_start." 00:00:00.000";
        }
        if($request->down_date_end != ''){
            $pixReceive->down_date_end = $request->down_date_end." 23:59:59.998";
        }
        return response()->json( $pixReceive->pixReceivePaymentsDetailed() );
    }

}
