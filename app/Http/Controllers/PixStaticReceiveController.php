<?php

namespace App\Http\Controllers;

use App\Libraries\ApiCelCoin;
use App\Libraries\Facilites;
use App\Libraries\QrCodeGenerator\QrCodeGenerator;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Models\PixStaticReceive;
use App\Models\CelcoinPixKey;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PDF;
use Illuminate\Support\Facades\Validator;

class PixStaticReceiveController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [56, 177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if(isset($request->only_for_account)){
            if (PixStaticReceive::where('account_id','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->where('status_id','=',4)->where('only_for_account','=',1)->whereNull('deleted_at')->count() > 0) {
                return response()->json(array("error" => "Poxa, sua conta já possui uma chave pix aleatória vinculada, em caso de dúvidas entre em contato com seu gerente"));
            }
        }

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
        
        $key                    = '605fa39a-ef70-4c51-9bee-2fae748fe731'; //'a016bc8b-d89a-4f62-be99-5fa0ebb91d77';

        $accountHasCelcoinKey = 0;
        // get celcoin accounnt key
        if ( $getCelcoinAccountPixKey = CelcoinPixKey::where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first() ){
            $key = $getCelcoinAccountPixKey->key;//'testepix@celcoin.com.br';//$getCelcoinAccountPixKey->key;
            //$key = 'testepix@celcoin.com.br';
            $accountHasCelcoinKey = 1;
        }

        
        
        
        
        if ($pix_static_receive = PixStaticReceive::create([
            'master_id'                 => $checkAccount->master_id,
            'account_id'                => $checkAccount->account_id,
            'status_id'                 => 38,
            'additional_information'    => $request->additional_information,
            'created_at'                => \Carbon\Carbon::now(),
            'description'               => $request->description,
            'only_for_account'          => $request->only_for_account
        ])) {

            $pix_static_receive_dtacc                  = new PixStaticReceive();
            $pix_static_receive_dtacc->id              = $pix_static_receive->id;
            $pix_static_receive_dtacc->account_id      = $checkAccount->account_id;
            $pix_static_receive_dtacc->master_id       = $checkAccount->master_id;
            $pix_static_receive_dtacc->onlyForAccount  = $request->only_for_account;
            $pix_static_dt                            = $pix_static_receive_dtacc->get()[0];
            $facilites                                = new Facilites();
            $apiCelCoin                               = new ApiCelCoin();
            $apiCelCoin->api_address                  = $api_address;
            $apiCelCoin->client_id                    = $client_id;
            $apiCelCoin->grant_type                   = $grant_type;
            $apiCelCoin->client_secret                = $client_secret;
            $apiCelCoin->api_address_request          = $api_address_receive;
            $apiCelCoin->clientRequestId              = 'sttc'.$pix_static_receive->id;
            $apiCelCoin->key                          = $key;
            $apiCelCoin->additionalInformation        = $pix_static_receive->additional_information;
            $apiCelCoin->tags                         = "";
            //$apiCelCoin->amount                       = "0.00";
            $apiCelCoin->postalCode                   = $pix_static_dt->zip_code;
            $apiCelCoin->city                         = mb_substr($facilites->sanitizeString($pix_static_dt->city),0,15);
            $apiCelCoin->name                         = mb_substr($facilites->sanitizeString($pix_static_dt->register_name),0,25);
            $apiCelCoin->accnt_number                 = $pix_static_dt->account_number;

            if ($put_pix_static_receive_payment = PixStaticReceive::where('id','=',$pix_static_receive->id)->first()){
                $data_pix_receive_payment_static = $apiCelCoin->pixRequestStatic();
                if ($data_pix_receive_payment_static->success == false) {
                    $pix_static_receive->deleted_at = \Carbon\Carbon::now();
                    if ($pix_static_receive->save()) {
                        return response()->json(array("error" => "Poxa, devido a instabilidade com o correspondente, não foi possível gerar o PIX, por favor tente novamente mais tarde"));
                    }
                } else {
                    $apiCelCoin->transactionId                      = $data_pix_receive_payment_static->data->transactionId;
                    $put_pix_static_receive_payment->transaction_id = $data_pix_receive_payment_static->data->transactionId;
                    $put_pix_static_receive_payment->emvqrps        = $data_pix_receive_payment_static->data->emvqrcps;
                    if ($put_pix_static_receive_payment->save()) {
                        if (!$put_pix_static_receive_payment->transaction_id == 0) {
                            $put_pix_static_receive_payment->status_id = 4;
                            if ($put_pix_static_receive_payment->save()) {
                                return response()->json(array("success"=> "PIX gerado com sucesso", "pix_data" => $put_pix_static_receive_payment,  "emvqrps" =>  $put_pix_static_receive_payment->emvqrps, "mime_type" => "image/jpeg", "base64"=> $apiCelCoin->pixQrCodeStatic()->data->base64image));
                            }
                        } else {
                            return response()->json(array("error" => "Poxa, devido a instabilidade com o correspondente, não foi possível gerar o PIX, por favor tente novamente mais tarde (código de transação)"));
                        }

                    } else {
                        return response()->json(array("error" => "Poxa, devido a instabilidade com o correspondente, não foi possível gerar o PIX, por favor tente novamente mais tarde (falha de atualização)"));
                    }
                }
            } else {
                return response()->json(array("error" => "Poxa, devido a instabilidade com o correspondente, não foi possível gerar o PIX, por favor tente novamente mais tarde (falha de registro)"));
            }

        } else {
            return response()->json(array("error" => "Poxa, devido a instabilidade com o correspondente, não foi possível gerar o PIX, por favor tente novamente mais tarde (falha de registro inicial)"));
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

        $pix_static_receive                    = new PixStaticReceive();
        $pix_static_receive->id                = $request->id;
        $pix_static_receive->account_id        = $checkAccount->account_id;
        $pix_static_receive->master_id         = $checkAccount->master_id;
        $pix_static_receive->onlyForAccount    = $request->only_for_account;
        $pix_static_receive->onlyActive        = 1;

        return response()->json($pix_static_receive->get());
    }

    protected function getStaticPixReceiveData(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'transaction_key' => ['required', 'string'],
            'id' => ['required'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        $pix_static_receive = new PixStaticReceive();
        $pix_static_receive->transaction_id = $request->transaction_key;
        $pix_static_receive->id = $request->id;

        if(isset( $request->onlyForAccount)){
            $pix_static_receive->onlyForAccount = $request->onlyForAccount;
        }

        $pix_data = $pix_static_receive->get();

        if($pix_data <> ''){

            $facilites = new Facilites();

            if (!strlen($pix_data[0]->register_cpf_cnpj > 11)) {
                $cpf_cnpj_masked  = substr_replace(substr_replace($facilites->mask_cpf_cnpj($pix_data[0]->register_cpf_cnpj),"XXX",-15,3),"XX",-2);
            } else {
                $cpf_cnpj_masked  = substr_replace(substr_replace($facilites->mask_cpf_cnpj($pix_data[0]->register_cpf_cnpj),"XX",-18,2),"XX",-2);
            }
            return response()->json(array("success" =>
                array(
                    "register_name"     => $pix_data[0]->register_name,
                    "register_cpf_cnpj" => $cpf_cnpj_masked,
                    "master_name"       => 'IP4Y INSTITUIÇÃO DE PAGAMENTO LTDA',//$data->master_name,
                    "master_cpf_cnpj"   => $facilites->mask_cpf_cnpj($pix_data[0]->master_cpf_cnpj),
                    "transaction_id"    => $pix_data[0]->transaction_id,
                    "emvqrcps"          => $pix_data[0]->emvqrps,
                )
            ));
        } else {
            return response()->json(array("error" => "Ocorreu uma falha ao consultar o PIX, por favor tente mais tarde"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [56, 177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if ($pix_static_receive = PixStaticReceive::where('id','=',$request->id)->when($checkAccount->account_id, function ($query, $account_id) {return $query->where('account_id','=',$account_id);})->where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->first()) {
            $pix_static_receive->deleted_at = \Carbon\Carbon::now();
            $pix_static_receive->status_id = 3;
            if ($pix_static_receive->save()) {
                return response()->json(array("success" => "Pix excluído com sucesso"));
            }
        }
        return response()->json(array("error" => "Poxa, não foi possível remover o pix informado, por favor, tente mais tarde"));

    }

    

    protected function getQrCode(Request $request)
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
        $key                    = 'a016bc8b-d89a-4f62-be99-5fa0ebb91d77'; //'605fa39a-ef70-4c51-9bee-2fae748fe731';

        if (!$pix_static_receives = PixStaticReceive::where('id','=',$request->id)->when($checkAccount->account_id, function ($query, $account_id) {return $query->where('account_id','=',$account_id);})->where('master_id','=',$checkAccount->master_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o pix informado, por favor, tente mais tarde"));
        }

        $apiCelCoin                          = new ApiCelCoin();
        $apiCelCoin->api_address             = $api_address;
        $apiCelCoin->client_id               = $client_id;
        $apiCelCoin->grant_type              = $grant_type;
        $apiCelCoin->client_secret           = $client_secret;
        $apiCelCoin->api_address_request     = $api_address_receive;
        $apiCelCoin->key                     = $key;
        $apiCelCoin->transactionId           = $pix_static_receives->transaction_id;

        $data = $apiCelCoin->pixQrCodeStatic();

        if ($data->success == true) {
            return response()->json(array("success"=> "QR Code gerado com sucesso", "mime_type" => "image/jpeg", "base64" => $data->data->base64image));
        }
        return response()->json(array("error" => "Poxa, não foi possível gerar o pix, por favor, tente mais tarde"));
    }


    protected function pdfQrCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [56, 177, 258];
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
        $key                    = 'a016bc8b-d89a-4f62-be99-5fa0ebb91d77'; //'605fa39a-ef70-4c51-9bee-2fae748fe731';

        if (!$pix_static_receives = PixStaticReceive::where('id','=',$request->id)->when($checkAccount->account_id, function ($query, $account_id) {return $query->where('account_id','=',$account_id);})->where('master_id','=',$checkAccount->master_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o pix informado, por favor, tente mais tarde"));
        }

        $account_id     = new Account();
        $account_id->id = $pix_static_receives->account_id;
        $data_account   = $account_id->getAccounts()[0];

        $qrCode = new QrCodeGenerator();
        $qrCode->data = $pix_static_receives->emvqrps;
        $qrCode->return_type = 'base64';
        $qrCode->quiet_zone = true;
        $qrCode->quiet_zone_size = 1;
        $QrCode = $qrCode->createQrCode();

        if(!$QrCode->success){
            return response()->json(array("error" => $QrCode->message));
        }

        $receipt = (object) array(
            'account_number'    => $data_account->account_number,
            'name'              => mb_substr($data_account->name,0,23),
            'description'       => mb_substr($pix_static_receives->description,0,23),
            'emvqrps'           => $pix_static_receives->emvqrps,
            'base64image'       => $QrCode->base64,
            'img_path'          => $QrCode->file_path
        );

        $file_name    = 'Pix_'.$pix_static_receives->id.'pdf';
        $pdf  = PDF::loadView('reports/report_pix_static', compact('receipt'))->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));

    }
}
