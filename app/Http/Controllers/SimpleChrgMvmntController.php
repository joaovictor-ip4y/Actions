<?php

namespace App\Http\Controllers;

use App\Models\SimpleChrgMvmnt;
use App\Models\Account;
use App\Libraries\SimpleCNAB;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Storage;

class SimpleChrgMvmntController extends Controller
{
    protected function getAccountMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [109];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        $accountMovement             = new SimpleChrgMvmnt();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->onlyActive = 1;
        return response()->json( $accountMovement->getAccountMovement() );
    }

    /*
    protected function getReturnFile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $getReturnFile             = new SimpleChrgMvmnt();
        $getReturnFile->account_id = $checkAccount->account_id;
        $getReturnFile->master_id  = $checkAccount->master_id;
        if($request->start_date != ''){
            $getReturnFile->date_start = $request->start_date." 00:00:00.000";
        }
        if($request->start_date != ''){
            $getReturnFile->date_end = $request->start_date." 23:59:59.998";
        }

       
        $cnabTitles = [];
        foreach($getReturnFile->getReturnFile() as $title){
            $identificacaoOcorrencia = "";
            $abatimento_concedido    = 0;
            $desconto_concedido      = 0;
            $motivoCodigoOcorrencia  = "";
            $valor_pago              = 0;

            $reference_value = $title->reference_value;
            if($reference_value < 0){
                $reference_value =  $reference_value * -1;
            }

            switch($title->chrg_mvnt_id){
                case 1: //Registro
                    $identificacaoOcorrencia = "02";
                break;
                case 2: //Abatimento
                    $identificacaoOcorrencia = "12";
                    $abatimento_concedido    = $reference_value;
                break;
                case 3: //Desconto
                    $identificacaoOcorrencia = "12";
                    $abatimento_concedido    = $reference_value;
                break;
                case 4: //Liquidação Parcial
                    $identificacaoOcorrencia = "06";
                    $valor_pago = $reference_value;
                    $motivoCodigoOcorrencia = "18";
                break;
                case 5: //Liquidação
                    $identificacaoOcorrencia = "06";
                    $valor_pago = $reference_value;
                    $motivoCodigoOcorrencia = "00";
                break;
                case 6: //Baixa para Simples
                    $identificacaoOcorrencia = "10";
                    $motivoCodigoOcorrencia  = "20";
                break;
                case 7: //Baixa
                    $identificacaoOcorrencia = "09";
                    $motivoCodigoOcorrencia = "00";
                break;
                case 8: //Recompra
                    $identificacaoOcorrencia = "09";
                    $motivoCodigoOcorrencia = "00";
                break;

            }

            array_push($cnabTitles,
                [
                    'codigoEmpresa'                         => $title->beneficiary_account_number,
                    'nomeEmpresa'                           => $title->beneficiary_name,
                    'dataCredito'                           => \Carbon\Carbon::parse($title->reference_date)->format('Y-m-d'),
                    'numeroInscricaoEmpresa'                => $title->beneficiary_cpf_cnpj,
                    'identificacaoEmpresaBeneficiariaBanco' => $title->wallet_number.'0001'.$title->beneficiary_account_number,
                    'numeroControleParticipante'            => "",
                    'identificacaoTituloBanco'              => $title->our_number,
                    'indicadorRateioCredito'                => "0",
                    'pagamentoParcial'                      => "00",
                    'carteira'                              => "9",
                    'identificacaoOcorrencia'               => $identificacaoOcorrencia,
                    'dataOcorrencia'                        => \Carbon\Carbon::parse($title->reference_date)->format('Y-m-d'),
                    'numeroDocumento'                       => $title->document,
                    'dataVencimento'                        => \Carbon\Carbon::parse($title->due_date)->format('Y-m-d'),
                    'valor'                                 => $title->value,
                    'valorDespesa'                          => 0,
                    'outrasDespesasCustaCartorio'           => 0,
                    'jurosOperacaoAtraso'                   => 0,
                    'valorIof'                              => 0,
                    'valorAbatimentoConcedidoOuCancelado'   => $abatimento_concedido,
                    'valorDesconto'                         => $desconto_concedido,
                    'valorPago'                             => $valor_pago,
                    'jurosMora'                             => 0,
                    'outrosCreditos'                        => 0,
                    'motivoCodigoOcorrencia'                => $motivoCodigoOcorrencia,
                    'origemPagamento'                       => "",
                    'motivoRejeicao'                        => "",
                    'numeroCartorio'                        => "",
                    'numeroProtocolo'                       => "",
                ]
            );
        }
        if($cnabTitles != null){
            $cnab           = new SimpleCNAB();
            $cnab->cnabData = json_encode($cnabTitles);
            $cnab->pathFile = Storage::disk('remittance')->path('/');
            $cnabStatus     = $cnab->writeReturnBradesco400();

            if($cnabStatus->success){
                
            }
        }
    }
    */
}
