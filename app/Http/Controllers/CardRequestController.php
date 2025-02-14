<?php

namespace App\Http\Controllers;

use App\Models\CardRequest;
use App\Models\CardUser;
use App\Models\CardUserDetail;
use App\Models\RegisterDetail;
use App\Models\Card;
use App\Models\ApiConfig;
use App\Models\Account;
use App\Libraries\ApiAquiCard;
use App\Services\Account\MovementTaxService;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Failures\sendFailureAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class CardRequestController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_request = new CardRequest();
        $card_request->account_id = $checkAccount->account_id;
        return response()->json($card_request->getCardRequest());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [122, 309];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = new Account();
        $account->id        = $checkAccount->account_id;
        $account->master_id = $checkAccount->master_id;
        $accountData        = $account->returnAccountData();

        $cardUser       = null;
        $cardUserDetail = null;

        if(strlen($accountData->cpf_cnpj) == 11){ //check if account is PF or PJ
            //PF
            if(CardUser::where('cpf_cnpj','=',$accountData->cpf_cnpj)->count() == 0){ //check if cpf has card user
                $cardUser = CardUser::create([ //create card user
                    'cpf_cnpj'      => $accountData->cpf_cnpj,
                    'created_at'    => \Carbon\Carbon::now(),
                ]);
            } else {
                $cardUser = CardUser::where('cpf_cnpj','=',$accountData->cpf_cnpj)->first(); //get card user data
            }

            if( CardUserDetail::where('card_user_id','=',$cardUser->id)->where('account_id','=',$checkAccount->account_id)->count() == 0 ){ // check if cpf has card user detail
                $registerDetail = new RegisterDetail();
                $registerDetail->register_master_id = $accountData->register_master_id;
                $registerDetail->master_id          = $checkAccount->master_id;
                $registerDetailData                 = $registerDetail->getRegister();

                //get first, middle and last name----
                $name       = trim($registerDetailData->name);
                $last_name  = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
                $first_name = trim( preg_replace('#'.$last_name.'#', '', $name ) );
                //---

                if($registerDetailData->email == null or $registerDetailData->email == ''){
                    return response()->json(array("error" => "E-Mail não definido para o titular"));
                }

                if($registerDetailData->phone == null or $registerDetailData->phone == ''){
                    return response()->json(array("error" => "Celular não definido para o titular"));
                }

                if($registerDetailData->address_zip_code == null or $registerDetailData->address_zip_code == ''){
                    return response()->json(array("error" => "CEP não definido para o titular"));
                }

                if($registerDetailData->address_city == null or $registerDetailData->address_city == ''){
                    return response()->json(array("error" => "Cidade não definida para o titular"));
                }

                if($registerDetailData->address_state_id == null or $registerDetailData->address_state_id == ''){
                    return response()->json(array("error" => "Estado não definido para o titular"));
                }

                if($registerDetailData->address == null or $registerDetailData->address == ''){
                    return response()->json(array("error" => "Endereço não definido para o titular"));
                }

                if($registerDetailData->rg_number == null or $registerDetailData->rg_number == ''){
                    return response()->json(array("error" => "RG não definido para o titular"));
                }

                if($registerDetailData->mother_name == null or $registerDetailData->mother_name == ''){
                    return response()->json(array("error" => "Nome da mãe não definido para o titular"));
                }

                if($registerDetailData->date_birth == null or $registerDetailData->date_birth == ''){
                    return response()->json(array("error" => "Data de nascimento não definida para o titular"));
                }

                if($registerDetailData->gender_id == null or $registerDetailData->gender_id == ''){
                    return response()->json(array("error" => "Gênero não definido para o titular"));
                }

                if ($request->another_delivery_address == 1) {

                    if(strlen(preg_replace( '/[^0-9]/', '', $request->address_zip_code)) > 8 ){
                        return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
                    }
            
                    $cardUserDetail = CardUserDetail::create([
                        'account_id'          => $checkAccount->account_id,
                        'card_type_id'        => 1,
                        'card_user_id'        => $cardUser->id,
                        'name'                => $first_name,
                        'surname'             => $last_name,
                        'email'               => $registerDetailData->email,
                        'cell_phone'          => preg_replace( '/[^0-9]/', '', $registerDetailData->phone),
                        'address_zip_code'    => $request->address_zip_code,
                        'address'             => $request->address,
                        'address_number'      => $request->address_number,
                        'address_complement'  => $request->address_complement,
                        'address_district'    => $request->address_district,
                        'address_city'        => $request->address_city,
                        'address_state_id'    => $request->address_state_id,
                        'rg_number'           => preg_replace( '/[^0-9]/', '', $registerDetailData->rg_number),
                        'politically_exposed' => $registerDetailData->politically_exposed,
                        'mother_name'         => $registerDetailData->mother_name,
                        'date_birth'          => $registerDetailData->date_birth,
                        'gender_id'           => $registerDetailData->gender_id,
                        'nationality_id'      => $registerDetailData->nationality_id,
                        'created_at'          => \Carbon\Carbon::now()
                    ]);

                } else {
                    $cardUserDetail = CardUserDetail::create([ //create card user detail
                        'account_id'          => $checkAccount->account_id,
                        'card_type_id'        => 1,
                        'card_user_id'        => $cardUser->id,
                        'name'                => $first_name,
                        'surname'             => $last_name,
                        'email'               => $registerDetailData->email,
                        'cell_phone'          => preg_replace( '/[^0-9]/', '', $registerDetailData->phone),
                        'address'             => $registerDetailData->address_public_place.' '.$registerDetailData->address,
                        'address_number'      => $registerDetailData->address_number,
                        'address_complement'  => $registerDetailData->address_complement,
                        'address_district'    => $registerDetailData->address_district,
                        'address_city'        => $registerDetailData->address_city,
                        'address_state_id'    => $registerDetailData->address_state_id,
                        'address_zip_code'    => $registerDetailData->address_zip_code,
                        'rg_number'           => preg_replace( '/[^0-9]/', '', $registerDetailData->rg_number),
                        'politically_exposed' => $registerDetailData->politically_exposed,
                        'mother_name'         => $registerDetailData->mother_name,
                        'date_birth'          => $registerDetailData->date_birth,
                        'gender_id'           => $registerDetailData->gender_id,
                        'nationality_id'      => $registerDetailData->nationality_id,
                        'created_at'          => \Carbon\Carbon::now()
                    ]);
                }

            } else {
                $cardUserDetail = CardUserDetail::where('card_user_id','=',$cardUser->id)->where('account_id','=',$checkAccount->account_id)->first(); //get card user detail data

                $registerDetail = new RegisterDetail();
                $registerDetail->register_master_id = $accountData->register_master_id;
                $registerDetail->master_id          = $checkAccount->master_id;
                $registerDetailData                 = $registerDetail->getRegister();

                if($registerDetailData->email == null or $registerDetailData->email == ''){
                    return response()->json(array("error" => "E-Mail não definido para o titular"));
                }

                if($registerDetailData->phone == null or $registerDetailData->phone == ''){
                    return response()->json(array("error" => "Celular não definido para o titular"));
                }

                if($registerDetailData->address_zip_code == null or $registerDetailData->address_zip_code == ''){
                    return response()->json(array("error" => "CEP não definido para o titular"));
                }

                if($registerDetailData->address_city == null or $registerDetailData->address_city == ''){
                    return response()->json(array("error" => "Cidade não definida para o titular"));
                }

                if($registerDetailData->address_state_id == null or $registerDetailData->address_state_id == ''){
                    return response()->json(array("error" => "Estado não definido para o titular"));
                }

                if($registerDetailData->address == null or $registerDetailData->address == ''){
                    return response()->json(array("error" => "Endereço não definido para o titular"));
                }

                if($registerDetailData->rg_number == null or $registerDetailData->rg_number == ''){
                    return response()->json(array("error" => "RG não definido para o titular"));
                }

                if($registerDetailData->mother_name == null or $registerDetailData->mother_name == ''){
                    return response()->json(array("error" => "Nome da mãe não definido para o titular"));
                }

                if($registerDetailData->date_birth == null or $registerDetailData->date_birth == ''){
                    return response()->json(array("error" => "Data de nascimento não definida para o titular"));
                }

                if($registerDetailData->gender_id == null or $registerDetailData->gender_id == ''){
                    return response()->json(array("error" => "Gênero não definido para o titular"));
                }

                //get first, middle and last name----
                $name       = trim($registerDetailData->name);
                $last_name  = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
                $first_name = trim( preg_replace('#'.$last_name.'#', '', $name ) );

                $cardUserDetail->name                = $first_name;
                $cardUserDetail->surname             = $last_name;
                $cardUserDetail->email               = $registerDetailData->email;
                $cardUserDetail->cell_phone          = preg_replace( '/[^0-9]/', '', $registerDetailData->phone);
                $cardUserDetail->address             = $registerDetailData->address_public_place.' '.$registerDetailData->address;
                $cardUserDetail->address_number      = $registerDetailData->address_number;
                $cardUserDetail->address_complement  = $registerDetailData->address_complement;
                $cardUserDetail->address_district    = $registerDetailData->address_district;
                $cardUserDetail->address_city        = $registerDetailData->address_city;
                $cardUserDetail->address_state_id    = $registerDetailData->address_state_id;
                $cardUserDetail->address_zip_code    = $registerDetailData->address_zip_code;
                $cardUserDetail->rg_number           = preg_replace( '/[^0-9]/', '', $registerDetailData->rg_number);
                $cardUserDetail->politically_exposed = $registerDetailData->politically_exposed;
                $cardUserDetail->mother_name         = $registerDetailData->mother_name;
                $cardUserDetail->date_birth          = $registerDetailData->date_birth;
                $cardUserDetail->gender_id           = $registerDetailData->gender_id;
                $cardUserDetail->nationality_id      = $registerDetailData->nationality_id;
                $cardUserDetail->save();

                if ($request->another_delivery_address == 1) {

                    if(strlen(preg_replace( '/[^0-9]/', '', $request->address_zip_code)) > 8 ){
                        return response()->json(array("error" => "O CEP informado é inválido, por favor verifique o CEP e tente novamente"));
                    }

                    $cardUserDetail->address_zip_code    = $request->address_zip_code;
                    $cardUserDetail->address             = $request->address;
                    $cardUserDetail->address_number      = $request->address_number;
                    $cardUserDetail->address_complement  = $request->address_complement;
                    $cardUserDetail->address_district    = $request->address_district;
                    $cardUserDetail->address_city        = $request->address_city;
                    $cardUserDetail->address_state_id    = $request->address_state_id;
                    $cardUserDetail->save();

                }
            }

            if( $cardUserDetail != null){
                if(CardRequest::where('account_id','=',$checkAccount->account_id)->where('card_user_detail_id','=',$cardUserDetail->id)->where('status_id','=',6)->where('card_type_id','=',1)->whereNull('deleted_at')->count() > 0){
                    return response()->json(array("error" => "Já existe uma solicitação de cartão aguardando a aprovação para o usuário"));
                } else if(CardRequest::where('account_id','=',$checkAccount->account_id)->where('card_user_detail_id','=',$cardUserDetail->id)->where('status_id','=',9)->where('card_type_id','=',1)->whereNull('deleted_at')->count() > 0){
                    return response()->json(array("error" => "Já existe uma solicitação de cartão aprovada para o usuário, para solicitar um novo cartão para este usuário cancele o atual e tente novamente"));
                } else {
                   $getTax = Account::getTax($checkAccount->account_id, 15, $checkAccount->master_id);
                    if($cardRequest = CardRequest::create([ //create card request
                        'account_id'          => $cardUserDetail->account_id,
                        'card_type_id'        => $cardUserDetail->card_type_id,
                        'card_user_detail_id' => $cardUserDetail->id,
                        'master_id'           => $checkAccount->master_id,
                        'status_id'           => 6,
                        'request_by'          => $request->header('userId'),
                        'approved'            => 0,
                        'tax_value'           => $getTax->value,
                        'created_at'          => \Carbon\Carbon::now(),
                        'ip'                  => $request->ip(),
                        'term_accepted'       => $request->term_accepted,
                    ])){

                        if($getTax->value > 0){
                            $tax = $getTax->value;
                            $movementTax = new MovementTaxService();
                            $movementTax->movementData = (object) [
                                'account_id'    => $checkAccount->account_id,
                                'master_id'     => $checkAccount->master_id,
                                'origin_id'     => $cardRequest->id,
                                'mvmnt_type_id' => 26,
                                'value'         => $getTax->value,
                                'description'   => 'Tarifa de Emissão de Cartão'
                            ];
                            if(!$movementTax->create()){
                                $sendFailureAlert               = new sendFailureAlert();
                                $sendFailureAlert->title        = 'Solicitação de Cartão';
                                $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de emissão de cartão na conta id: '.$checkAccount->account_id.', id solicitação: '.$cardRequest->id.', valor da tarifa: '.$getTax->value;
                                $sendFailureAlert->sendFailures();
                            }
                        }
                        return response()->json(array("success" => "Solicitação de cartão realizada com sucesso, por favor aguarde a aprovação da agência"));
                    } else {
                        return response()->json(array("error" => "Não foi possível realizar a solicitação de cartão no momento, por favor tente novamente mais tarde"));
                    }
                }
            } else {
                return response()->json(array("error" => "Dados do usuário não localizados para solicitação de cartão"));
            }
        } else {
            //PJ
            return response()->json(array("error" => "Desculpe, ainda não é possível solicitar cartão para contas PJ"));
        }
    }

    protected function requestCard(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [123];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( CardRequest::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->count() > 0){
            $cardRequest                      = new CardRequest();
            $cardRequest->id                  = $request->id;
            $cardRequest->master_id           = $checkAccount->master_id;
            $cardRequestData                  = $cardRequest->requestCardData();

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

            $apiAquiCard->surname             = $cardRequestData->crd_usr_dtls_surname;
            $apiAquiCard->name                = $cardRequestData->crd_usr_dtls_name;
            $apiAquiCard->mother_name         = $cardRequestData->crd_usr_dtls_mother_name;
            $apiAquiCard->gender              = $cardRequestData->gndr_short_description;
            $apiAquiCard->cpf_cnpj            = $cardRequestData->rgstr_cpf_cnpj;
            $apiAquiCard->email               = $cardRequestData->crd_usr_dtls_email;
            $apiAquiCard->phone               = $cardRequestData->crd_usr_dtls_cell_phone;
            $apiAquiCard->date_birth          = $cardRequestData->crd_usr_dtls_date_birth;
            $apiAquiCard->address             = $cardRequestData->crd_usr_dtls_address;
            $apiAquiCard->address_number      = $cardRequestData->crd_usr_dtls_address_number;
            $apiAquiCard->state               = $cardRequestData->crd_usr_dtls_address_state_short_description;
            $apiAquiCard->city                = $cardRequestData->crd_usr_dtls_address_city;
            $apiAquiCard->zip_code            = $cardRequestData->crd_usr_dtls_address_zip_code;
            $apiAquiCard->district            = $cardRequestData->crd_usr_dtls_address_district;
            $apiAquiCard->complement          = $cardRequestData->crd_usr_dtls_address_complement;
            $apiAquiCard->fourth_line         = $cardRequestData->fourth_line; //strtok($cardRequestData->fourth_line, " ");
            $apiAquiCard->document_type       = "RGE"; //or CNH
            $apiAquiCard->document_number     = $cardRequestData->crd_usr_dtls_rg_number;
            $apiAquiCard->politically_exposed = "false";
            $apiAquiCard->income              = "5000";

            $apiCardRequest = $apiAquiCard->cardRequest();

            if($apiCardRequest->success){
                //return response()->json($apiCardRequest->data);
                Log::debug([$apiCardRequest->data]);
                if($apiCardRequest->data->status == 'success'){
                    $cardRqt = CardRequest::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first();
                    $cardRqt->code_request      = $apiCardRequest->data->data->content->codeRequest;
                    $cardRqt->code              = $apiCardRequest->data->data->content->code;
                    $cardRqt->approved          = 1;
                    $cardRqt->approved_by       = $request->header('userId');
                    $cardRqt->approved_at       = \Carbon\Carbon::now();
                    $cardRqt->status_id         = 9;
                    $cardRqt->card_rqst_stts_id = 1;
                    $cardRqt->save();
                    return response()->json(array("success" => "Cartão solicitado com sucesso"));
                } else {
                    $errorData = "";
                    if(isset($apiCardRequest->data->info)){
                        $errorData = ' | '.$apiCardRequest->data->info;
                    }
                    return response()->json(array("error" => "Ocorreu um erro na solicitação do cartão ".$errorData));
                }
            } else {
                $errorData = "";
                if(isset($apiCardRequest->data->info)){
                    $errorData =  ' | '.$apiCardRequest->data->info;
                }
                return response()->json(array("error" => "Ocorreu um erro ao solicitar o cartão ".$errorData));
            }
        } else {
            return response()->json(array("error" => "Solicitação de cartão não localizada"));
        }

    }

    public function checkCardRequestStatus()
    {
        $cardRequests = CardRequest::where('card_rqst_stts_id','=',1)->whereNull('deleted_at')->get();
        foreach($cardRequests as $cardRequest){

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

            $apiAquiCard->codeRequest =  $cardRequest->code_request;

            $apiCardRequestStatus = $apiAquiCard->cardRequestStatus();

            if($apiCardRequestStatus->success){
                if($apiCardRequestStatus->data->status == 'success'){
                    $contents = $apiCardRequestStatus->data->data->content;
                    foreach($contents as $content){
                        switch($content->codeRequestStatusType){
                            case 'CMPL': //2
                                // update card request
                                $cardRqst = CardRequest::where('id','=', $cardRequest->id)->first();
                                $cardRqst->card_rqst_stts_id = 2;
                                $cardRqst->save();

                                $internal_code = null;
                                $pan_vas       = null;
                                $pan           = null;
                                $last_digits   = null;
                                $expire_date   = null;

                                $apiAquiCard->codeRequest = $content->codeRequest;

                                $apiCardInfo = $apiAquiCard->cardInfo();
                                if($apiCardInfo->success){
                                    if($apiCardInfo->data->status == 'success'){
                                        $cardsInfo = $apiCardInfo->data->data->cards;
                                        foreach($cardsInfo as $cardInfo){
                                            $internal_code = $cardInfo->internalCode;
                                            $pan_vas       = $cardInfo->panVas;
                                            $pan           = $cardInfo->pan;
                                            $last_digits   = substr($pan,-4);
                                            $expire_date   = $cardInfo->expireDate;
                                        }
                                    }
                                }

                                //create card
                                Card::create([
                                    'account_id'          => $cardRequest->account_id,
                                    'card_request_id'     => $cardRequest->id,
                                    'card_user_detail_id' => $cardRequest->card_user_detail_id,
                                    'card_code'           => $cardRequest->code_request,
                                    'card_status_id'      => 4,
                                    'blocked'             => 0,
                                    'internal_code'       => $internal_code,
                                    'pan_vas'             => $pan_vas,
                                    'pan'                 => $pan,
                                    'last_digits'         => $last_digits,
                                    'expire_date'         => $expire_date,
                                    'created_at'          => \Carbon\Carbon::now()
                                ]);
                            break;
                            case 'MSOK': //3
                                 // update card request
                                 $cardRqst = CardRequest::where('id','=', $cardRequest->id)->first();
                                 $cardRqst->card_rqst_stts_id = 3;
                                 $cardRqst->save();
                            break;
                            case 'MSKO': //4
                                 // update card request
                                 $cardRqst = CardRequest::where('id','=', $cardRequest->id)->first();
                                 $cardRqst->card_rqst_stts_id = 4;
                                 $cardRqst->save();
                            break;
                        }
                    }
                }
            }
        }
    }

    public function reproveCardRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [123];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( CardRequest::where('id','=',$request->id)->whereNotIn('status_id',[9,48])->where('master_id','=',$checkAccount->master_id)->count() > 0){
            $cardRequest = CardRequest::where('id','=',$request->id)->whereNotIn('status_id',[9,48])->where('master_id','=',$checkAccount->master_id)->first();
            $cardRequest->status_id = 48;
            $cardRequest->approved = 0;
            $cardRequest->approved_by = $request->header('userId');
            $cardRequest->approved_at = \Carbon\Carbon::now();
            if($cardRequest->save()){
                return response()->json(array("success" => "Solicitação de cartão reprovada com sucesso"));
            } else {
                return response()->json(array("error" => "Poxa, ocorreu uma falha ao reprovar a solicitação de cartão, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" => "Solicitação de cartão já foi aprovada ou reprovada, por favor verifique os dados informados e tente novamente"));
        }
    }
}
