<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Payer;
use App\Models\PayerDetail;
use App\Models\PayerEmail;
use App\Models\PayerPhone;
use App\Models\PayerAddress;
use App\Models\ChargeHistory;
use App\Models\ChargeConfig;
use App\Models\SimpleCharge;
use App\Models\SimpleChargeHistory;
use App\Models\Account;
use App\Models\ZipCodeAddress;
use App\Models\ZipCodeCity;
use App\Models\ZipCodeDistrict;
use App\Models\Master;
use App\Models\SystemFunctionMaster;
use App\Models\ApiConfig;
use App\Libraries\SimpleCNAB;
use App\Libraries\xmlBrazilNF;
use App\Libraries\Facilites;
use App\Libraries\ViaCep;
use App\Libraries\ApiBancoRendimento;
use App\Services\BilletLiquidation\SimpleChargeBilletLiquidationService;
use App\Services\BilletLiquidation\AntecipationChargeBilletLiquidationService;
use App\Services\BilletLiquidation\AddMoneyBilletLiquidationService;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\BancoRendimento\BancoRendimentoClass;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChargeController extends Controller
{
    public function checkServiceAvailable(Request $request)
    {
        if( (SystemFunctionMaster::where('system_function_id','=',6)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível gerar boletos."));
        } else {
            return response()->json(array("success" => ""));
        }
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [214, 280, 373];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $charge               = new Charge();
        $charge->master_id    = $checkAccount->master_id;
        $charge->account_id   = $checkAccount->account_id;
        $charge->onlyActive   = $request->onlyActive;
        $charge->status_id_in = [13,40,45];
        return response()->json($charge->getCharge());
    }

    protected function importFile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( (SystemFunctionMaster::where('system_function_id','=',6)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if($request->fileName != ''){
            
            $chargeConfig = ChargeConfig::where('account_id','=',$checkAccount->account_id)->first();
            $headerName = '';
            if(isset($chargeConfig->header_name)){
                $headerName = $chargeConfig->header_name;
            }

            $fileName =  strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName);
            if(Storage::disk('charge_upload')->put( $fileName, base64_decode($request->file64))){
                $path =  Storage::disk('charge_upload')->path($fileName);
                switch(\File::extension($path)){
                    case 'rem':
                        //check permission
                        $accountCheckService->permission_id = [216, 282, 375];
                        $checkAccount = $accountCheckService->checkAccount();
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }

                        return $this->newCNAB($path, $fileName,$checkAccount->master_id, (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id, $checkAccount->account_id, $headerName);
                    break;
                    case 'txt':
                        //check permission
                        $accountCheckService->permission_id = [216, 282, 375];
                        $checkAccount = $accountCheckService->checkAccount();
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }

                        return $this->newCNAB($path, $fileName,$checkAccount->master_id, (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id, $checkAccount->account_id, $headerName);
                    break;
                    case 'xml':
                        //check permission
                        $accountCheckService->permission_id = [215, 281, 374];
                        $checkAccount = $accountCheckService->checkAccount();
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }
                        return $this->newXML($path, $fileName,$checkAccount->master_id, $request->header('registerId'), $checkAccount->account_id);
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

    protected function importReturnFile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [111];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->fileName != ''){
            $fileName =  strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName);
            if(Storage::disk('charge_upload')->put( $fileName, base64_decode($request->file64))){
                $path =  Storage::disk('charge_upload')->path($fileName);
                switch(\File::extension($path)){
                    case 'ret':
                        return $this->newReturnCNAB($path, $request->fileName,$checkAccount->master_id);
                    break;
                    case 'txt':
                        return $this->newReturnCNAB($path, $request->fileName,$checkAccount->master_id);
                    break;
                    default:
                        return response()->json(array("error" => "Formato inválido para o arquivo ".$request->fileName ));
                    break;
                }
            } else {
                return response()->json(array("error" => "Não foi possível fazer o upload do arquivo ".$request->fileName));
            }
        } else {
            return response()->json(array("error" => "Não foi possível fazer o upload do arquivo"));
        }
    }

    protected function newReturnCNAB($path, $fileName, $masterId)
    {
        $errors = [];

        $simpleCNAB             = new SimpleCNAB();
        $simpleCNAB->pathFile   = $path;
        $simpleCNAB->optionType = 'readReturn';
        $cnabData               = (object) json_decode($simpleCNAB->getCNABFile());
        
        if($cnabData->success){
            if(isset($cnabData->remittanceData->titles)){
                foreach($cnabData->remittanceData->titles as $title){
                    if($cnabData->remittanceData->header->numeroBanco == '633' or $cnabData->remittanceData->header->numeroBanco == '001'){

                        switch($title->contaMovimentoBeneficiario){
                            case '58841540': //Conta Dinari Securitizadora - Banco Rendimento - falta final 07
                                $apiId = 1;
                            break;
                            case '05491690': //Conta Dinari Pagamentos - Banco Rendimento - falta final 05
                                $apiId = 10;
                            break;
                            case '000000014293X': //Conta Dinari Pagamentos - Banco do Brasil
                                $apiId = 13;
                            break;
                            default:
                                $apiId = 1;
                            break;
                        }
                       
                        $titleDataObj = (object) [
                            'our_number'    => $title->nossoNumero,
                            'wallet_number' => $title->codigoCarteira,
                            'bank_code'     => '0'.$cnabData->remittanceData->header->numeroBanco,
                            'payment_date'  => $title->dataOcorrencia,
                            'payment_value' => $title->valorRecebido,
                            'api_id'        => $apiId,
                            'account_id'    => null
                        ];
                        switch($title->codigoOcorrencia){
                            case '06': //billetLiquidation
                                //simple charge
                                $simpleChargeLiquidation = new SimpleChargeBilletLiquidationService();
                                $simpleChargeLiquidation->paymentData = $titleDataObj;
                                $liquidationData = $simpleChargeLiquidation->billetLiquidation();
                                if(!$liquidationData->success){
                                    array_push($errors, $liquidationData->message);
                                }

                                //antecipation charge
                                $antecipationChargeLiquidation = new AntecipationChargeBilletLiquidationService();
                                $antecipationChargeLiquidation->paymentData = $titleDataObj;
                                $liquidationData = $antecipationChargeLiquidation->billetLiquidation();
                                if(!$liquidationData->success){
                                    array_push($errors, $liquidationData->message);
                                }

                                //add money liquidation
                                $addMoneyLiquidation = new AddMoneyBilletLiquidationService();
                                $addMoneyLiquidation->paymentData = $titleDataObj;
                                $liquidationData = $addMoneyLiquidation->billetLiquidation();
                                if(!$liquidationData->success){
                                    array_push($errors, $liquidationData->message);
                                }

                            break;
                        }
                    }
                }
            }

            if(sizeof($errors) > 0){
                return response()->json(array("error" => $errors));
            } else {

                return response()->json(array("success" => "Arquivo ".$fileName."  processado com sucesso", "titles" => $cnabData->remittanceData->titles));
            }
        } else {
            return response()->json(array("error" => $cnabData->error));
        }
    }

    protected function newXML($path, $fileName, $masterId, $registerMasterId, $accountId)
    {
        $errors = [];
        $xmlBrazilNF             = new xmlBrazilNF();
        $xmlBrazilNF->pathFile   = $path;
        $xmlData                 = (object) json_decode($xmlBrazilNF->getXMLFile());

        if($xmlData->success){

            $fine         = null;
            $interest     = null;
            $message1     = null;
            $message2     = null;
            
            if(ChargeConfig::where('account_id','=',$accountId)->count() > 0){
                $chargeConfig = ChargeConfig::where('account_id','=',$accountId)->first();
                $fine         = $chargeConfig->fine;
                $interest     = $chargeConfig->interest;
                $message1     = $chargeConfig->message1;
                $message2     = $chargeConfig->message2;
            }

            foreach($xmlData->xmlData->duplicatas as $duplicata){
                $payerData = (object) [
                    'payer_cpf_cnpj'             => $xmlData->xmlData->destinatario_cnpj,
                    'payer_name'                 => $xmlData->xmlData->destinatario_nome,
                    'payer_fantasy_name'         => null,
                    'payer_state_registration'   => $xmlData->xmlData->destinatario_inscricao_estadual,
                    'payer_address'              => $xmlData->xmlData->destinatario_endereco_logradouro,
                    'payer_address_zip_code'     => $xmlData->xmlData->destinatario_endereco_cep,
                    'payer_address_public_place' => null,
                    'payer_address_number'       => $xmlData->xmlData->destinatario_endereco_numero,
                    'payer_address_complement'   => null,
                    'payer_address_district'     => $xmlData->xmlData->destinatario_endereco_bairro,
                    'payer_address_city'         => $xmlData->xmlData->destinatario_endereco_municipio,
                    'payer_address_state_id'     => Facilites::convertStateToInt($xmlData->xmlData->destinatario_endereco_uf),
                    'payer_address_ibge_code'    => null,
                    'payer_address_gia_code'     => null,
                    'payer_email_address'        => $xmlData->xmlData->destinatario_email,
                    'payer_phone_number'         => $xmlData->xmlData->destinatario_fone,
                    'observation'                => 'Criado ao inserir título (XML)'
                ];

                $payer = $this->checkPayer($payerData, $registerMasterId, 1);
                $chargeData = null;
                $chargeData = (object) array(
                    'master_id'                  => $masterId,
                    'register_master_id'         => $registerMasterId,
                    'account_id'                 => $accountId,
                    'payer_detail_id'            => $payer,
                    'chrg_imprt_tp_id'           => 2,
                    'invoice'                    => $xmlData->xmlData->nf_numero,
                    'issue_date'                 =>  (\Carbon\Carbon::parse( $xmlData->xmlData->nf_data_emissao ))->format('Y-m-d'),
                    'document'                   => $duplicata->duplicata_numero,
                    'due_date'                   => $duplicata->duplicata_vencimento,
                    'value'                      => $duplicata->duplicata_valor,
                    'file_name_imported'         => $fileName,
                    'title_kind'                 => '01',
                    'our_number_imported'        => null,
                    'nfe_key'                    => $xmlData->xmlData->prot_chave_nfe,
                    'participant_control_number' => null,
                    'discount_bonus_day'         => null,
                    'discount_value'             => null,
                    'discount_deadline'          => null,
                    'fine_type'                  => null,
                    'fine_percentage'            => null,
                    'amount_charged_day_delay'   => null,
                    'first_instruction'          => null,
                    'second_instruction'         => null,
                    'message1'                   => $message1,
                    'message2'                   => $message2,
                    'message3'                   => null,
                    'message4'                   => null,
                    'fine'                       => $fine,
                    'interest'                   => $interest,
                    'payer_cpf_cnpj'             => $xmlData->xmlData->destinatario_cnpj,
                    'payer_name'                 => $xmlData->xmlData->destinatario_nome,
                    'payer_address_public_place' => null,
                    'payer_address'              => $xmlData->xmlData->destinatario_endereco_logradouro,
                    'payer_address_number'       => $xmlData->xmlData->destinatario_endereco_numero,
                    'payer_address_complement'   => null,
                    'payer_address_district'     => $xmlData->xmlData->destinatario_endereco_bairro,
                    'payer_address_city'         => $xmlData->xmlData->destinatario_endereco_municipio,
                    'payer_address_state'        => $xmlData->xmlData->destinatario_endereco_uf,
                    'payer_zip_code'             => $xmlData->xmlData->destinatario_endereco_cep,
                    'observation'                => "",
                );

                if(!$this->newCharge($chargeData)){
                   array_push($errors, 'Não foi possível inserir o título '.$duplicata->duplicata_numero.', com vencimento em '.$duplicata->duplicata_vencimento);
                }
            }
            if(sizeof($errors) > 0){
                return response()->json(array("error" => $errors));
            } else {
                return response()->json(array("success" => "Títulos do arquivo ".$fileName." inseridos com sucesso"));
            }
        } else {
            return response()->json(array("error" => $xmlData->error));
        }

    }

    protected function newCNAB($path, $fileName, $masterId, $registerMasterId, $accountId, $headerName)
    {
        $errors = [];

        $fine = null;
        $interest = null;
        $message1 = null;
        $message2 = null;

        // get account data
        $account = Account::where('id', '=', $accountId)->first();

        // get account charge config
        if(ChargeConfig::where('account_id','=',$accountId)->count() > 0){
            $chargeConfig = ChargeConfig::where('account_id','=',$accountId)->first();
            $fine         = $chargeConfig->fine;
            $interest     = $chargeConfig->interest;
            $message1     = $chargeConfig->message1;
            $message2     = $chargeConfig->message2;
        }

        // get CNAB data
        $simpleCNAB = new SimpleCNAB();
        $simpleCNAB->pathFile = $path;
        $simpleCNAB->optionType = 'readRemittance';
        $cnabData = (object) json_decode($simpleCNAB->getCNABFile());

        if( ! $cnabData->success ){
            return response()->json(array("error" => $cnabData->error));
        }

        // validate header name
        if($headerName != ''){
            if($headerName != null){
                if($headerName != $cnabData->remittanceData->header->nomeEmpresa){
                    return response()->json(array("error" => "Nome do header do arquivo divergente do nome definido na configuração de cobrança"));
                }
            }
        }

        //Validate cnab data
        foreach($cnabData->remittanceData->titles as $title){

            if( $account->charge_mandatory_validation_cnab_import == 1 ){
                if( $account->charge_registration_api_id == 13 ){
                    //Brasil Bank Validations
                    if( $cnabData->remittanceData->header->numeroBanco != '001' ) {
                        return response()->json(array('error' => 'Arquivo de remessa não pertence ao Banco do Brasil (Posições 077 a 079 do HEADER devem ser: 001). Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa.'));
                    }

                    if( ($title->agenciaDebito.$title->digitoAgenciaDebito) != $account->charge_agency ){
                        array_push($errors, 'Agência definida no título é inválida para importação (Posições 018 a 022 -> Agência + DV devem ser: '.$account->charge_agency.', foi informado: '.$title->agenciaDebito.$title->digitoAgenciaDebito.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa.');
                    }

                    if( ($title->contaCorrente.$title->digitoContaCorrente) != $account->charge_account ){
                        array_push($errors, 'Conta definida no título é inválida para importação (Posições 023 a 031 -> Conta + DV devem ser: '.$account->charge_account.', foi informado: '.$title->contaCorrente.$title->digitoContaCorrente.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa.');
                    }

                    if( $title->identificacaoEmpresaBeneficiariaBanco != $account->charge_covenant ){
                        array_push($errors, 'Convênio de cobrança definido no título é inválido para importação (Posições 032 a 038 -> Convênio de cobrança deve ser: '.$account->charge_covenant.', foi informado: '.$title->identificacaoEmpresaBeneficiariaBanco.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa.');
                    }

                    if( $title->carteira != $account->charge_wallet ){
                        array_push($errors, 'Carteira definida no título é inválida para importação (Posições 107 a 108 -> Carteira deve ser: '.$account->charge_wallet.', foi informado: '.$title->carteira.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa.');
                    }

                    if( $title->variacaoCarteira != $account->charge_wallet_variation ){
                        array_push($errors, 'Variação de carteira definida no título é inválida para importação (Posições 092 a 094 -> Variação de carteira deve ser: '.$account->charge_wallet_variation.', foi informado: '.$title->variacaoCarteira.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa.');
                    }


                    if( $account->charge_register_with_our_number_of_cnab == 1 ){
                        if( 
                            $title->identificacaoTituloBanco < $account->charge_our_number_first
                            or $title->identificacaoTituloBanco > $account->charge_our_number_last
                        ){
                            array_push($errors, 'Nosso número definido no título é inválido para importação (Posições 064 a 080: Nosso número deve se manter no range de: '.$account->charge_our_number_first.' até '.$account->charge_our_number_last.', foi informado: '.$title->identificacaoTituloBanco.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. Em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa. Caso tenha utilizado todo o range de nosso número disponibilizado, entre em contato com seu gerente de relacionamento.');
                        }


                        if( $checkCharge = Charge::where('our_number_imported', '=', $title->identificacaoTituloBanco)->where('account_id', '=', $account->id)->where('status_id', '<>', 44)->whereNull('deleted_at')->first() ) {
                            array_push($errors, 'Nosso número '.$title->identificacaoTituloBanco.' já importado para o título '.$checkCharge->document.', se necessário exclua o título da tela Cobrança - Adicionar e realize a importação novamente. Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. O nosso número de cada título deve ser único, em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa. Se necessário, entre em contato com seu gerente de relacionamento para maiores esclarecimentos.');
                        }

                        if( $checkSimpleCharge = SimpleCharge::where('our_number', '=', ('000'.$title->identificacaoTituloBanco) )->where('account_id', '=', $account->id)->whereNull('deleted_at')->first() ) {
                            array_push($errors, 'Nosso número '.$title->identificacaoTituloBanco.' já registrado em carteira simples para o documento '.$checkSimpleCharge->document.'. Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada para o Banco do Brasil. O nosso número de cada título deve ser único, em caso de dúvidas, entre em contato com a equipe de suporte do seu sistema para parametrizar a remessa. Se necessário, entre em contato com seu gerente de relacionamento para maiores esclarecimentos.');
                        }


                    }

                } else {
                    //Other Banks Validation
                    if( ($title->agenciaDebito.$title->digitoAgenciaDebito) != $account->charge_agency ){
                        array_push($errors, 'Agência definida no título é inválida para importação (Agência + DV devem ser: '.$account->charge_agency.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada. Em caso de dúvidas, entre em contato com seu gerente de relacionamento.');
                    }

                    if( ($title->contaCorrente.$title->digitoContaCorrente) != $account->charge_account ){
                        array_push($errors, 'Conta definida no título é inválida para importação (Conta + DV devem ser: '.$account->charge_account.'). Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.'. Conforme sua parametrização, é necessário importar uma remessa homologada. Em caso de dúvidas, entre em contato com seu gerente de relacionamento.');
                    }
                }
            }

            if($title->numeroInscricaoPagador == '' or $title->numeroInscricaoPagador == '00000000000000'){
                array_push($errors, 'Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.', CPF/CNPJ do sacado não informado, gere uma nova remessa com o CPF/CNPJ atualizado.');
            }

            ///validar CPF CNPJ sacado

            //validar CEP
            if(
                ( ($title->cep.$title->sufixoCep) == '00000000' )
                or ( ($title->cep.$title->sufixoCep) == '11111111' )
                or ( ($title->cep.$title->sufixoCep) == '22222222' )
                or ( ($title->cep.$title->sufixoCep) == '33333333' )
                or ( ($title->cep.$title->sufixoCep) == '44444444' )
                or ( ($title->cep.$title->sufixoCep) == '55555555' )
                or ( ($title->cep.$title->sufixoCep) == '66666666' )
                or ( ($title->cep.$title->sufixoCep) == '77777777' )
                or ( ($title->cep.$title->sufixoCep) == '88888888' )
                or ( ($title->cep.$title->sufixoCep) == '99999999' )
                or ( ($title->cep.$title->sufixoCep) == '        ' )
                or ( ($title->cep.$title->sufixoCep) == '' )
                or ( ($title->cep.$title->sufixoCep) == null )
            ) {
                array_push($errors, 'Não é possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento.', CEP informado é inválido, gere uma nova remessa com o CEP atualizado.');
            }
        }

        if(sizeof($errors) > 0){
            return response()->json(array("error" => $errors));
        } 

        foreach($cnabData->remittanceData->titles as $title) {

            $titleDistrict = $title->bairro;
            $titleCity = $title->municipio;
            $titleUf = null;

            $cepData = ZipCodeAddress::returnZipCodeData($title->cep.$title->sufixoCep);
            if($cepData->zip_code == ''){
                $viaCepAPI = new ViaCep();
                $viaCepAPI->cep = $title->cep.$title->sufixoCep;
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
                    }
                }
            }

            if( $cepData->city != '' and $cepData->city != null) {
                $titleCity = $cepData->city;
            }
            
            if( $cepData->district != '' and $cepData->district != null) {
                $titleDistrict = $cepData->district;
            }
            
            if( $cepData->state_id != '' and $cepData->state_id != null) {
                $titleUf = $cepData->state_id;
            }

            if (isset( $title->uf )) {
                if ($title->uf != '' and $title->uf != null) {
                    $titleUf = Facilites::convertStateToInt($title->uf);
                }
            }

            $payerData = (object) [
                'payer_cpf_cnpj'             => $title->numeroInscricaoPagador,
                'payer_name'                 => $title->nomePagador,
                'payer_fantasy_name'         => null,
                'payer_state_registration'   => null,
                'payer_address'              => $title->enderecoCompleto,
                'payer_address_zip_code'     => $title->cep.$title->sufixoCep,
                'payer_address_public_place' => '',//$cepData->public_place,
                'payer_address_number'       => null,
                'payer_address_complement'   => null,
                'payer_address_district'     => $titleDistrict,
                'payer_address_city'         => $titleCity,
                'payer_address_state_id'     => $titleUf,
                'payer_address_ibge_code'    => $cepData->ibge_code,
                'payer_address_gia_code'     => $cepData->gia_code,
                'payer_email_address'        => null,
                'payer_phone_number'         => null,
                'observation'                => 'Criado ao inserir título (CNAB)'
            ];
            $payer = $this->checkPayer($payerData, $registerMasterId, 1);
            $chargeData = null;
            $mensagem1 = "";
            $mensagem2 = "";
            $mensagem3 = "";
            $mensagem4 = "";
            $multa = 0;
            $juros = 0;

            if(isset($chargeConfig)){
                if($chargeConfig->cnab_messages == 1){
                    if(isset($title->mensagem1)){
                        $mensagem1 = $title->mensagem1;
                    }
                    if(isset($title->mensagem2)){
                        $mensagem2 = $title->mensagem2;
                    }
                    if(isset($title->mensagem3)){
                        $mensagem3 = $title->mensagem3;
                    }
                    if(isset($title->mensagem4)){
                        $mensagem4 = $title->mensagem4;
                    }
                } else {
                    $mensagem1 = $message1;
                    $mensagem2 = $message2;
                }

                if($chargeConfig->cnab_interest_fine == 1){
                    if(isset($title->percentualMulta)){
                        if($title->percentualMulta > 0){
                            $multa = $title->percentualMulta;
                        }
                    }
                    if(isset($title->valorCobradoPorDiaAtraso)){
                        if($title->valorCobradoPorDiaAtraso > 0){
                            $juros = round( ( ( ($title->valorCobradoPorDiaAtraso * 30) / $title->valor) * 100 ),0 );
                        }
                    }
                } else {
                    $multa = $fine;
                    $juros = $interest;
                }
            }

            $chargeData = (object) array(
                'master_id'                  => $masterId,
                'register_master_id'         => $registerMasterId,
                'account_id'                 => $accountId,
                'payer_detail_id'            => $payer,
                'chrg_imprt_tp_id'           => 3,
                'invoice'                    => $title->numeroDocumento,
                'issue_date'                 => $title->dataEmissao,
                'document'                   => $title->numeroDocumento,
                'due_date'                   => $title->dataVencimento,
                'value'                      => $title->valor,
                'file_name_imported'         => $fileName,
                'title_kind'                 => $title->especieTitulo,
                'our_number_imported'        => $title->identificacaoTituloBanco.$title->digitoAutoConferenciaNumeroBancario,
                'nfe_key'                    => $title->chaveNFE,
                'participant_control_number' => $title->numeroControleParticipante,
                'discount_bonus_day'         => $title->descontoBonificacaoDia,
                'discount_value'             => $title->valorDesconto,
                'discount_deadline'          => $title->dataLimiteDesconto,
                'fine_type'                  => $title->campoMulta,
                'fine_percentage'            => $title->percentualMulta,
                'amount_charged_day_delay'   => $title->valorCobradoPorDiaAtraso,
                'first_instruction'          => $title->primeiraInstrucao,
                'second_instruction'         => $title->segundaInstrucao,
                'message1'                   => $mensagem1,
                'message2'                   => $mensagem2,
                'message3'                   => $mensagem3,
                'message4'                   => $mensagem4,
                'fine'                       => $multa,
                'interest'                   => $juros,
                'payer_cpf_cnpj'             => $title->numeroInscricaoPagador,
                'payer_name'                 => $title->nomePagador,
                'payer_address_public_place' => null,
                'payer_address'              => $title->enderecoCompleto,
                'payer_address_number'       => null,
                'payer_address_complement'   => null,
                'payer_address_district'     => $cepData->district,
                'payer_address_city'         => $cepData->city,
                'payer_address_state'        => $cepData->short_state,
                'payer_zip_code'             => $title->cep.$title->sufixoCep,
                'observation'                => ""
            );

            if( ! $this->newCharge($chargeData) ){
                array_push($errors, 'Não foi possível inserir o título '.$title->numeroDocumento.', com vencimento em '.$title->dataVencimento);
            }

        }

        if( sizeof($errors) > 0 ){
            return response()->json(array("error" => $errors));
        } 

        return response()->json(array("success" => "Títulos do arquivo ".$fileName." inseridos com sucesso."));

    }

    protected function newTyped(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [218, 377];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
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

        if( (SystemFunctionMaster::where('system_function_id','=',6)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if(Account::where('id','=',$accountId)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
            return response()->json(array("error" => "Falha de verificação da conta"));
        }

        //check if expired
        $today = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
        if( (\Carbon\Carbon::parse( $request->due_date ))->format('Y-m-d') < $today ){
            return response()->json(array("error" => "Não é possível inserir título vencido"));
        }

        //check if future issue date
        if( (\Carbon\Carbon::parse( $request->issue_date ))->format('Y-m-d') > $today ){
            return response()->json(array("error" => "Não é possível inserir título com data de emissão superior ao dia útil atual"));
        }

        //check if need to register payer / return payer id if registered
        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;

        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return response()->json(array("error" => "CPF inválido"));
            }
        } else if(strlen($cpf_cnpj) == 14){
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json(array("error" => "CNPJ inválido"));
            }
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido"));
        }


        if( Payer::where('cpf_cnpj','=',$cpf_cnpj)->count() > 0 ){
            $payer = Payer::where('cpf_cnpj','=',$cpf_cnpj)->first();
            if( PayerDetail::where('payer_id','=',$payer->id)->where('register_master_id','=',$request->header('registerId'))->count() > 0 ){
                $payerDetail   = PayerDetail::where('payer_id','=',$payer->id)->where('register_master_id','=',$request->header('registerId'))->first();
                $payerDetailId = $payerDetail->id;
            } else {
                return response()->json(array("success" => "Realize o cadastro do sacado para continuar", "createPayer" => 1));
            }
        } else {
            return response()->json(array("success" => "Realize o cadastro do sacado para continuar", "createPayer" => 1));
        }

        //check if exists document
        if(Charge::where('document','=',$request->document)->where('register_master_id','=',$request->header('registerId'))->where('master_id','=',$request->header('masterId'))->whereNull('deleted_at')->count() > 0){
            return response()->json(array("error" => "Já existe duplicata com esse número cadastrada no sistema (Se não constar nessa tela, está em cobrança simples ou caucionada, ou ainda em análise para antecipação)"));
        }

        $chargeData = (object) array(
            'master_id'                  => $checkAccount->master_id,
            'register_master_id'         => $request->header('registerId'),
            'account_id'                 => $accountId,
            'payer_detail_id'            => $payerDetailId,
            'chrg_imprt_tp_id'           => 1,
            'invoice'                    => $request->invoice,
            'issue_date'                 => $request->issue_date,
            'document'                   => $request->document,
            'due_date'                   => $request->due_date,
            'value'                      => $request->value,
            'observation'                => $request->observation,
            'file_name_imported'         => null,
            'participant_control_number' => null
        );
        if($this->newCharge($chargeData)){
            return response()->json(array("success" => "Título inserido com sucesso", "createPayer" => 0));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao inserir o título", "createPayer" => 0));
        }
    }

    public function newCharge($chargeData)
    {
        if( $charge = Charge::Create([
            'uuid'                      =>  Str::orderedUuid(),
            'master_id'                  => $chargeData->master_id,
            'register_master_id'         => $chargeData->register_master_id,
            'account_id'                 => $chargeData->account_id,
            'payer_detail_id'            => $chargeData->payer_detail_id,
            'status_id'                  => 45,
            'chrg_imprt_tp_id'           => $chargeData->chrg_imprt_tp_id,
            'invoice'                    => mb_substr(trim($chargeData->invoice), 0, 10),
            'issue_date'                 => $chargeData->issue_date,
            'document'                   => mb_substr(trim($chargeData->document), 0, 10),
            'due_date'                   => $chargeData->due_date,
            'value'                      => $chargeData->value,
            'file_name_imported'         => $chargeData->file_name_imported,
            'participant_control_number' => $chargeData->participant_control_number,
            'our_number_imported'        => isset($chargeData->our_number_imported) ? $chargeData->our_number_imported : null,
            'observation'                => $chargeData->observation,
            'fine'                       => $chargeData->fine,
            'interest'                   => $chargeData->interest,
            'message1'                   => $chargeData->message1,
            'message2'                   => $chargeData->message2,
            'discount_value'             => isset($chargeData->discount_value) ? $chargeData->discount_value : null,
            'discount_deadline'          => isset($chargeData->discount_deadline) ? $chargeData->discount_deadline : null,
            'created_at'                 => \Carbon\Carbon::now()
        ]) ){
            return true;
        } else {
            return false;
        }
    }

    public function checkPayer($payerData, $registerMasterId, $updateData)
    {
        //check if payer exists
        if( Payer::where('cpf_cnpj','=',$payerData->payer_cpf_cnpj)->count() > 0 ){
            $payer = Payer::where('cpf_cnpj','=',$payerData->payer_cpf_cnpj)->first();
            if( PayerDetail::where('payer_id','=',$payer->id)->where('register_master_id','=',$registerMasterId)->count() > 0 ){
                $payerDetail = PayerDetail::where('payer_id','=',$payer->id)->where('register_master_id','=',$registerMasterId)->first();
                if($updateData == 0){
                    return $payerDetail->id;
                } else {
                    if($payerData->payer_email_address != ''){
                        if( PayerEmail::where('payer_detail_id', '=', $payerDetail->id)->where('email','=',$payerData->payer_email_address)->count() == 0 ) {
                            PayerEmail::removeAllMainEmail($payerDetail->id);
                            PayerEmail::Create([
                                'payer_detail_id'    => $payerDetail->id,
                                'email'              => $payerData->payer_email_address,
                                'main'               => 1,
                                'observation'        => 'Criado ao inserir título',
                                'created_at'         => \Carbon\Carbon::now()
                            ]);
                        }
                    }
                    if($payerData->payer_phone_number != ''){
                        if( PayerPhone::where('payer_detail_id', '=', $payerDetail->id)->where('number','=',$payerData->payer_phone_number)->count() == 0 ) {
                            PayerPhone::removeAllMainPhone($payerDetail->id);
                            PayerPhone::Create([
                                'payer_detail_id'    => $payerDetail->id,
                                'contact_type_id'    => 2,
                                'phone_type_id'      => 1,
                                'number'             => $payerData->payer_phone_number,
                                'main'               => 1,
                                'observation'        => 'Criado ao inserir título',
                                'created_at'         => \Carbon\Carbon::now()
                            ]);
                        }
                    }
                    if($payerData->payer_address != ''){
                        if( PayerAddress::where('payer_detail_id', '=', $payerDetail->id)->where('address', '=', $payerData->payer_address)->where('city', '=', $payerData->payer_address_city)->where('state_id', '=', $payerData->payer_address_state_id)->whereNull('register_deleted_at')->whereNull('deleted_at')->count() == 0 ) {
                            
                            if( 
                                $payerData->payer_address_city != '' and 
                                $payerData->payer_address_city != null and
                                $payerData->payer_address_zip_code != '' and 
                                $payerData->payer_address_zip_code != null
                            ) {
                                PayerAddress::removeAllMainAddress($payerDetail->id);
                            }

                            
                            PayerAddress::Create([
                                'payer_detail_id'    => $payerDetail->id,
                                'contact_type_id'    => 2,
                                'state_id'           => $payerData->payer_address_state_id,
                                'public_place'       => $payerData->payer_address_public_place,
                                'address'            => $payerData->payer_address,
                                'number'             => mb_substr($payerData->payer_address_number, 0, 25),
                                'complement'         => $payerData->payer_address_complement,
                                'district'           => $payerData->payer_address_district,
                                'city'               => $payerData->payer_address_city,
                                'zip_code'           => str_pad($payerData->payer_address_zip_code, 8, "0", STR_PAD_LEFT),
                                'ibge_code'          => $payerData->payer_address_ibge_code,
                                'gia_code'           => $payerData->payer_address_gia_code,
                                'main'               => 1,
                                'observation'        => 'Criado ao inserir título',
                                'created_at'         => \Carbon\Carbon::now()
                            ]);
                        }
                    }
                    return $payerDetail->id;
                }
            }
        }
        //create payer if not exists
        $payer = app('App\Http\Controllers\PayerController')->returnPayer($payerData->payer_cpf_cnpj, $payerData->payer_name, $payerData->payer_fantasy_name, $payerData->payer_state_registration, $payerData->observation, $registerMasterId);
        if($payer->status == 0 ){
            return null;
        } else {
            if($payerData->payer_email_address != null){
                PayerEmail::removeAllMainEmail($payer->success->id);
                PayerEmail::Create([
                    'payer_detail_id'    => $payer->success->id,
                    'email'              => $payerData->payer_email_address,
                    'main'               => 1,
                    'observation'        => 'Criado ao inserir título',
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            if($payerData->payer_phone_number != null){
                PayerPhone::removeAllMainPhone($payer->success->id);
                PayerPhone::Create([
                    'payer_detail_id'    => $payer->success->id,
                    'contact_type_id'    => 2,
                    'phone_type_id'      => 1,
                    'number'             => $payerData->payer_phone_number,
                    'main'               => 1,
                    'observation'        => 'Criado ao inserir título',
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            if($payerData->payer_address != null){
                PayerAddress::removeAllMainAddress($payer->success->id);
                PayerAddress::Create([
                    'payer_detail_id'    => $payer->success->id,
                    'contact_type_id'    => 2,
                    'state_id'           => $payerData->payer_address_state_id,
                    'public_place'       => $payerData->payer_address_public_place,
                    'address'            => $payerData->payer_address,
                    'number'             =>  mb_substr($payerData->payer_address_number,0,25),
                    'complement'         => $payerData->payer_address_complement,
                    'district'           => $payerData->payer_address_district,
                    'city'               => $payerData->payer_address_city,
                    'zip_code'           => str_pad($payerData->payer_address_zip_code, 8, "0", STR_PAD_LEFT),
                    'ibge_code'          => $payerData->payer_address_ibge_code,
                    'gia_code'           => $payerData->payer_address_gia_code,
                    'main'               => 1,
                    'observation'        => 'Criado ao inserir título',
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            return $payer->success->id;
        }
    }

    public function newPayer(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [218, 377];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $registerMasterId = (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id;
        $payerData        = app('App\Http\Controllers\PayerController')->returnPayer($request->payer_cpf_cnpj, trim($request->payer_name), $request->payer_fantasy_name, $request->payer_state_registration, $request->observation, $registerMasterId);

        if($payerData->status == 0 ){
            return response()->json(array("error" => $payerData->error));
        } else {
            PayerEmail::removeAllMainEmail($payerData->success->id);
            PayerPhone::removeAllMainPhone($payerData->success->id);
            PayerAddress::removeAllMainAddress($payerData->success->id);

            PayerEmail::Create([
                'payer_detail_id'    => $payerData->success->id,
                'email'              => $request->payer_email_address,
                'main'               => 1,
                'observation'        => 'Criado ao inserir título',
                'created_at'         => \Carbon\Carbon::now()
            ]);
            PayerPhone::Create([
                'payer_detail_id'    => $payerData->success->id,
                'contact_type_id'    => 2,
                'phone_type_id'      => 1,
                'number'             => $request->payer_phone_number,
                'main'               => 1,
                'observation'        => 'Criado ao inserir título',
                'created_at'         => \Carbon\Carbon::now()
            ]);
            PayerAddress::Create([
                'payer_detail_id'    => $payerData->success->id,
                'contact_type_id'    => 2,
                'state_id'           => $request->payer_address_state_id,
                'public_place'       => $request->payer_address_public_place,
                'address'            => $request->payer_address,
                'number'             => mb_substr($request->payer_address_number,0,25),
                'complement'         => $request->payer_address_complement,
                'district'           => $request->payer_address_district,
                'city'               => $request->payer_address_city,
                'zip_code'           => str_pad($request->payer_address_zip_code, 8, "0", STR_PAD_LEFT),
                'ibge_code'          => $request->payer_address_ibge_code,
                'gia_code'           => $request->payer_address_gia_code,
                'main'               => 1,
                'observation'        => 'Criado ao inserir título',
                'created_at'         => \Carbon\Carbon::now()
            ]);
            return response()->json(array("success" => "Sacado inserido com sucesso"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [220, 286, 379];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error = 0;
        $accountPJId = $request->header('accountPJId');
        $accountPFId = $request->header('accountPFId');
        if($accountPJId != ''){
            $accountId = $accountPJId;
        } else if($accountPFId != ''){
            $accountId = $accountPFId;
        } else {
            return response()->json(array("error" => "Falha ao verificar conta"));
        }
        if(Account::where('id','=',$accountId)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
            return response()->json(array("error" => "Falha de verificação da conta"));
        }

        foreach($request->title as $title){
            if( $titleData = Charge::where('id','=',$title)->where('master_id','=',$request->header('masterId'))->where('account_id','=',$accountId)->whereNull('deleted_at')->first() ) {
                if($titleData->status_id == 40 or $titleData->status_id == 43 or $titleData->status_id == 45 or $titleData->status_id == 13){
                    $titleData->deleted_at = \Carbon\Carbon::now();
                    if(!$titleData->save()){
                        $error++;
                    }
                }
            }

        }
        if($error == 0){
            return response()->json(array("success" => "Título(s) excluído(s) com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu uma falha, por favor recarregue e tente novamente"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [219, 285, 378];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
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
        if(Account::where('id','=',$accountId)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
            return response()->json(array("error" => "Falha de verificação da conta"));
        }

        if( Charge::where('id','=',$request->id)->where('master_id','=',$request->header('masterId'))->where('account_id','=',$accountId)->whereNull('deleted_at')->count() > 0){
            $titleData = Charge::where('id','=',$request->id)->where('master_id','=',$request->header('masterId'))->where('account_id','=',$accountId)->whereNull('deleted_at')->first();

            if($titleData->status_id == 44){
                return response()->json(array("error" => "Não é possível alterar título em simples cobrança"));
            }

            $chergeHistory = [
                'charge_id'          => $titleData->id,
                'user_id'            => $request->header('userId'),
                'master_id'          => $titleData->master_id,
                'register_master_id' => $titleData->register_master_id,
                'account_id'         => $titleData->account_id,
                'old_invoice'        => $titleData->invoice,
                'new_invoice'        => mb_substr(trim($request->invoice), 0, 10),
                'old_document'       => $titleData->document,
                'new_document'       => mb_substr(trim($request->document), 0, 10),
                'old_value'          => $titleData->value,
                'new_value'          => $request->value,
                'old_due_date'       => $titleData->due_date,
                'new_due_date'       => $request->due_date,
                'created_at'         => \Carbon\Carbon::now()
            ];

            $titleData->invoice     = mb_substr(trim($request->invoice), 0, 10);
            $titleData->document    = mb_substr(trim($request->document), 0, 10);
            $titleData->value       = $request->value;
            $titleData->due_date    = $request->due_date;
            $titleData->issue_date  = $request->issue_date;
            $titleData->fine        = $request->fine;
            $titleData->interest    = $request->interest;
            $titleData->message1    = $request->message1;
            $titleData->message2    = $request->message2;
            $titleData->observation = $request->observation;

            
            if( (\Carbon\Carbon::parse( $request->due_date ))->format('Y-m-d') < (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ){
                $titleData->status_id = 13;
            } else {
                $titleData->status_id = 45;
            }
            if($titleData->save()){
                ChargeHistory::create($chergeHistory);
                return response()->json(array("success" => "Título alterado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao atualizar o título"));
            }
        } else {
            return response()->json(array("error" => "Título não localizado"));
        }


    }

    protected function getChargeReturn(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [245, 305];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $chargeReturn                       = new Charge();
        $chargeReturn->master_id            = $checkAccount->master_id;
        $chargeReturn->account_id           = $checkAccount->account_id;
        $chargeReturn->remittance_charge_id = $request->remittance_charge_id;
        if($request->occurrence_date_start != ''){
            $chargeReturn->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $chargeReturn->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $remittanceTitles = $chargeReturn->getRemittance();


        $cnabTitles = [];
        foreach($remittanceTitles as $title){
            array_push($cnabTitles, [
                'agenciaDebito'                       => $title->agency_number,
                'contaCorrente'                       => $title->account_number,
                'agenciaDebitoCorrespondente'         => $title->bank_agency,
                'contaCorrenteCorrespondente'         => $title->bank_account,
                'contaCobranca'                       => mb_substr($title->control_number, 8, 7),
                'codigoBancoCorrespondente'           => mb_substr($title->bank_code,1,3),
                'nomeEmpresa'                         => $title->beneficiary_name,
                'numeroInscricaoEmpresa'              => $title->beneficiary_cpf_cnpj,
                'numeroControleParticipante'          => $title->participant_control_number,
                'identificacaoTituloBanco'            => $title->our_number,
                'carteira'                            => $title->wallet_number,
                'identificacaoOcorrencia'             => $title->occurrence_id,
                'dataPagamento'                       => (\Carbon\Carbon::parse( $title->payment_date ))->format('Y-m-d'),
                'dataCredito'                         => (\Carbon\Carbon::parse( $title->payment_occurrence_date ))->format('Y-m-d'),
                'numeroDocumento'                     => $title->document,
                'dataVencimento'                      => (\Carbon\Carbon::parse( $title->due_date ))->format('Y-m-d'),
                'valor'                               => $title->value,
                'bancoPagamento'                      => $title->bank_payment,
                'agenciaPagamento'                    => $title->agency_payment,
                'especieTitulo'                       => '01',
                'valorDespesa'                        => $title->tax_value,
                'outrasDespesasCustaCartorio'         => 0,
                'jurosOperacaoAtraso'                 => 0,
                'valorIof'                            => 0,
                'valorAbatimentoConcedidoOuCancelado' => 0,
                'valorDesconto'                       => 0,
                'valorPago'                           => $title->payment_value,
                'jurosMora'                           => (($title->payment_value > $title->value) ?  ($title->payment_value - $title->value) :  0),
                'outrosCreditos'                      => 0,
                'dataOcorrencia'                      => (\Carbon\Carbon::parse( $title->occurrence_date ))->format('Y-m-d'),
                'codigoBarras'                        => $title->bar_code,
                'linhaDigitavel'                      => $title->digitable_line,
                'numeroConvenio'                      => mb_substr($title->our_number, -17, 7),
                'variacaoCarteira'                    => $title->wallet_variation

            ]);
        }

        if($cnabTitles != []){
            $cnab              = new SimpleCNAB();
            $cnab->cnabData    = json_encode($cnabTitles);
            $cnab->pathFile    = Storage::disk('remittance')->path('/');
            $cnab->returnType  = 'client';

            $accountData = Account::where('id', '=', $checkAccount->account_id)->first();
            if($accountData->charge_registration_api_id == 13){
                $rendimentoReturn  = $cnab->writeReturnBrasil400();
            } else {
                $rendimentoReturn  = $cnab->writeReturnRendimento400();
            }
            
            
            if($rendimentoReturn->success){
                $base64File = base64_encode(Storage::disk('remittance')->get($rendimentoReturn->file_name));
                \File::delete($cnab->pathFile.$rendimentoReturn->file_name);
                return response()->json(array(
                    "success"    => "Arquivo de retorno gerado com sucesso",
                    "file_name"  => $rendimentoReturn->file_name,
                    "mime_type"  => "text/plain",
                    "base64"     => $base64File
                ));
            } else {
                return response()->json(array("error" => $rendimentoReturn->error));
            }

        } else {
            return response()->json(array("error" => "Sem títulos para retorno"));
        }
    }

    protected function getChargeReturnTitles(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [244, 304];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        $chargeReturn                   = new Charge();
        $chargeReturn->master_id        = $checkAccount->master_id;
        $chargeReturn->account_id       = $checkAccount->account_id;
        if($request->occurrence_date_start != ''){
            $chargeReturn->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $chargeReturn->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        return response()->json($chargeReturn->getRemittance());
    }

    protected function getTitleRendimento(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig                                        = new ApiConfig();
        $apiConfig->master_id                             = $request->header('masterId');
        $apiConfig->api_id                                = 1;
        $apiConfig->onlyActive                            = 1;
        $apiData                                          = $apiConfig->getApiConfig()[0];
        $apiRendimento                                    = new ApiBancoRendimento();
        $apiRendimento->id_cliente                        = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                      = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                      = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                      = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                           = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                    = Crypt::decryptString($apiData->api_account);
        $apiRendimento->tit_data_inicio                   = $request->start_dt;
        $apiRendimento->tit_data_fim                      = $request->end_dt;
        $apiRendimento->tit_nosso_numero                  = $request->our_number;
        if($request->return_type == 1){
            return response()->json($apiRendimento->tituloConsultar());
        } else if($request->return_type == 2) {
            return response()->json($apiRendimento->tituloConsultarBoleto());
        } else {

            return response()->json($apiRendimento->consultarDadosBoleto());
        }
    }

    public function conciliationRendimento(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $apiConfig                                        = new ApiConfig();
        $apiConfig->master_id                             = 1;
        $apiConfig->api_id                                = 1;
        $apiConfig->onlyActive                            = 1;
        $apiData                                          = $apiConfig->getApiConfig()[0];
        $apiRendimento                                    = new ApiBancoRendimento();
        $apiRendimento->id_cliente                        = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                      = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                      = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                      = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                           = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                    = Crypt::decryptString($apiData->api_account);
        $apiRendimento->tit_data_inicio                   = '2021-07-18';//$request->start_dt;
        $apiRendimento->tit_data_fim                      = '2021-07-19';//$request->end_dt;
        return response()->json( $apiRendimento->conciliacaoCobranca() );
    }

    protected function newTyped2(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [218, 284, 377];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $errors = [];

        foreach(json_decode($request->parcels) as $parcel){
            if(PayerDetail::where('id','=',$request->payer_detail_id)->count() > 0 ){
                if((\Carbon\Carbon::parse($request->issue_date))->format('Y-m-d') > \Carbon\Carbon::now()->format('Y-m-d')){
                    array_push($errors, 'Não foi possível inserir o título '.$parcel->document.', data de emissão não pode ser superior a data atual');
                }

                if((\Carbon\Carbon::parse($parcel->due_date))->format('Y-m-d') < \Carbon\Carbon::now()->format('Y-m-d')){
                    array_push($errors, 'Não foi possível inserir o título '.$parcel->document.', data de vencimento não pode ser inferior a data atual');
                }

                if(Charge::where('document','=',$parcel->document)->where('register_master_id','=',$request->header('registerId'))->where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->count() > 0){
                    array_push($errors, 'Não foi possível inserir o título '.$parcel->document.', já existe título com esse número de duplicata cadastrada no sistema (Se não constar nessa tela, está em cobrança simples ou caucionada, ou ainda em análise para antecipação)');
                }

                $fine         = null;
                $interest     = null;
                $message1     = null;
                $message2     = null;
                
                if(ChargeConfig::where('account_id','=', $checkAccount->account_id)->count() > 0){
                    $chargeConfig = ChargeConfig::where('account_id','=', $checkAccount->account_id)->first();
                    $fine         = $chargeConfig->fine;
                    $interest     = $chargeConfig->interest;
                    $message1     = $chargeConfig->message1;
                    $message2     = $chargeConfig->message2;
                }

                $chargeData = null;
                $chargeData = (object) array(
                    'master_id'                  => $checkAccount->master_id,
                    'register_master_id'         => $request->header('registerId'),
                    'account_id'                 => $checkAccount->account_id,
                    'payer_detail_id'            => $request->payer_detail_id,
                    'chrg_imprt_tp_id'           => 1,
                    'invoice'                    => $request->invoice,
                    'issue_date'                 => $request->issue_date,
                    'document'                   => $parcel->document,
                    'due_date'                   => $parcel->due_date,
                    'value'                      => $parcel->value,
                    'observation'                => $parcel->observation,
                    'file_name_imported'         => null,
                    'participant_control_number' => null,
                    'fine'                       => $fine,
                    'interest'                   => $interest,
                    'message1'                   => $message1,
                    'message2'                   => $message2
                );
                if(!$this->newCharge($chargeData)){
                    array_push($errors, 'Não foi possível inserir o título '.$parcel->document.', ocorreu uma falha ao inserir o tiítulo, por favor tente novamente mais tarde');
                }
            } else {
                array_push($errors, 'Sacado não cadastrado para o título '.$parcel->document.', por favor realize o cadastro do sacado e tente novamente');
            }
        }

        if(count($errors) > 0){
            return response()->json(array("success" => "Não foi possível inserir alguns títulos | Erros: ".(implode("|",$errors))));
        } else {
            return response()->json(array("success" => "Títulos inseridos com sucesso"));
        }          
    }

    public function titleLiquidationDinari()
    {
        $bancoRendimentoClass = new BancoRendimentoClass();
        $bancoRendimentoClass->billetLiquidationDinari();
        return true;
    }

    public function titleLiquidationIp4y()
    {
        $bancoRendimentoClass = new BancoRendimentoClass();
        $bancoRendimentoClass->billetLiquidationIp4y();
        return true;
    }

}
