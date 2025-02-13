<?php

namespace App\Http\Controllers;

use App\Models\AntecipationReview;
use App\Models\Account;
use App\Models\SimpleCharge;
use App\Models\SimpleChargeHistory;
use App\Models\AntecipationBatch;
use App\Models\Charge;
use App\Models\ChargeHistory;
use App\Libraries\SimpleCNAB;
use App\Libraries\sendMail;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use File;

class AntecipationReviewController extends Controller
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

        $antecipationReview = new AntecipationReview();
        $antecipationReview->id                      = $request->id;
        $antecipationReview->unique_id               = $request->unique_id;
        $antecipationReview->master_id               = $checkAccount->master_id;
        $antecipationReview->register_master_id      = $request->register_master_id;
        $antecipationReview->account_id              = $request->account_id;
        $antecipationReview->status_id_in            = $request->status_id_in;
        $antecipationReview->batch_id                = $request->batch_id;
        $antecipationReview->batch_unique_id         = $request->batch_unique_id;
        $antecipationReview->financial_agent_id      = $request->financial_agent_id;
        $antecipationReview->batch_date              = $request->batch_date;
        $antecipationReview->batch_date_start        = $request->batch_date_start;
        $antecipationReview->batch_date_end          = $request->batch_date_end;
        $antecipationReview->batch_created_at        = $request->batch_created_at;
        $antecipationReview->batch_created_at_start  = $request->batch_created_at_start;
        $antecipationReview->batch_created_at_end    = $request->batch_created_at_end;
        $antecipationReview->batch_approved_at       = $request->batch_approved_at;
        $antecipationReview->batch_approved_at_start = $request->batch_approved_at_start;
        $antecipationReview->batch_approved_at_end   = $request->batch_approved_at_end;
        $antecipationReview->batch_number            = $request->batch_number;

        return response()->json($antecipationReview->get());
    }

    protected function approveTitle(Request $request)
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

        $ids       = $request->id;
        $unique_id = $request->unique_id;
        $x = 0;

        $checkTitle = AntecipationReview::where('id','=',$ids[0])->where('unique_id','=',$unique_id[0])->first();
        $checkBatch = AntecipationBatch::where('id','=',$checkTitle->batch_id)->first();
        if($checkBatch->status_id == 9){
            return response()->json(array("error" => "Não é possível mudar o estado de um título que pertence a um lote liberado"));
        }
        foreach($ids as $id){
            $title = AntecipationReview::where('id','=',$id)->where('unique_id','=',$unique_id[$x])->first();
            $title->status_id = 9;
            $title->save();
            $x++;
        }
        $antecipationBatch                    =  AntecipationBatch::where('id','=',$title->batch_id)->first();
        $antecipationBatch->approved_value    =  AntecipationReview::where('batch_id','=',$title->batch_id)->where('status_id','=',9)->sum('value');
        $antecipationBatch->disapproved_value =  AntecipationReview::where('batch_id','=',$title->batch_id)->where('status_id','=',11)->sum('value');
        $antecipationBatch->save();
        return response()->json(
            array(
                "success"                 => "Título(s) aprovado(s) com sucesso", 
                "batch_approved_value"    => $antecipationBatch->approved_value, 
                "batch_disapproved_value" => $antecipationBatch->disapproved_value
            )
        );
    }

    protected function disapproveTitle(Request $request)
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

        $ids       = $request->id;
        $unique_id = $request->unique_id;
        $x = 0;

        $checkTitle = AntecipationReview::where('id','=',$ids[0])->where('unique_id','=',$unique_id[0])->first();
        $checkBatch = AntecipationBatch::where('id','=',$checkTitle->batch_id)->first();
        if($checkBatch->status_id == 9){
            return response()->json(array("error" => "Não é possível mudar o estado de um título que pertence a um lote liberado"));
        }

        foreach($ids as $id){
            $title = AntecipationReview::where('id','=',$id)->where('unique_id','=',$unique_id[$x])->first();
            $title->status_id = 11;
            $title->save();
            $x++;
        }
        $antecipationBatch                    =  AntecipationBatch::where('id','=',$title->batch_id)->first();
        $antecipationBatch->approved_value    =  AntecipationReview::where('batch_id','=',$title->batch_id)->where('status_id','=',9)->sum('value');
        $antecipationBatch->disapproved_value =  AntecipationReview::where('batch_id','=',$title->batch_id)->where('status_id','=',11)->sum('value');
        $antecipationBatch->save();
        return response()->json(
            array(
                "success"                 => "Título(s) recusado(s) com sucesso", 
                "batch_approved_value"    => $antecipationBatch->approved_value, 
                "batch_disapproved_value" => $antecipationBatch->disapproved_value
            )
        );
    }
    
    protected function simpleToReview(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [101, 228, 294];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // check if account can send antecipation
        $account = Account::where('id', '=', $checkAccount->account_id)->first();
        
        if( $account->can_send_charge_to_anticipation_review != 1 ) {
            return response()->json(array("error" => "Sua conta não está habilitada a enviar títulos para antecipação. Em caso de dúvidas, entre em contato com seu gerente de relacionamento."));
        }

        if(SimpleCharge::whereIn('id',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->whereNull('payment_date')->whereNull('down_date')->whereIn('status_id',[4])->whereIn('antcptn_status_id',[40,45])->count() > 0){
            $antecipationBatchId = $this->createAntecipationBatch($checkAccount->account_id);
            if($antecipationBatchId == null){
                return response()->json(array("error" => "Ocorreu uma falha ao criar o lote de análise de antecipação, por favor tente novamente em alguns minutos"));
            }
            $simpleChargeTitles = SimpleCharge::whereIn('id',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->whereNull('payment_date')->whereNull('down_date')->whereIn('status_id',[4])->whereIn('antcptn_status_id',[40,45])->get();
            $value = 0;
            $arrayErrors = [];
            foreach($simpleChargeTitles as $title){
                $importType       = null;
                $fileNameImported = null;
                if($title->charge_id != null){
                    $chargeTitle = Charge::where('id','=',$title->charge_id)->first();
                    $importType       = $chargeTitle->chrg_imprt_tp_id;
                    $fileNameImported = $chargeTitle->file_name_imported;
                }
                if($title->antcptn_status_id == 45 || $title->antcptn_status_id == 40 ){
                    if($this->addReviewTitle( [
                        'unique_id'          => md5('2'.$checkAccount->account_id.$title->id.date('Ymd').time()),
                        'origin_id'          => $title->id,
                        'origin_type_id'     => 2,
                        'batch_id'           => $antecipationBatchId,
                        'master_id'          => $title->master_id,
                        'register_master_id' => $title->register_master_id,
                        'account_id'         => $title->account_id,
                        'payer_detail_id'    => $title->payer_detail_id,
                        'issue_date'         => $title->issue_date,
                        'document'           => $title->document,
                        'due_date'           => $title->due_date,
                        'value'              => $title->value,
                        'status_id'          => 39,
                        'bank_code'          => $title->bank_code,
                        'agency'             => $title->agency,
                        'account'            => $title->account,
                        'wallet_number'      => $title->wallet_number,
                        'our_number'         => $title->our_number,
                        'bar_code'           => $title->bar_code,
                        'digitable_line'     => $title->digitable_line,
                        'control_number'     => $title->control_number,
                        'dda'                => $title->dda,
                        'chrg_imprt_tp_id'   => $importType,
                        'file_name_imported' => $fileNameImported,
                        'api_id'             => $title->api_id,
                        'created_at'         => \Carbon\Carbon::now()
                    ])){
                        $value += $title->value;
                        $simpleChargeTitle = SimpleCharge::where('id','=',$title->id)->first();
                        $simpleChargeTitle->antcptn_status_id = 39;
                        $simpleChargeTitle->save();
                        SimpleChargeHistory::create([
                            'simple_charge_id'    => $simpleChargeTitle->id,
                            'master_id'           => $simpleChargeTitle->master_id,
                            'register_master_id'  => $simpleChargeTitle->register_master_id,
                            'account_id'          => $simpleChargeTitle->account_id,
                            'description'         => 'Enviado para análise de antecipação',
                            'created_at'          => \Carbon\Carbon::now(),
                        ]);
                    } else {
                        array_push($arrayErrors, 'Não foi possível acrescentar o título '.$title->document.' para análise de antecipação');
                    }
                } else {
                    array_push($arrayErrors, 'Não foi possível acrescentar o título '.$title->document.' para análise de antecipação, pois já consta em análise');
                }
            }
            $antecipationBatch = AntecipationBatch::where('id','=',$antecipationBatchId)->first();
            $antecipationBatch->total_value = $value;
            if($antecipationBatch->save()){
                if(sizeof($arrayErrors) > 0){
                    return response()->json(array("error" => "Não foi possível enviar alguns títulos para a análise de antecipação", "errorList" => $arrayErrors));
                } else {

                    $antecipationReview           = new AntecipationReview();
                    $antecipationReview->batch_id = $antecipationBatchId;
                    $titles                       = $antecipationReview->get();
                    $cnabTitles = [];
                    $sumValue = 0;
                    $emailTable = "<table width='100%'><tr> <td><b>Documento</b></td><td><b>Valor</b></td><td><b>Vencimento</b></td><td><b>Sacado</b></td><td><b>Inserção</b></td> </tr>";
                    foreach($titles as $title){
                        array_push($cnabTitles, [
                            'codigoEmpresa'                         => $title->register_cpf_cnpj,
                            'nomeEmpresa'                           => $title->register_name,
                            'numeroSequencialRemessa'               => $title->batch_id,
                            'agenciaDebito'                         => '00001',
                            'digitoAgenciaDebito'                   => '0',
                            'razaoContaCorrente'                    => mb_substr($title->account_number, 0, 2),
                            'contaCorrente'                         => mb_substr($title->account_number, 2, 7),
                            'digitoContaCorrente'                   => mb_substr($title->account_number, 9, 1),
                            'identificacaoEmpresaBeneficiariaBanco' => $title->account_number,
                            'numeroControleParticipante'            => $title->id,
                            'identificacaoTituloBanco'              => ltrim(substr($title->our_number,0,-1),'0'),
                            'digitoAutoConferenciaNumeroBancario'   => substr($title->our_number, -1),
                            'identificacaoOcorrencia'               => '01',
                            'numeroDocumento'                       => $title->document,
                            'dataVencimento'                        => \Carbon\Carbon::parse($title->due_date)->format('Y-m-d'),
                            'valor'                                 => $title->value,
                            'especieTitulo'                         => '01',
                            'dataEmissao'                           => \Carbon\Carbon::parse($title->issue_date)->format('Y-m-d'),
                            'numeroInscricaoPagador'                => $title->payer_cpf_cnpj,
                            'nomePagador'                           => $title->payer_name,
                            'enderecoCompleto'                      => $title->payer_address_public_place." ".$title->payer_address." ".$title->payer_address_number,
                            'cep'                                   => substr($title->payer_address_zip_code,0,-3),
                            'sufixoCep'                             => substr($title->payer_address_zip_code, -3)
                        ]);
                        $emailTable .= "
                            <tr>
                                <td>$title->document</td>
                                <td>".number_format($title->value,2,',','.')."</td>
                                <td>".\Carbon\Carbon::parse($title->due_date)->format('d/m/Y')."</td>
                                <td>$title->payer_name - $title->payer_cpf_cnpj</td>
                                <td>$title->charge_import_type_description</td>
                            </tr>
                        ";
                        $sumValue += $title->value;
                    }
                    $emailTable .= "
                            <tr>
                                <td><b>TOTAL</b></td>
                                <td>".number_format($sumValue,2,',','.')."</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        ";
                    $emailTable .= "</table>";
                    if($cnabTitles != null){
                        $cnab = new SimpleCNAB();
                        $cnab->cnabData = json_encode($cnabTitles);
                        $cnab->pathFile = Storage::disk('remittance')->path('/');
                        $cnabStatus = $cnab->writeRemittanceBradesco400();
                    }
                    if($cnabStatus->success){
                        $remittanceFilePath  = '../storage/app/remittance/';
                        $chargeFilePath      = '../storage/app/charge_upload/';
                        $remittanceFileName  = $cnabStatus->file_name;
                        $attach_multiples = [];
                        array_push($attach_multiples , [
                            'attach_path' => $remittanceFilePath.$remittanceFileName,
                            'attach_file' => $remittanceFileName,
                            'mime'        => 'text/plain'
                        ]);

                        
                        foreach( $antecipationReview->getSimpleChargeFilesImporteds() as $fileImported ){
                            if( file_exists($chargeFilePath.$fileImported->file_name_imported) ){
                                if(substr($fileImported->file_name_imported, -3) == 'xml'){                           
                                    array_push($attach_multiples, [
                                        'attach_path' => $chargeFilePath.$fileImported->file_name_imported,
                                        'attach_file' => $fileImported->file_name_imported,
                                        'mime'        => 'application/xml'
                                    ]);
                                }
                            }
                        }

                        $message = "Olá, <br>
                            <b>$title->register_name</b> enviou uma nova remessa para análise de antecipação de títulos <b>registrados em carteira simples</b>.<br><br>
                        ".$emailTable;
                        $sendMail = new sendMail();
                        $sendMail->to_mail               = 'analise@dinari.com.br';
                        $sendMail->to_name               = 'Analise';
                        $sendMail->send_cc               = 0;
                        $sendMail->to_cc_mail            = null;
                        $sendMail->to_cc_name            = null;
                        $sendMail->send_cco              = 0;
                        $sendMail->to_cco_mail           = 'ragazzi@dinari.com.br';
                        $sendMail->to_cco_name           = 'Ragazzi';
                        $sendMail->attach_multiple       = 1;
                        $sendMail->attach_multiple_files = $attach_multiples;
                        $sendMail->subject               = 'Remessa para análise de '.$title->register_name;
                        $sendMail->email_layout          = 'emails/confirmEmailAccount';
                        $sendMail->bodyMessage           = $message;
                        if($sendMail->send()){
                            File::delete($remittanceFilePath.$remittanceFileName);
                        }
                    }
                    return response()->json(array("success" => "Títulos enviados para análise de antecipação com sucesso"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao atualizar o lote de análise de antecipação"));
            }
        } else {
            return response()->json(array("error" => "Nenhum título localizado para análise de antecipação"));
        }
    }

    protected function addToReview(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [222, 288, 381];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        
        // check if account can send antecipation
        $account = Account::where('id', '=', $checkAccount->account_id)->first();
        
        if( $account->can_send_charge_to_anticipation_review != 1 ) {
            return response()->json(array("error" => "Gere a cobrança simples. Sua conta não está habilitada a enviar títulos para antecipação. Em caso de dúvidas, entre em contato com seu gerente de relacionamento."));
        }

        if(Charge::whereIn('id',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->whereIn('status_id',[45])->count() > 0){
            $antecipationBatchId = $this->createAntecipationBatch($checkAccount->account_id);
            if($antecipationBatchId == null){
                return response()->json(array("error" => "Ocorreu uma falha ao criar o lote de análise de antecipação, por favor tente novamente em alguns minutos"));
            }
            $chargeTitles = Charge::whereIn('id',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$checkAccount->account_id)->whereIn('status_id',[45])->get();
            $value = 0;
            $arrayErrors = [];
            $statusId = 6;

            if( $checkAccount->is_master ) {
                $statusId = 9;
            }

            foreach($chargeTitles as $title){
                if($this->addReviewTitle( [
                    'unique_id'          => md5('1'.$checkAccount->account_id.$title->id.date('Ymd').time()),
                    'origin_id'          => $title->id,
                    'origin_type_id'     => 1,
                    'batch_id'           => $antecipationBatchId,
                    'master_id'          => $title->master_id,
                    'register_master_id' => $title->register_master_id,
                    'account_id'         => $title->account_id,
                    'payer_detail_id'    => $title->payer_detail_id,
                    'issue_date'         => $title->issue_date,
                    'document'           => $title->document,
                    'due_date'           => $title->due_date,
                    'value'              => $title->value,
                    'chrg_imprt_tp_id'   => $title->chrg_imprt_tp_id,
                    'file_name_imported' => $title->file_name_imported,
                    'status_id'          => 6,
                    'created_at'         => \Carbon\Carbon::now()
                ])){
                    $value += $title->value;
                    $chargeTitle = Charge::where('id','=',$title->id)->first();
                    $chargeTitle->status_id = 39;
                    $chargeTitle->save();

                    ChargeHistory::create([
                        'charge_id'           => $chargeTitle->id,
                        'master_id'           => $chargeTitle->master_id,
                        'register_master_id'  => $chargeTitle->register_master_id,
                        'account_id'          => $chargeTitle->account_id,
                        'description'         => 'Enviado para análise de antecipação',
                        'created_at'          => \Carbon\Carbon::now(),
                    ]);
                } else {
                    array_push($arrayErrors, 'Não foi possível acrescentar o título '.$title->document.' para análise de antecipação');
                }
            }
            $antecipationBatch = AntecipationBatch::where('id','=',$antecipationBatchId)->first();
            $antecipationBatch->total_value = $value;
            if($antecipationBatch->save()){
                if(sizeof($arrayErrors) > 0){
                    return response()->json(array("error" => "Não foi possível enviar alguns títulos para a análise de antecipação", "errorList" => $arrayErrors));
                } else {
                    if( ! $checkAccount->is_master ) {
                        $antecipationReview           = new AntecipationReview();
                        $antecipationReview->batch_id = $antecipationBatchId;
                        $titles                       = $antecipationReview->get();
                        $cnabTitles = [];
                        $sumValue = 0;
                        $emailTable = "<table width='100%'><tr> <td><b>Documento</b></td><td><b>Valor</b></td><td><b>Vencimento</b></td><td><b>Sacado</b></td><td><b>Inserção</b></td> </tr>";
                        foreach($titles as $title){
                            array_push($cnabTitles, [
                                'codigoEmpresa'                         => $title->register_cpf_cnpj,
                                'nomeEmpresa'                           => $title->register_name,
                                'numeroSequencialRemessa'               => $title->batch_id,
                                'agenciaDebito'                         => '00001',
                                'digitoAgenciaDebito'                   => '0',
                                'razaoContaCorrente'                    => mb_substr($title->account_number, 0, 2),
                                'contaCorrente'                         => mb_substr($title->account_number, 2, 7),
                                'digitoContaCorrente'                   => mb_substr($title->account_number, 9, 1),
                                'identificacaoEmpresaBeneficiariaBanco' => $title->id,
                                'numeroControleParticipante'            => $title->id,
                                'identificacaoTituloBanco'              => substr($title->our_number,0,-1),
                                'digitoAutoConferenciaNumeroBancario'   => substr($title->our_number, -1),
                                'identificacaoOcorrencia'               => '01',
                                'numeroDocumento'                       => $title->document,
                                'dataVencimento'                        => \Carbon\Carbon::parse($title->due_date)->format('Y-m-d'),
                                'valor'                                 => $title->value,
                                'especieTitulo'                         => '01',
                                'dataEmissao'                           => \Carbon\Carbon::parse($title->issue_date)->format('Y-m-d'),
                                'numeroInscricaoPagador'                => $title->payer_cpf_cnpj,
                                'nomePagador'                           => $title->payer_name,
                                'enderecoCompleto'                      => $title->payer_address_public_place." ".$title->payer_address." ".$title->payer_address_number,
                                'cep'                                   => substr($title->payer_address_zip_code,0,-3),
                                'sufixoCep'                             => substr($title->payer_address_zip_code, -3)
                            ]);
                            $emailTable .= "
                            <tr>
                                <td>$title->document</td>
                                <td>".number_format($title->value,2,',','.')."</td>
                                <td>".\Carbon\Carbon::parse($title->due_date)->format('d/m/Y')."</td>
                                <td>$title->payer_name - $title->payer_cpf_cnpj</td>
                                <td>$title->charge_import_type_description</td>
                            </tr>
                            ";
                            $sumValue += $title->value;
                        }
                    
                        $emailTable .= "
                            <tr>
                                <td><b>TOTAL</b></td>
                                <td>".number_format($sumValue,2,',','.')."</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        ";
                        $emailTable .= "</table>";
                        
                        if($cnabTitles != null){
                            $cnab = new SimpleCNAB();
                            $cnab->cnabData = json_encode($cnabTitles);
                            $cnab->pathFile = Storage::disk('remittance')->path('/');
                            $cnabStatus = $cnab->writeRemittanceBradesco400();
                        }
                        if($cnabStatus->success){
                            $remittanceFilePath  = '../storage/app/remittance/';
                            $chargeFilePath      = '../storage/app/charge_upload/';
                            $remittanceFileName  = $cnabStatus->file_name;
                            $message = "Olá, <br>
                                <b>$title->register_name</b> enviou uma nova remessa para análise de antecipação para <b>títulos não registrados</b>.<br><br>
                            ".$emailTable;
                            $attach_multiples = [];
                            array_push($attach_multiples , [
                                'attach_path' => $remittanceFilePath.$remittanceFileName,
                                'attach_file' => $remittanceFileName,
                                'mime'        => 'text/plain'
                            ]);

                            foreach( $antecipationReview->getChargeFilesImporteds() as $fileImported ){
                                if( file_exists($chargeFilePath.$fileImported->file_name_imported) ){
                                    if(substr($fileImported->file_name_imported, -3) == 'xml'){                           
                                        array_push($attach_multiples, [
                                            'attach_path' => $chargeFilePath.$fileImported->file_name_imported,
                                            'attach_file' => $fileImported->file_name_imported,
                                            'mime'        => 'application/xml'
                                        ]);
                                    }
                                }
                            }
                            
                            $sendMail = new sendMail();
                            $sendMail->to_mail               = 'analise@dinari.com.br';
                            $sendMail->to_name               = 'Analise';
                            $sendMail->send_cc               = 0;
                            $sendMail->to_cc_mail            = null;
                            $sendMail->to_cc_name            = null;
                            $sendMail->send_cco              = 0;
                            $sendMail->to_cco_mail           = 'ragazzi@dinari.com.br';
                            $sendMail->to_cco_name           = 'Ragazzi';
                            $sendMail->attach_multiple       = 1;
                            $sendMail->attach_multiple_files = $attach_multiples;
                            /* $sendMail->attach_pdf   = 1;
                            $sendMail->attach_path  = $remittanceFilePath.$remittanceFileName;
                            $sendMail->attach_file  = $remittanceFileName;
                             */
                            $sendMail->subject      = 'Remessa para análise de '.$title->register_name;
                            $sendMail->email_layout = 'emails/confirmEmailAccount';
                            $sendMail->bodyMessage  = $message;
                            if($sendMail->send()){

                                File::delete($remittanceFilePath.$remittanceFileName);
                            } else {
                                return response()->json(array("error" => "erro"));
                            }
                        }
                    }
                    return response()->json(array("success" => "Títulos enviados para análise de antecipação com sucesso"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao atualizar o lote de análise de antecipação"));
            }
        } else {
            return response()->json(array("error" => "Nenhum título localizado para análise de antecipação"));
        }
    }

    protected function createAntecipationBatch($accountId)
    {
        $accountData = Account::where('id','=',$accountId)->first();
        $antecipationBatch = AntecipationBatch::create([
            'unique_id'          => md5($accountData->id.$accountData->master_id.date('Ymd').time()),
            'master_id'          => $accountData->master_id,
            'register_master_id' => $accountData->register_master_id,
            'account_id'         => $accountId,
            'status_id'          => 6,
            'batch_date'         => (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d'),
            'created_at'         => \Carbon\Carbon::now()
        ]);
        return $antecipationBatch->id;
    }

    protected function addReviewTitle($titleData)
    {
        if(AntecipationReview::create($titleData)){
            return true;
        } else {
            return false;
        }
    }
    
    protected function createCNAB(Request $request)
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

        $antecipationReview           = new AntecipationReview();
        $antecipationReview->idIn     = $request->id;
        $antecipationReview->batch_id = $request->batch_id;
        $titles                       = $antecipationReview->get();

        $cnabTitles = [];
        foreach($titles as $title){
            array_push($cnabTitles, [
                'codigoEmpresa'                         => $title->register_cpf_cnpj,
                'nomeEmpresa'                           => $title->register_name,
                'numeroSequencialRemessa'               => $title->batch_id,
                'agenciaDebito'                         => '00001',
                'digitoAgenciaDebito'                   => '0',
                'razaoContaCorrente'                    => mb_substr($title->account_number, 0, 2),
                'contaCorrente'                         => mb_substr($title->account_number, 2, 7),
                'digitoContaCorrente'                   => mb_substr($title->account_number, 9, 1),
                'identificacaoEmpresaBeneficiariaBanco' => $title->account_number,
                'numeroControleParticipante'            => $title->id,
                'identificacaoTituloBanco'              => ltrim(substr($title->our_number,0,-1),'0'),
                'digitoAutoConferenciaNumeroBancario'   => substr($title->our_number, -1),
                'identificacaoOcorrencia'               => '01',
                'numeroDocumento'                       => $title->document,
                'dataVencimento'                        => \Carbon\Carbon::parse($title->due_date)->format('Y-m-d'),
                'valor'                                 => $title->value,
                'especieTitulo'                         => '01',
                'dataEmissao'                           => \Carbon\Carbon::parse($title->issue_date)->format('Y-m-d'),
                'numeroInscricaoPagador'                => $title->payer_cpf_cnpj,
                'nomePagador'                           => $title->payer_name,
                'enderecoCompleto'                      => $title->payer_address_public_place." ".$title->payer_address." ".$title->payer_address_number,
                'cep'                                   => substr($title->payer_address_zip_code,0,-3),
                'sufixoCep'                             => substr($title->payer_address_zip_code, -3)
            ]);
        }
        if($cnabTitles != null){
            $cnab = new SimpleCNAB();
            $cnab->cnabData = json_encode($cnabTitles);
            $cnab->pathFile = Storage::disk('remittance')->path('/');

            $cnabStatus = $cnab->writeRemittanceBradesco400();

            if($cnabStatus->success){
                return response()->json(array("success" => "", "file_name" => $cnabStatus->file_name, "file_path" => $cnabStatus->file_path));
            } else {
                return response()->json(array("error" => $cnabStatus->error));
            }
        } else {
            return response()->json(array("error" => "Nenhum título selecionado para gerar o arquivo"));
        }


    }
}
