<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardRequest;
use App\Models\CardUser;
use App\Models\CardUserDetail;
use App\Models\ApiConfig;
use App\Models\Account;
use App\Models\CardShipping;
use App\Models\ChangeLimit;
use App\Models\CardBlockStatus;
use App\Libraries\ApiAquiCard;
use App\Libraries\ApiFlashCourier;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Account\MovementTaxService;
use App\Services\Failures\sendFailureAlert;
use App\Classes\Account\ChangeLimitClass;
use App\Classes\Account\AccountMovementFutureClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class CardController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card             = new Card();
        $card->master_id  = $checkAccount->master_id;
        $card->account_id = $checkAccount->account_id;
        return response()->json($card->getCards());
    }

    protected function getCardDetails(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card = new Card();
        $card->id = $request->id;
        $card->master_id = $checkAccount->master_id;
        $card->account_id = $checkAccount->account_id;
        $card->card_user_detail_id = $request->card_user_detail_id;
        $card->card_status_id = $request->card_status_id;
        $card->register_master_id = $request->register_master_id;
        $card->created_at_start = $request->created_at_start;
        $card->created_at_end = $request->created_at_end;
        return response()->json($card->getCardDetails());
    }

    protected function getUserActiveCardData(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card                 = new Card();
        $card->master_id      = $checkAccount->master_id;
        $card->account_id     = $checkAccount->account_id;
        $card->card_status_id = 2; //cartão ativo
        return response()->json($card->getCardsData());
    }

    protected function getCardsToActivate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card = new Card();
        $card->master_id         = $checkAccount->master_id;
        $card->account_id        = $checkAccount->account_id;
        $card->card_status_id_in = [1,4,5,6]; //não ativados
        return response()->json($card->getCardsToActivate());
    }

    protected function cardActivation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card = Card::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->where('internal_code','=',$request->internal_code)->first();

        $cardUserDetail = CardUserDetail::where('id','=',$card->card_user_detail_id)->first();

        $cardUser = CardUser::where('id','=',$cardUserDetail->card_user_id)->first();

        if($cardUser->cpf_cnpj != $request->cpf){
            return response()->json(array('error' => 'CPF informado não confere com CPF do titular cadastrado'));
        }

        if((\Carbon\Carbon::parse($cardUserDetail->date_birth))->format('Y-m-d') != $request->birth_date){
            return response()->json(array('error' => 'Data de nascimento inválida'));
        }

        if($card->last_digits != $request->last_digits){
            return response()->json(array('error' => 'Digitos finais inválidos'));
        }

        if( base64_decode($request->cpass) != base64_decode($request->confirm_cpass) ){
            return response()->json(array('error' => 'Senha informada não é idêntica a confirmação de senha'));
        }

        if(strlen(base64_decode($request->cpass)) != 4){
            return response()->json(array('error' => 'Senha deve possuir 4 caracteres'));
        }

        $passAllowedNumbers = [0,1,2,3,4,5,6,7,8,9,'0','1','2','3','4','5','6','7','8','9'];
        $passArray          = str_split(base64_decode($request->cpass));
        $lastNumber         = null;
        foreach($passArray as $pass){
            if(  !in_array($pass, $passAllowedNumbers, true ) ){
                return response()->json(array("error" => "Senha deve possuir apenas caracteres numéricos"));
            } else {
                if($lastNumber != null){
                    if(($lastNumber + 1) == (int) $pass){
                        return response()->json(array("error" => "A senha não pode conter números sequenciais"));
                    }
                }
                $lastNumber = (int) $pass;
            }
        }

        foreach (count_chars(base64_decode($request->cpass), 1) as $i => $val) {
            if($val > 2){
                return response()->json(array("error" => "Não é possível utilizar o mesmo caracter mais de uma vez na senha"));
            }
        }

        $apiConfig                        = new ApiConfig();
        $apiConfig->master_id             = $checkAccount->master_id;
        $apiConfig->api_id                = 6;
        $apiConfig->onlyActive            = 1;
        $apiData                          = $apiConfig->getApiConfig()[0];

        $apiAquiCard                      = new ApiAquiCard();

        $apiAquiCard->user_name           = Crypt::decryptString($apiData->api_client_id);
        $apiAquiCard->password            = Crypt::decryptString($apiData->api_authentication);
        $apiAquiCard->codeCobrander       = Crypt::decryptString($apiData->api_agency);
        $apiAquiCard->codeCardCategory    = Crypt::decryptString($apiData->api_account);
        $apiAquiCard->api_address         = Crypt::decryptString($apiData->api_address);

        $apiAquiCard->codeRequest         = $card->card_code;
        $apiAquiCard->codeCardExt         = "";

        $apiCardActivation = $apiAquiCard->cardActivation();

        Log::debug([$apiCardActivation->data]);

        if($apiCardActivation->success){
            if($apiCardActivation->data->status == 'success'){
                $card->card_status_id = 2;
                $card->activated_at   = \Carbon\Carbon::now();
                $card->activated_ip   = $request->header('ip');
                $card->activated_by   = $request->header('userId');
                if($card->save()){
                    $apiAquiCard->pinblock = base64_decode($request->cpass);
                    $apiCardSetPin = $apiAquiCard->cardCreatePin();
                    Log::debug([$apiCardSetPin->data]);
                    if($apiCardSetPin->success){
                        return response()->json(array("success" => "Cartão ativado com sucesso"));
                    } else {
                        return response()->json(array("error" => "O cartão está ativado, porém não foi possível definir sua senha, acesse alterar senha para defini-la"));
                    }
                } else {
                    return response()->json(array("error" => "Não foi possível ativar o seu cartão no momento, por favor tente novamente dentro de alguns minutos"));
                }
            } else {
                return response()->json(array("error" => "Não foi possível ativar o seu cartão no momento, por favor tente novamente dentro de alguns minutos"));
            }
        } else {
            return response()->json(array("error" => "Não foi possível ativar o seu cartão no momento, por favor tente novamente dentro de alguns minutos"));
        }
    }

    protected function setPassword(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card = Card::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->where('internal_code','=',$request->internal_code)->first();

        if( base64_decode($request->cpass) != base64_decode($request->confirm_cpass) ){
            return response()->json(array('error' => 'Senha informada não é idêntica a confirmação de senha'));
        }

        if(strlen(base64_decode($request->cpass)) != 4){
            return response()->json(array('error' => 'Senha deve possuir 4 caracteres'));
        }

        $passAllowedNumbers = [0,1,2,3,4,5,6,7,8,9,'0','1','2','3','4','5','6','7','8','9'];
        $passArray          = str_split(base64_decode($request->cpass));
        $lastNumber         = null;
        foreach($passArray as $pass){
            if(  !in_array($pass, $passAllowedNumbers, true ) ){
                return response()->json(array("error" => "Senha deve possuir apenas caracteres numéricos"));
            } else {
                if($lastNumber != null){
                    if(($lastNumber + 1) == (int) $pass){
                        return response()->json(array("error" => "A senha não pode conter números sequenciais"));
                    }
                }
                $lastNumber = (int) $pass;
            }
        }

        foreach (count_chars(base64_decode($request->cpass), 1) as $i => $val) {
            if($val > 2){
                return response()->json(array("error" => "Não é possível utilizar o mesmo caracter mais de uma vez na senha"));
            }
        }

        $apiConfig                        = new ApiConfig();
        $apiConfig->master_id             = $checkAccount->master_id;
        $apiConfig->api_id                = 6;
        $apiConfig->onlyActive            = 1;
        $apiData                          = $apiConfig->getApiConfig()[0];

        $apiAquiCard                      = new ApiAquiCard();

        $apiAquiCard->user_name           = Crypt::decryptString($apiData->api_client_id);
        $apiAquiCard->password            = Crypt::decryptString($apiData->api_authentication);
        $apiAquiCard->codeCobrander       = Crypt::decryptString($apiData->api_agency);
        $apiAquiCard->codeCardCategory    = Crypt::decryptString($apiData->api_account);
        $apiAquiCard->api_address         = Crypt::decryptString($apiData->api_address);

        $apiAquiCard->codeRequest         = $card->card_code;
        $apiAquiCard->codeCardExt         = "";
        $apiAquiCard->pinblock            = base64_decode($request->cpass);

        if($request->oldpass != ''){
            $apiCardSetPin = $apiAquiCard->cardCreatePin();
            Log::debug([$apiCardSetPin->data]);
            if($apiCardSetPin->success){
                return response()->json(array("success" => "Senha definida com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao definir a senha, por favor tente novamente mais tarde"));
            }
        } else {
            $apiAquiCard->oldPinblock = base64_decode($request->oldpass);
            $apiCardSetPin = $apiAquiCard->cardChangePin();
            Log::debug([$apiCardSetPin->data]);
            if($apiCardSetPin->success){
                return response()->json(array("success" => "Senha alterada com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao alterar a senha, por favor tente novamente mais tarde"));
            }
        }
    }

    public function resetPassword()
    {
        // ----------------- Check Account Verification ----------------- //
       /* $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        } */
        // -------------- Finish Check Account Verification -------------- //

        $card = Card::where('id','=',12)->first();//Card::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->where('internal_code','=',$request->internal_code)->first();

        $apiConfig                        = new ApiConfig();
        $apiConfig->master_id             = 1;//$checkAccount->master_id;
        $apiConfig->api_id                = 6;
        $apiConfig->onlyActive            = 1;
        $apiData                          = $apiConfig->getApiConfig()[0];

        $apiAquiCard                      = new ApiAquiCard();

        $apiAquiCard->user_name           = Crypt::decryptString($apiData->api_client_id);
        $apiAquiCard->password            = Crypt::decryptString($apiData->api_authentication);
        $apiAquiCard->codeCobrander       = Crypt::decryptString($apiData->api_agency);
        $apiAquiCard->codeCardCategory    = Crypt::decryptString($apiData->api_account);
        $apiAquiCard->api_address         = Crypt::decryptString($apiData->api_address);

        $apiAquiCard->codeRequest         = $card->card_code;
        $apiCardResetPin = $apiAquiCard->cardResetPin();
        Log::debug([$apiCardResetPin->data]);
        if($apiCardResetPin->success){
            return response()->json(array("success" => "Senha enviada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao enviar a senha, por favor tente novamente mais tarde"));
        }



    }

    protected function cardBlock(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [127, 313];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card = Card::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->first();

        if(isset($checkAccount->is_master)){
            if($checkAccount->is_master){
                $card = Card::where('id','=',$request->card_id)->first();
            }
        }        

        $apiConfig                        = new ApiConfig();
        $apiConfig->master_id             = $checkAccount->master_id;
        $apiConfig->api_id                = 6;
        $apiConfig->onlyActive            = 1;
        $apiData                          = $apiConfig->getApiConfig()[0];

        $apiAquiCard                      = new ApiAquiCard();

        $apiAquiCard->user_name           = Crypt::decryptString($apiData->api_client_id);
        $apiAquiCard->password            = Crypt::decryptString($apiData->api_authentication);
        $apiAquiCard->codeCobrander       = Crypt::decryptString($apiData->api_agency);
        $apiAquiCard->codeCardCategory    = Crypt::decryptString($apiData->api_account);
        $apiAquiCard->api_address         = Crypt::decryptString($apiData->api_address);

        $apiAquiCard->codeRequest         = $card->card_code;
        $apiAquiCard->blockEvent          = $request->block_status_id;

        $apiCardBlock = $apiAquiCard->cardBlock();

        if($apiCardBlock->success){
            $cardBlockStatus = CardBlockStatus::where('code', '=', $request->block_status_id)->first();
            if($apiCardBlock->data->status == 'success'){
                $card->card_status_id    = 3;
                $card->blocked           = 1;
                $card->blocked_at        = \Carbon\Carbon::now();
                //$card->deleted_at        = \Carbon\Carbon::now();
                $card->blocked_ip        = $request->header('ip');
                $card->blocked_by        = $request->header('userId');
                $card->blocked_status_id = $cardBlockStatus->id;
                if($card->save()){

                    $card_request = CardRequest::where('id','=',$card->card_request_id)->first();
                    $card_request->status_id = 3;
                    $card_request->save();

                    return response()->json(array("success" => "Cartão bloqueado com sucesso"));
                } else {
                    return response()->json(array("error" => "Não foi possível bloquear o seu cartão no momento, por favor tente novamente dentro de alguns minutos"));
                }
            } else {
                return response()->json(array("error" => "Não foi possível bloquear o seu cartão no momento, por favor tente novamente dentro de alguns minutos"));
            }
        } else {
            return response()->json(array("error" => "Não foi possível bloquear o seu cartão no momento, por favor tente novamente dentro de alguns minutos"));
        }
    }

    public function checkCardInfosAqui()
    {
        $cards = Card::whereNull('pan_vas')->whereNull('deleted_at')->get();
        foreach($cards as $card){
            $cardRequest                      = CardRequest::where('id','=',$card->card_request_id)->first();
            $apiConfig                        = new ApiConfig();
            $apiConfig->master_id             = $cardRequest->master_id;
            $apiConfig->api_id                = 6;
            $apiConfig->onlyActive            = 1;
            $apiData                          = $apiConfig->getApiConfig()[0];

            $apiAquiCard                      = new ApiAquiCard();

            $apiAquiCard->user_name           = Crypt::decryptString($apiData->api_client_id);
            $apiAquiCard->password            = Crypt::decryptString($apiData->api_authentication);
            $apiAquiCard->codeCobrander       = Crypt::decryptString($apiData->api_agency);
            $apiAquiCard->codeCardCategory    = Crypt::decryptString($apiData->api_account);
            $apiAquiCard->api_address         = Crypt::decryptString($apiData->api_address);

            $apiAquiCard->codeRequest         =  $card->card_code;

            $apiCardInfo = $apiAquiCard->cardInfo();
            if($apiCardInfo->success){
                if($apiCardInfo->data->status == 'success'){
                    $internal_code = null;
                    $pan_vas       = null;
                    $pan           = null;
                    $last_digits   = null;
                    $expire_date   = null;
                    $card_stt_id   = $card->card_status_id;
                    $cardsInfo = $apiCardInfo->data->data->cards;
                    foreach($cardsInfo as $cardInfo){
                        $internal_code = $cardInfo->internalCode;
                        $pan_vas       = $cardInfo->panVas;
                        $pan           = $cardInfo->pan;
                        $last_digits   = substr($pan,-4);
                        $expire_date   = $cardInfo->expireDate;

                        switch($cardInfo->status){
                            case 'ASS':
                                $card_stt_id = 1;
                            break;
                            case '01':
                                $card_stt_id = 2;
                            break;
                            case '03':
                                $card_stt_id = 3;
                            break;
                            case 'IAA':
                                $card_stt_id = 4;
                            break;
                            case 'APP':
                                $card_stt_id = 5;
                            break;
                            case 'PRD':
                                $card_stt_id = 6;
                            break;
                        }

                        $crd                 = Card::where('id','=',$card->id)->first();
                        $crd->internal_code  = $internal_code;
                        $crd->pan_vas        = $pan_vas;
                        $crd->pan            = $pan;
                        $crd->last_digits    = $last_digits;
                        $crd->expire_date    = $expire_date;
                        $crd->card_status_id = $card_stt_id;
                        $crd->save();
                    }
                }
            }
        }
    }

    public function checkDeliveryStatus()
    {
        $flashCourier               = new ApiFlashCourier();
        $flashCourier->api_address  = 'https://webservice.flashpegasus.com.br';
        $flashCourier->user         = 'ws.dinaribank';
        $flashCourier->password     = '123qwe';
        $flashCourier->client_id    = '5393';

        $cards = Card::where('card_status_id', '=', 1)->whereNull('deleted_at')->get();

        foreach($cards as $card){
            
            $flashCourier->nun_enc_clis = [$card->pan_vas];
            $deliveryStatus             = $flashCourier->getData();

            if($deliveryStatus->success){
                $xml = new SimpleXMLElement($deliveryStatus->xml);
                $delivery_data     = [];

                foreach($xml->hawbsWS as $ar) {
                    $delivery_down = [];
                    
                    $dtCad = null;
                    $dtCollect = null;
                    $dtPost = null;
                    $deliveryAr = null;
                    $deliveryUfDest = null;
                    $deliveryHawbId = null;

                    $delivery = (object) json_decode(json_encode($ar),TRUE);

                    if ( isset($delivery->statusRetorno) ){

                        if(isset($delivery->dtCad)) {
                            $dtCad = \Carbon\Carbon::CreateFromFormat('d/m/Y H:i', $delivery->dtCad)->format('Y-m-d H:i');
                        }
                       
                        if(isset($delivery->dtColeta)) {
                            $dtCollect = \Carbon\Carbon::CreateFromFormat('d/m/Y H:i', $delivery->dtColeta)->format('Y-m-d H:i');
                        }
                        
                        if(isset($delivery->dtPostagem)) {
                            $dtPost = \Carbon\Carbon::CreateFromFormat('d/m/Y H:i', $delivery->dtPostagem)->format('Y-m-d H:i');
                        }

                        if(isset($delivery->ar)){
                            $deliveryAr = $delivery->ar;
                        }
                        
                        if(isset($delivery->ufDest)){
                            $deliveryUfDest = $delivery->ufDest;
                        }
                        
                        if(isset($delivery->hawbId)){
                            $deliveryHawbId = $delivery->hawbId;
                        }

                        $updateCard = Card::where('id', '=', $card->id)->first();
                        $updateCard->delivery_status = $delivery->statusRetorno;
                        $updateCard->delivery_ar = $deliveryAr;
                        $updateCard->delivery_register_at = $dtCad;
                        $updateCard->delivery_collect_at = $dtCollect;
                        $updateCard->delivery_hawb_id = $deliveryHawbId;
                        $updateCard->delivery_uf = $deliveryUfDest;
                        $updateCard->delivery_post_at = $dtPost;
                        $updateCard->save();

                        foreach($ar->baixa as $baixa ){
                            $baixa = (object) json_decode(json_encode($baixa),TRUE);
                            array_push( $delivery_down, [
                                'try' => $baixa->tentativas
                            ]);
                        }

                        foreach($ar->historico as $historico ){
                            $historico = (object) json_decode(json_encode($historico),TRUE);
                            $situation = "";
                            if(!is_array($historico->situacao)){
                                $situation = $historico->situacao;
                            }

                            if ( CardShipping::where('card_id', '=', $card->id)->where('occourence_date', '=', \Carbon\Carbon::CreateFromFormat('d/m/Y H:i', $historico->ocorrencia)->format('Y-m-d h:i'))->where('event_id', '=', $historico->eventoId)->whereNull('deleted_at')->count() == 0  ){
                                CardShipping::create([
                                    'uuid' => Str::orderedUuid(),
                                    'card_id' => $card->id,
                                    'occourence_date' => \Carbon\Carbon::CreateFromFormat('d/m/Y H:i', $historico->ocorrencia)->format('Y-m-d h:i'),
                                    'event_id' => $historico->eventoId,
                                    'event' => $historico->evento,
                                    'frq' =>  $historico->frq,
                                    'local' => $historico->local,
                                    'situation' => $situation
                                ]);
                            }
                        }
                    }
                }
            } 
        }
    }

    public function createCardMaintenanceFee()
    {
        $card = new Card();
        $movementTax = new MovementTaxService();
        $competence = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('m/Y');
        foreach($card->getCardMaintanceFee() as $activeCard){
            if($activeCard->value > 0){

                $accountMovementFuture = new AccountMovementFutureClass();
                $accountMovementFuture->account_id = $activeCard->account_id;
                $accountMovementFuture->master_id = $activeCard->master_id;
                $accountMovementFuture->mvmnt_type_id = 36;
                $accountMovementFuture->description = 'MENSALIDADE DE MANUTENÇÃO DE CARTÃO | '.$competence;
                $accountMovementFuture->value = $activeCard->value;
                $accountMovementFuture->create();


                /*$movementTax->movementData = (object) [
                    'account_id'    => $activeCard->account_id,
                    'master_id'     => $activeCard->master_id,
                    'origin_id'     => $activeCard->id,
                    'mvmnt_type_id' => 36,
                    'value'         => $activeCard->value,
                    'description'   => 'Tarifa de Manutenção do Cartão'
                ];
                if(!$movementTax->create()){
                    $sendFailureAlert               = new sendFailureAlert();
                    $sendFailureAlert->title        = 'Tarifa Manutenção Cartão';
                    $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de manutenção do cartão na conta: '.$activeCard->account_number.', id cartão: '.$activeCard->id.', valor da tarifa: '.$activeCard->value;
                    $sendFailureAlert->sendFailures();
                } */






            }
        }
    }

    public function requestUpdateCardLimit(Request $request, ChangeLimitClass $changeLimitClass)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [127, 313];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $request->request->add(['account_id' => (int) $checkAccount->account_id]);
        $request->request->add(['change_limit_type_id' => 1]);
        $request->request->add(['ip' => $request->header('ip')]);

        $changeLimitClass->payload = $request;

        $requestChangeLimit = $changeLimitClass->requestChangeLimit();

        if( ! $requestChangeLimit->success ){
            return response()->json(array("error" => $requestChangeLimit->message_pt_br));
        }

        return response()->json(array("success" => $requestChangeLimit->message_pt_br, "data" => $requestChangeLimit->data));        
    }

    public function approveUpdateCardLimit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [127, 313];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // Get change limt data
        if( ! $changeLimitData = ChangeLimit::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->where('change_limit_type_id', '=', 1)->whereNull('deleted_at')->first() ){
            return response()->json(array("error" => "Solicitação de alteração de limite não localizada"));
        }

        // Check if token is set
        if( $changeLimitData->approval_token == null or $changeLimitData->approval_token == '' ){
            return response()->json(array("error" => "Token inválido"));
        }

        // Check if token informed by user is equals change limit data
        if( $request->token != $changeLimitData->approval_token ){
            return response()->json(array("error" => "Token inválido"));
        }

        // Check if token is expired
        if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse( $changeLimitData->approval_token_expiration )->format('Y-m-d H:i:s')) ){
            return response()->json(array("error" => "Token inválido, token gerado a mais de 10 minutos, cancele e refaça o processo de alteração de limite"));
        }

        // Check card data
        if( ! $card = Card::where('id', '=', $changeLimitData->card_id)->where('account_id', '=', $changeLimitData->account_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Cartão não localizado"));
        }

        // Update card month limit
        $card->check_limit = $changeLimitData->check_limit;
        $card->limit_value = $changeLimitData->new_value;
        $card->save();

        // Update change limit data
        $changeLimitData->limit_changed = 1;
        $changeLimitData->limit_changed_at = \Carbon\Carbon::now();
        $changeLimitData->save();

        return response()->json(array("success" => "Limite mensal de cartão alterado com sucesso"));


    }


}
