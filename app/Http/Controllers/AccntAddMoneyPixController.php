<?php

namespace App\Http\Controllers;

use App\Libraries\ApiCelCoin;
use App\Libraries\Facilites;
use App\Models\AccntAddMoneyPix;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Models\SystemFunctionMaster;
use App\Models\CelcoinPixKey;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AccntAddMoneyPixController extends Controller
{

    public function checkServiceAvailable(Request $request)
    {
        if( (SystemFunctionMaster::where('system_function_id','=',8)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível gerar pix."));
        } else {
            return response()->json(array("success" => ""));
        }
    }

    protected function addMoneyPix(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [175,256];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( (SystemFunctionMaster::where('system_function_id','=',8)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if (AccntAddMoneyPix::where('status_id','=', 4)->where('account_id','=',$checkAccount->account_id)->count() > 3) {
            return response()->json(array("error" => "Conta possui 3 PIX em aberto, realize o pagamento ou a baixa para emitir outro"));
        } else {

            if ( ! $getCelcoinAccountPixKey = CelcoinPixKey::where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first() ){
                return response()->json(array("error" => "Poxa, sua conta está sem uma chave pix vinculada, por favor entre em contato com o suporte"));
            }

            if($request->value <= 0 ) {
                return response()->json(array("error" => "Informe um valor maior que 0"));
            }

            $key = $getCelcoinAccountPixKey->key;

            $account = new Account();
            $account->id  = $checkAccount->account_id;
            $data_account = $account->getAccounts()[0];


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

            $cpf                 = '';
            $cnpj                = '';

            if ($accnt_add_money_pix = AccntAddMoneyPix::create([
                'master_id'                 => $checkAccount->master_id,
                'account_id'                => $checkAccount->account_id,
                'status_id'                 => 38,
                'key'                       => $key,
                'value'                     => $request->value,
                'expiration'                => 604800,
                'creation_tax_value'        => null,
                'payment_tax_value'         => 0,
                'created_at'                => \Carbon\Carbon::now(),
            ])) {

                if (strlen($data_account->cpf_cnpj) == 11) {
                    $cpf =  $data_account->cpf_cnpj;
                } else {
                    $cnpj =  $data_account->cpf_cnpj;
                }

                $facilites = new Facilites();

                $apiCelCoin                          = new ApiCelCoin();
                $apiCelCoin->api_address             = $api_address;
                $apiCelCoin->client_id               = $client_id;
                $apiCelCoin->grant_type              = $grant_type;
                $apiCelCoin->client_secret           = $client_secret;

                $data_accnt_add_money_pix            = new AccntAddMoneyPix();
                $data_accnt_add_money_pix->id        = $accnt_add_money_pix->id;
                $data_accnt_add_money_pix_get        = $data_accnt_add_money_pix->get()[0];
                $apiCelCoin->api_address_request     = $api_address_receive;
                $apiCelCoin->clientRequestId         = 'addmon_'.$accnt_add_money_pix->id;
                $apiCelCoin->key                     = $key;
                $apiCelCoin->amount                  = number_format($accnt_add_money_pix->value,2,'.','');
                $apiCelCoin->value                   = number_format($accnt_add_money_pix->value,2,'.','');
                $apiCelCoin->postalCode              = $data_accnt_add_money_pix_get->zip_code;
                $apiCelCoin->city                    = mb_substr($facilites->sanitizeString($data_accnt_add_money_pix_get->city),0,15);
                $apiCelCoin->additionalInformation   = 'Gerado para creditar conta '.$data_accnt_add_money_pix_get->account_number; //required
                $apiCelCoin->name                    = mb_substr($facilites->sanitizeString($data_account->name),0,25);
                $apiCelCoin->payerCPF                = $cpf;
                $apiCelCoin->payerCNPJ               = $cnpj;
                $apiCelCoin->payerName               = $facilites->sanitizeString($data_account->name);
                $apiCelCoin->expiration              = $accnt_add_money_pix->expiration;
                $apiCelCoin->accnt_number            = (int) $data_accnt_add_money_pix_get->account_number;
                $apiCelCoin->tags                    = null;

                if ($put_accnt_add_money_pix = AccntAddMoneyPix::where('id','=',$accnt_add_money_pix->id)->first()) {
                    $data_accnt_add_money_pix         = $apiCelCoin->pixRequestDynamic();

                    if(!isset($data_accnt_add_money_pix->data->body->body->key)){
                        return response()->json(array("error" => "Poxa, não foi possível gerar o pix! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                    }

                    $apiCelCoin->dict_key             = $data_accnt_add_money_pix->data->body->body->key;

                    if ($data_accnt_add_money_pix->success == false) {
                        $put_accnt_add_money_pix->deleted_at = \Carbon\Carbon::now();
                        if ($put_accnt_add_money_pix->save()) {
                            return response()->json(array("error" => "Poxa, não foi possível gerar o pix! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                        }
                    } else {
                        if ($put_accnt_add_money_pix = AccntAddMoneyPix::where('id','=',$accnt_add_money_pix->id)->first()) {
                            $put_accnt_add_money_pix->transaction_identification = $data_accnt_add_money_pix->data->body->transactionId;
                            $put_accnt_add_money_pix->transaction_key            = $data_accnt_add_money_pix->data->body->transactionIdentification;
                            $put_accnt_add_money_pix->transaction_currency       = $data_accnt_add_money_pix->data->body->body->dynamicBRCodeData->transactionCurrency;
                            $put_accnt_add_money_pix->pactual_id                 = $data_accnt_add_money_pix->data->body->pactualId;
                            $put_accnt_add_money_pix->emvqrcps                   = $data_accnt_add_money_pix->data->body->body->dynamicBRCodeData->emvqrcps;
                            $put_accnt_add_money_pix->due_date                   = (\Carbon\Carbon::parse( $data_accnt_add_money_pix->data->body->body->calendar->dueDate))->format('Y-m-d H:i:s');
                            $put_accnt_add_money_pix->url                        = $data_accnt_add_money_pix->data->body->body->location;
                            if ($put_accnt_add_money_pix->save()) {
                                if (!$put_accnt_add_money_pix->transaction_identification == 0) {
                                    $apiCelCoin->api_address_request     = $api_address_receive;
                                    $apiCelCoin->transactionId           =  $put_accnt_add_money_pix->transaction_identification;
                                    $put_accnt_add_money_pix->status_id  = 4;
                                    if($put_accnt_add_money_pix->save()) {
                                        return response()->json(array("success"=> "PIX gerado com sucesso", "pix_data" =>  $put_accnt_add_money_pix ,"emvqrcps" =>  $put_accnt_add_money_pix->emvqrcps, "mime_type" => "image/jpeg", "base64"=> $apiCelCoin->pixQrCodeDynamic()->data->base64image));
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
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao recuperar os dados do PIX, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao cadastrar o pix, por favor tente novamente mais tarde"));
            }
        }
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accnt_add_money_pix                = new AccntAddMoneyPix();
        $accnt_add_money_pix->master_id     = $checkAccount->master_id;
        $accnt_add_money_pix->account_id    = $checkAccount->account_id;
        $accnt_add_money_pix->onlyActive    = $request->onlyActive;

        return response()->json($accnt_add_money_pix->get());
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

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

        if ($accnt_add_money_pix = AccntAddMoneyPix::where('id','=',$request->id)->where('transaction_identification','=',$request->transaction_identification)->whereNull('payment_date')->where('account_id','=',$checkAccount->account_id)->where('master_id', '=', $checkAccount->master_id)->first()) {
            $apiCelCoin->transactionId = $accnt_add_money_pix->transaction_identification;
            $apiCelCoin->pixDelete();

            $accnt_add_money_pix->deleted_at = \Carbon\Carbon::now();
            $accnt_add_money_pix->down_date  = \Carbon\Carbon::now();
            $accnt_add_money_pix->status_id  = 28;
            if ($accnt_add_money_pix->save()){
                return response()->json(array("success" => "Pix cancelado com sucesso"));
            }
            return response()->json(array("error" => "Poxa, no momento, não é possível cancelar PIX, por favor, tente mais tarde"));
        }
        return response()->json(array("error" => "Poxa, não encontramos o pix informado ou o mesmo foi pago, por favor, tente mais tarde"));
    }

    protected function getPixQrCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

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

        if ($accnt_add_moneyPix = AccntAddMoneyPix::where('account_id','=',$checkAccount->account_id)->where('master_id', '=', $checkAccount->master_id)->where('id', '=', $request->id)->first()) {
            $apiCelCoin->transactionId = $accnt_add_moneyPix->transaction_identification;
            return response()->json(array("success" => "QR Code gerado com sucesso", "mime_type" => "image/jpeg", "base64" => $apiCelCoin->pixQrCodeDynamic()->data->base64image));
        } else {
            return response()->json(array("error" => "PIX não encontrado"));
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

        $addMoney                                   = new AccntAddMoneyPix();
        $addMoney->master_id                        = $checkAccount->master_id;
        $addMoney->account_id                       = $checkAccount->account_id;
        $addMoney->status_id                        = $request->status_id;
        $addMoney->onlyActive                       = $request->onlyActive;
        $addMoney->type_id                          = $request->type_id;
        $addMoney->manager_id                       = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $addMoney->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $addMoney->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $addMoney->created_at_start             = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $addMoney->created_at_end               = $request->created_at_end." 23:59:59.998";
        }
        if($request->payment_date_start != ''){
            $addMoney->payment_date_start           = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $addMoney->payment_date_end             = $request->payment_date_end." 23:59:59.998";
        }
        if($request->down_date_start != ''){
            $addMoney->down_date_start              = $request->down_date_start." 00:00:00.000";
        }
        if($request->down_date_end != ''){
            $addMoney->down_date_end                = $request->down_date_end." 23:59:59.998";
        }
        return response()->json( $addMoney->addMoneyPixDetailed() );
    }
}
