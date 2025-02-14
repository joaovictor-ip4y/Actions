<?php

namespace App\Http\Controllers;

use App\Models\AccntAddMoneyBankCharging;
use App\Models\ApiConfig;
use App\Models\AccountMovement;
use App\Models\ChargeInstruction;
use App\Models\Account;
use App\Models\Master;
use App\Models\SystemFunctionMaster;
use App\Libraries\Facilites;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\BilletGenerator;
use App\Libraries\QrCodeGenerator\QrCodeGenerator;
use App\Services\BilletLiquidation\AddMoneyBilletLiquidationService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PDF;
use App\Services\Account\BalanceService;
use App\Classes\Account\AccountMovementFutureClass;

class AccntAddMoneyBankChargingController extends Controller
{
    public function checkServiceAvailable(Request $request)
    {
        if( (SystemFunctionMaster::where('system_function_id','=',2)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível gerar boletos para depósito."));
        } else {
            return response()->json(array("success" => ""));
        }
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $addMoney = new AccntAddMoneyBankCharging();
        $addMoney->onlyRegistred = $request->onlyRegistred;
        $addMoney->onlyActive    = $request->onlyActive;
        $addMoney->onlyNotPay    = 1;
        $addMoney->account_id    = $checkAccount->account_id;
        $addMoney->master_id     = $checkAccount->master_id;
        return response()->json($addMoney->getAccntAddMoneyBankCharging());
    }

    protected function getAnalitic(Request $request)
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

        $addMoney             = new AccntAddMoneyBankCharging();
        $addMoney->master_id  = $checkAccount->master_id;
        $addMoney->account_id = $checkAccount->account_id;

        //period created
        if($request->created_at_start != ''){
            $addMoney->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $addMoney->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->created_at_end != ''){
            $addMoney->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $addMoney->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $period_created                 = $addMoney->addMoneyChargeAnalitic();
        $addMoney->created_at_start     = null;
        $addMoney->created_at_end       = null;
        //----

        //period liquidated
        if($request->payment_date_start != ''){
            $addMoney->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $addMoney->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $addMoney->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $addMoney->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $period_liquidated                = $addMoney->addMoneyChargeAnalitic();
        $addMoney->payment_date_start     = null;
        $addMoney->payment_date_end       = null;
        //----

        //period down
        if($request->payment_down_date_start != ''){
            $addMoney->payment_down_date_start = $request->payment_down_date_start." 00:00:00.000";
        } else {
            $addMoney->payment_down_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_down_date_end != ''){
            $addMoney->payment_down_date_end = $request->payment_down_date_end." 23:59:59.998";
        } else {
            $addMoney->payment_down_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $period_down                           = $addMoney->addMoneyChargeAnalitic();
        $addMoney->payment_down_date_start     = null;
        $addMoney->payment_down_date_end       = null;
        //-----

        return response()->json(array(
            'period_created'    => $period_created,
            'period_liquidated' => $period_liquidated,
            'period_down'       => $period_down
        ));
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

        $addMoney                                   = new AccntAddMoneyBankCharging();
        $addMoney->master_id                        = $checkAccount->master_id;
        $addMoney->account_id                       = $checkAccount->account_id;
        $addMoney->status_id                        = $request->status_id;
        $addMoney->onlyActive                       = $request->onlyActive;
        $addMoney->type_id                          = $request->type_id;
        $addMoney->manager_id = $request->manager_id;

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
        return response()->json( $addMoney->addMoneyChargeDetailed() );
    }

    protected function downloadBankCharge(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( ! $title = AccntAddMoneyBankCharging::where('our_number','=',$request->our_number)->where('account_id','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->whereNull('payment_date')->whereNull('down_date')->first() ) {
            return response()->json(array("error" => "Boleto não localizado, pago ou baixado, por favor verifique os dados informados, em caso de dúvidas entre em contato com o suporte"));
        }

        if( $title->count() > 0 ){

            if($title->status_id == 28 or $title->status_id == 29){
                return response()->json(array("error" => "Não é possível gerar boleto para título liquidado ou baixado"));
            }

            $billetGenerator                = new BilletGenerator();
            $billetGenerator->barcode       = $title->bar_code;
            $billetGenerator->digitableLine = $title->digitable_line;
            $billetGenerator->bankNumber    = substr($title->bank_code,1,3);

            $facilities                                         = new Facilites();

            $billetData                                         = $title->getAccntAddMoneyBankCharging()[0];
            $billetData->draw_digitable_line                    = $billetGenerator->drawDigitableLine();
            $billetData->draw_bar_code                          = $billetGenerator->drawBarCode();
            $billetData->bank_code_formated                     = $billetGenerator->createBankCode();
            $billetData->master_cpf_cnpj                        = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
            $billetData->beneficiary_cpf_cnpj                   = $facilities->mask_cpf_cnpj($billetData->register_cpf_cnpj);
            $billetData->payer_cpf_cnpj                         = $facilities->mask_cpf_cnpj($billetData->register_cpf_cnpj);
            $billetData->beneficiary_address_zip_code           = $facilities->mask_cep($billetData->register_address_zip_code);
            $billetData->payer_address_zip_code                 = $facilities->mask_cep($billetData->register_address_zip_code);
            $billetData->owner_cpf_cnpj                         = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
            $billetData->document_type                          = "DM";
            $billetData->payer_name                             = $billetData->register_name;
            $billetData->beneficiary_name                       = $billetData->register_name;
            $billetData->logo                                   = null;
            $billetData->pix_qr_code = "";
            if($billetData->pix_emv <> null and $billetData->pix_emv <> ''){

                $qrCode = new QrCodeGenerator();
                $qrCode->data = $billetData->pix_emv;
                $qrCode->return_type = 'base64';
                $qrCode->quiet_zone = true;
                $qrCode->quiet_zone_size = 1;
                $QrCode = $qrCode->createQrCode();

                $billetData->pix_qr_code = '<img height="100" src="data:image/png;base64, '.preg_replace('#^data:image/\w+;base64,#i', '', $QrCode->base64).' /><br>
                <div class="tableCellData code"><center>Pague com PIX</center></div>';
            }
            switch($billetData->api_id){
                case 10:
                    $billetData->path_bank_logo = "billet/logorendimento.jpg";
                break;
                case 13:
                    $billetData->path_bank_logo = "billet/logobb.jpg";
                break;
                default:
                    $billetData->path_bank_logo = "billet/logorendimento.jpg";
                break;
            }
            $billetData->path_qr_code                           = "billet/qrCodeDinariPay.png";
            $billetData->issue_date                             = ((\Carbon\Carbon::parse( $billetData->issue_date ))->format('d/m/Y'));
            $billetData->due_date                               = ((\Carbon\Carbon::parse( $billetData->due_date ))->format('d/m/Y'));
            $billetData->value                                  = number_format(($billetData->value),2,',','.');
            $billetData->observation                            = 'Boleto para crédito na conta digital nº '.$billetData->account_number.'<br>Não pagar após o vencimento';
            $billetData->message_fine_interest                  = '';
            $billetData->message1                               = 'Boleto para crédito na conta digital nº '.$billetData->account_number;
            $billetData->message2                               = 'Não pagar após o vencimento';

            $pdfFilePath  = '../storage/app/billet_download/';
            $file_name    = $title->our_number.'.pdf';

            $pdf = PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->download( $title->our_number.'.pdf', ['Content-Type: application/pdf']);
            return response()->json(array("success" => "true", "file_name" => $title->our_number.'.pdf', "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));

        }
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [174,255];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( (SystemFunctionMaster::where('system_function_id','=',2)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if( AccntAddMoneyBankCharging::whereNull('payment_date')->whereNull('down_date')->whereNotNull('our_number')->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->count() >= 3 ){
            return response()->json(array("error" => 'Você possui 3 boletos para depósito emitidos e não liquidados, realize o pagamento de um deles, ou solicite baixa para emitir outro. Também é possível realizar o download dos boletos já emitidos'));
        }

        if($request->value <= 0) {
            return response()->json(array("error" => "Informe um valor maior que 0"));
        }

        if($addMoney = AccntAddMoneyBankCharging::create([
            "master_id"  => $checkAccount->master_id,
            "account_id" => $checkAccount->account_id,
            "type_id"    => null,
            "issue_date" =>  (\Carbon\Carbon::parse( (\Carbon\Carbon::now()) ))->format('Y-m-d'),
            "document"   => "DPT".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('dmy').str_pad(AccntAddMoneyBankCharging::getCountDPTDay($checkAccount->master_id), 4, '0', STR_PAD_LEFT),
            "due_date"   => (\Carbon\Carbon::parse( (\Carbon\Carbon::now())->addDays(5) ))->format('Y-m-d'),
            "value"      => $request->value,
            'created_at' => \Carbon\Carbon::now(),
            'uuid'       => Str::orderedUuid(),
            'api_id'     => 10
        ])){
            $registerBillet = new AccntAddMoneyBankCharging();
            $registerBillet->accn_add_mny_id = $addMoney->id;

            $billetData = $registerBillet->getAccntAddMoneyBankCharging()[0];

            $apiConfig                                        = new ApiConfig();
            $apiConfig->master_id                             = $checkAccount->master_id;
            $apiConfig->api_id                                = 10;
            $apiConfig->onlyActive                            = 1;
            $apiData                                          = $apiConfig->getApiConfig()[0];

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
            $apiRendimento->tit_cnpj_cpf                      = $billetData->register_cpf_cnpj;
            $apiRendimento->tit_nome                          = $billetData->register_name;
            $apiRendimento->tit_endereco                      = $billetData->register_address_description;
            $apiRendimento->tit_bairro                        = $billetData->register_address_district;
            $apiRendimento->tit_cidade                        = $billetData->register_address_city;
            $apiRendimento->tit_uf                            = $billetData->register_address_state_short_description;
            $apiRendimento->tit_cep                           = $billetData->register_address_zip_code;
            $apiRendimento->tit_email                         = "";//$billetData->register_email;
            $apiRendimento->tit_telefone                      = $billetData->register_phone;
            $apiRendimento->tit_campo_livre                   = "";
            $apiRendimento->tit_cnpj_cpf_sacador              = $billetData->register_cpf_cnpj;
            $apiRendimento->tit_nome_sacador                  = $billetData->register_name;
            $apiRendimento->tit_endereco_sacador              = $billetData->register_address_description;
            $apiRendimento->tit_bairro_sacador                = $billetData->register_address_district;
            $apiRendimento->tit_cep_sacador                   = $billetData->register_address_zip_code;
            $apiRendimento->tit_cidade_sacador                = $billetData->register_address_city;
            $apiRendimento->tit_uf_sacador                    = $billetData->register_address_state_short_description;
            $apiRendimento->tit_mensagem_1                    = "Boleto para crédito na conta digital: ".$billetData->account_number;
            $apiRendimento->tit_mensagem_2                    = "| Não pagar após o vencimento";
            $apiRendimento->tit_mensagem_3                    = "";
            $apiRendimento->tit_mensagem_4                    = "";
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
            $apiRendimento->tit_cod_multa                     = 0;
            $apiRendimento->tit_data_multa                    = "";
            $apiRendimento->tit_tx_multa                      = "";
            $apiRendimento->tit_vlr_multa                     = "";
            $apiRendimento->tit_cod_mora                      = 3;
            $apiRendimento->tit_data_mora                     = "";
            $apiRendimento->tit_tx_mora                       = "";
            $apiRendimento->tit_vlr_mora                      = "";
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
                    $updateBillet =  AccntAddMoneyBankCharging::where('id','=',$addMoney->id)->first();
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
                        return response()->json(array("success" => "Boleto Registrado com sucesso", "billet_data" => $updateBillet));
                    } else {
                        return response()->json(array("error" => "Ocorreu um erro ao atualizar os dados do boleto"));
                    }
                } else {
                    return response()->json(array("error" => "Ocorreu um erro ao registrar o boleto, por favor tente novamente ", "errorData" => $titleInclude ));
                }
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao registrar o boleto, por favor tente novamente dentro de alguns minutos", "errorData" => $titleInclude ));
            }
        }
    }

    protected function sendInstruction(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountPJId = $request->header('accountPJId');
        $accountPFId = $request->header('accountPFId');
        if($accountPJId != ''){
            $accountId = $accountPJId;
        } else if($accountPFId != ''){
            $accountId = $accountPFId;
        } else {
            return response()->json(array("error" => "Falha ao verificar conta"));
        }

        if( AccntAddMoneyBankCharging::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->count() > 0 ){
            $accntAddMoney = AccntAddMoneyBankCharging::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->first();

            $tax = 0;
            $taxId = 9;
            if($request->instruction == 1){
                $taxId = 8;
            }

            $getTax = Account::getTax($checkAccount->account_id, $taxId, $checkAccount->master_id);
            if($getTax->value > 0){
                $tax = $getTax->value;
            } else if($getTax->percentage > 0){
                if($accntAddMoney->value > 0){
                    $tax = round(( ($getTax->percentage/100) * $accntAddMoney->value),2);
                }
            }
            $accountMovement             = new AccountMovement();
            $accountMovement->account_id = $accntAddMoney->account_id;
            $accountMovement->master_id  = $checkAccount->master_id;
            $accountMovement->start_date = \Carbon\Carbon::now();
            $accountBalance              = 0;
            $accountMasterBalance        = 0;
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $accountBalance = $accountMovement->getAccountBalance()->balance;
            }
            if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
                $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
            }
            if($accountBalance < $tax){
                return response()->json(array("error" => "Saldo insuficiente para solicitar instrução"));
            }


            $apiConfig                           = new ApiConfig();
            $apiConfig->master_id                = $checkAccount->master_id;
            $apiConfig->api_id                   = $accntAddMoney->api_id;
            $apiConfig->onlyActive               = 1;
            $apiData                             = $apiConfig->getApiConfig()[0];
            $apiRendimento                       = new ApiBancoRendimento();
            $apiRendimento->id_cliente           = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso         = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao         = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api         = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia              = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente       = Crypt::decryptString($apiData->api_account);
            $apiRendimento->tit_nosso_numero     = $accntAddMoney->our_number;

            $apiRendimento->tit_data_referencia  = '';
            $apiRendimento->tit_texto            = 'nn: '.$accntAddMoney->our_number.' | id: '.$accntAddMoney->id;
            $apiRendimento->tit_tipo_referencia  = '0';
            $apiRendimento->tit_valor_referencia = '0';


            $chargeInstruction = ChargeInstruction::where('id','=',$request->instruction)->first();
            $apiRendimento->tit_codigo_instrucao = $chargeInstruction->code;
            $apiRendimento->tit_codigo_produto   = 2;
            $mvmntTypeId = null;
            switch($chargeInstruction->code){
                case 51:
                    $mvmntTypeId = 6;
                break;
                case 145:
                    $apiRendimento->tit_tipo_referencia  = 1;
                    $apiRendimento->tit_data_referencia  = $request->new_due_date;
                    $mvmntTypeId = 7;
                break;
                case 148:
                    $apiRendimento->tit_tipo_referencia  = 2;
                    $apiRendimento->tit_valor_referencia = $request->discount_value;
                    $mvmntTypeId = 16;
                break;
            }

            $instruction = $apiRendimento->tituloIncluirInstrucao();
            if(isset($instruction->body->value)){
                if( $tax > 0 ){
                    AccountMovement::create([
                        'account_id'       => $accntAddMoney->account_id,
                        'master_id'        => $checkAccount->master_id,
                        'origin_id'        => $accntAddMoney->id,
                        'mvmnt_type_id'    => $mvmntTypeId,
                        'date'             => \Carbon\Carbon::now(),
                        'value'            => ($tax * -1),
                        'balance'          => ($accountBalance - $tax),
                        'master_balance'   => ($accountMasterBalance - $tax),
                        'description'      => 'Tarifa de Instrução | '.$chargeInstruction->description.' Boleto para Depósito | '.$accntAddMoney->document,
                        'created_at'       => \Carbon\Carbon::now(),
                    ]);
                    $master = Master::where('id','=',$checkAccount->master_id)->first();
                    if($master->margin_accnt_id != ''){
                        $masterAccountMovement             = new AccountMovement();
                        $masterAccountMovement->account_id = $master->margin_accnt_id;
                        $masterAccountMovement->master_id  = $checkAccount->master_id;
                        $masterAccountMovement->start_date = \Carbon\Carbon::now();
                        $masterAccountBalance              = 0;
                        $masterAccountMasterBalance        = 0;
                        if(isset( $masterAccountMovement->getAccountBalance()->balance )){
                            $masterAccountBalance = $masterAccountMovement->getAccountBalance()->balance;
                        }
                        if(isset( $masterAccountMovement->getMasterAccountBalance()->master_balance )){
                            $masterAccountMasterBalance = $masterAccountMovement->getMasterAccountBalance()->master_balance;
                        }

                        AccountMovement::create([
                            'account_id'       => $master->margin_accnt_id,
                            'accnt_origin_id'  => $accntAddMoney->account_id,
                            'master_id'        => $checkAccount->master_id,
                            'origin_id'        => $accntAddMoney->id,
                            'mvmnt_type_id'    => $mvmntTypeId,
                            'date'             => \Carbon\Carbon::now(),
                            'value'            => $tax,
                            'balance'          => $masterAccountBalance  + $tax,
                            'master_balance'   => $masterAccountMasterBalance + $tax,
                            'description'      => 'Tarifa de Instrução | '.$chargeInstruction->description.' Boleto para Depósito | '.$accntAddMoney->document,
                            'created_at'       => \Carbon\Carbon::now(),
                        ]);
                    }
                }
                switch($chargeInstruction->code){
                    case 51:
                        $accntAddMoney->status_id =  28;
                        $accntAddMoney->down_date = \Carbon\Carbon::now();
                    break;
                }
                if($accntAddMoney->save()){
                    return response()->json(array("success" => "Instrução aplicada com sucesso"));
                } else {
                    return response()->json(array("error" => "Instrução aplicada com sucesso, porém ocorreu um erro ao atualizar o título no sistema, entre em contato com o suporte"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao aplicar a instrução, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Não foi possível localizar o título"));
        }
    }

    public function billetLiquidation()
    {
        $i            = 0;
        $searchTitles = null;

        //Get business day to liquidation
        $day = app('App\Http\Controllers\HolidayController')->returnBusinessDay((\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d'));
        if(isset($day->businessDayPrevious)){
            $paymentDate = $day->businessDayPrevious;
        } else {
            $paymentDate = (\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d');
        }

        $titles = AccntAddMoneyBankCharging::whereNotNull('our_number')->whereNull('payment_date')->whereNull('down_date')->get();
        foreach($titles as $title){
            $apiConfig                                          = new ApiConfig();
            $apiConfig->api_id                                  = $title->api_id;
            $apiConfig->onlyActive                              = 1;
            $apiConfig->master_id                               = $title->master_id;
            $apiData                                            = $apiConfig->getApiConfig()[0];
            $apiRendimento                                      = new ApiBancoRendimento();
            $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
            $apiRendimento->tit_nosso_numero                    = $title->our_number;

            $titleData = $apiRendimento->tituloConsultar();
            if(isset($titleData->body->value[0]->codigoSituacao)){
                if($titleData->body->value[0]->codigoSituacao == 'Pago'){
                    $titleDataObj = (object) [
                        'our_number'    => $titleData->body->value[0]->nossoNumero,
                        'wallet_number' => $titleData->body->value[0]->numeroCarteira,
                        'bank_code'     => $title->bank_code,
                        'payment_date'  => $paymentDate,
                        'payment_value' => $titleData->body->value[0]->valorTitulo,
                        'api_id'        => $title->api_id
                    ];
                    $addMoneyLiquidation = new AddMoneyBilletLiquidationService();
                    $addMoneyLiquidation->paymentData = $titleDataObj;
                    $liquidationData = $addMoneyLiquidation->billetLiquidation();
                    if(!$liquidationData->success){
                        array_push($errors, $liquidationData->message);
                    }
                }
                $searchTitles[$i] = $titleData;
                $i++;
            }
        }
        return response()->json($searchTitles);
    }

    protected function getDetailedPDF(Request $request)
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

        $addMoney                                   = new AccntAddMoneyBankCharging();
        $addMoney->master_id                        = $checkAccount->master_id;
        $addMoney->account_id                       = $checkAccount->account_id;
        $addMoney->onlyActive                       = $request->onlyActive;
        $addMoney->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $addMoney->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $addMoney->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $items = [];
        foreach($addMoney->addMoneyChargeDetailed() as $movementData){
            array_push($items, (object) [
                'occurrence_date'       =>  $movementData->occurrence_date ? \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y h:m:s') : null,
                'type_description'      =>  $movementData->type_description,
                'status_description'    =>  $movementData->status_description,
                'beneficiary_name'      =>  $movementData->beneficiary_name,
                'account_number'        =>  $movementData->account_number,
                'document'              =>  $movementData->document,
                'our_number'            =>  $movementData->our_number,
                'value'                 =>  $movementData->value,
                'payment_date'          =>  $movementData->payment_date ? \Carbon\Carbon::parse($movementData->payment_date)->format('d/m/Y') : null,
                'payment_value'         =>  $movementData->payment_value,
                'down_date'             =>  $movementData->down_date ? \Carbon\Carbon::parse($movementData->down_date)->format('d/m/Y') : null,
                'issue_date'            =>  \Carbon\Carbon::parse($movementData->issue_date)->format('d/m/Y'),
                'due_date'              =>  \Carbon\Carbon::parse($movementData->due_date)->format('d/m/Y'),
                'created_at'            =>  \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')
            ]);
        }

        $data = (object) array(
            "movement_data"     => $items
        );

        $file_name = "Movimentacao_Boleto.pdf";
        $pdf       = PDF::loadView('reports/movement_bank_charging', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    public function deadLineDown()
    {
        $addMoneyCharge = new AccntAddMoneyBankCharging();

        $titles = $addMoneyCharge->getDeadLineTitles();

        foreach($titles as $title){
            $tax = 0;
            $taxId = 19;

            $getTax = Account::getTax($title->account_id, $taxId, $title->master_id);

            if($getTax->value > 0){
                $tax = $getTax->value;
            } else if($getTax->percentage > 0){
                if($title->value > 0){
                    $tax = round(( ($getTax->percentage/100) * $title->value),2);
                }
            }

            $accntAddMoney = AccntAddMoneyBankCharging::where('id','=',$title->id)->where('master_id','=',$title->master_id)->where('account_id','=',$title->account_id)->first();

            $apiConfig                           = new ApiConfig();
            $apiConfig->master_id                = $title->master_id;
            $apiConfig->api_id                   = $accntAddMoney->api_id;
            $apiConfig->onlyActive               = 1;
            $apiData                             = $apiConfig->getApiConfig()[0];
            $apiRendimento                       = new ApiBancoRendimento();
            $apiRendimento->id_cliente           = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso         = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao         = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api         = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia              = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente       = Crypt::decryptString($apiData->api_account);
            $apiRendimento->tit_nosso_numero     = $accntAddMoney->our_number;

            $apiRendimento->tit_data_referencia  = '';
            $apiRendimento->tit_texto            = 'nn: '.$accntAddMoney->our_number.' | id: '.$accntAddMoney->id;
            $apiRendimento->tit_tipo_referencia  = '0';
            $apiRendimento->tit_valor_referencia = '0';
            $apiRendimento->tit_codigo_instrucao = 51;
            $apiRendimento->tit_codigo_produto   = 2;
            $instruction = $apiRendimento->tituloIncluirInstrucao();

            if(isset($instruction->body->value)){
                if( $tax > 0 ){

                    $accountMovement             = new AccountMovement();
                    $accountMovement->account_id = $accntAddMoney->account_id;
                    $accountMovement->master_id  = $accntAddMoney->master_id;
                    $accountMovement->start_date = \Carbon\Carbon::now();
                    $accountBalance              = 0;
                    $accountMasterBalance        = 0;
                    if(isset( $accountMovement->getAccountBalance()->balance )){
                        $accountBalance = $accountMovement->getAccountBalance()->balance;
                    }
                    if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
                        $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
                    }

                    //get account balance
                    $balanceService = new BalanceService();
                    $balanceService->accountData = (object)[
                        'account_id' => $accntAddMoney->account_id,
                        'master_id'  => $accntAddMoney->master_id
                    ];
                    $balance = $balanceService->getBalance();

                    if($balance->account_balance < $tax) {
                        
                        $accountMovementFuture = new AccountMovementFutureClass();
                        $accountMovementFuture->account_id = $accntAddMoney->account_id;
                        $accountMovementFuture->master_id = $accntAddMoney->master_id;
                        $accountMovementFuture->mvmnt_type_id = 30;
                        $accountMovementFuture->description = 'Tarifa de Instrução | Baixa Boleto Depósito - Decurso de Prazo | '.$accntAddMoney->document;
                        $accountMovementFuture->value = $tax;
                        $accountMovementFuture->create();

                    } else {
                        AccountMovement::create([
                            'account_id'       => $accntAddMoney->account_id,
                            'master_id'        => $accntAddMoney->master_id,
                            'origin_id'        => $accntAddMoney->id,
                            'mvmnt_type_id'    => 30,
                            'date'             => \Carbon\Carbon::now(),
                            'value'            => ($tax * -1),
                            'balance'          => ($accountBalance - $tax),
                            'master_balance'   => ($accountMasterBalance - $tax),
                            'description'      => 'Tarifa de Instrução | Baixa Boleto Depósito - Decurso de Prazo | '.$accntAddMoney->document,
                            'created_at'       => \Carbon\Carbon::now(),
                        ]);
                        $master = Master::where('id','=', $accntAddMoney->master_id)->first();
                        if($master->margin_accnt_id != ''){
                            $masterAccountMovement             = new AccountMovement();
                            $masterAccountMovement->account_id = $master->margin_accnt_id;
                            $masterAccountMovement->master_id  = $accntAddMoney->master_id;
                            $masterAccountMovement->start_date = \Carbon\Carbon::now();
                            $masterAccountBalance              = 0;
                            $masterAccountMasterBalance        = 0;
                            if(isset( $masterAccountMovement->getAccountBalance()->balance )){
                                $masterAccountBalance = $masterAccountMovement->getAccountBalance()->balance;
                            }
                            if(isset( $masterAccountMovement->getMasterAccountBalance()->master_balance )){
                                $masterAccountMasterBalance = $masterAccountMovement->getMasterAccountBalance()->master_balance;
                            }

                            AccountMovement::create([
                                'account_id'       => $master->margin_accnt_id,
                                'accnt_origin_id'  => $accntAddMoney->account_id,
                                'master_id'        => $accntAddMoney->master_id,
                                'origin_id'        => $accntAddMoney->id,
                                'mvmnt_type_id'    => 30,
                                'date'             => \Carbon\Carbon::now(),
                                'value'            => $tax,
                                'balance'          => $masterAccountBalance  + $tax,
                                'master_balance'   => $masterAccountMasterBalance + $tax,
                                'description'      => 'Tarifa de Instrução | Baixa Boleto Depósito - Decurso de Prazo | '.$accntAddMoney->document,
                                'created_at'       => \Carbon\Carbon::now(),
                            ]);
                        }

                    }
                }
                $accntAddMoney->status_id =  28;
                $accntAddMoney->down_date = \Carbon\Carbon::now();
                $accntAddMoney->save();
            }
        }
    }
}
