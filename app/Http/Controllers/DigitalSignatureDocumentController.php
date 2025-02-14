<?php

namespace App\Http\Controllers;

use App\Libraries\ApiD4Sign;
use App\Libraries\sendMail;
use App\Models\Account;
use App\Models\DigitalSignatureDocument;
use App\Models\DigitalSignatureDocumentSigner;
use App\Models\DigitalSignatureDocumentType;
use App\Models\DigitalSignatureSafe;
use App\Models\ManagersRegister;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\DigitalSignature\DigitalSignatureService;
use Illuminate\Http\Request;
use App\Libraries\ApiSendgrid;


class DigitalSignatureDocumentController extends Controller
{

    //public $tokenAPI    = "live_63072990493f5e84d0340a8d7ce59396d4dc45766b187c26868f444aaed4b8a5";
    //public $cryptKey    = "live_crypt_JtC67LG3u8THfzD3MCLBLlum1dpv392V";
    //public $api_address = "http://demo.d4sign.com.br/api";

    public $tokenAPI    = "live_5f8f79fcae30189bba3ceabffd9919054709ad9cf830010a0d11ce5ee367330a";
    public $cryptKey    = "live_crypt_XbIEHufD5967Yl1iPHJbo7OeXwUoSAZt";
    public $api_address = "https://secure.d4sign.com.br/api";

    public function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $account            = new Account();
        $account->id        = $checkAccount->account_id;

        //if (count($account_data = $account->getAccounts()) == 0) {
        //    return response()->json(array("error" => "Poxa, não foi possível localizar os dados da conta, por favor tente novamente mais tarde"));
        //}

        //$account_result     = $account_data[0];

        $digital_signature_document                     = new DigitalSignatureDocument();
        $digital_signature_document->id                 = $request->document_id;
        $digital_signature_document->cpf_cnpj           = $request->cpf_cnpj;
        //$digital_signature_document->register_master_id = $account_result->register_master_id;
        $digital_signature_document->master_id          = $checkAccount->master_id;
        $digital_signature_document->onlyActive         = 1;
        $digital_signature_document->sent_at            = $request->sent_at;

        if ($request->sent_at_start != '') {
            $digital_signature_document->sent_at_start = $request->sent_at_start." 00:00:00.000";
        }

        if ($request->sent_at_end != '') {
            $digital_signature_document->sent_at_end = $request->sent_at_end." 23:59:59.998";
        }

        return response()->json($digital_signature_document->get());
    }

    protected function setDocumentToRegister(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $digital_signature_service                      = new DigitalSignatureService();
        $digital_signature_service->master_id           = $checkAccount->master_id;
        $digital_signature_service->account_id          = $checkAccount->account_id;
        $digital_signature_service->register_master_id  = $request->register_master_id;

        $serviceData = $digital_signature_service->setDocumentToRegister();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_data" => $serviceData->document_data ));

    }

    protected function setDocumentType(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $digital_signature_service                      = new DigitalSignatureService();
        $digital_signature_service->master_id           = $checkAccount->master_id;
        $digital_signature_service->register_master_id  = $request->register_master_id;
        $digital_signature_service->document_id         = $request->document_id;
        $digital_signature_service->document_type_id    = $request->document_type_id;

        $serviceData = $digital_signature_service->setDocumentType();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_data" => $serviceData->document_data ));
    }

    protected function uploadDocumentLocal(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $digital_signature_service                      = new DigitalSignatureService();
        $digital_signature_service->master_id           = $checkAccount->master_id;
        $digital_signature_service->register_master_id  = $request->register_master_id;
        $digital_signature_service->document_id         = $request->document_id;
        $digital_signature_service->file64              = $request->file64;
        $digital_signature_service->file_name           = $request->file_name;

        if($request->file_name == null or $request->file_name == ''){
            $digitalSignatureDocument = DigitalSignatureDocument::where('id','=',$request->document_id)->first();
            $digitalSignatureDocumentType = DigitalSignatureDocumentType::where('id','=',$digitalSignatureDocument->document_type_id)->first();
            $digital_signature_service->file_name = $digitalSignatureDocumentType->description.' - '.$checkAccount->user_name.'.'.strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
        }

        $serviceData = $digital_signature_service->uploadLocalDocument();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_data" => $serviceData->document_data ));
    }

    protected function sendDocumentToSign(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $digital_signature_service                      = new DigitalSignatureService();
        $digital_signature_service->master_id           = $checkAccount->master_id;
        $digital_signature_service->register_master_id  = $request->register_master_id;
        $digital_signature_service->document_id         = $request->document_id;

        $safe_data = DigitalSignatureSafe::where('master_id','=',$checkAccount->master_id)->first();

        $digital_signature_service->safe_data        = $safe_data;

        $serviceData = $digital_signature_service->sendDocumentToSign();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_data" => $serviceData->document_data));
    }

    protected function listSignaturesDocument(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $document                       = new DigitalSignatureDocument();
        $document->id                   = $request->document_id;
        $document->register_master_id   = $request->register_master_id;
        $document->onlyActive           = 1;

        if (count($document_result = $document->get()) == 0) {
            return response()->json(array("error" => "Poxa, esse documento não existe ou foi excluido, por favor informe outro ou tente mais tarde"));
        }

        $document_data = $document_result[0];

        if (!$uuid_safe = DigitalSignatureSafe::where('master_id','=',$checkAccount->master_id)->where('id','=',1)->first()) {
            return response()->json(array("error" => "Cofre de assinatura não definido, por favor entre em contato com o administrador do sistema"));
        }

        $api_d4                         = new ApiD4Sign();
        $api_d4->uuid_safe              = $uuid_safe->uuid_safe;
        $api_d4->tokenAPI               = $this->tokenAPI;
        $api_d4->cryptKey               = $this->cryptKey;
        $api_d4->api_address            = $this->api_address;
        $api_d4->uuid_doc               = $document_data->uuid_document;

        return response()->json($api_d4->listSignature());
    }

    protected function documentsRegistered(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $documents              = new DigitalSignatureDocument();
        $documents->document_id = $request->document_id;

        if (count($document_count_sign = $documents->get()) == 0) {
            return response()->json(array("error" => "Poxa, não encotramos o(s) documento(s) informado(s)"));
        }

        $document_data = [];

        foreach ($document_count_sign as $doc) {
            array_push($document_data,[
                'id'                    => $doc->id,
                'unique_id'             => $doc->unique_id,
                'document_type_id'      => $doc->document_type_id,
                'document_type'         => $doc->document_type,
                'document_folder_id'    => $doc->document_folder_id,
                'register_master_id'    => $doc->register_master_id,
                'master_id'             => $doc->master_id,
                'status_id'             => $doc->status_id,
                'name'                  => $doc->name,
                'uuid_document'         => $doc->uuid_document,
                'finished_at'           => $doc->finished_at,
                'canceled_at'           => $doc->canceled_at,
                'created_at'            => $doc->created_at,
                'updated_at'            => $doc->updated_at,
                'deleted_at'            => $doc->deleted_at,
                'signer_id_count'       => DigitalSignatureDocumentSigner::countSign($doc->id),
            ]);
        }

        return response()->json($document_data);
    }

    public function checkSign()
    {
        if (!$digitalSignatureDocument = DigitalSignatureDocument::whereNull('signed_at')->whereNotNull('uuid_document')->whereNull('deleted_at')->whereNull('canceled_at')->get()) {
            return response()->json(array("error"=>"Não existem documentos pendentes de assinatura"));
        }

        if (!$uuid_safe = DigitalSignatureSafe::where('master_id','=',1)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Cofre de assinatura não definido, por favor entre em contato com o administrador do sistema"));
        }

        $error_list     = [];
        $message_data   = "";

        foreach ($digitalSignatureDocument as $dsdocument) {

            $api_d4                 = new ApiD4Sign();
            $api_d4->uuid_safe      = $uuid_safe->uuid_safe;
            $api_d4->tokenAPI       = $this->tokenAPI;
            $api_d4->cryptKey       = $this->cryptKey;
            $api_d4->api_address    = $this->api_address;
            $api_d4->uuid_doc       = $dsdocument->uuid_document;
            $api_d4->type           = "ZIP";
            $api_d4->language       = "pt";
            $data_result            = $api_d4->listSignature();
            $data_link_result       = $api_d4->downloadDocument();

            if ($data_result->success || $data_link_result->success) {

                $data_result_ob     = $data_result->data[0];

                if (isset($data_result_ob->statusId)) {

                    $date = [];

                    if ($data_result_ob->statusId == 4) {

                        foreach ($data_result_ob->list as $signer) {
                            array_push($date, $signer->sign_info->date_signed);
                        }

                        $dsdocument->signed_at      = max($date);
                        $dsdocument->status_id      = 37;
                        $dsdocument->document_url   = $data_link_result->data->url;

                        if (!$dsdocument->save()) {
                            array_push($error_list, ["error"=>"Poxa, não foi possível definir o documento ID $dsdocument->id como assinado, por favor tente novamente mais tarde"]);
                        }

                        $message_data .=
                        "<tr>
                            <td>$dsdocument->id</td>
                            <td>$dsdocument->name</td>
                            <td>".\Carbon\Carbon::parse($dsdocument->signed_at)->format('d/m/Y')."</td>
                            <td>$dsdocument->document_url</td>
                        </tr>";
                    }
                }
            } else {
                array_push($error_list, ["error"=>"Poxa, tivemos uma falha de comunicação com o correspondente de assinatura, por favor tente novamente mais tarde"]);
            }
        }

        if ($message_data != "") {

            $message_data_header =  "<tr>
                                        <td width='5%'><strong>ID</strong></td>
                                        <td width='50%'><strong>Nome</strong></td>
                                        <td width='10%'><strong>Data da Assinatura</strong></td>
                                        <td><strong>Link do arquivo</strong></td>
                                    </tr>";
            $message = "Olá, <br>
            <p>Segue abaixo lista de documentos assinados na D4:</p>"."<br>
            <table width='100%' style='border-collapse:collapse' border='1px'>
                $message_data_header
                $message_data
            </table>";

            $sendMail = new sendMail();
            $sendMail->to_mail      = 'ragazzi@dinari.com.br';
            $sendMail->to_name      = 'iP4y';
            $sendMail->send_cc      = 0;
            $sendMail->to_cc_mail   = '';
            $sendMail->to_cc_name   = '';
            $sendMail->send_cco     = 0;
            $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
            $sendMail->to_cco_name  = 'Ragazzi';
            $sendMail->attach_pdf   = 0;
            $sendMail->attach_path  = '';
            $sendMail->attach_file  = '';
            $sendMail->subject      = 'Documentos assinados na D4';
            $sendMail->email_layout = 'emails/confirmEmailAccount';
            $sendMail->bodyMessage  = $message;

            if (!$sendMail->send()) {
                array_push($error_list, 'Ocorreu uma falha ao enviar o e-mail, por favor tente novamente');
            }
        }

        if (sizeof($error_list) == 0) {
            return response()->json(array("success" => "Documentos verificados com sucesso"));
        } else {
            return response()->json(array("error" => "Poxa, não foi possível verificar todos os documentos, por favor verifique a lista de erros e tente novamente mais tarde","error_list" => $error_list));
        }
    }

    protected function downloadDocument(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if (!$digitalSignatureDocument = DigitalSignatureDocument::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNotNull('uuid_document')->whereNotNull('signed_at')->first()) {
            return response()->json(array("error" => "Poxa, não é possível gerar link para download de um documento que não foi assinado."));
        }

        $api_d4                 = new ApiD4Sign();
        $api_d4->tokenAPI       = $this->tokenAPI;
        $api_d4->cryptKey       = $this->cryptKey;
        $api_d4->api_address    = $this->api_address;
        $api_d4->uuid_doc       = $digitalSignatureDocument->uuid_document;
        $api_d4->type           = "ZIP";
        $api_d4->language       = "pt";

        $data_result = $api_d4->downloadDocument();

        if ($data_result->data == null) {
            return response()->json(array("error" => "Poxa, não foi possível gerar o link para download do documento com o correspondente de assinatura, por favor tente novamente mais tarde."));
        }

        $digitalSignatureDocument->document_url = $data_result->data->url;

        if (!$digitalSignatureDocument->save()) {
            return response()->json(array("error" => "Poxa, não foi possível atualizar o link documento assinado, por favor tente novamente mais tarde."));
        }

        return response()->json(array(
            "success"   => "Link para download gerado com sucesso",
            "file_name" => $data_result->data->name,
            "download_link" => $data_result->data->url
        ));
    }

    protected function cancelDocument(Request $request)
    {
       // ----------------- Check Account Verification ----------------- //
       $accountCheckService                = new AccountRelationshipCheckService();
       $accountCheckService->request       = $request;
       $accountCheckService->permission_id = [5];
       $checkAccount                       = $accountCheckService->checkAccount();
       if(!$checkAccount->success){
           return response()->json(array("error" => $checkAccount->message));
       }
       // -------------- Finish Check Account Verification -------------- //


        if (!$digitalSignatureDocument = DigitalSignatureDocument::where('id','=',$request->document_id)->whereNotNull('uuid_document')->whereNull('signed_at')->first()) {
            return response()->json(array("error" => "Poxa, não é possível  cancelar um documento assinado"));
        }

        $api_d4                 = new ApiD4Sign();
        $api_d4->tokenAPI       = $this->tokenAPI;
        $api_d4->cryptKey       = $this->cryptKey;
        $api_d4->api_address    = $this->api_address;
        $api_d4->uuid_doc       = $digitalSignatureDocument->uuid_document;
        $api_d4->comment        = $request->comment;

        $data_result = $api_d4->cancelDocument();

        if ($data_result->data == null) {
            return response()->json(array("error" => "Poxa, não foi possível cancelar o documento com o correspondente de assinatura, por favor tente novamente mais tarde"));
        }

        $digitalSignatureDocument->canceled_at  = \Carbon\Carbon::now();
        $digitalSignatureDocument->status_id    = 49;

        if (!$digitalSignatureDocument->save()) {
            return response()->json(array("error" => "Poxa, não foi possível cancelar o documento, por favor tente novamente mais tarde"));
        }
        return response()->json(array("success" => "Documento cancelado com sucesso"));
    }

    protected function resendToSign(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if (!$digitalSignatureDocument = DigitalSignatureDocument::where('id','=',$request->id)->where('uuid','=',$request->uuid)->whereNotNull('uuid_document')->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o documento informado ou o mesmo foi cancelado"));
        }

        $documentSigner              = new DigitalSignatureDocumentSigner();
        $documentSigner->document_id = $request->id;
        $documentSigner->onlyActive  = 1;

        $error_list  = [];

        foreach ($documentSigner->getDocumentSigner() as $signer) {

            $api_d4                 = new ApiD4Sign();
            $api_d4->tokenAPI       = $this->tokenAPI;
            $api_d4->cryptKey       = $this->cryptKey;
            $api_d4->api_address    = $this->api_address;
            $api_d4->uuid_doc       = $signer->uuid_document;
            $api_d4->key_signer     = $signer->key_signer;
            $api_d4->email          = $signer->signer_email;
            $data_result            = $api_d4->resendSignature();

            if ($data_result->data == null) {
                array_push($error_list, ["error" => "Poxa, não foi possível encontar o signatário deste documento, por favor tente novamente mais tarde", "signature_id" => $signer->id]);
            }
        }

        if (sizeof($error_list) == 0) {

            $managerRegister = new ManagersRegister();
            $managerRegister->register_master_id = $digitalSignatureDocument->register_master_id;
            $managerRegister->onlyActive = 1;
            $managerRegister = $managerRegister->getManagersRegister();

            $documentType = DigitalSignatureDocumentType::where('id', '=', $digitalSignatureDocument->document_type_id)->first();

            if (count($managerRegister) > 0) {
                foreach ($managerRegister as $mngr_rgstr) {
                    $message = "Olá $mngr_rgstr->mngr_dtl_name, <br>
                    <p>Foi reenviado para <strong>$mngr_rgstr->register_name </strong> assinar digitalmente o documento de <strong>$documentType->description</strong>.</p>"."<br>";
                    
                    $apiSendGrind = new ApiSendgrid();
                    $apiSendGrind->to_email    = $mngr_rgstr->mngr_dtl_email;
                    $apiSendGrind->to_name     = $mngr_rgstr->mngr_dtl_email;
                    $apiSendGrind->to_cc_email = 'ragazzi@dinari.com.br';
                    $apiSendGrind->to_cc_name  = 'Ragazzi';
                    $apiSendGrind->subject     = 'Documento reenviado para assinatura';
                    $apiSendGrind->content     = $message;

                    if( ! $apiSendGrind->sendSimpleEmail()){
                        array_push($error_list, 'Ocorreu uma falha ao enviar o e-mail, por favor tente novamente');
                    }
                }
            }
            return response()->json(array("success" => "Documento reenviado para os signatários com sucesso"));
        }

        return response()->json(array("error" => "Poxa, não foi possível reenviar o documento para os signatários, por favor tente novamente mais tarde", "error_list" => $error_list));
    }

    protected function deleteDocument(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $digitalSignatureDocument = DigitalSignatureDocument::where('id','=',$request->document_id)->where('master_id','=',$checkAccount->master_id)->first();

        if($digitalSignatureDocument->sent_at != null && $digitalSignatureDocument->canceled_at == null){
            return response()->json(["error" => "Não é possível excluir um documento enviado para assinatura que não foi cancelado, cancele o documento para depois realizar a exclusão"]);
        }

        $digitalSignatureDocument->deleted_at = \Carbon\Carbon::now();

        if(!$digitalSignatureDocument->save()){
            return response()->json(["error" => "Poxa, não foi possível remover o documento, por favor tente novamente mais tarde"]);
        }
        return response()->json(["success" => "Documento excluído com sucesso"]);
    }

    public function linkWebhook()
    {
        $digitalSignatureDocument = DigitalSignatureDocument::whereNull('signed_at')->whereNotNull('uuid_document')->whereNull('deleted_at')->whereNull('canceled_at')->get();
        $uuid_safe = DigitalSignatureSafe::where('master_id','=',1)->whereNull('deleted_at')->first();

        foreach ($digitalSignatureDocument as $dsdocument) {

            $api_d4                 = new ApiD4Sign();
            $api_d4->uuid_safe      = $uuid_safe->uuid_safe;
            $api_d4->tokenAPI       = $this->tokenAPI;
            $api_d4->cryptKey       = $this->cryptKey;
            $api_d4->api_address    = $this->api_address;
            $api_d4->uuid_doc       = $dsdocument->uuid_document;

            $api_d4->linkWebhook();

        }

    }
}
