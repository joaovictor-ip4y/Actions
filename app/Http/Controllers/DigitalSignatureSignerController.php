<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignatureSigner;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\DigitalSignature\DigitalSignatureService;
use Illuminate\Http\Request;

class DigitalSignatureSignerController extends Controller
{

    protected function get(Request $request)
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


        $DigitalSignatureSigner            = new DigitalSignatureSigner();
        $DigitalSignatureSigner->cpf_cnpj  = $request->cpf_cnpj;
        $DigitalSignatureSigner->master_id = $checkAccount->master_id;

        return response()->json($DigitalSignatureSigner->get());
    }

    protected function new(Request $request)
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
        $digital_signature_service->signer_cpf_cnpj     = $request->cpf_cnpj;
        $digital_signature_service->signer_name         = $request->name;
        $digital_signature_service->signer_email        = $request->email;
        $digital_signature_service->signer_phone        = $request->phone;
        $digital_signature_service->signer_foreign      = $request->foreign;

        $serviceData = $digital_signature_service->setDocumentSigner();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_signer_data" => $serviceData->document_signer_data ));
    }
}
