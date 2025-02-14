<?php

namespace App\Http\Controllers;

use App\Models\AntecipationBatch;
use App\Models\AntecipationReview;
use App\Models\SimpleCharge;
use App\Models\SimpleChargeHistory;
use App\Models\ApiConfig;
use App\Models\Charge;
use App\Models\ChargeHistory;
use App\Models\AntecipationCharge;
use App\Models\AntecipationChargeHistory;
use App\Models\Account;
use App\Models\AccountMovement;
use App\Models\Remittance;
use App\Models\AntecipationChrgMvmnt;
use App\Models\Master;
use App\Models\ChargeConfig;
use App\Models\ChargeInstruction;
use App\Models\User;
use App\Libraries\SimpleCNAB;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\sendMail;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

use App\Models\ZipCodeAddress;
use App\Models\ZipCodeCity;
use App\Models\ZipCodeDistrict;
use App\Libraries\ViaCep;

use App\Models\PayerAddress;


use ZipArchive;
use File;

class AntecipationBatchController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [104];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationBatch = new AntecipationBatch();
        $antecipationBatch->master_id            = $checkAccount->master_id;
        $antecipationBatch->id                   = $request->id;
        $antecipationBatch->unique_id            = $request->unique_id;
        $antecipationBatch->register_master_id   = $request->register_master_id;
        $antecipationBatch->account_id           = $request->account_id;
        $antecipationBatch->financial_agent_id   = $request->financial_agent_id;
        $antecipationBatch->number               = $request->number;
        $antecipationBatch->status_id_in         = $request->status_id_in;
        $antecipationBatch->onlyActive           = $request->onlyActive;
        $antecipationBatch->onlyNotFinish        = $request->onlyNotFinish;
        $antecipationBatch->onlyDeleted          = $request->onlyDeleted;
        $antecipationBatch->batch_date           = $request->batch_date;
        $antecipationBatch->batch_date_start     = $request->batch_date_start;
        $antecipationBatch->batch_date_end       = $request->batch_date_end;
        $antecipationBatch->created_at           = $request->created_at;
        $antecipationBatch->created_at_start     = $request->created_at_start;
        $antecipationBatch->created_at_end       = $request->created_at_end;
        $antecipationBatch->approved_at          = $request->approved_at;
        $antecipationBatch->approved_at_start    = $request->approved_at_start;
        $antecipationBatch->approved_at_end      = $request->approved_at_end;
        $antecipationBatch->disapproved_at       = $request->disapproved_at;
        $antecipationBatch->disapproved_at_start = $request->disapproved_at_start;
        $antecipationBatch->disapproved_at_end   = $request->disapproved_at_end;
        return response()->json($antecipationBatch->get());
    }

    protected function liberation(Request $request)
    {
       // ----------------- Check Account Verification ----------------- //
       $accountCheckService           = new AccountRelationshipCheckService();
       $accountCheckService->request  = $request;
       $accountCheckService->permission_id = [104];
       $checkAccount                  = $accountCheckService->checkAccount();
       if(!$checkAccount->success){
           return response()->json(array("error" => $checkAccount->message));
       }
       // -------------- Finish Check Account Verification -------------- //


        if($request->liberation_date == '' or $request->liberation_date == null) {
            return response()->json(array("error" => "Informe a data de liberação."));
        }

        if($request->antecipation_charge_liquidation_account_id == '' or $request->antecipation_charge_liquidation_account_id == null) {
            return response()->json(array("error" => "Informe a conta de destino para liquidação."));
        }

        $checkLiquidationAccount = Account::where('id', '=', $request->antecipation_charge_liquidation_account_id)->where('is_antecipation_charge_liquidation', '=', 1)->count();
        if ($checkLiquidationAccount < 1) {
            return response()->json(array("error" => "Conta de destino para liquidação não habilitada, entre em contato com o suporte."));
        }


        if(AntecipationBatch::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->count() > 0){
            $antecipationBatch  = AntecipationBatch::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->first();
            if($antecipationBatch->status_id == 9){
                return response()->json(array("error" => "Lote de antecipação já liberado"));
            }

            $error_array = [];
            $error_count = 0;

            if(AntecipationReview::where('batch_id','=',$antecipationBatch->id)->count() > 0){

                if(AntecipationReview::where('batch_id','=',$antecipationBatch->id)->whereNotIn('status_id',[9,11])->count() > 0){
                    return response()->json(array("error" => "Existem títulos aguardando análise, por favor recuse ou aprove antes de realizar a liberação"));
                }

                $titlesAntecipation = AntecipationReview::where('batch_id','=',$antecipationBatch->id)->get();

                $antecipationBatch->approved_value = (AntecipationReview::sumApprovedValue($antecipationBatch->id))->value;

                if( $antecipationBatch->approved_value > 0){
                    $remittance = Remittance::create([
                        'master_id'          => $checkAccount->master_id,
                        'account_id'         => $antecipationBatch->account_id,
                        'remittance_type_id' => 2,
                        'number'             => Remittance::getNextRemittanceNumber($checkAccount->master_id),
                        'created_at'         => \Carbon\Carbon::now()
                    ]);
                }
                $today = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                foreach($titlesAntecipation as $title){
                    if($title->status_id == 9){ //approved
                        if($title->origin_type_id == 1){ //was on add charge
                            
                            if( (\Carbon\Carbon::parse( $title->due_date ))->format('Y-m-d') >= $today ){
                                

                                if($antecipation_charge = AntecipationCharge::create([
                                    'review_id'                  => $title->id,
                                    'master_id'                  => $title->master_id,
                                    'register_master_id'         => $title->register_master_id,
                                    'account_id'                 => $title->account_id,
                                    'payer_detail_id'            => $title->payer_detail_id,
                                    'issue_date'                 => $title->issue_date,
                                    'document'                   => $title->document,
                                    'due_date'                   => $title->due_date,
                                    'value'                      => $title->value,
                                    'status_id'                  => 4,
                                    'participant_control_number' => $title->participant_control_number,
                                    'api_id'                     => 10,
                                    'liquidation_account_id'     => $request->antecipation_charge_liquidation_account_id,
                                    'created_at'                 => \Carbon\Carbon::now()
                                ])){
                                    $tax = 0;
                                    if( $tax > 0 ){
                                        /*AccountMovement::create([
                                            'account_id'       => $antecipation_charge->account_id,
                                            'master_id'        => $checkAccount->master_id,
                                            'origin_id'        => $antecipation_charge->id,
                                            'mvmnt_type_id'    => 5,
                                            'date'             => \Carbon\Carbon::now(),
                                            'value'            => ($tax * -1),
                                            'balance'          => ($accountBalance - $tax),
                                            'master_balance'   => ($accountMasterBalance - $tax),
                                            'description'      => 'Tarifa de Emissão de Boleto | Cobrança Caucionada | '.$antecipation_charge->document,
                                            'created_at'       => \Carbon\Carbon::now(),
                                        ]);*/
                                    }
                                }
                                $addTitle = $this->addOnBank($antecipation_charge->id);
                                switch($addTitle->status){
                                    case 0:
                                        if($title->origin_id != null){
                                            $this->updateCharge($title->origin_id, $remittance->id);
                                        }
                                        AntecipationChargeHistory::create([
                                            'antcptn_chrg_id'     => $antecipation_charge->id,
                                            'master_id'           => $antecipation_charge->master_id,
                                            'register_master_id'  => $antecipation_charge->register_master_id,
                                            'account_id'          => $antecipation_charge->account_id,
                                            'description'         => 'Título antecipado',
                                            'created_at'          => \Carbon\Carbon::now(),
                                        ]);
                                        $this->createChargeMovement($antecipation_charge->id, 1);
                                        $our_numbers[] = $addTitle->our_number;
                                    break;
                                    case 1:
                                        array_push($error_array,"Ocorreu uma falha ao incluir o título ".$title->document.' | '.$addTitle->message);
                                        $this->deleteSimpleCharge($antecipation_charge->id);
                                        if( $tax > 0 ){
                                            $this->reverseTax($antecipation_charge->id, $antecipation_charge->account_id, $checkAccount->master_id, $antecipation_charge->document, $tax);
                                        }
                                        $error_count++;
                                    break;
                                    case 2:
                                        array_push($error_array,"Ocorreu uma falha ao incluir o título ".$title->document.' | '.$addTitle->message.' | '.$addTitle->errorData);
                                        $this->deleteSimpleCharge($antecipation_charge->id);
                                        if( $tax > 0 ){
                                            $this->reverseTax($antecipation_charge->id, $antecipation_charge->account_id, $checkAccount->master_id, $antecipation_charge->document, $tax);
                                        }
                                        $error_count++;
                                    break;
                                    case 3:
                                        array_push($error_array,"Ocorreu uma falha ao incluir o título ".$title->document.' | '.$addTitle->message.' | '.$addTitle->errorData);
                                        $this->deleteSimpleCharge($antecipation_charge->id);
                                        if( $tax > 0 ){
                                            $this->reverseTax($antecipation_charge->id, $antecipation_charge->account_id, $checkAccount->master_id, $antecipation_charge->document, $tax);
                                        }
                                        $error_count++;
                                    break; 
                                }
                            } else {
                                array_push($error_array,"Ocorreu uma falha ao incluir o título ".$title->document." Não é possível registrar título vencido");
                                $update_charge            = Charge::where('id','=',$title->id)->first();
                                $update_charge->status_id = 13;
                                $update_charge->save();
                                $error_count++;
                            }
                        }
                        if($title->origin_type_id == 2){ //was on simple charge
                            $antecipationCharge = AntecipationCharge::create([
                                'unique_id'                  => md5($antecipationBatch->id.$title->id.date('Ymd').time()),
                                'review_id'                  => $title->id,
                                'master_id'                  => $title->master_id,
                                'register_master_id'         => $title->register_master_id,
                                'account_id'                 => $title->account_id,
                                'payer_detail_id'            => $title->payer_detail_id,
                                'issue_date'                 => $title->issue_date,
                                'document'                   => $title->document,
                                'due_date'                   => $title->due_date,
                                'value'                      => $title->value,
                                'status_id'                  => 4,
                                'bank_code'                  => $title->bank_code,
                                'agency'                     => $title->agency,
                                'account'                    => $title->account,
                                'wallet_number'              => $title->wallet_number,
                                'our_number'                 => $title->our_number,
                                'bar_code'                   => $title->bar_code,
                                'digitable_line'             => $title->digitable_line,
                                'control_number'             => $title->control_number,
                                'dda'                        => $title->dda,
                                'participant_control_number' => $title->participant_control_number,
                                'api_id'                     => $title->api_id,
                                'liquidation_account_id'     => $request->antecipation_charge_liquidation_account_id,
                                'created_at'                 => \Carbon\Carbon::now()
                            ]);
                            AntecipationChargeHistory::create([
                                'antcptn_chrg_id'     => $antecipationCharge->id,
                                'master_id'           => $antecipationCharge->master_id,
                                'register_master_id'  => $antecipationCharge->register_master_id,
                                'account_id'          => $antecipationCharge->account_id,
                                'description'         => 'Título antecipado da carteira simples',
                                'created_at'          => \Carbon\Carbon::now(),
                            ]);
                            $simpleChargeTitle = SimpleCharge::where('id','=',$title->origin_id)->first();
                            $simpleChargeTitle->status_id          = 41;
                            $simpleChargeTitle->antcptn_status_id  = 41;
                            $simpleChargeTitle->antecipation_date  = \Carbon\Carbon::now();
                            $simpleChargeTitle->antecipation_value = $simpleChargeTitle->value;
                            $simpleChargeTitle->save();
                            SimpleChargeHistory::create([
                                'simple_charge_id'    => $simpleChargeTitle->id,
                                'master_id'           => $simpleChargeTitle->master_id,
                                'register_master_id'  => $simpleChargeTitle->register_master_id,
                                'account_id'          => $simpleChargeTitle->account_id,
                                'description'         => 'Título antecipado',
                                'created_at'          => \Carbon\Carbon::now(),
                            ]);
                            $this->createChargeMovement($antecipationCharge->id, 1);
                        }
                    } else {
                        if($title->origin_type_id == 1){
                            $chargeTitle = Charge::where('id','=',$title->origin_id)->first();
                            $chargeTitle->status_id = 40;
                            $chargeTitle->save();
                            ChargeHistory::create([
                                'charge_id'           => $chargeTitle->id,
                                'master_id'           => $chargeTitle->master_id,
                                'register_master_id'  => $chargeTitle->register_master_id,
                                'account_id'          => $chargeTitle->account_id,
                                'description'         => 'Título recusado para antecipação',
                                'created_at'          => \Carbon\Carbon::now(),
                            ]);
                        }
                        if($title->origin_type_id == 2){
                            $simpleChargeTitle = SimpleCharge::where('id','=',$title->origin_id)->first();
                            $simpleChargeTitle->antcptn_status_id = 40;
                            $simpleChargeTitle->save();
                            SimpleChargeHistory::create([
                                'simple_charge_id'    => $simpleChargeTitle->id,
                                'master_id'           => $simpleChargeTitle->master_id,
                                'register_master_id'  => $simpleChargeTitle->register_master_id,
                                'account_id'          => $simpleChargeTitle->account_id,
                                'description'         => 'Título recusado para antecipação',
                                'created_at'          => \Carbon\Carbon::now(),
                            ]);
                        }
                    }
                }
            }
            $antecipationBatch->approved_at = $request->liberation_date;
            $antecipationBatch->status_id = 9;
            $antecipationBatch->liquidation_account_id = $request->antecipation_charge_liquidation_account_id;
            if($antecipationBatch->save()){
                return response()->json(array("success" => "Lote de antecipação liberado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao liberar o lote de antecipação, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Lote de antecipação não localizado"));
        }
    }

    protected function addOnBank($id)
    {
        $antecipationCharge                               = new AntecipationCharge();
        $antecipationCharge->id                           = $id;
        $billetData                                       = $antecipationCharge->getAntecipationCharge()[0];

        $apiConfig                                        = new ApiConfig();
        $apiConfig->master_id                             = $billetData->master_id;
        $apiConfig->api_id                                = 10;
        $apiConfig->onlyActive                            = 1;
        $apiData                                          = $apiConfig->getApiConfig()[0];

        $mensagem1  = '';
        $mensagem2  = '';
        $mensagem3  = '';
        $mensagem4  = '';
        $tx_mora    = '';
        $valor_mora = '';
        $tx_multa   = '';
        $dt_base    = '';
        $data_mora  = '';
        $data_multa = '';
        $cod_multa  = '0';
        $cod_mora   = '3';

        $master = Master::where('id','=',$billetData->master_id)->first();
        if($master->antcptn_accnt_id != null){
            if(ChargeConfig::where('account_id','=',$master->antcptn_accnt_id)->count() > 0){
                $chargeConfig             = new ChargeConfig();
                $chargeConfig->master_id  = $billetData->master_id;
                $chargeConfig->account_id = $master->antcptn_accnt_id;
                $config                   = $chargeConfig->getAccountChargeConfig()[0];
                $mensagem1                = $config->message1;
                $mensagem2                = $config->message2;
                $mensagem3                = $config->message3;
                $tx_mora                  = $config->interest;
                //$valor_mora               = ( ($config->interest/100) * $billetData->value);
                $tx_multa                 = $config->fine;
                $dt_base                  = $billetData->due_date;
                if($config->interest > 0){
                    $cod_mora                 = 2;
                    $tx_mora                  = $config->interest;
                    $data_mora                = $dt_base;
                }
                if($config->fine > 0){
                    $cod_multa                = 2;
                    $tx_multa                 = $config->fine;
                    $data_multa               = $dt_base;
                }
            }
        }


        $payerDistrict = $billetData->payer_address_district;
        $payerCity = $billetData->payer_address_city;
        $payerState = $billetData->beneficiary_address_state_short_description;

        if( $payerCity == '' or $payerCity == null or $payerState == '' or $payerState == null ) {
            //atualizar CEP
            $cepData = ZipCodeAddress::returnZipCodeData($billetData->payer_address_zip_code);

            if($cepData->zip_code == ''){
                $viaCepAPI = new ViaCep();
                $viaCepAPI->cep = $billetData->payer_address_zip_code;
                $cep = $viaCepAPI->checkCep();
                if($cep != null){
                    if(isset($cep->cep)){
                        $cepData = (object) [
                            'address'     => $cep->logradouro,
                            'district'    => $cep->bairro,
                            'city'        => $cep->localidade,
                            'state_id'    => Facilites::convertStateToInt($cep->uf),
                            'short_state' => $cep->uf,
                            'ibge_code'   => $cep->ibge,
                            'gia_code'    => $cep->gia,
                            'cep'         => preg_replace("/[^0-9]/",'',$cep->cep)
                        ];
                        //check to register new city in zip code cities table
                        if(ZipCodeCity::where('city','=',$cepData->city)->where('state_id','=',$cepData->state_id)->count() == 0){
                            $zipCodeCity = ZipCodeCity::create([
                                'code'       => (ZipCodeCity::getNextCityCode())->code,
                                'city'       => $cepData->city,
                                'state_id'   => $cepData->state_id,
                                'zip_code'   => $cepData->cep,
                                'ibge_code'  => $cepData->ibge_code,
                                'created_at' => \Carbon\Carbon::now()
                            ]);
                        } else {
                            $zipCodeCity = ZipCodeCity::where('city','=',$cepData->city)->where('state_id','=',$cepData->state_id)->first();
                        }
                        //check to register new district in zip code district table
                        if(ZipCodeDistrict::where('city_code','=',$zipCodeCity->code)->where('district','=',$cepData->district)->count() == 0){
                            $zipCodeDistrict = ZipCodeDistrict::create([
                                'city_code'     => $zipCodeCity->code,
                                'district_code' => (ZipCodeDistrict::getNextDistrictCode())->district_code,
                                'district'      => $cepData->district,
                                'created_at'    => \Carbon\Carbon::now()
                            ]);
                        } else {
                            $zipCodeDistrict = ZipCodeDistrict::where('city_code','=',$zipCodeCity->code)->where('district','=',$cepData->district)->first();
                        }
                        //register new address
                        ZipCodeAddress::create([
                            'city_code'     => $zipCodeCity->code,
                            'district_code' => $zipCodeDistrict->district_code,
                            'address'       => $cepData->address,
                            'zip_code'      => $cepData->cep,
                            'ibge_code'     => $cepData->ibge_code,
                            'gia_code'      => $cepData->gia_code,
                            'created_at'    => \Carbon\Carbon::now()
                        ]);

                        $cepData = ZipCodeAddress::returnZipCodeData($billetData->payer_address_zip_code);

                        $payerAddress = PayerAddress::where('id', '=', $billetData->payer_address_id)->first();
                        
                        if ($payerDistrict == null or $payerDistrict == '') {
                            if ( isset( $cepData->district ) ) {
                                $payerDistrict = $cepData->district;
                                $payerAddress->district = $cepData->district;
                            }
                        }

                        if ($payerCity == null or $payerCity == '') {
                            if ( isset($cepData->city) ) {
                                $payerCity = $cepData->city;
                                $payerAddress->city = $cepData->city;
                            }
                        }

                        if ($payerState == null or $payerState == '') {
                            if ( isset($cepData->short_state) ) {
                                $payerState = $cepData->short_state;
                                $payerAddress->state_id = $cepData->state_id;
                            }
                        }

                        $payerAddress->save();                        
                    }
                }
            }


        }

    
        $apiRendimento                                    = new ApiBancoRendimento();
        $apiRendimento->id_cliente                        = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                      = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                      = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                      = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                           = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                    = Crypt::decryptString($apiData->api_account);

        $apiRendimento->tit_codBanco                      = "0000";
        $apiRendimento->tit_numero_carteira               = "000";
        $apiRendimento->tit_nosso_numero                  = "";
        $apiRendimento->tit_cod_modal_bancos              = "";
        $apiRendimento->tit_nosso_numero_bancos           = "";
        $apiRendimento->tit_cod_especie_doc               = "02";
        $apiRendimento->tit_vlr_nominal                   = $billetData->value;
        $apiRendimento->tit_vlr_abatimento                = "";
        $apiRendimento->tit_data_emissao                  = $billetData->issue_date;
        $apiRendimento->tit_data_vencimento               = $billetData->due_date;
        $apiRendimento->tit_seu_numero                    = $billetData->document;
        $apiRendimento->tit_aceite                        = "";
        $apiRendimento->tit_cnpj_cpf                      = $billetData->payer_cpf_cnpj;
        $apiRendimento->tit_nome                          = $billetData->payer_name;
        $apiRendimento->tit_endereco                      = $billetData->payer_address_description;
        $apiRendimento->tit_bairro                        = $billetData->payer_address_district;
        $apiRendimento->tit_cidade                        = $payerCity;
        $apiRendimento->tit_uf                            = $payerState;
        $apiRendimento->tit_cep                           = $billetData->payer_address_zip_code;
        $apiRendimento->tit_email                         = "";//$billetData->payer_email;
        $apiRendimento->tit_telefone                      = $billetData->payer_phone;
        $apiRendimento->tit_campo_livre                   = "";
        $apiRendimento->tit_cnpj_cpf_sacador              = $billetData->beneficiary_cpf_cnpj;
        $apiRendimento->tit_nome_sacador                  = $billetData->beneficiary_name;
        $apiRendimento->tit_endereco_sacador              = $billetData->beneficiary_address_description;
        $apiRendimento->tit_bairro_sacador                = $billetData->beneficiary_address_district;
        $apiRendimento->tit_cep_sacador                   = $billetData->beneficiary_address_zip_code;
        $apiRendimento->tit_cidade_sacador                = $billetData->beneficiary_address_city;
        $apiRendimento->tit_uf_sacador                    = $billetData->beneficiary_address_state_short_description;
        $apiRendimento->tit_mensagem_1                    = $mensagem1;
        $apiRendimento->tit_mensagem_2                    = $mensagem2;
        $apiRendimento->tit_mensagem_3                    = $mensagem3;
        $apiRendimento->tit_mensagem_4                    = $mensagem4;
        $apiRendimento->tit_mensagem_5                    = "";
        $apiRendimento->tit_cod_desconto_1                = 0;
        $apiRendimento->tit_vlr_desconto_1                = "";
        $apiRendimento->tit_tx_desconto_1                 = "";
        $apiRendimento->tit_data_desconto_1               = "";
        $apiRendimento->tit_cod_desconto_2                = 0;
        $apiRendimento->tit_vlr_desconto_2                = "";
        $apiRendimento->tit_tx_desconto_2                 = "";
        $apiRendimento->tit_data_desconto_2               = "";
        $apiRendimento->tit_cod_desconto_3                = 0;
        $apiRendimento->tit_vlr_desconto_3                = "";
        $apiRendimento->tit_tx_desconto_3                 = "";
        $apiRendimento->tit_data_desconto_3               = "";
        $apiRendimento->tit_cod_multa                     = $cod_multa;
        $apiRendimento->tit_data_multa                    = $data_multa;
        $apiRendimento->tit_tx_multa                      = $tx_multa;
        $apiRendimento->tit_vlr_multa                     = "";
        $apiRendimento->tit_cod_mora                      = $cod_mora;
        $apiRendimento->tit_data_mora                     = $data_mora;
        $apiRendimento->tit_tx_mora                       = $tx_mora;
        $apiRendimento->tit_vlr_mora                      = $valor_mora;
        $apiRendimento->tit_possui_agenda                 = "NAO";
        $apiRendimento->tit_tipo_agendamento              = "";
        $apiRendimento->tit_criterio_dias                 = "";
        $apiRendimento->tit_num_dias_agenda               = "";
        $apiRendimento->tit_cod_indice                    = "";
        $apiRendimento->tit_ind_pagto_parcial             = "";
        $apiRendimento->tit_qtd_pagtos_parciais           = 0;
        $apiRendimento->tit_tipo_vlr_perc_minimo          = "";
        $apiRendimento->tit_vlr_perc_minimo               = "";
        $apiRendimento->tit_tipo_vlr_perc_maximo          = "";
        $apiRendimento->tit_vlr_perc_maximo               = "";
        $apiRendimento->tit_tipo_aut_rec_divergente       = "";
        $apiRendimento->tit_cod_coligada_conta_creditar   = "";
        $apiRendimento->tit_cod_agencia_conta_creditar    = "";
        $apiRendimento->tit_num_conta_creditar            = "";
        $titleInclude = $apiRendimento->tituloIncluir();

        if(isset($titleInclude->body->value->nossoNumero)){
            if( $titleInclude->body->value->nossoNumero != "" ){
                $updateBillet                 = AntecipationCharge::where('id','=',$id)->first();
                $updateBillet->bank_code      = $titleInclude->body->value->codBanco;
                $updateBillet->agency         = $titleInclude->body->value->codAgencia;
                $updateBillet->account        = $apiRendimento->conta_corrente;
                $updateBillet->wallet_number  = $titleInclude->body->value->numCarteira;
                $updateBillet->our_number     = $titleInclude->body->value->nossoNumero;
                $updateBillet->bar_code       = $titleInclude->body->value->codBarras;
                $updateBillet->digitable_line = $titleInclude->body->value->linhaDigitavel;
                $updateBillet->control_number = $titleInclude->body->value->numControleLegado;
                if($titleInclude->body->value->tituloIncluidoDDA == "SIM"){
                    $updateBillet->dda            = 1;
                } else {
                    $updateBillet->dda            = 0;
                }
                if($updateBillet->save()){
                    return (object) array("status" => 0, "message" => "Título incluido com sucesso", "our_number" => $updateBillet->our_number);
                } else {
                    return (object) array("status" => 1, "message" => "Ocorreu uma falha ao atualizar os dados do título");
                }
            } else {
                $errorMessage = null;
                if(isset($titleInclude->body->erroMessage->errors)){
                    $errorMessage = implode( ", ", $titleInclude->body->erroMessage->errors);
                }
                return (object) array("status" => 2, "message" => "Ocorreu um erro ao registrar o título, por favor tente novamente dentro de alguns minutos", "errorData" => $errorMessage);
            }
        } else {
            $errorMessage = 'Ocorreu uma falha ao registrar o título';
            if(isset($titleInclude->body->erroMessage->errors)){

                if ( isset($titleInclude->body->value->msgErro) ) {
                    $errorMessage = $titleInclude->body->value->msgErro;
                }
                
            }
            return (object) array("status" => 3, "message" => "Ocorreu um erro ao registrar o boleto, por favor tente novamente dentro de alguns minutos", "errorData" => $errorMessage);
        }
    }

    protected function updateCharge($charge_id, $remittance_id)
    {
        $charge                = Charge::where('id','=',$charge_id)->first();
        $charge->remittance_id = $remittance_id;
        $charge->status_id     = 41;
        $charge->save();
    }

    protected function deleteAntecipationCharge($id)
    {
        $antecipationChargeTitle             = AntecipationCharge::where('id','=',$id)->first();
        $antecipationChargeTitle->status_id  = 38;
        $antecipationChargeTitle->deleted_at = \Carbon\Carbon::now();
        $antecipationChargeTitle->save();
    }

    public function createChargeMovement($chargeId, $movemntType)
    {
        if(AntecipationCharge::where('id','=',$chargeId)->count() > 0){
            $antecipationCharge          =  AntecipationCharge::where('id','=',$chargeId)->first();
            
            $chargeMovement              = new AntecipationChrgMvmnt();
            $chargeMovement->account_id  = $antecipationCharge->account_id;
            $chargeMovement->master_id   = $antecipationCharge->master_id;
            $chargeMovement->start_date  = \Carbon\Carbon::now();
            $accountBalance              = 0;
            $accountMasterBalance        = 0;
            
            if(isset( $chargeMovement->getAccountBalance()->balance )){
                $accountBalance = $chargeMovement->getAccountBalance()->balance;
            }
            if(isset( $chargeMovement->getMasterAccountBalance()->master_balance )){
                $accountMasterBalance = $chargeMovement->getMasterAccountBalance()->master_balance;
            }

            $value = $antecipationCharge->value;
            if($movemntType != 1){
                $value = $value * -1;
            }

            if(AntecipationChrgMvmnt::create([
                'account_id'     => $antecipationCharge->account_id,
                'master_id'      => $antecipationCharge->master_id,
                'charge_id'      => $antecipationCharge->id,
                'chrg_mvnt_id'   => $movemntType,
                'document'       => $antecipationCharge->document,
                'date'           => \Carbon\Carbon::now(),
                'value'          => $value,
                'balance'        => $accountBalance + $value,
                'master_balance' => $accountMasterBalance + $value,
                'created_at'     => \Carbon\Carbon::now()
            ])){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    protected function conciliation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [104];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->fileName != ''){
            $fileName =  strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName);
            if(Storage::disk('charge_upload')->put( $fileName, base64_decode($request->file64))){
                $path =  Storage::disk('charge_upload')->path($fileName);
                switch(\File::extension($path)){
                    case 'rem':
                        return $this->updateReviewByCNAB($path, $request->fileName, $checkAccount->master_id, $request->header('registerId'));
                    break;
                    case 'txt':
                        return $this->updateReviewByCNAB($path, $request->fileName, $checkAccount->master_id, $request->header('registerId'));
                    break;
                    default:
                        return response()->json(array("error" => "Formato inválido para o arquivo ".$request->fileName ));
                        //delete file
                    break;
                }
            } else {
                return response()->json(array("error" => "Não foi possível fazer o upload do arquivo ".$request->fileName));
            }
        } else {
            return response()->json(array("error" => "Não foi possível fazer o upload do arquivo"));
        }
    }

    protected function updateReviewByCNAB($path, $fileName, $masterId, $registerMasterId)
    {
        $errors = [];
        $simpleCNAB             = new SimpleCNAB();
        $simpleCNAB->pathFile   = $path;
        $simpleCNAB->optionType = 'readRemittance';
        $cnabData               = (object) json_decode($simpleCNAB->getCNABFile());
        if($cnabData->success){
            foreach($cnabData->remittanceData->titles as $title){
                if($title->identificacaoOcorrencia == '01'){
                    if($title->identificacaoTituloBanco == '0'){
                        $idTitBanco = null;
                    } else {
                        $idTitBanco = $title->identificacaoTituloBanco;
                    }
                    if( AntecipationReview::countReviewConciliation($masterId, $title->numeroDocumento, null, $title->numeroInscricaoPagador) > 0  ){
                        $antecipationReview = AntecipationReview::where('document','=',$title->numeroDocumento)->whereIn('status_id',[6,39])->whereNull('deleted_at')->first();
                        if( (AntecipationBatch::where('id','=', $antecipationReview->batch_id)->first())->status_id == 9 ){
                            array_push($errors, 'Não foi possível conciliar o título '.$title->numeroDocumento.', título consta em lote liberado');
                        } else {
                            $antecipationReview->status_id                  = 9;
                            $antecipationReview->participant_control_number = $title->numeroControleParticipante;
                            $antecipationReview->save();
                        }
                    } else {
                        array_push($errors, 'Não foi possível conciliar o título '.$title->numeroDocumento.', título não localizado nos lotes em aberto');
                    }
                }
            }
            if(sizeof($errors) > 0){
                return response()->json(array("error" => $errors));
            } else {
                return response()->json(array("success" => "Títulos do arquivo ".$fileName." processados com sucesso"));
            }
        } else {
            return response()->json(array("error" => $cnabData->error));
        }
    }
}
