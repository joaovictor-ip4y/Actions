<?php

namespace App\Http\Controllers;

use App\Models\CardSaleBanner;
use App\Models\CardSaleBuyer;
use App\Models\CardSaleModality;
use App\Models\CardSaleMovement;
use App\Models\CardSaleParcType;
use App\Models\CardSaleStatus;
use App\Models\CardSaleTerminal;
use App\Models\CardSaleTerminalTax;
use App\Models\CardSaleType;
use App\Models\Justa;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Libraries\ApiJusta;
use App\Libraries\Facilites;
use App\Services\Account\MovementService;
use App\Services\Account\MovementTaxService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class CardSaleMovementController extends Controller
{
    public function sum_terminal(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_sale_movement = new CardSaleMovement();
        $card_sale_movement->master_id           = $checkAccount->master_id;
        $card_sale_movement->register_master_id  = $request->register_master_id;

        if($checkAccount->account_id != NULL){
            //  $card_sale_moviment->account_id            = $checkAccount->account_id;
        }

        return response()->json($card_sale_movement->sum_action());
    }

    protected function credit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [120];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $errors = [];
        $card_sale_movement          = new CardSaleMovement();
        $card_sale_movement->idin    = $request->id;

        foreach($card_sale_movement->creditSumCardSaleMovement() as $movement){
            $terminal = CardSaleTerminal::where('id','=',$movement->terminal_id)->first();
            $movementService = new MovementService();
            $movementService->movementData = (object)[
                'account_id'    => $terminal->account_id,
                'master_id'     => $terminal->master_id,
                'origin_id'     => null,
                'mvmnt_type_id' => 20,
                'value'         => round($movement->value,2),
                'description'   => 'Compensação de Venda Maquininha | Crédito Ref Terminal '.$terminal->terminal,
            ];
            if(!$movementService->create()){
                array_push($errors,'Não foi possível realizar o crédito do terminal '.$terminal->terminal);
            }else{
                foreach($request->id as $movementid){
                    if(CardSaleMovement::where('id','=',$movementid)->where('terminal_id','=',$terminal->id)->count() > 0){
                        $movementUpdt = CardSaleMovement::where('id','=',$movementid)->where('terminal_id','=',$terminal->id)->first();
                        $movementUpdt->credit_status = 29;
                        $movementUpdt->credit_date   = \Carbon\Carbon::now();
                        $movementUpdt->save();
                    }
                }
                $tax = 0;
                $getTax = Account::getTax($terminal->account_id, 14, $terminal->master_id);
                if($getTax->value > 0){
                    $tax = $getTax->value;
                } else if($getTax->percentage > 0){
                    if($movement->value > 0){
                        $tax = round(( ($getTax->percentage/100) * $movement->value),2);
                    }
                }
                if($tax > 0){
                    //create movement for payment tax value
                    $movementTax = new MovementTaxService();
                    $movementTax->movementData = (object) [
                        'account_id'    => $terminal->account_id,
                        'master_id'     => $terminal->master_id,
                        'origin_id'     => null,
                        'mvmnt_type_id' => 21,
                        'value'         => $tax,
                        'description'   => 'Tarifa de Compensação Venda Maquininha | Ref Terminal '.$terminal->terminal,
                    ];
                    $movementTax->create();
                }
            }
        }
        if(sizeof($errors) > 0){
            return response()->json(array("error" => "Não foi possível realizar todos os créditos","error_list" => $errors));
        }else{
            return response()->json(array("success" => "Créditos realizados com sucesso"));
        }
    }

    public function get(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [117, 249];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $userMasterId = $request->header('userMasterId');
        if($userMasterId == ''){
            if(Account::where('id','=',$checkAccount->account_id)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
                return response()->json(array("error" => "Falha de verificação da conta"));
            }
        }

        $card_sale_moviment = new CardSaleMovement();
        $card_sale_moviment->id                  = $request->id;
        $card_sale_moviment->master_id           = $checkAccount->master_id;
        $card_sale_moviment->register_master_id  = $request->register_master_id;
        $card_sale_moviment->terminal            = $request->terminal;
        $card_sale_moviment->value_start         = $request->value_start;
        $card_sale_moviment->value_end           = $request->value_end;
        $card_sale_moviment->net_value_start     = $request->net_value_start;
        $card_sale_moviment->net_value_end       = $request->net_value_end;
        $card_sale_moviment->status_id           = $request->status_id;
        $card_sale_moviment->credit_status       = $request->credit_status;
        $card_sale_moviment->modality            = $request->modality;
        $card_sale_moviment->flag                = $request->flag;
        $card_sale_moviment->parcel              = $request->parcel;
        $card_sale_moviment->code                = $request->code;
        $card_sale_moviment->nsu                 = $request->nsu;
        $card_sale_moviment->auth_code           = $request->auth_code;
        $card_sale_moviment->response_code       = $request->response_code;
        $card_sale_moviment->onlyActive          = $request->onlyActive;
        $card_sale_moviment->onlyToPay           = $request->onlyToPay;
        if($request->date_start != ''){
            $start_date = \Carbon\Carbon::parse($request->date_start)->format('Y-m-d');
            $card_sale_moviment->date_start = $start_date." 00:00:00.000";
        }
        if($request->date_end != ''){
            $end_date = \Carbon\Carbon::parse($request->date_end)->format('Y-m-d');
            $card_sale_moviment->date_end   = $end_date." 23:59:59.998";
        }
        if($checkAccount->account_id != NULL){
            $card_sale_moviment->account_id = $checkAccount->account_id;
        }
       return response()->json($card_sale_moviment->viewall());
    }

    protected function getDetailed(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [117, 249];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $cardSaleMovement                                   = new CardSaleMovement();
        $cardSaleMovement->master_id                        = $checkAccount->master_id;
        $cardSaleMovement->account_id                       = $checkAccount->account_id;
        $cardSaleMovement->onlyActive                       = $request->onlyActive;
        $cardSaleMovement->type_id                          = $request->type_id;
        $cardSaleMovement->manager_id                       = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $cardSaleMovement->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $cardSaleMovement->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $cardSaleMovement->created_at_start             = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $cardSaleMovement->created_at_end               = $request->created_at_end." 23:59:59.998";
        }
        return response()->json( $cardSaleMovement->cardSaleMovementDetailed() );
    }

    protected function exportSaleMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [249];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_sale_moviment = new CardSaleMovement();
        $card_sale_moviment->id                  = $request->id;
        $card_sale_moviment->master_id           = $checkAccount->master_id;
        $card_sale_moviment->register_master_id  = $request->register_master_id;
        $card_sale_moviment->terminal            = $request->terminal;
        $card_sale_moviment->value_start         = $request->value_start;
        $card_sale_moviment->value_end           = $request->value_end;
        $card_sale_moviment->net_value_start     = $request->net_value_start;
        $card_sale_moviment->net_value_end       = $request->net_value_end;
        $card_sale_moviment->status_id           = $request->status_id;
        $card_sale_moviment->credit_status       = $request->credit_status;
        $card_sale_moviment->modality            = $request->modality;
        $card_sale_moviment->flag                = $request->flag;
        $card_sale_moviment->parcel              = $request->parcel;
        $card_sale_moviment->code                = $request->code;
        $card_sale_moviment->nsu                 = $request->nsu;
        $card_sale_moviment->auth_code           = $request->auth_code;
        $card_sale_moviment->response_code       = $request->response_code;
        $card_sale_moviment->onlyActive          = $request->onlyActive;
        $card_sale_moviment->onlyToPay           = $request->onlyToPay;
        if($request->date_start != ''){
            $start_date = \Carbon\Carbon::parse($request->date_start)->format('Y-m-d');
            $card_sale_moviment->date_start = $start_date." 00:00:00.000";
        }
        if($request->date_end != ''){
            $end_date = \Carbon\Carbon::parse($request->date_end)->format('Y-m-d');
            $card_sale_moviment->date_end   = $end_date." 23:59:59.998";
        }
        if($checkAccount->account_id != NULL){
            $card_sale_moviment->account_id = $checkAccount->account_id;
        }

        $items = [];

        foreach($card_sale_moviment->viewall() as $movementData){
            array_push($items, (object) [
                'type_description'              =>   $movementData->type_description,
                'terminal_terminal'             =>   $movementData->terminal_terminal,
                'sale_movement_date'            =>   \Carbon\Carbon::parse($movementData->sale_movement_date)->format('d/m/Y'),
                'status_description'            =>   $movementData->status_description,
                'sale_movement_value'           =>   $movementData->sale_movement_value,
                'sale_movement_net_value'       =>   $movementData->sale_movement_net_value,
                'sale_movement_cet'             =>   number_format($movementData->sale_movement_cet,2,'.',','),
                'sale_movement_card_number'     =>   $movementData->sale_movement_card_number,
                'modality_description'          =>   $movementData->modality_description,
                'banner_description'            =>   $movementData->banner_description,
                'parc_type_id_description'      =>   $movementData->parc_type_id_description,
                'sale_movement_code'            =>   $movementData->sale_movement_code,
                'sale_movement_nsu'             =>   $movementData->sale_movement_nsu,
                'sale_movement_auth_code'       =>   $movementData->sale_movement_auth_code,
                'sale_movement_auth_code'       =>   $movementData->sale_movement_auth_code,
                'sale_movement_response_code'   =>   $movementData->sale_movement_response_code,
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items,
        );
        $file_name = "Extrato_Cartao.pdf";
        $pdf       = PDF::loadView('reports/card_movement', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    public function import(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [119];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $fileName = strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName); //definir nome para o arquivo
        if(Storage::disk('public')->put( $fileName, base64_decode($request->file64))){
            $path =  Storage::disk('public')->path($fileName);
            $dadosplanilha = (Excel::toCollection(new Justa,$path)[0]);
            $i = 0;
            $return = [];
            foreach($dadosplanilha as $valorlinha){
                if($i > 0){
                    if(isset($valorlinha[0])){
                        if(!CardSaleMovement::checkexist(CardSaleTerminal::returnid($valorlinha[1]),$valorlinha[13], $valorlinha[14],$valorlinha[15])){
                            $credit_status = 48;
                            if(CardSaleStatus::returnid($valorlinha[3]) == 1){
                                $credit_status = 47;
                            }

                            $terminalAccountId = CardSaleTerminal::returnAccountId($valorlinha[1]);
                            $tax    = 0;
                            $getTax = Account::getTax($terminalAccountId , 14, $checkAccount->master_id);
                            if($getTax->value > 0){
                                $tax = $getTax->value;
                            } else if($getTax->percentage > 0){
                                if((float) str_replace( ',','.',$valorlinha[5]) > 0){
                                    $tax = round(( ($getTax->percentage/100) * ( (float) str_replace( ',','.',$valorlinha[5]))  ),2);
                                }
                            }

                            CardSaleMovement::create([
                                "type_id"       => CardSaleType::returnid($valorlinha[0]),
                                "terminal_id"   => CardSaleTerminal::returnid($valorlinha[1]),
                                "date"          => ((\Carbon\Carbon::createFromFormat('d/m/y H:i', $valorlinha[2] ))->format('Y-m-d H:i')),
                                "status_id"     => CardSaleStatus::returnid($valorlinha[3]),
                                "value"         => (float) str_replace( ',','.', $valorlinha[4]),
                                "net_value"     => (float) str_replace( ',','.',$valorlinha[5]),
                                "cet"           => (float) str_replace( ',','.',$valorlinha[6]),
                                "card_number"   => $valorlinha[7],
                                "modality_id"   => CardSaleModality::returnid($valorlinha[8]),
                                "banner_id"     => CardSaleBanner::returnid($valorlinha[9]),
                                "buyer_id"      => CardSaleBuyer::returnid($valorlinha[10]),
                                "parc_type_id"  => CardSaleParcType::returnid($valorlinha[11]),
                                "parcel"        => $valorlinha[12],
                                "code"          => $valorlinha[13],
                                "nsu"           => $valorlinha[14],
                                "auth_code"     => $valorlinha[15],
                                "response_code" => $valorlinha[16],
                                "credit_status" => $credit_status,
                                "credit_value"  => (float) str_replace( ',','.',$valorlinha[5]),
                                "account_id"    => $terminalAccountId,
                                "tax_value"     => $tax
                            ]);
                        }
                    }
                }
                $i++;
            }
            return response()->json(['success'=>'Planilha processada com sucesso!']);
        } else {
            return response()->json(['error'=>'Ocorreu um erro ao processar a Planilha']);
        }
    }

    public function getMovementByJustaAPI()
    {
        $apiConfig                                          = new ApiConfig();
        $apiConfig->api_id                                  = 5;
        $apiConfig->onlyActive                              = 1;

        foreach($apiConfig->getApiConfig() as $apiData){
            $apiJusta                  = new ApiJusta();
            $apiJusta->client_id       = Crypt::decryptString($apiData->api_client_id);
            $apiJusta->authentication  = Crypt::decryptString($apiData->api_authentication);
            $apiJusta->api_address     = Crypt::decryptString($apiData->api_address);
            $apiJusta->start_date      = (\Carbon\Carbon::parse( (\Carbon\Carbon::now() )->addDays(-5) ))->format('Y-m-d');
            $movements = $apiJusta->getSales();
            if($movements->success){
                if(isset($movements->response->data)){
                    foreach($movements->response->data as $movement){
                        if(!CardSaleMovement::checkexist(CardSaleTerminal::returnid($movement->poiId),$movement->id, $movement->nsuAcquirer,$movement->authorizationCode)){
                            $credit_status = 48;
                            if(CardSaleStatus::returnid($movement->status) == 1){
                                $credit_status = 47;
                            }

                            $terminalAccountId = CardSaleTerminal::returnAccountId($movement->poiId);
                            $tax    = 0;
                            $getTax = Account::getTax($terminalAccountId , 14, $apiData->master_id);
                            if($getTax->value > 0){
                                $tax = $getTax->value;
                            } else if($getTax->percentage > 0){
                                if($movement->value > 0){
                                    $tax = round(( ($getTax->percentage/100) * ( (float) str_replace(',','.',str_replace('.','',$movement->liquidValue)))  ),2);
                                }
                            }


                            CardSaleMovement::create([
                                "type_id"       => CardSaleType::returnid($movement->saleMode),
                                "terminal_id"   => CardSaleTerminal::returnid($movement->poiId),
                                "date"          => ((\Carbon\Carbon::createFromFormat('d/m/y H:i', $movement->date ))->format('Y-m-d H:i')),
                                "status_id"     => CardSaleStatus::returnid($movement->status),
                                "value"         => (float) str_replace(',','.',str_replace('.','',$movement->value)),
                                "net_value"     => (float) str_replace(',','.',str_replace('.','',$movement->liquidValue)),
                                "cet"           => (float) str_replace( ',','.',$movement->cetValue),
                                "card_number"   => $movement->maskedCardNumber,
                                "modality_id"   => CardSaleModality::returnid($movement->saleType),
                                "banner_id"     => CardSaleBanner::returnid($movement->flag),
                                "buyer_id"      => CardSaleBuyer::returnid($movement->acquirer),
                                "parc_type_id"  => CardSaleParcType::returnid($movement->interestType),
                                "parcel"        => $movement->installments,
                                "code"          => $movement->id,
                                "nsu"           => $movement->nsuAcquirer,
                                "auth_code"     => $movement->authorizationCode,
                                "response_code" => $movement->acquirerResponseCode,
                                "credit_status" => $credit_status,
                                "credit_value"  => (float) str_replace(',','.',str_replace('.','',$movement->liquidValue)),
                                "account_id"    => $terminalAccountId,
                                "tax_value"     => $tax
                            ]);
                        }
                    }
                }
            }
            //return response()->json($movements);
        }
    }

    public function simulation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [121, 250];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account_id         = null;
        $qtd                = $request->parcel;
        $value              = $request->value;
        $antecipation_fee   = 0; //(3.48/100);
        $administration_fee = 0; //(1.9/100);


        $defaultBannerData = CardSaleBanner::where('id','=',$request->bnr_ir)->first();
        if(isset($defaultBannerData->id)){
            $credit_tax       = $defaultBannerData->credit_tax;
            $credit_tax_2_6   = $defaultBannerData->credit_tax_2_6;
            $credit_tax_7_12  = $defaultBannerData->credit_tax_7_12;
            $debit_tax        = $defaultBannerData->debit_tax;
            $antecipation_tax = $defaultBannerData->tax_antecipation;
        }

        if($account_id != null){
            $defaultAccountBannerData = CardSaleTerminalTax::where('banner_id','=',$request->bnr_ir)->where('account_id','=',$account_id)->first();
            if(isset($defaultAccountBannerData->id)){
                $credit_tax       = $defaultAccountBannerData->credit_tax;
                $credit_tax_2_6   = $defaultAccountBannerData->credit_tax_2_6;
                $credit_tax_7_12  = $defaultAccountBannerData->credit_tax_7_12;
                $debit_tax        = $defaultAccountBannerData->debit_tax;
                $antecipation_tax = $defaultAccountBannerData->tax_antecipation;
            }
        }

        //set antecipation and administration taxes
        if($qtd == 1){
            $administration_fee = $credit_tax/100;
        } else if($qtd >= 2 and $qtd <= 6  ){
            $administration_fee = $credit_tax_2_6/100;
        } else{
            $administration_fee = $credit_tax_7_12/100;
        }
        if($request->antecipation != 0){
            $antecipation_fee = $antecipation_tax/100;
        }


        $administration_discount = $administration_fee * $value;

        $delta         = 0;
        $sum_delta     = 0;
        $sum_factor    = 0;
        $sum_disccount = 0;

        $store_total_transaction  = 0;
        $store_sum_to_receive_value = 0;
        $client_total_transaction = 0;

        $client_interest = [];
        $store_interest  = [];

        for ($i=1; $i<=$qtd; $i++){
            $sum_delta         +=  1-(1/((1+$antecipation_fee)**($i-1/30)));
            $transaction_value =   $value/(1-$administration_fee-$sum_delta/$qtd);
            $parcel_value      =   $transaction_value/$qtd;
        }

        for ($i=1; $i<=$qtd; $i++){
            $delta         +=  1-(1/((1+$antecipation_fee)**($i-1/30)));
            $sum_factor    +=  1/((1+$antecipation_fee)**($i-1/30));
            $sum_disccount +=  ($value/$qtd)*(1 -(1/((1+$antecipation_fee)**(($i*30-1)/30))));

            $store_total_transaction    += ($value/$qtd);
            $store_sum_to_receive_value += ($value/$qtd) - ($administration_discount/$qtd) - ($value/$qtd)*(1 -(1/((1+$antecipation_fee)**(($i*30-1)/30))));
            $client_total_transaction   += ($value/$qtd)/(1-$administration_fee-$sum_delta/$qtd);

            array_push($client_interest, [
                "type"                                  => "Juros Cliente",
                "parcel_number"                         => $i,
                "parcel_division_value"                 => $value/$qtd,
                "parcel_antecipation_fee"               => $antecipation_fee * 100,
                "parcel_administration_fee"             => $administration_fee * 100,
                "parcel_delta_factor"                   => 1-(1/((1+$antecipation_fee)**($i-1/30))),
                "parcel_delta_sum"                      => $delta,
                "parcel_to_receive_value"               => ($value/$qtd)/(1-$administration_fee-$sum_delta/$qtd),
                "parcel_transaction_sum"                => $client_total_transaction
            ]);

            array_push($store_interest, [
                "type"                                  => "Juros Lojista",
                "parcel_number"                         => $i,
                "parcel_division_value"                 => $value/$qtd,
                "parcel_antecipation_fee"               => $antecipation_fee * 100,
                "parcel_antecipation_factor"            => 1/((1+$antecipation_fee)**($i-1/30)),
                "parcel_antecipation_value"             => ($value/$qtd)*(1 -(1/((1+$antecipation_fee)**(($i*30-1)/30)))),
                "parcel_administration_fee"             => $administration_fee * 100,
                "parcel_administration_value"           => $administration_discount/$qtd,
                "parcel_to_receive_value"               => ($value/$qtd) - ($administration_discount/$qtd) - ($value/$qtd)*(1 -(1/((1+$antecipation_fee)**(($i*30-1)/30)))),
                "parcel_transaction_sum"                => $store_total_transaction,
            ]);
        }

        $client_statistic = [
                "transaction_value"  => $transaction_value,
                "to_receive_value"   => $value,
                "antecipation_fee"   => $antecipation_fee*100,
                "administration_fee" => $administration_fee*100,
                "cet"                => (1-($value/$transaction_value))*100,
                "parcel_qtd"         => $qtd,
                "parcel_value"       => $parcel_value
            ];

        $store_statistic = [
                "transaction_value"  => $value,
                "to_receive_value"   => $store_sum_to_receive_value,
                "antecipation_fee"   => $antecipation_fee*100,
                "administration_fee" => $administration_fee*100,
                "cet"                => (1-($store_sum_to_receive_value/$value))*100,
                "parcel_qtd"         => $qtd,
                "parcel_value"       => $value/$qtd
            ];

        return response()->json([
            "success"          => "Simulação realizada com sucesso",
            "client"           => $client_interest,
            "store"            => $store_interest,
            "client_statistic" => $client_statistic,
            "store_statistic"  => $store_statistic
        ]);
    }

    protected function resume(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_sale_movement           = new CardSaleMovement();
        $card_sale_movement->date     = $request->date;
        return response()->json( $card_sale_movement->resumeCardSaleMovement()[0]);
    }

    protected function getDetailedPDF(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $cardSaleMovement                                   = new CardSaleMovement();
        $cardSaleMovement->master_id                        = $checkAccount->master_id;
        $cardSaleMovement->account_id                       = $checkAccount->account_id;
        $cardSaleMovement->onlyActive                       = $request->onlyActive;
        $cardSaleMovement->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $cardSaleMovement->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $cardSaleMovement->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $items = [];
        foreach($cardSaleMovement->cardSaleMovementDetailed() as $movementData){
            array_push($items, (object) [
                'occurrence_date'           =>  $movementData->occurrence_date ? \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y h:m:s') : null,
                'type_description'          =>  $movementData->type_description,
                'modality_description'      =>  $movementData->modality_description,
                'status_description'        =>  $movementData->status_description,
                'credit_status_description' =>  $movementData->credit_status_description,
                'rgstr_dtl_name'            =>  $movementData->rgstr_dtl_name,
                'rgstr_cpf_cnpj'            =>  Facilites::mask_cpf_cnpj($movementData->rgstr_cpf_cnpj),
                'account_number'            =>  $movementData->account_number,
                'terminal'                  =>  $movementData->terminal,
                'card_number'               =>  $movementData->card_number,
                'banner_description'        =>  $movementData->banner_description,
                'parcel'                    =>  $movementData->parcel,
                'value'                     =>  $movementData->value,
                'net_value'                 =>  $movementData->net_value,
                'cet'                       =>  $movementData->cet,
                'movement_date'             =>  $movementData->movement_date ? \Carbon\Carbon::parse($movementData->movement_date)->format('d/m/Y h:m:s') : null,
                'credit_date'               =>  $movementData->credit_date ? \Carbon\Carbon::parse($movementData->credit_date)->format('d/m/Y h:m:s') : null,
                'credit_value'              =>  $movementData->credit_value,
                'tax_value'                 =>  $movementData->tax_value,
                'nsu'                       =>  $movementData->nsu,
                'auth_code'                 =>  $movementData->auth_code,
                'code'                      =>  $movementData->code,
                'response_code'             =>  $movementData->response_code,
            ]);
        }
        $data = (object) array(
            "movement_data" => $items
        );
        $file_name = "Movimentacao_Maquininha.pdf";
        $pdf       = PDF::loadView('reports/movement_card_sale_machine', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }
}
