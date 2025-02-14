<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignatureDocumentSigner;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\DigitalSignature\DigitalSignatureService;
use Illuminate\Http\Request;

class DigitalSignatureDocumentSignerController extends Controller
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


        $DigitalSignatureDocumentSigner = new DigitalSignatureDocumentSigner();
        $DigitalSignatureDocumentSigner->signer_id   = $request->signer_id;
        $DigitalSignatureDocumentSigner->document_id = $request->document_id;
        $DigitalSignatureDocumentSigner->onlyActive  = 1;

        return response()->json($DigitalSignatureDocumentSigner->getDocumentSigner());
    }

    protected function setSignatureProfile(Request $request)
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
        $digital_signature_service->signer_profile_id   = $request->signer_profile_id;
        $digital_signature_service->documnet_signer_id  = $request->documnet_signer_id;
        $digital_signature_service->signer_id           = $request->signer_id;

        $serviceData = $digital_signature_service->setDocumentSignerProfile();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_signer_profile_data" => $serviceData->document_signer_profile_data));
    }

    protected function setSignatureValidationMethod(Request $request)
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


        $digital_signature_service                          = new DigitalSignatureService();
        $digital_signature_service->master_id               = $checkAccount->master_id;
        $digital_signature_service->register_master_id      = $request->register_master_id;
        $digital_signature_service->document_id             = $request->document_id;
        $digital_signature_service->validation_method_id    = $request->validation_method_id;
        $digital_signature_service->documnet_signer_id      = $request->documnet_signer_id;
        $digital_signature_service->signer_id               = $request->signer_id;

        $serviceData = $digital_signature_service->setDocumentSignerValidation();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_signer_validation_data" => $serviceData->document_signer_validation_data));
    }

    protected function setSignatureAuthMethod(Request $request)
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


        $digital_signature_service                          = new DigitalSignatureService();
        $digital_signature_service->master_id               = $checkAccount->master_id;
        $digital_signature_service->register_master_id      = $request->register_master_id;
        $digital_signature_service->document_id             = $request->document_id;
        $digital_signature_service->auth_method_id          = $request->auth_method_id;
        $digital_signature_service->documnet_signer_id      = $request->documnet_signer_id;
        $digital_signature_service->signer_id               = $request->signer_id;

        $serviceData = $digital_signature_service->setDocumentSignerAuthentication();

        if (!$serviceData->success) {
            return response()->json(array("error" => $serviceData->message));
        }
        return response()->json(array("success" => $serviceData->message, "document_signer_authentication_data" => $serviceData->document_signer_authentication_data));
    }
}
