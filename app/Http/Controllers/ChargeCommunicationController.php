<?php

namespace App\Http\Controllers;

use App\Libraries\ApiSendgrid;
use App\Libraries\BilletGenerator;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Models\AntecipationCharge;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use PDF;
use App\Libraries\SimpleZip;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use File;

class ChargeCommunicationController extends Controller
{
    protected function sendAntecipationChargeBilletZipEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $arrayError = [];
        $payer_id = [];

        $antecipationCharge                         = new AntecipationCharge();
        $antecipationCharge->master_id              = $checkAccount->master_id;
        $antecipationCharge->account_id             = $checkAccount->account_id;
        $antecipationCharge->payer_detail_id_in     = $request->payer_detail_id;
        $antecipationCharge->id_in                  = $request->id;
        $antecipationCharge->onlyActive             = $request->onlyActive;
        $antecipationCharge->value_start            = $request->value_start;
        $antecipationCharge->value_end              = $request->value_end;

        if($request->created_at_start != ''){
            $antecipationCharge->created_at_start   = $request->created_at_start." 00:00:00.000";
        }

        if($request->created_at_end != ''){
            $antecipationCharge->created_at_end     = $request->created_at_end." 23:59:59.998";
        }

        if($request->due_date_start != ''){
            $antecipationCharge->due_date_start     = $request->due_date_start." 00:00:00.000";
        }
        if($request->due_date_end != ''){
            $antecipationCharge->due_date_end       = $request->due_date_end." 23:59:59.998";
        }

        if (count($antecipation_chages = $antecipationCharge->getAntecipationCharge()) == 0) {
            return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
        }

        foreach($antecipation_chages as $antcptn_chg) {
            array_push($payer_id,  $antcptn_chg->payer_detail_id
            );
        }

        $pay_detail = array_unique($payer_id);

        foreach ($pay_detail as $py) {

            $SimpleZip = new SimpleZip();
            $createZipFolder = $SimpleZip->createZipFolder();

            if (!$createZipFolder->success) {
                return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
            }

            $data_antecipation = [];

            foreach($antecipation_chages as $antecipation_chage){

                if ($antecipation_chage->payer_detail_id == $py) {

                    array_push($data_antecipation, $antecipation_chage);

                    if($antecipation_chage->status_id == 28 or $antecipation_chage->status_id == 29){
                        array_push($arrayError, 'Não é possível gerar boleto para título liquidado ou baixado, título: '.$antecipation_chage->document);
                    }else{
                        $billetGenerator                          = new BilletGenerator();
                        $billetGenerator->barcode                 = $antecipation_chage->bar_code;
                        $billetGenerator->digitableLine           = $antecipation_chage->digitable_line;
                        $billetGenerator->bankNumber              = substr($antecipation_chage->bank_code,1,3);
                        $facilities                               = new Facilites();

                        $getBilletData                            = new AntecipationCharge();
                        $getBilletData->id                        = $antecipation_chage->id;
                        $billetData                               = $getBilletData->getBilletData();

                        $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                        $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                        $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                        $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                        $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                        $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
                        $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
                        $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
                        $billetData->document_type                = "DM";
                        $billetData->path_bank_logo               = "billet/logorendimento.jpg";
                        $billetData->path_qr_code                 = "billet/qrCodeDinariPay.png";
                        $billetData->issue_date                   = ((\Carbon\Carbon::parse( $billetData->issue_date ))->format('d/m/Y'));
                        $billetData->due_date                     = ((\Carbon\Carbon::parse( $billetData->due_date ))->format('d/m/Y'));
                        $billetData->value                        = number_format(($billetData->value),2,',','.');
                        $billetData->observation                  = $billetData->observation;
                        $billetData->message_fine_interest        = '';
                        if($billetData->fine > 0 or $billetData->interest > 0 ){
                            $billetData->message_fine_interest =  'Após vencimento, cobrar multa de '.number_format(($billetData->fine),2,',','.').'% e mora de '.number_format(( ($billetData->interest/30) ),2,',','.').'% ao dia.';
                        }
                        $pdfFilePath  = '../storage/app/zip/'.$createZipFolder->folderName.'/';
                        $file_name    = $antecipation_chage->our_number.'.pdf';
                        if(!(PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name))){
                            array_push($arrayError,'Não foi possível gerar o boleto: '.$antecipation_chage->document);
                        }
                    }
                }
            }

            $SimpleZip->fileData = (object) [
                "folderName" => $createZipFolder->folderName,
                "deleteFiles" => true
            ];

            $createZipFile = $SimpleZip->createZipFile();

            if (!$createZipFile->success) {
                return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
            }

            if (!Storage::disk('zip')->put($createZipFile->zipFileName, base64_decode($createZipFile->zipFile64))) {
                return response()->json(array("error" => "Falha ao salvar o arquivo"));
            }

            $user = User::where('id','=',$request->header('userId'))->first();
            $facilities = new Facilites();

            $date = \Carbon\Carbon::now();

            $antecipation_data = $data_antecipation[0];

            $messag = "<p><strong> GUARULHOS, ".$date->format('d')." de ".$facilities->convertNumberMonthToString($date->format('m'))." de ".$date->format('Y')."</strong> </p>
             <p>A <strong>$billetData->payer_name</strong>,</p>
            <p>Prezado(s) Senhor(es),</p>
            <p> Informamos que o(s) título(s) abaixo discriminado(s) foi/foram transferido(s)
            por endosso em preto pelo Cedente <b>$billetData->payer_name</b>,
            inscrito no CNPJ ".$facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj)."
            com sede em $antecipation_data->payer_address_public_place
            $antecipation_data->payer_address, $antecipation_data->payer_address_number, $antecipation_data->payer_address_district,
            $antecipation_data->payer_address_city, $antecipation_data->payer_address_state_description - $antecipation_data->payer_address_state_short_description
            no CEP: $antecipation_data->payer_address_zip_code, à nossa empresa, que se tornou a única e legítima proprietária:</p>
            <br><br>";

            $messages_data = "";
            $due_date = "";
            $total = 0;

            foreach ($data_antecipation as $antcptn_chgs) {
                $messages_data .="
                <tr align='left'>
                    <td>$antcptn_chgs->payer_name</td>
                    <td>$antcptn_chgs->document</td>
                    <td>".\Carbon\Carbon::parse($antcptn_chgs->due_date)->format('d/m/Y')."</td>
                    <td>R$ ".number_format($antcptn_chgs->value,2,',','.')."</td>
                    <td>$antcptn_chgs->our_number</td>
                </tr>";

                $due_date .= \Carbon\Carbon::parse($antcptn_chgs->due_date)->format('d/m/Y').", ";

                $total += $antcptn_chgs->value;
            }
            $messages =
                "<table width='100%'>
                    <tr align='left'>
                        <th>
                            <b>
                                <span>
                                    <strong>Cedente</strong>
                                <u></u><u></u>
                                </span>
                            </b>
                        </th>
                        <th>
                            <p>
                                <span>
                                <strong>Documento</strong>
                                <u></u><u></u>
                                </span>
                            </p>
                        </th>
                        <th>
                            <p>
                                <span>
                                    <strong>Vencimento</strong>
                                <u></u><u></u>
                                </span>
                            </p>
                        </th>
                        <th>
                            <p>
                                <span>
                                    <strong>Valor</strong>
                                <u></u><u></u>
                                </span>
                            </p>
                        </th>
                        <th>
                            <p>
                                <span>
                                    <strong>Nosso número</strong>
                                    <u></u><u></u>
                                </span>
                            </p>
                        </th>
                    </tr>
                    ".$messages_data."
                    <tr align='left'>
                        <td></td>
                        <td></td>
                        <td>
                            <p>
                            <span>
                                <strong>Valor total:</strong>
                                <u></u><u></u>
                            </span>
                            </p>
                        </td>
                        <td>
                            <p>
                                <span>
                                    <strong>R$ ".number_format($total,2,',','.')."</strong>
                                    <u></u><u></u>
                                </span>
                            </p>
                        </td>
                    </tr>
                </table>

                <div>
                    <p>
                        <span>
                            O vencimento desse(s) título(s) ocorrerá dentro de 7 dias e em seu respectivo vencimento $due_date
                            deverá(ão) ser pago(s) diretamente e exclusivamente a nossa empresa através de boleto bancário.
                        </span>
                    </p>
                </div>

                <div>
                    <p>
                        <span>
                            O pagamento não poderá ser efetuado em hipótese alguma diretamente ao Cedente ".$antcptn_chgs->payer_name.",
                            pois assim sendo, o(s) título(s) será(ão) encaminhado(s) para protesto.
                        </span>
                    </p>
                </div>

                <div>
                    <p>
                        <span>
                            Quaisquer problemas referentes à mercadoria ou prestação de serviço desta cobrança ou no recebimento do boleto bancário,
                            favor entrar em contato primeiramente com nossa empresa para que seja verificada nova forma de pagamento, para quitação do(s) título(s).
                        </span>
                    </p>

                </div>
                <div>
                    <p>
                        <span>
                        <strong>ATENÇÃO:</strong> É obrigatório que o BENEFICIÁRIO do boleto seja DINARIPAY SECURITIZADORA S/A CNPJ 31.252.860/0001-46.
                            Caso apareça qualquer outro nome ou CNPJ, pedimos que entre em contato imediatamente com o nosso departamento de Risco e Compliance através do telefone (11) 2229-8282.
                        </span>
                    </p>
                </div>
                <div>
                    <p>
                        <span>
                            Colocamo-nos à inteira disposição para quaisquer esclarecimentos necessários.
                            <u></u><u></u>
                        </span>
                    </p>
                </div>
                <div>
                    <p>
                        <span>
                        <strong>Atenciosamente,</strong>
                            <u></u><u></u>
                        </span>
                    </p>
                </div>

                <div><p><strong><span>DINARIPAY SECURITIZADORA S/A </span></strong><span>(<a href='http://dinari.com.br' target='_blank' data-saferedirecturl='https://www.google.com/url?q=http://dinari.com.br&amp;source=gmail&amp;ust=1627395656139000&amp;usg=AFQjCNFEcmj13ArzQ5LNJ4Eb-YAv9uen7A'>dinari.com.br</a>)<u></u><u></u></span></p></div>
                <div><p><span>RUA JOSEPH ZARZOUR, 93 SL 1411 - VILA MOREIRA<u></u><u></u></span></p></div>
                <div><p><span>GUARULHOS - SP - Fone: (11) 2229-8282<u></u><u></u></span></p></div>
                <div><p><span>e-mail: <a href='mailto:info@dinari.com.br' target='_blank'>info@dinari.com.br</a><u></u><u></u></span></p></div>
                ";

            $message = $messag.$messages;

            if (!$file_encode = base64_encode(Storage::disk('zip')->get($createZipFile->zipFileName))) {
                array_push($arrayError, 'Ocorreu uma falha ao converter o documento, por favor tente novamente');
            }

            $apiSendGrind = new ApiSendgrid();
            $apiSendGrind->to_email                 = $billetData->payer_email;
            $apiSendGrind->to_name                  = $billetData->payer_name;
            $apiSendGrind->to_cc_email              = $user->email;
            $apiSendGrind->to_cc_name               = $user->name;
            $apiSendGrind->subject                  = 'Boletos';
            $apiSendGrind->content                  = $message;
            $apiSendGrind->attachment_content       = $file_encode;
            $apiSendGrind->attachment_file_name     = $createZipFile->zipFileName;
            $apiSendGrind->attachment_mime_type     = 'application/zip';

            if ($billetData->payer_email != '') {

                if ($apiSendGrind->sendEmailWithAttachment()) {
                    File::delete('../storage/app/zip/'.$createZipFile->zipFileName);

                } else {
                    array_push($arrayError, 'Ocorreu uma falha ao enviar o e-mail, por favor tente novamente');
                }
            } else {
                array_push($arrayError, 'Ocorreu uma falha ao enviar o e-mail, reveja os endereços de e-mails e tente novamente');
            }
        }

        return response()->json(array(
            "success"=>"Emails enviados com sucesso",
            "error"=>$arrayError,
        ));
    }
}
