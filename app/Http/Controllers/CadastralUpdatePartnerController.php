<?php

namespace App\Http\Controllers;

use App\Libraries\AmazonS3;
use App\Models\CadastralUpdate;
use App\Models\CadastralUpdatePartner;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\Account\AccountRelationshipCheckService;

class CadastralUpdatePartnerController extends Controller
{

    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        
        if ( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->where('register_master_id', '=', $checkAccount->register_master_id)
        ->whereNull('deleted_at')
        ->first()
        ) {
            return response()->json(["error" => "Solicitação de atualização cadastral não localizada."]);
        }

        $cadastralUpdatePartner                      = new CadastralUpdatePartner();
        $cadastralUpdatePartner->cadastral_update_id = $request->cadastral_update_id;
        $cadastralUpdatePartner->onlyActive          = $request->onlyActive;
        return response()->json($cadastralUpdatePartner->get());

    }

    public function importDocumentFront(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'register_master_id'               => ['required', 'integer'],
            'cadastral_update_id'              => ['required', 'integer'],
            'cadastral_update_uuid'            => ['required', 'string'],
            'document_front_type_id'           => ['required', 'integer'],
            'document_front_imported_filename' => ['required', 'string'],
            'file64'                           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        switch ($request->document_front_type_id) {
            case 1:
                $document_type_id = 1;
            break;
            case 2:
                $document_type_id = 3;
            break;
            case 3:
                $document_type_id = 5;
            break;
            default:
                return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->document_front_imported_filename, $request->file64, $document_type, $cadastralUpdatePartner->document_front_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdatePartner->document_front_type_id           = $document_type_id;
        $cadastralUpdatePartner->document_front_imported_filename = $request->document_front_imported_filename;
        $cadastralUpdatePartner->document_front_s3_filename       = $fileName->data;
        $cadastralUpdatePartner->document_front_status_id         = 4;
        $cadastralUpdatePartner->document_front_observation       = null;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Frente do documento importada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar a frente do documento."));
    }

    public function importDocumentVerse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'register_master_id'               => ['required', 'integer'],
            'cadastral_update_id'              => ['required', 'integer'],
            'cadastral_update_uuid'            => ['required', 'string'],
            'document_verse_type_id'           => ['required', 'integer'],
            'document_verse_imported_filename' => ['required', 'string'],
            'file64'                           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        switch ($request->document_verse_type_id) {
            case 1:
                $document_type_id = 2;
            break;
            case 2:
                $document_type_id = 4;
            break;
            case 3:
                $document_type_id = 6;
            break;
            default:
                return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->document_verse_imported_filename, $request->file64, $document_type, $cadastralUpdatePartner->document_verse_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdatePartner->document_verse_type_id           = $document_type_id;
        $cadastralUpdatePartner->document_verse_imported_filename = $request->document_verse_imported_filename;
        $cadastralUpdatePartner->document_verse_s3_filename       = $fileName->data;
        $cadastralUpdatePartner->document_verse_status_id         = 4;
        $cadastralUpdatePartner->document_verse_observation       = null;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Verso do documento importado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar o verso do documento."));
    }

    public function importAddressProof(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                              => ['required', 'integer'],
            'uuid'                            => ['required', 'string'],
            'register_master_id'              => ['required', 'integer'],
            'cadastral_update_id'             => ['required', 'integer'],
            'cadastral_update_uuid'           => ['required', 'string'],
            'address_proof_imported_filename' => ['required', 'string'],
            'file64'                          => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        switch ($cadastralUpdatePartner->cadastral_update_type_id) {
            case '1':
                $document_type_id = 8;
            break;
            case '2':
                $document_type_id = 12;
            break;
            default:
                return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->address_proof_imported_filename, $request->file64, $document_type, $cadastralUpdatePartner->address_proof_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdatePartner->address_proof_imported_filename = $request->address_proof_imported_filename;
        $cadastralUpdatePartner->address_proof_s3_filename       = $fileName->data;
        $cadastralUpdatePartner->address_proof_status_id         = 4;
        $cadastralUpdatePartner->address_proof_observation       = null;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Comprovante de endereço importado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar o comprovante de endereço."));
    }

    public function importSelfie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'register_master_id'       => ['required', 'integer'],
            'cadastral_update_id'      => ['required', 'integer'],
            'cadastral_update_uuid'    => ['required', 'string'],
            'selfie_imported_filename' => ['required', 'string'],
            'file64'                   => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        $document_type_id = 7;

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->selfie_imported_filename, $request->file64, $document_type, $cadastralUpdatePartner->selfie_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdatePartner->selfie_imported_filename = $request->selfie_imported_filename;
        $cadastralUpdatePartner->selfie_s3_filename       = $fileName->data;
        $cadastralUpdatePartner->selfie_status_id         = 4;
        $cadastralUpdatePartner->selfie_observation       = null;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Selfie importada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar a selfie."));
    }

    public function importInvoicingDeclaration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                                      => ['required', 'integer'],
            'uuid'                                    => ['required', 'string'],
            'register_master_id'                      => ['required', 'integer'],
            'cadastral_update_id'                     => ['required', 'integer'],
            'cadastral_update_uuid'                   => ['required', 'string'],
            'invoicing_declaration_imported_filename' => ['required', 'string'],
            'file64'                                  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        $document_type_id = 14;

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->invoicing_declaration_imported_filename, $request->file64, $document_type, $cadastralUpdatePartner->invoicing_declaration_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdatePartner->invoicing_declaration_imported_filename = $request->invoicing_declaration_imported_filename;
        $cadastralUpdatePartner->invoicing_declaration_s3_filename       = $fileName->data;
        $cadastralUpdatePartner->invoicing_declaration_status_id         = 4;
        $cadastralUpdatePartner->invoicing_declaration_observation       = null;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Declaração de faturamento importada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar a declaração de faturamento."));
    }

    public function importSocialContract(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
            'register_master_id'                => ['required', 'integer'],
            'cadastral_update_id'               => ['required', 'integer'],
            'cadastral_update_uuid'             => ['required', 'string'],
            'social_contract_imported_filename' => ['required', 'string'],
            'file64'                            => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        $document_type_id = 11;

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->social_contract_imported_filename, $request->file64, $document_type, $cadastralUpdatePartner->social_contract_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdatePartner->social_contract_imported_filename = $request->social_contract_imported_filename;
        $cadastralUpdatePartner->social_contract_s3_filename       = $fileName->data;
        $cadastralUpdatePartner->social_contract_status_id         = 4;
        $cadastralUpdatePartner->social_contract_observation       = null;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Contrato social importado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar o contrato social."));
    }

    public function getDocumentFront(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdatePartner->document_front_s3_filename != null ) {

            $documentFrontTypeId = $cadastralUpdatePartner->document_front_type_id;
            $documentType = DocumentType::where('id', '=', $documentFrontTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdatePartner->document_front_s3_filename;

            $ext = pathinfo($cadastralUpdatePartner->document_front_s3_filename, PATHINFO_EXTENSION);
            switch($ext){
                case 'jpg':
                    $mimeType = 'image/jpg';
                break;
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                break;
                case 'png':
                    $mimeType = 'image/png';
                break;
                default:
                    $mimeType = "application/".$ext;
                break;
            }

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Frente do documento obtida com sucesso.',
                    'file_name'  => $cadastralUpdatePartner->document_front_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Frente do documento não importada."));
        }
        
    }

    public function getDocumentVerse(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdatePartner->document_verse_s3_filename != null ) {

            $documentVerseTypeId = $cadastralUpdatePartner->document_verse_type_id;
            $documentType = DocumentType::where('id', '=', $documentVerseTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdatePartner->document_verse_s3_filename;

            $ext = pathinfo($cadastralUpdatePartner->document_verse_s3_filename, PATHINFO_EXTENSION);
            switch($ext){
                case 'jpg':
                    $mimeType = 'image/jpg';
                break;
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                break;
                case 'png':
                    $mimeType = 'image/png';
                break;
                default:
                    $mimeType = "application/".$ext;
                break;
            }

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Verso do documento obtido com sucesso.',
                    'file_name'  => $cadastralUpdatePartner->document_verse_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Verso do documento não importado."));
        }
        
    }

    public function getSelfie(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdatePartner->selfie_s3_filename != null ) {

            $documentTypeId = 7;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdatePartner->selfie_s3_filename;

            $ext = pathinfo($cadastralUpdatePartner->document_verse_s3_filename, PATHINFO_EXTENSION);
            switch($ext){
                case 'jpg':
                    $mimeType = 'image/jpg';
                break;
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                break;
                case 'png':
                    $mimeType = 'image/png';
                break;
                default:
                    $mimeType = "application/".$ext;
                break;
            }

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Selfie obtida com sucesso.',
                    'file_name'  => $cadastralUpdatePartner->selfie_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Selfie não importada."));
        }
        
    }

    public function getAddressProof(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdatePartner->address_proof_s3_filename != null ) {

            switch ($cadastralUpdatePartner->cadastral_update_type_id) {
                case '1':
                    $document_type_id = 8;
                break;
                case '2':
                    $document_type_id = 12;
                break;
                default:
                    return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
            }

            $documentTypeId = $document_type_id;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdatePartner->address_proof_s3_filename;

            $ext = pathinfo($cadastralUpdatePartner->address_proof_s3_filename, PATHINFO_EXTENSION);
            switch($ext){
                case 'jpg':
                    $mimeType = 'image/jpg';
                break;
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                break;
                case 'png':
                    $mimeType = 'image/png';
                break;
                default:
                    $mimeType = "application/".$ext;
                break;
            }

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Comprovante de endereço obtido com sucesso.',
                    'file_name'  => $cadastralUpdatePartner->address_proof_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Comprovante de endereço não importado."));
        }
        
    }

    public function getInvoicingDeclaration(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdatePartner->invoicing_declaration_s3_filename != null ) {

            $documentTypeId = 14;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdatePartner->invoicing_declaration_s3_filename;

            $ext = pathinfo($cadastralUpdatePartner->invoicing_declaration_s3_filename, PATHINFO_EXTENSION);
            switch($ext){
                case 'jpg':
                    $mimeType = 'image/jpg';
                break;
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                break;
                case 'png':
                    $mimeType = 'image/png';
                break;
                default:
                    $mimeType = "application/".$ext;
                break;
            }

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Declaração de faturamento obtida com sucesso.',
                    'file_name'  => $cadastralUpdatePartner->invoicing_declaration_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Declaração de faturamento não importada."));
        }
        
    }

    public function getSocialContract(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdatePartner->social_contract_s3_filename != null ) {

            $documentTypeId = 11;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdatePartner->social_contract_s3_filename;

            $ext = pathinfo($cadastralUpdatePartner->social_contract_s3_filename, PATHINFO_EXTENSION);
            switch($ext){
                case 'jpg':
                    $mimeType = 'image/jpg';
                break;
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                break;
                case 'png':
                    $mimeType = 'image/png';
                break;
                default:
                    $mimeType = "application/".$ext;
                break;
            }

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Documento obtido com sucesso.',
                    'file_name'  => $cadastralUpdatePartner->social_contract_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Documento não importado."));
        }
        
    }

    public function approveDocumentFront(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'register_master_id'         => ['required', 'integer'],
            'cadastral_update_id'        => ['required', 'integer'],
            'cadastral_update_uuid'      => ['required', 'string'],
            'document_front_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->document_front_status_id   = 9;
        $cadastralUpdatePartner->document_front_observation = $request->document_front_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Frente do documento aprovada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao aprovar a frente do documento."));
    }

    public function reproveDocumentFront(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'register_master_id'         => ['required', 'integer'],
            'cadastral_update_id'        => ['required', 'integer'],
            'cadastral_update_uuid'      => ['required', 'string'],
            'document_front_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->document_front_status_id   = 11;
        $cadastralUpdatePartner->document_front_observation = $request->document_front_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Frente do documento recusada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao recusar a frente do documento."));
    }

    public function approveDocumentVerse(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'register_master_id'         => ['required', 'integer'],
            'cadastral_update_id'        => ['required', 'integer'],
            'cadastral_update_uuid'      => ['required', 'string'],
            'document_verse_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->document_verse_status_id   = 9;
        $cadastralUpdatePartner->document_verse_observation = $request->document_verse_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Verso do documento aprovado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao aprovar o verso do documento."));
    }

    public function reproveDocumentVerse(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'register_master_id'         => ['required', 'integer'],
            'cadastral_update_id'        => ['required', 'integer'],
            'cadastral_update_uuid'      => ['required', 'string'],
            'document_verse_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->document_verse_status_id   = 11;
        $cadastralUpdatePartner->document_verse_observation = $request->document_verse_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Verso do documento recusado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao recusar o verso do documento."));
    }

    public function approveAddressProof(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'register_master_id'        => ['required', 'integer'],
            'cadastral_update_id'       => ['required', 'integer'],
            'cadastral_update_uuid'     => ['required', 'string'],
            'address_proof_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->address_proof_status_id   = 9;
        $cadastralUpdatePartner->address_proof_observation = $request->address_proof_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Comprovante de endereço aprovado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao aprovar o comprovante de endereço."));
    }

    public function reproveAddressProof(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'register_master_id'        => ['required', 'integer'],
            'cadastral_update_id'       => ['required', 'integer'],
            'cadastral_update_uuid'     => ['required', 'string'],
            'address_proof_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->address_proof_status_id   = 11;
        $cadastralUpdatePartner->address_proof_observation = $request->address_proof_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Comprovante de endereço recusado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao recusar o comprovante de endereço."));
    }

    public function approveSelfie(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
            'selfie_observation'    => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->selfie_status_id   = 9;
        $cadastralUpdatePartner->selfie_observation = $request->selfie_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Selfie aprovada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao aprovar a selfie."));
    }

    public function reproveSelfie(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'register_master_id'    => ['required', 'integer'],
            'cadastral_update_id'   => ['required', 'integer'],
            'cadastral_update_uuid' => ['required', 'string'],
            'selfie_observation'    => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->selfie_status_id   = 11;
        $cadastralUpdatePartner->selfie_observation = $request->selfie_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Selfie recusada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao recusar a selfie."));
    }

    public function approveInvoicingDeclaration(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
            'register_master_id'                => ['required', 'integer'],
            'cadastral_update_id'               => ['required', 'integer'],
            'cadastral_update_uuid'             => ['required', 'string'],
            'invoicing_declaration_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->invoicing_declaration_status_id   = 9;
        $cadastralUpdatePartner->invoicing_declaration_observation = $request->invoicing_declaration_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Declaração de faturamento aprovada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao aprovar a declaração de faturamento."));
    }

    public function reproveInvoicingDeclaration(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
            'register_master_id'                => ['required', 'integer'],
            'cadastral_update_id'               => ['required', 'integer'],
            'cadastral_update_uuid'             => ['required', 'string'],
            'invoicing_declaration_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->invoicing_declaration_status_id   = 11;
        $cadastralUpdatePartner->invoicing_declaration_observation = $request->invoicing_declaration_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Declaração de faturamento recusada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao recusar a declaração de faturamento."));
    }

    public function approveSocialContract(Request $request)
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

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'register_master_id'          => ['required', 'integer'],
            'cadastral_update_id'         => ['required', 'integer'],
            'cadastral_update_uuid'       => ['required', 'string'],
            'social_contract_observation' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->social_contract_status_id   = 9;
        $cadastralUpdatePartner->social_contract_observation = $request->social_contract_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Contrato social aprovado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao aprovar o contrato social."));
    }

    public function reproveSocialContract(Request $request)
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
        
        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'register_master_id'          => ['required', 'integer'],
            'cadastral_update_id'         => ['required', 'integer'],
            'cadastral_update_uuid'       => ['required', 'string'],
            'social_contract_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->cadastral_update_id)
        ->where('uuid', '=', $request->cadastral_update_uuid)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        if( ! $cadastralUpdatePartner = CadastralUpdatePartner::where('cadastral_update_id', '=', $request->cadastral_update_id)
        ->where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $cadastralUpdatePartner->social_contract_status_id   = 11;
        $cadastralUpdatePartner->social_contract_observation = $request->social_contract_observation;

        if( $cadastralUpdatePartner->save() ) {
            return response()->json(array("success" => "Contrato social recusado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao recusar o contrato social."));
    }

    protected function fileManagerS3($file_name_request, $file64, $document_type, $file_name_data)
    {
        $ext = strtolower(pathinfo($file_name_request, PATHINFO_EXTENSION));

        if( ! in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf']) ){
            return (object) [
                "success" => false,
                "message" => "Formato de arquivo $ext não permitido, formatos permitidos: jpg, jpeg, png e pdf."
            ];
        }

        $fileName = md5($document_type->id.date('Ymd').time()).'.'.$ext;

        $amazons3 = new AmazonS3();

        if (empty($file_name_data)){
            $amazons3->fileName = $file_name_data;
            $amazons3->path     = $document_type->s3_path;
            $amazons3->fileDeleteAmazon();
        }

        $amazons3->fileName = $fileName;
        $amazons3->file64   = base64_encode(file_get_contents($file64));;
        $amazons3->path     = $document_type->s3_path;
        $upfile             = $amazons3->fileUpAmazon();

        if (!$upfile->success){
            return (object) [
                "success" => false,
                "message" => "Poxa, não foi possível realizar o upload do documento informado, por favor tente novamente mais tarde."
            ];
        }
        return (object) [
            "success" => true,
            "data" => $fileName
        ];
    }

}
