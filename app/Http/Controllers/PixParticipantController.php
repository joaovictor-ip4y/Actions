<?php

namespace App\Http\Controllers;

use App\Models\PixParticipant;
use App\Models\Master;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Libraries\ApiCelCoin;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Crypt;

class PixParticipantController extends Controller
{
    public function loadPixParticipant()
    {

        $mstr = new Master();

        foreach($mstr->getMaster() as $master){
            $apiConfig              = new ApiConfig();
            $apiConfig->master_id   = $master->id;
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

            $pix_participant   = $apiCelCoin->pixParticipant();
            $included   = [];
            $errors     = [];

            foreach($pix_participant->data as $key => $value){
                if($pix_participant = PixParticipant::where('ispb','=',$value->ispb)->first()){
                    array_push($errors, "Esse codigo já foi cadastrado"." ISPB: ".$value->ispb);
                }else{
                    if(PixParticipant::create([
                        'ispb'              =>$value->ispb,
                        'name'              =>$value->name,
                        'type'              =>$value->type,
                        'start_operation'   =>((\Carbon\Carbon::parse($value->startOperationDatetime))->toDateString()),
                        'created_at'        =>\Carbon\Carbon::now(),
                    ])){
                        array_push($included, "Dados incluidos com sucesso"." ISPB: ".$value->ispb." Nome: ".$value->name);
                    }
                }
            }
            return response()->json(array(
                "success" => $included,
                "error"   => $errors
            ));
        }
    }

    public function show(PixParticipant $pixParticipant)
    {
        return response()->json( $pixParticipant->get() );
    }

    public function showInstitutionToCreateKey(PixParticipant $pixParticipant, Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // include celcoin
        $participantIdIn = [647];
        
        $account = Account::where('id', '=', $checkAccount->account_id)->first();

        if ($account->is_alias_account == 1) {
            // check if is BMP alias account
            if ($account->alias_account_bank_id == 161) {
                // include bmp
                array_push($participantIdIn, 201);
            }
        }

        $pixParticipant->participantIdIn = $participantIdIn;

        return response()->json( $pixParticipant->get() );
    }
}
