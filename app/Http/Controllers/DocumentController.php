<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentIdentificationType;
use App\Models\DocumentType;
use App\Models\RegisterMaster;
use App\Models\DocumentRg;
use App\Models\DocumentCnh;
use App\Models\RegisterDataPf;
use App\Libraries\AmazonS3;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [38];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $document = new Document();
        $document->register_master_id = $request->register_master_id;
        $document->onlyActive = $request->onlyActive;
        $document->onlyExpired = $request->onlyExpired;
        $document->document_type_id = $request->document_type_id;
        $document->register_id = $request->register_id;
        $document->expiration_date_start = $request->expiration_date_start;
        $document->expiration_date_end = $request->expiration_date_end;
        return response()->json($document->getDocument());
    }

    protected function importMaster(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [37];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($document_type = DocumentType::where('id','=', $request->type_id)->first()){
            $ext = strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
            $fileName = md5($request->type_id.$request->register_master_id.date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
                Document::create([
                    'register_master_id' => $request->register_master_id,
                    'master_id'          => $checkAccount->master_id,
                    'document_type_id'   => $request->type_id,
                    's3_file_name'       => $fileName,
                    'status_id'          => 50,
                    'description'        => $request->description,
                    'created_by'         => $request->header('userId'),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
                return response()->json(array("success"=>"Documento enviado com sucesso"));
            }else{
                return response()->json(array("error"=>"Falha ao enviar o documento"));
            }
        }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
        }
    }

    protected function downloadMaster(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($document = Document::where('id','=',$request->id)->where('register_master_id','=',$request->register_master_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $document_type = DocumentType::where('id','=', $document->document_type_id)->first();
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $document->s3_file_name;
            $amazons3->path     = $document_type->s3_path;
            $downfile            = $amazons3->fileDownAmazon();
            if($downfile->success){
                $ext = pathinfo($document->s3_file_name, PATHINFO_EXTENSION);
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
                if(isset($downfile->file64)){
                    return response()->json(array(
                        "success"   =>"Download do documento realizado com sucesso",
                        "file_name" => $document_type->description.'_'.$document->id.'.'.$ext,
                        "mime_type" => $mimeType,
                        "base64"    => $downfile->file64
                    ));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao baixar o documento"));
                }

            }else{
                return response()->json(array("error"=>"Falha ao baixar o documento"));
            }
        }else{
            return response()->json(array("error"=>"Documento não localizado"));
        }

    }

    protected function approveDocument(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [42];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($document = Document::where('id','=',$request->document_id)->where('register_master_id','=',$request->register_master_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $document->status_id   = 51;
            $document->analyzed_by = $request->header('userId');
            $document->analyzed_at = \Carbon\Carbon::now();
            if($document->save()){
                return response()->json(array("success" => "Documento validado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao validar o documento, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" => "Documento não localizado"));
        }
    }

    protected function repproveDocument(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [41];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($document = Document::where('id','=',$request->document_id)->where('register_master_id','=',$request->register_master_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $document->status_id   = 49;
            $document->deleted_at  = \Carbon\Carbon::now();
            //$document->analyzed_by = $request->header('userId');
            //$document->analyzed_at = \Carbon\Carbon::now();
            if($document->save()){
                return response()->json(array("success" => "Documento recusado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao recusar o documento, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" => "Documento não localizado"));
        }
    }

    protected function importUser(Request $request)
    {
        if($document_type = DocumentType::where('id','=', $request->type_id)->first()){
            $ext = strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
            $fileName = md5($request->type_id.$request->register_master_id.date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
                Document::create([
                    'register_master_id' => $request->register_master_id,
                    'master_id'          => $request->header('masterId'),
                    'document_type_id'   => $request->type_id,
                    's3_file_name'       => $fileName,
                    'status_id'          => 50,
                    'description'        => $request->description,
                    'created_by'         => $request->header('userId'),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
                return response()->json(array("success"=>"Documento enviado com sucesso"));
            }else{
                return response()->json(array("error"=>"Falha ao enviar o documento"));
            }
        }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
        }
    }

    protected function importSelfie(Request $request)
    {
        $register_master = RegisterMaster::returnRegisterUser($request->header('userId'), $request->header('masterId'));
        if($register_master->count() < 1){
            return response()->json(array("error"=>"Cadastro do usuário não localizado"));
        } else {
            $register_master_id = $register_master->first()->register_master_id;
        }

        $document_type = DocumentType::where('id','=',7)->first();
        $ext = strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
        $fileName = md5('7'.rand(1,999).date('Ymd').time()).'.'.$ext;
        $amazons3 = new AmazonS3();
        $amazons3->fileName = $fileName;
        $amazons3->file64   = base64_encode(file_get_contents($request->file64));
        $amazons3->path     = $document_type->s3_path;
        $upfile             = $amazons3->fileUpAmazon();
        if($upfile->success){
            Document::create([
                'register_master_id' => $register_master_id,
                'master_id'          => $request->header('masterId'),
                'document_type_id'   => 7,
                's3_file_name'       => $fileName,
                'status_id'          => 50,
                'description'        => $request->description,
                'created_by'         => $request->header('userId'),
                'created_at'         => \Carbon\Carbon::now()
            ]);
            return response()->json(array("success"=>"Selfie enviada com sucesso"));
        }else{
            return response()->json(array("error"=>"Falha ao enviar a Selfie"));
        }

    }

    protected function importDocumentFront(Request $request)
    {
        $register_master = RegisterMaster::returnRegisterUser($request->header('userId'), $request->header('masterId'));
        if($register_master->count() < 1){
            return response()->json(array("error"=>"Cadastro do usuário não localizado"));
        } else {
            $register_master_id = $register_master->first()->register_master_id;
        }

        switch($request->dcmnt_idtf_tp_id){
            case(1):
              $documentType = 1;
            break;
            case(2):
              $documentType = 3;
            break;
            case(3):
              $documentType = 5;
            break;
            default:
            return response()->json(array("error"=>"Tipo de Documento não definido"));
        }
        if($document_type = DocumentType::where('id','=',$documentType)->first()){
            $ext = strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
            $fileName = md5($documentType.rand(1,999).date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
                Document::create([
                    'register_master_id' => $register_master_id,
                    'master_id'          => $request->header('masterId'),
                    'document_type_id'   => $documentType,
                    's3_file_name'       => $fileName,
                    'status_id'          => 50,
                    'description'        => $request->description,
                    'created_by'         => $request->header('userId'),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
                return response()->json(array("success"=>"Frente do Documento enviada com sucesso"));
            }else{
                return response()->json(array("error"=>"Falha ao enviar a frente do Documento"));
            }
        }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
        }
    }

    protected function importDocumentVerse(Request $request)
    {
        $register_master = RegisterMaster::returnRegisterUser($request->header('userId'), $request->header('masterId'));
        if($register_master->count() < 1){
            return response()->json(array("error"=>"Cadastro do usuário não localizado"));
        } else {
            $register_master_id = $register_master->first()->register_master_id;
        }

        switch($request->dcmnt_idtf_tp_id){
            case(1):
             $documentType = 2;
            break;
            case(2):
              $documentType = 4;
            break;
            case(3):
              $documentType = 6;
            break;
            default:
            return response()->json(array("error"=>"Tipo de Documento não definido"));
        }
        if($document_type = DocumentType::where('id','=',$documentType)->first()){
            $ext = strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
            $fileName = md5($documentType.rand(1,999).date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
                Document::create([
                    'register_master_id' => $register_master_id,
                    'master_id'          => $request->header('masterId'),
                    'document_type_id'   => $document_type->id,
                    's3_file_name'       => $fileName,
                    'status_id'          => 50,
                    'description'        => $request->description,
                    'created_by'         => $request->header('userId'),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
                return response()->json(array("success"=>"Verso do documento enviado com sucesso"));
            }else{
                return response()->json(array("error"=>"Falha ao enviar a verso do documento"));
            }
        }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
        }
    }

    protected function importPFAddressProof(Request $request)
    {
        $register_master = RegisterMaster::returnRegisterUser($request->header('userId'), $request->header('masterId'));
        if($register_master->count() < 1){
            return response()->json(array("error"=>"Cadastro do usuário não localizado"));
        } else {
            $register_master_id = $register_master->first()->register_master_id;
        }

        if($document_type = DocumentType::where('id','=',8)->first()){
            $ext = strtolower(pathinfo($request->file_name, PATHINFO_EXTENSION));
            $fileName = md5('8'.rand(1,999).date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
                Document::create([
                    'register_master_id' => $register_master_id,
                    'master_id'          => $request->header('masterId'),
                    'document_type_id'   => $document_type->id,
                    's3_file_name'       => $fileName,
                    'status_id'          => 50,
                    'description'        => $request->description,
                    'created_by'         => $request->header('userId'),
                    'created_at'         => \Carbon\Carbon::now()
                ]);
                return response()->json(array("success"=>"Comprovante de endereço enviado com sucesso"));
            }else{
                return response()->json(array("error"=>"Falha ao enviar o comprovante de endereço"));
            }
        }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
        }
    }

    protected function updateDocumentData(Request $request)
    {
        $register_master = RegisterMaster::returnRegisterUser($request->header('userId'), $request->header('masterId'));
        if($register_master->count() < 1){
            return response()->json(array("error"=>"Cadastro do usuário não localizado"));
        } else {
            if( $registerDataPf = RegisterDataPf::where('register_master_id','=',($register_master->first())->register_master_id)->first() ){

                if($request->document_type == 1){
                
                    $documentRg = DocumentRg::where('rgstr_data_pf_id','=',$registerDataPf->id)->first();
                    if($documentRg->number == '' or $documentRg->number == null){
                        $documentRg->number = $request->rg_number;
                        if($documentRg->save()){
                            return response()->json(array("success" => "Documentos enviados com sucesso"));
                        } else {
                            return response()->json(array("success" => "Poxa, não foi possível finalizar o envio de seus documentos, por favor tente novamente mais tarde."));
                        }
                    } else {
                        return response()->json(array("success" => "Documentos enviados com sucesso"));
                    }
                } else {
                    $documentCnh = DocumentCnh::where('rgstr_data_pf_id','=',$registerDataPf->id)->first();
                    if($documentCnh->number == '' or $documentCnh->number == null){
                        $documentCnh->number = $request->cnh_number;
                        if($documentCnh->save()){
                            return response()->json(array("success" => "Documentos enviados com sucesso"));
                        } else {
                            return response()->json(array("success" => "Poxa, não foi possível finalizar o envio de seus documentos, por favor tente novamente mais tarde."));
                        }
                    } else {
                        return response()->json(array("success" => "Documentos enviados com sucesso"));
                    }
                }
            } else {
                return response()->json(array("error" => "Poxa, existe uma inconsistência com seu cadastro, por favor entre em contato com o suporte"));
            }
        }
    }

    protected function getExpiredDocInfos(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'register_master_id'   => ['required', 'integer']
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if(!$document = Document::where('id', '=', $request->id)->where('register_master_id', '=', $request->register_master_id)->where('master_id', '=', $checkAccount->master_id)->first()){
            return response()->json(array("error" => "Documento não localizado"));
        }

        $document_type_ids_with_document_expired = [1, 2, 3, 4, 5, 6, 8, 11, 12, 13, 14, 30];
        if(in_array($document->document_type_id, $document_type_ids_with_document_expired)) {
            return response()->json(array(
                "success" => true, 
                "have_expired_document" => true,
                "is_document_expired" => $document->is_expired, 
                "expired_document_deadline" => $document->expired_document_deadline_at, 
                "is_document_regularized" => $document->is_regularized
            ));
        }

        return response()->json(array("success" => true, "have_expired_document" => false, "data" => "O arquivo enviado não possui documento a vencer"));

    }

    protected function setExpiredDocInfos(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'register_master_id'   => ['required', 'integer'],
            'is_expired'           => ['required', 'integer'],
            'deadline_at'          => ['nullable', 'string'],
            'is_regularized'       => ['required', 'integer']
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if($document = Document::where('id', '=', $request->id)->where('register_master_id', '=', $request->register_master_id)->where('master_id', '=', $checkAccount->master_id)->first()){
            $document->is_expired = $request->is_expired;
            $document->expired_document_deadline_at = $request->deadline_at;
            $document->is_regularized = $request->is_regularized;
            $document->save();

            return response()->json(array("success" => "As informações de documento vencido foram atualizadas com sucesso."));
        }

        return response()->json(array("error" => "Documento não localizado"));
    }

    protected function checkExpiredDocuments(Request $request) {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //        

        $expiredDocuments = new Document();
        $expiredDocuments->register_master_id = $checkAccount->register_master_id;
        $expiredDocuments->onlyExpired = 1;
        $expiredDocuments->onlyActive = 1;        

        $nomeDocs = [];
        foreach( $expiredDocuments->getDocument() as $expiredDoc) {

            $expiredDocumentDate = \Carbon\Carbon::parse($expiredDoc->expired_document_deadline_at);
            $actualDate = \Carbon\Carbon::now();
            
            if($expiredDocumentDate->addDays(30)->lessThan($actualDate)) {
                array_push($nomeDocs, $expiredDoc->document_type_description);
            }
        }
   
        if( $nomeDocs != null ) {
            return response()->json(["success" => "É necessária a atualização de documentos.", "data" => $nomeDocs]);
        }
        
        return response()->json(["error" => "Nenhum documento vencido."]);
    }
}
