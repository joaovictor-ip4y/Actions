<?php

namespace App\Http\Controllers;

use App\Libraries\ApiCelCoin;
use App\Libraries\Facilites;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Models\ChargeConfig;
use App\Models\PixCharge;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PixChargeController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account                    = new Account();
        $account->id                = $checkAccount->account_id;
        $account->master_id         = $checkAccount->master_id;
        $accountData                = $account->returnAccountData();

        $charge_config              = new ChargeConfig();
        $charge_config->account_id  = $checkAccount->account_id;
        $charge_config_data         = $charge_config->getAccountChargeConfig()[0];

        if (PixCharge::create([
            'charge_id'                 => null,
            'master_id'                 => $checkAccount->master_id,
            'register_master_id'        => $accountData->register_master_id,
            'account_id'                => $checkAccount->account_id,
            'payer_detail_id'           => 2,
            'status_id'                 => 38,
            'document'                  => 'Nota',
            'issue_date'                => $request->issue_date ? \Carbon\Carbon::parse($request->issue_date)->format('Y-m-d h:m:s')  : null,
            'due_date'                  => $request->due_date   ? \Carbon\Carbon::parse($request->due_date)->format('Y-m-d h:m:s')    : null,
            'value'                     => $request->value,
            'payment_occurrence_date'   => null,
            'payment_date'              => null,
            'payment_value'             => null,
            'down_date'                 => null,
            'antcptn_status_id'         => null,
            'antecipation_date'         => null,
            'antecipation_value'        => null,
            'discount_date'             => null,
            'discount_value'            => null,
            'observation'               => null,
            'fine'                      => $charge_config_data->fine,
            'interest'                  => $charge_config_data->interest,
            'message1'                  => $charge_config_data->message1,
            'message2'                  => $charge_config_data->message2,
            'created_at'                => \Carbon\Carbon::now(),
        ])) {
            return response()->json(array("success" => "Cobrança gerada com sucesso"));
        } else {
            return response()->json(array("error" => "Erro ao gerar a Cobrança gerada com sucesso"));
        }
    }

    protected function sendApi(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if ($pix_charge = PixCharge::where('id','=',$request->id)->where('account_id','=', $checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->first()) {
            if ($pix_charge->due_date < \Carbon\Carbon::now()) { //does it check if the date saved is greater than current date
                return response()->json(array("error" => "Data de vencimento expirada"));
            } else {
                $apiConfig              = new ApiConfig();
                $apiConfig->master_id   = $checkAccount->master_id;
                $apiConfig->onlyActive  = 1;

                $apiConfig->api_id      = 8;
                $api_cel_coin           = $apiConfig->getApiConfig()[0];

                $apiConfig->api_id      = 9;
                $api_cel_coin_pix       = $apiConfig->getApiConfig()[0];

                $facilites = new Facilites;

                $apiCelCoin                         = new ApiCelCoin();
                $apiCelCoin->api_address_request    = Crypt::decryptString($api_cel_coin_pix->api_address);
                $apiCelCoin->api_address            = Crypt::decryptString($api_cel_coin->api_address);
                $apiCelCoin->client_id              = Crypt::decryptString($api_cel_coin->api_client_id);
                $apiCelCoin->grant_type             = Crypt::decryptString($api_cel_coin->api_key);
                $apiCelCoin->client_secret          = Crypt::decryptString($api_cel_coin->api_authentication);

                $key                                = "5d000ece-b3f0-47b3-8bdd-c183e8875862";

                $pix_charge_data                    = $pix_charge->get()[0];

                $apiCelCoin->clientRequestId        = $pix_charge_data->id;
                $apiCelCoin->postalCode             = $pix_charge_data->zip_code;
                $apiCelCoin->city                   = $pix_charge_data->city;
                $apiCelCoin->name                   = mb_substr($facilites->sanitizeString($pix_charge_data->register_name),0,22);

                $location_data                      = $apiCelCoin->pixLocation();

                if ($location_data == true) {

                    $apiCelCoin->key                        = $key;
                    $apiCelCoin->clientRequestId            = $pix_charge_data->id;
                    $apiCelCoin->expirationAfterPayment     = 10;
                    $apiCelCoin->duedate                    = $pix_charge_data->due_date  ? \Carbon\Carbon::parse($pix_charge_data->due_date)->toISOString('')    : null;

                    // -------------- START API DEBITOR -------------- //
                    $debtorCNPJ     = '';
                    $debtorCPF      = '';

                    if (strlen($pix_charge_data->debitor_cpf_cnpj) == 11) {
                        $debtorCPF =  $pix_charge_data->debitor_cpf_cnpj;
                    } else {
                        $debtorCNPJ =  $pix_charge_data->debitor_cpf_cnpj;
                    }
                    $apiCelCoin->debtor_name        = mb_substr($facilites->sanitizeString($pix_charge_data->debitor_name),0,9);
                    $apiCelCoin->debtor_cpf         = $debtorCPF;
                    $apiCelCoin->debtor_cnpj        = $debtorCNPJ;
                    $apiCelCoin->debtor_city        = $pix_charge_data->debtor_city;
                    $apiCelCoin->debtor_publicArea  = $pix_charge_data->debtor_publicArea;
                    $apiCelCoin->debtor_state       = $pix_charge_data->debtor_state;
                    $apiCelCoin->debtor_postalCode  = $pix_charge_data->debtor_postalCode;
                    $apiCelCoin->debtor_email       = $pix_charge_data->debtor_email;
                    // -------------- FINISH API DEBITOR -------------- //

                    // -------------- START API RECEIVER -------------- //
                    $receiverCNPJ   = '';
                    $receiverCPF    = '';

                    if (strlen($pix_charge_data->register_cpf_cnpj) == 11) {
                        $receiverCPF =  $pix_charge_data->register_cpf_cnpj;
                    } else {
                        $receiverCNPJ =  $pix_charge_data->register_cpf_cnpj;
                    }
                    $apiCelCoin->receiver_name          = mb_substr($facilites->sanitizeString($pix_charge_data->register_name),0,7);
                    $apiCelCoin->receiver_cpf           = $receiverCPF;
                    $apiCelCoin->receiver_cnpj          = $receiverCNPJ;
                    $apiCelCoin->receiver_postalCode    = $pix_charge_data->zip_code;
                    $apiCelCoin->receiver_city          = $pix_charge_data->city;
                    $apiCelCoin->receiver_publicArea    = $pix_charge_data->address;
                    $apiCelCoin->receiver_state         = $pix_charge_data->states_short_description;
                    $apiCelCoin->receiver_fantasyName   = mb_substr($facilites->sanitizeString($pix_charge_data->register_name),0,7); //verificar nome fantasia
                    // -------------- FINISH API RECEIVER -------------- //
                    $apiCelCoin->discountDateFixed_date                 = \Carbon\Carbon::now()->toISOString();
                    $apiCelCoin->discountDateFixed_amountPerc           = "0.00";
                    // -------------- START LOCATION_ID -------------- //
                    $apiCelCoin->locationId             = $location_data->data->locationId;
                    // -------------- FINISH LOCATION_ID -------------- //
                    $apiCelCoin->amount                 = (float)$pix_charge_data->value; //forcing a float
                    $pixCobvData                        = $apiCelCoin->pixCobv();

                    if (!$pixCobvData == false) {

                        $pix_charge->key                        = $pixCobvData->data->key;
                        $pix_charge->location_id                = $pixCobvData->data->location->locationId;
                        $pix_charge->transaction_identification = $pixCobvData->data->transactionId;
                        // $pix_charge->transaction_currency;   //i not did find the field in api
                        // $pix_charge->pactual_id;             //i not did find the field in api
                        $pix_charge->emvqrcps                   = $pixCobvData->data->location->emv;
                        $pix_charge->expiration                 = $pixCobvData->data->calendar->expirationAfterPayment;
                        $pix_charge->url                        = $pixCobvData->data->location->url;
                        $pix_charge->status_id                  = 4;
                       // $pix_charge->transaction_key;         //i not did find the field in api
                       // $pix_charge->approval_id;             //i not did find the field in api
                       if ($pix_charge->save()) {
                            return response()->json(array("success" => "Pix Cobrança gerado com sucesso"));
                       } else {
                            return response()->json(array("error" => "Falha ao concluir a cobrança"));
                       }
                    } else {
                        return response()->json(array("error" => "Falha ao concluir o Pix cobrança"));
                    }
                } else {
                    return response()->json(array("error" => "Falha ao concluir o Pix cobrança"));
                }
            }
        } else {
            return response()->json(array("error" => "Pix Cobrança não localizado"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if ($pix_charge = PixCharge::where('id','=',$request->id)->whereNull('payment_date')->first()) {
            $apiCelCoin->transactionId = $pix_charge->transaction_identification;
            $pix_cobv_delete = $apiCelCoin->pixCobvDelete();
            if ($pix_cobv_delete->success == false) {
                return response()->json(array("error" => "Ocorreu uma falha ao cancelar o pix cobrança, por favor tente mais tarde"));
            } else {
                $pix_charge->deleted_at = \Carbon\Carbon::now();
                $pix_charge->down_date  = \Carbon\Carbon::now();
                $pix_charge->status_id  = 28;
                if ($pix_charge->save()) {
                    return response()->json(array("success" =>  "Pix cobrança cancelado com sucesso"));
                } else {
                    return response()->json(array("error" => "Não é possível cancelar PIX cobrança"));
                }
            }
        } else {
            return response()->json(array("error" => "Não é possível cancelar PIX cobrança pago"));
        }
    }
}
