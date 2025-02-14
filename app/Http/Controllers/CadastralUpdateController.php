<?php

namespace App\Http\Controllers;

use App\Models\CadastralUpdate;
use App\Models\CadastralUpdatePartner;
use App\Models\DocumentType;
use App\Models\RegisterDataPj;
use App\Models\RegisterDetail;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Libraries\AmazonS3;
use App\Libraries\ApiSendgrid;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Libraries\SimpleZip;
use App\Models\User;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Storage;

class CadastralUpdateController extends Controller
{

    protected function show(Request $request)
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

        $cadastralUpdate                     = new CadastralUpdate();
        $cadastralUpdate->id                 = $request->id;
        $cadastralUpdate->uuid               = $request->uuid;
        $cadastralUpdate->status_id_in       = $request->status_id;
        $cadastralUpdate->register_master_id = $request->register_master_id;
        $cadastralUpdate->onlyActive         = $request->onlyActive;

        return response()->json($cadastralUpdate->get());
    }

    protected function store(Request $request)
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
            'register_master_id'   => ['required', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( CadastralUpdate::where('register_master_id', '=', $request->register_master_id)
        ->where('status_id', '=', 4)
        ->whereNull('deleted_at')
        ->first()
        ) {
            return response()->json(["error" => "Já existe uma solicitação de atualização cadastral em aberto para esse cadastro."]);
        }


        $registerDetail = new RegisterDetail();
        $registerDetail->register_master_id = $request->register_master_id;
        $cpf_cnpj = $registerDetail->getRegister()->cpf_cnpj;
        $cadastral_update_type_id = '';

        if(strlen($cpf_cnpj) == 11) {
            $cadastral_update_type_id = 1;
        } else if(strlen($cpf_cnpj) == 14) {
            $cadastral_update_type_id = 2;
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido."));
        }


        if( ! $cadastralUpdate = CadastralUpdate::create([
            'uuid'                     =>  Str::orderedUuid(), 
            'cadastral_update_type_id' => $cadastral_update_type_id,
            'status_id'                => 4,
            'register_master_id'       => $request->register_master_id,
            'create_by_user_id'        => Auth::user()->id
        ]) ) {
            return response()->json(["error" => "Não foi possível realizar a atualização cadastral, por favor tente novamente mais tarde."]);
        }


        $registerDataPj = new RegisterDataPj();
        $registerDataPj->register_master_id = $request->register_master_id;
        $dataPartners = $registerDataPj->getAll();     
        $errorPf = [];       
        $errorPj = [];

        if( ! empty($dataPartners->partner_pf) ) { //if array not empty

            foreach( $dataPartners->partner_pf as $dtPartnerPf ) {
    
                if( ! CadastralUpdatePartner::create([
                    'uuid'                     =>  Str::orderedUuid(), 
                    'cadastral_update_type_id' => 1,
                    'cadastral_update_id'      => $cadastralUpdate->id,
                    'status_id'                => 4,
                    'register_master_id'       => $dtPartnerPf->register_master_id,
                ]) ) {
                    array_push($errorPf, [
                        'register_master_id' => $dtPartnerPf->register_master_id
                    ]);
                }
                
            }

        }

        if( ! empty($dataPartners->partner_pj) ) { //if array not empty

            foreach( $dataPartners->partner_pj as $dtPartnerPj ) {
    
                if( ! CadastralUpdatePartner::create([
                    'uuid'                     =>  Str::orderedUuid(), 
                    'cadastral_update_type_id' => 2,
                    'cadastral_update_id'      => $cadastralUpdate->id,
                    'status_id'                => 4,
                    'register_master_id'       => $dtPartnerPj->register_master_id,
                ]) ) {
                    array_push($errorPj, [
                        'register_master_id', $dtPartnerPj->register_master_id
                    ]);
                }

            }

        }

        if( ! empty($errorPf) || ! empty($errorPj) ) {
            return response()->json(["error" => "Ocorreu uma falha ao cadastrar o(s) sócio(s), por favor tente novamente."]);
        }

        return response()->json(["success" => "Solicitação de atualização cadastral realizada com sucesso."]);
    }

    protected function update(Request $request)
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }

        $cadastralUpdate->status_id = 9;
        $cadastralUpdate->analized_by_user_id = Auth::user()->id;
        $cadastralUpdate->analized_at = \Carbon\Carbon::now();

        if( ! $cadastralUpdate->save()) {
            return response()->json(array("error" => "Ocorreu um erro ao editar a atualização cadastral."));
        }


        if( $cadastralUpdate->document_front_s3_filename != null ) {
            if( ! Document::create([
                "register_master_id" => $cadastralUpdate->register_master_id,
                "master_id"          => $checkAccount->master_id,
                "document_type_id"   => $cadastralUpdate->document_front_type_id,
                "s3_file_name"       => $cadastralUpdate->document_front_s3_filename,
                "status_id"          => 51,
                "description"        => "Aprovado na atualização cadastral",
                "analyzed_by"        => Auth::user()->id,
                "analyzed_at"        => \Carbon\Carbon::now(),
                "created_by"         => $cadastralUpdate->send_by_user_id
            ]) ) {
                return response()->json(["success" => "Ocorreu um erro ao editar a frente do documento."]);
            }
        }


        if( $cadastralUpdate->document_verse_s3_filename != null ) {
            if( ! Document::create([
                "register_master_id" => $cadastralUpdate->register_master_id,
                "master_id"          => $checkAccount->master_id,
                "document_type_id"   => $cadastralUpdate->document_verse_type_id,
                "s3_file_name"       => $cadastralUpdate->document_verse_s3_filename,
                "status_id"          => 51,
                "description"        => "Aprovado na atualização cadastral",
                "analyzed_by"        => Auth::user()->id,
                "analyzed_at"        => \Carbon\Carbon::now(),
                "created_by"         => $cadastralUpdate->send_by_user_id
            ]) ) {
                return response()->json(["success" => "Ocorreu um erro ao editar o verso do documento."]);
            }
        }


        if( $cadastralUpdate->selfie_s3_filename != null ) {
            if( ! Document::create([
                "register_master_id" => $cadastralUpdate->register_master_id,
                "master_id"          => $checkAccount->master_id,
                "document_type_id"   => 7,
                "s3_file_name"       => $cadastralUpdate->selfie_s3_filename,
                "status_id"          => 51,
                "description"        => "Aprovado na atualização cadastral",
                "analyzed_by"        => Auth::user()->id,
                "analyzed_at"        => \Carbon\Carbon::now(),
                "created_by"         => $cadastralUpdate->send_by_user_id
            ]) ) {
                return response()->json(["success" => "Ocorreu um erro ao editar a selfie."]);
            }
        }


        if( $cadastralUpdate->address_proof_s3_filename != null ) {
            
            switch ($cadastralUpdate->cadastral_update_type_id) {
                case '1':
                    $document_type_id = 8;
                break;
                case '2':
                    $document_type_id = 12;
                break;
                default:
                    return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
            }

            if( ! Document::create([
                "register_master_id" => $cadastralUpdate->register_master_id,
                "master_id"          => $checkAccount->master_id,
                "document_type_id"   => $document_type_id,
                "s3_file_name"       => $cadastralUpdate->address_proof_s3_filename,
                "status_id"          => 51,
                "description"        => "Aprovado na atualização cadastral",
                "analyzed_by"        => Auth::user()->id,
                "analyzed_at"        => \Carbon\Carbon::now(),
                "created_by"         => $cadastralUpdate->send_by_user_id
            ]) ) {
                return response()->json(["success" => "Ocorreu um erro ao editar o comprovante de endereço."]);
            }
        }

        
        if( $cadastralUpdate->social_contract_s3_filename != null ) {
            if( ! Document::create([
                "register_master_id" => $cadastralUpdate->register_master_id,
                "master_id"          => $checkAccount->master_id,
                "document_type_id"   => 11,
                "s3_file_name"       => $cadastralUpdate->social_contract_s3_filename,
                "status_id"          => 51,
                "description"        => "Aprovado na atualização cadastral",
                "analyzed_by"        => Auth::user()->id,
                "analyzed_at"        => \Carbon\Carbon::now(),
                "created_by"         => $cadastralUpdate->send_by_user_id
            ]) ) {
                return response()->json(["success" => "Ocorreu um erro ao editar o contrato social."]);
            }
        }


        if( $cadastralUpdate->invoicing_declaration_s3_filename != null ) {
            if( ! Document::create([
                "register_master_id" => $cadastralUpdate->register_master_id,
                "master_id"          => $checkAccount->master_id,
                "document_type_id"   => 14,
                "s3_file_name"       => $cadastralUpdate->invoicing_declaration_s3_filename,
                "status_id"          => 51,
                "description"        => "Aprovado na atualização cadastral",
                "analyzed_by"        => Auth::user()->id,
                "analyzed_at"        => \Carbon\Carbon::now(),
                "created_by"         => $cadastralUpdate->send_by_user_id
            ]) ) {
                return response()->json(["success" => "Ocorreu um erro ao editar a declaração de faturamento."]);
            }
        }


        if( $cadastralUpdate->cadastral_update_type == 2 ) {

            $cadastralUpdatePartners = CadastralUpdatePartner::where('cadastral_update_id', '=', $cadastralUpdate->id)
            ->whereNull('deleted_at')
            ->first();

            foreach( $cadastralUpdatePartners as $cadastralUpdatePartner ) {

                if( $cadastralUpdatePartner->document_front_s3_filename != null ) {
                    if( ! Document::create([
                        "register_master_id" => $cadastralUpdatePartner->register_master_id,
                        "master_id"          => $checkAccount->master_id,
                        "document_type_id"   => $cadastralUpdatePartner->document_front_type_id,
                        "s3_file_name"       => $cadastralUpdatePartner->document_front_s3_filename,
                        "status_id"          => 51,
                        "description"        => "Aprovado na atualização cadastral",
                        "analyzed_by"        => Auth::user()->id,
                        "analyzed_at"        => \Carbon\Carbon::now(),
                        "created_by"         => $cadastralUpdate->send_by_user_id
                    ]) ) {
                        return response()->json(["success" => "Ocorreu um erro ao editar a frente do documento do sócio."]);
                    }
                }

                if( $cadastralUpdatePartner->document_verse_s3_filename != null ) {
                    if( ! Document::create([
                        "register_master_id" => $cadastralUpdatePartner->register_master_id,
                        "master_id"          => $checkAccount->master_id,
                        "document_type_id"   => $cadastralUpdatePartner->document_verse_type_id,
                        "s3_file_name"       => $cadastralUpdatePartner->document_verse_s3_filename,
                        "status_id"          => 51,
                        "description"        => "Aprovado na atualização cadastral",
                        "analyzed_by"        => Auth::user()->id,
                        "analyzed_at"        => \Carbon\Carbon::now(),
                        "created_by"         => $cadastralUpdate->send_by_user_id
                    ]) ) {
                        return response()->json(["success" => "Ocorreu um erro ao editar o verso do documento do sócio."]);
                    }
                }

                if( $cadastralUpdatePartner->selfie_s3_filename != null ) {
                    if( ! Document::create([
                        "register_master_id" => $cadastralUpdatePartner->register_master_id,
                        "master_id"          => $checkAccount->master_id,
                        "document_type_id"   => 7,
                        "s3_file_name"       => $cadastralUpdatePartner->selfie_s3_filename,
                        "status_id"          => 51,
                        "description"        => "Aprovado na atualização cadastral",
                        "analyzed_by"        => Auth::user()->id,
                        "analyzed_at"        => \Carbon\Carbon::now(),
                        "created_by"         => $cadastralUpdate->send_by_user_id
                    ]) ) {
                        return response()->json(["success" => "Ocorreu um erro ao editar a selfie."]);
                    }
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
        
                    if( ! Document::create([
                        "register_master_id" => $cadastralUpdatePartner->register_master_id,
                        "master_id"          => $checkAccount->master_id,
                        "document_type_id"   => $document_type_id,
                        "s3_file_name"       => $cadastralUpdatePartner->address_proof_s3_filename,
                        "status_id"          => 51,
                        "description"        => "Aprovado na atualização cadastral",
                        "analyzed_by"        => Auth::user()->id,
                        "analyzed_at"        => \Carbon\Carbon::now(),
                        "created_by"         => $cadastralUpdate->send_by_user_id
                    ]) ) {
                        return response()->json(["success" => "Ocorreu um erro ao editar o comprovante de endereço."]);
                    }
                }
                
                if( $cadastralUpdatePartner->social_contract_s3_filename != null ) {
                    if( ! Document::create([
                        "register_master_id" => $cadastralUpdatePartner->register_master_id,
                        "master_id"          => $checkAccount->master_id,
                        "document_type_id"   => 11,
                        "s3_file_name"       => $cadastralUpdatePartner->social_contract_s3_filename,
                        "status_id"          => 51,
                        "description"        => "Aprovado na atualização cadastral",
                        "analyzed_by"        => Auth::user()->id,
                        "analyzed_at"        => \Carbon\Carbon::now(),
                        "created_by"         => $cadastralUpdate->send_by_user_id
                    ]) ) {
                        return response()->json(["success" => "Ocorreu um erro ao editar o contrato social."]);
                    }
                }

                if( $cadastralUpdatePartner->invoicing_declaration_s3_filename != null ) {
                    if( ! Document::create([
                        "register_master_id" => $cadastralUpdatePartner->register_master_id,
                        "master_id"          => $checkAccount->master_id,
                        "document_type_id"   => 14,
                        "s3_file_name"       => $cadastralUpdatePartner->invoicing_declaration_s3_filename,
                        "status_id"          => 51,
                        "description"        => "Aprovado na atualização cadastral",
                        "analyzed_by"        => Auth::user()->id,
                        "analyzed_at"        => \Carbon\Carbon::now(),
                        "created_by"         => $cadastralUpdate->send_by_user_id
                    ]) ) {
                        return response()->json(["success" => "Ocorreu um erro ao editar a declaração de faturamento."]);
                    }
                }

            }
        }

        return response()->json(["success" => "Solicitação de atualização cadastral aprovada com sucesso."]);

    }

    protected function destroy(Request $request) 
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }

        $cadastralUpdate->status_id = 11;
        $cadastralUpdate->analized_by_user_id = Auth::user()->id;
        $cadastralUpdate->analized_at = \Carbon\Carbon::now();

        if( $cadastralUpdate->save() ) {
            return response()->json(array("success" => "Solicitação de atualização cadastral reprovada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao reprovar a atualização cadastral."));
    }

    public function importDocumentFront(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'register_master_id'               => ['required', 'integer'],
            'document_front_type_id'           => ['required', 'integer'],
            'document_front_imported_filename' => ['required', 'string'],
            'file64'                           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
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

        if ( ! (($fileName = $this->fileManagerS3($request->document_front_imported_filename, $request->file64, $document_type, $cadastralUpdate->document_front_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdate->document_front_type_id           = $document_type_id;
        $cadastralUpdate->document_front_imported_filename = $request->document_front_imported_filename;
        $cadastralUpdate->document_front_s3_filename       = $fileName->data;
        $cadastralUpdate->document_front_status_id         = 4;
        $cadastralUpdate->document_front_observation       = null;

        if( $cadastralUpdate->save() ) {
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
            'document_verse_type_id'           => ['required', 'integer'],
            'document_verse_imported_filename' => ['required', 'string'],
            'file64'                           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
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

        if ( ! (($fileName = $this->fileManagerS3($request->document_verse_imported_filename, $request->file64, $document_type, $cadastralUpdate->document_verse_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdate->document_verse_type_id           = $document_type_id;
        $cadastralUpdate->document_verse_imported_filename = $request->document_verse_imported_filename;
        $cadastralUpdate->document_verse_s3_filename       = $fileName->data;
        $cadastralUpdate->document_verse_status_id         = 4;
        $cadastralUpdate->document_verse_observation       = null;

        if( $cadastralUpdate->save() ) {
            return response()->json(array("success" => "Verso do documento importado com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu um erro ao importar o verso do documento."));
    }

    public function importAddressProof(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'register_master_id'               => ['required', 'integer'],
            'address_proof_imported_filename'  => ['required', 'string'],
            'file64'                           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        switch ($cadastralUpdate->cadastral_update_type_id) {
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

        if ( ! (($fileName = $this->fileManagerS3($request->address_proof_imported_filename, $request->file64, $document_type, $cadastralUpdate->address_proof_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdate->address_proof_imported_filename = $request->address_proof_imported_filename;
        $cadastralUpdate->address_proof_s3_filename       = $fileName->data;
        $cadastralUpdate->address_proof_status_id         = 4;
        $cadastralUpdate->address_proof_observation       = null;

        if( $cadastralUpdate->save() ) {
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
            'selfie_imported_filename' => ['required', 'string'],
            'file64'                   => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }

        $document_type_id = 7;

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->selfie_imported_filename, $request->file64, $document_type, $cadastralUpdate->selfie_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdate->selfie_imported_filename = $request->selfie_imported_filename;
        $cadastralUpdate->selfie_s3_filename       = $fileName->data;
        $cadastralUpdate->selfie_status_id         = 4;
        $cadastralUpdate->selfie_observation       = null;

        if( $cadastralUpdate->save() ) {
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
            'invoicing_declaration_imported_filename' => ['required', 'string'],
            'file64'                                  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }

        $document_type_id = 14;

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->invoicing_declaration_imported_filename, $request->file64, $document_type, $cadastralUpdate->invoicing_declaration_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdate->invoicing_declaration_imported_filename = $request->invoicing_declaration_imported_filename;
        $cadastralUpdate->invoicing_declaration_s3_filename       = $fileName->data;
        $cadastralUpdate->invoicing_declaration_status_id         = 4;
        $cadastralUpdate->invoicing_declaration_observation       = null;

        if( $cadastralUpdate->save() ) {
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
            'social_contract_imported_filename' => ['required', 'string'],
            'file64'                            => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $document_type_id = 11;

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->social_contract_imported_filename, $request->file64, $document_type, $cadastralUpdate->social_contract_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }


        $cadastralUpdate->social_contract_imported_filename = $request->social_contract_imported_filename;
        $cadastralUpdate->social_contract_s3_filename       = $fileName->data;
        $cadastralUpdate->social_contract_status_id         = 4;
        $cadastralUpdate->social_contract_observation       = null;

        if( $cadastralUpdate->save() ) {
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->document_front_s3_filename != null ) {

            $documentFrontTypeId = $cadastralUpdate->document_front_type_id;
            $documentType = DocumentType::where('id', '=', $documentFrontTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdate->document_front_s3_filename;

            $ext = pathinfo($cadastralUpdate->document_front_s3_filename, PATHINFO_EXTENSION);
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
                    'success'   => 'Frente do documento obtida com sucesso.',
                    'file_name'  => $cadastralUpdate->document_front_imported_filename,
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->document_verse_s3_filename != null ) {

            $documentVerseTypeId = $cadastralUpdate->document_verse_type_id;
            $documentType = DocumentType::where('id', '=', $documentVerseTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdate->document_verse_s3_filename;

            $ext = pathinfo($cadastralUpdate->document_verse_s3_filename, PATHINFO_EXTENSION);
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
                    'file_name'  => $cadastralUpdate->document_verse_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Verso do documento não importado."));
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->address_proof_s3_filename != null ) {

            switch ($cadastralUpdate->cadastral_update_type_id) {
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
            $fileAmazon->fileName = $cadastralUpdate->address_proof_s3_filename;

            $ext = pathinfo($cadastralUpdate->address_proof_s3_filename, PATHINFO_EXTENSION);
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
                    'file_name'  => $cadastralUpdate->address_proof_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Comprovante de endereço não importado."));
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->selfie_s3_filename != null ) {

            $documentTypeId = 7;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdate->selfie_s3_filename;

            $ext = pathinfo($cadastralUpdate->selfie_s3_filename, PATHINFO_EXTENSION);
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
                    'file_name'  => $cadastralUpdate->selfie_imported_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o documento, por favor tente novamente mais tarde."));

        } else {
            return response()->json(array("error" => "Selfie não importada."));
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->invoicing_declaration_s3_filename != null ) {

            $documentTypeId = 14;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdate->invoicing_declaration_s3_filename;

            $ext = pathinfo($cadastralUpdate->invoicing_declaration_s3_filename, PATHINFO_EXTENSION);
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
                    'file_name'  => $cadastralUpdate->invoicing_declaration_imported_filename,
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->social_contract_s3_filename != null ) {

            $documentTypeId = 11;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $cadastralUpdate->social_contract_s3_filename;

            $ext = pathinfo($cadastralUpdate->social_contract_s3_filename, PATHINFO_EXTENSION);
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
                    'file_name'  => $cadastralUpdate->social_contract_imported_filename,
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
            'document_front_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $cadastralUpdate->document_front_status_id   = 9;
        $cadastralUpdate->document_front_observation = $request->document_front_observation;

        if( $cadastralUpdate->save() ) {
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
            'document_front_observation' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $cadastralUpdate->document_front_status_id   = 11;
        $cadastralUpdate->document_front_observation = $request->document_front_observation;

        if( $cadastralUpdate->save() ) {
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
            'document_verse_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }
        

        $cadastralUpdate->document_verse_status_id   = 9;
        $cadastralUpdate->document_verse_observation = $request->document_verse_observation;

        if( $cadastralUpdate->save() ) {
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
            'document_verse_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $cadastralUpdate->document_verse_status_id   = 11;
        $cadastralUpdate->document_verse_observation = $request->document_verse_observation;

        if( $cadastralUpdate->save() ) {
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
            'address_proof_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }
        

        $cadastralUpdate->address_proof_status_id   = 9;
        $cadastralUpdate->address_proof_observation = $request->address_proof_observation;

        if( $cadastralUpdate->save() ) {
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
            'address_proof_observation' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $cadastralUpdate->address_proof_status_id   = 11;
        $cadastralUpdate->address_proof_observation = $request->address_proof_observation;

        if( $cadastralUpdate->save() ) {
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
            'selfie_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }
        

        $cadastralUpdate->selfie_status_id   = 9;
        $cadastralUpdate->selfie_observation = $request->selfie_observation;

        if( $cadastralUpdate->save() ) {
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
            'selfie_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $cadastralUpdate->selfie_status_id   = 11;
        $cadastralUpdate->selfie_observation = $request->selfie_observation;

        if( $cadastralUpdate->save() ) {
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
            'invoicing_declaration_observation' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }
        

        $cadastralUpdate->invoicing_declaration_status_id   = 9;
        $cadastralUpdate->invoicing_declaration_observation = $request->invoicing_declaration_observation;

        if( $cadastralUpdate->save() ) {
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
            'invoicing_declaration_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }

        $cadastralUpdate->invoicing_declaration_status_id   = 11;
        $cadastralUpdate->invoicing_declaration_observation = $request->invoicing_declaration_observation;

        if( $cadastralUpdate->save() ) {
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
            'social_contract_observation' => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }
        

        $cadastralUpdate->social_contract_status_id   = 9;
        $cadastralUpdate->social_contract_observation = $request->social_contract_observation;

        if( $cadastralUpdate->save() ) {
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
            'social_contract_observation' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }


        $cadastralUpdate->social_contract_status_id   = 11;
        $cadastralUpdate->social_contract_observation = $request->social_contract_observation;

        if( $cadastralUpdate->save() ) {
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

    public function checkPending(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        if( ! $cadastralUpdate = CadastralUpdate::where('register_master_id', '=', $checkAccount->register_master_id)
        ->where('status_id', '=', 4)        
        ->first()
        ) {
            return response()->json(["error" => "Não existe atualização cadastral necessária para sua conta."]);
        }

        return response()->json(["success" => "Existe uma atualização cadastral necessária para sua conta.", "data" => $cadastralUpdate->get()]);
    }

    public function returnToUser(Request $request)
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
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }
       

        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }

        
        $cadastralUpdate->status_id = 4;
        $cadastralUpdate->analized_by_user_id = Auth::user()->id;

        if( ! $cadastralUpdate->save() ) {
            return response()->json(array("error" => "Ocorreu um erro ao retornar a atualização cadastral para edição do usuário."));
        }

        $accName =  $cadastralUpdate->get()[0]->name;

        $apiSendGrind = new ApiSendgrid();
        $apiSendGrind->subject = "Solicitação de Atualização Cadastral";
        $apiSendGrind->content = "
            <html>
                <body>
                    <p>
                    Olá, <br><br>
                    A atualização cadastral da conta $accName precisa de revisão.<br><br><br>
                    Acesse https://ip4y.com.br e entre na sua conta.
                    </p>
                </body>
            </html>
        ";

        $user = User::where('id', '=', $cadastralUpdate->send_by_user_id)->first();
        $apiSendGrind->to_email = $user->email;
        $apiSendGrind->sendMail(); 

        return response()->json(["success" => "Solicitação de atualização cadastral disponibilizada para edição do usuário."]);
    }

    public function sendToAnalyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'register_master_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if( ! $cadastralUpdate = CadastralUpdate::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('register_master_id', '=', $request->register_master_id)
        ->first()
        ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $cadastralUpdate->status_id == 9 || $cadastralUpdate->status_id == 11 ) {
            return response()->json(["error" => "Solicitação de atualização cadastral já finalizada."]);
        }
            
        $cadastralUpdateMail = $this->prepareCadastralUpdateMail($cadastralUpdate->id);
        if( is_string($cadastralUpdateMail) ) {
            return response()->json(["error" => $cadastralUpdateMail]);
        }
        
        $cadastralUpdate->status_id = 6;
        $cadastralUpdate->send_by_user_id = Auth::user()->id;
        $cadastralUpdate->send_at = \Carbon\Carbon::now();

        if( ! $cadastralUpdate->save() ) {
            return response()->json(array("error" => "Ocorreu um erro ao enviar a atualização cadastral para análise da agência."));
        }


        return response()->json(["success" => "Atualização cadastral enviada para análise da agência."]);
    }

    public function prepareCadastralUpdateMail($cadastralUpdateId)
    {

        $cdstrUpd = new CadastralUpdate();
        $cdstrUpd->id = $cadastralUpdateId;
        $cdstrUpdData = $cdstrUpd->get()[0];

        $facilites = new Facilites();

        $bodyMessage = "
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                </tr>
            </table> <br>
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td>
                        A atualização cadastral da conta foi concluída.<br><br>
                        <b>Dados da Atualização</b>
                    </td>
                </tr>
            </table> <br>
        ";

        if ($cdstrUpdData->cadastral_update_type_id == 1) {
            $bodyMessage .="
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='80%'><b>Nome</b></td>
                        <td><b>CPF</b></td>
                    </tr>
                    <tr>
                        <td width='80%'>$cdstrUpdData->name</td>
                        <td>".$facilites->mask_cpf_cnpj($cdstrUpdData->cpf_cnpj)."</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>Tipo de Atualização</b></td>
                        <td width='25%'><b>Criado Por</b></td>
                        <td width='25%'><b>Observação</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$cdstrUpdData->cadastral_update_type_description</td>
                        <td width='25%'>$cdstrUpdData->created_by_user_name</td>
                        <td width='25%'>$cdstrUpdData->observation</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>ID Solicitação</b></td>
                        <td width='25%'><b>Iniciada Em</b></td>
                        <td width='25%'><b>Enviada Em</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$cdstrUpdData->uuid</td>
                        <td width='25%'>".\Carbon\Carbon::parse($cdstrUpdData->created_at)->format('d/m/Y H:i:s')."</td>
                        <td width='25%'>".\Carbon\Carbon::parse($cdstrUpdData->send_at)->format('d/m/Y H:i:s')."</td>
                    </tr>
                </table>
            ";


            if( $cdstrUpdData->document_front_s3_filename == null ) {
                return "Frente do documento não encontrada.";
            }

            if( $cdstrUpdData->document_verse_s3_filename == null ) {
                return "Verso do documento não encontrado.";
            }            

            if( $cdstrUpdData->address_proof_s3_filename == null ) {
                return "Comprovante de endereço não encontrado.";
            }

            if( $cdstrUpdData->selfie_s3_filename == null ) {
                return "Selfie não encontrada.";
            }

            $filesData = (object) [
                (object) [
                    "filename" => $cdstrUpdData->address_proof_s3_filename,
                    "prefixFileName" => "ComprovanteEndereço_",
                    "type"     => 8,
                ],
                (object) [
                    "filename" => $cdstrUpdData->document_front_s3_filename,
                    "prefixFileName" => "DocumentoFrente_",
                    "type"     => $cdstrUpdData->document_front_type_id

                ],
                (object) [
                    "filename" => $cdstrUpdData->document_verse_s3_filename,
                    "prefixFileName" => "DocumentoVerso_",
                    "type"     => $cdstrUpdData->document_verse_type_id
                ],
                (object) [
                    "filename" => $cdstrUpdData->selfie_s3_filename,
                    "prefixFileName" => "Selfie_",
                    "type"     => 7
                ]
            ];



        } else {

            if( $cdstrUpdData->address_proof_s3_filename == null ) {
                return "Comprovante de endereço não encontrado.";
            }

            if( $cdstrUpdData->invoicing_declaration_s3_filename == null ) {
                return "Declaração de faturamento não encontrada.";
            }

            if( $cdstrUpdData->social_contract_s3_filename == null ) {
                return "Contrato social não encontrado.";
            }


            $filesDatas = [];
            $fileDataFinal = [];

            array_push($filesDatas, [
                (object) [
                    "filename" => $cdstrUpdData->social_contract_s3_filename,
                    "prefixFileName" => "ContratoSocial_".$cdstrUpdData->social_contract_s3_filename,
                    "type" => 11
                ],
                (object) [
                    "filename" => $cdstrUpdData->invoicing_declaration_s3_filename,
                    "prefixFileName" => "DeclaraçãoFaturamento_".$cdstrUpdData->invoicing_declaration_s3_filename,
                    "type" => 14
                ],
                (object) [
                    "filename" => $cdstrUpdData->address_proof_s3_filename,
                    "prefixFileName" => "ComprovanteEndereço_".$cdstrUpdData->address_proof_s3_filename,
                    "type" =>  12,
                ]
            ]); 


            $cadastralUpdatePartners                      = new CadastralUpdatePartner();
            $cadastralUpdatePartners->cadastral_update_id = $cadastralUpdateId;
            $cadastralUpdatePartners->onlyActive          = 1;

            foreach( $cadastralUpdatePartners->get() as $cadastralUpdatePartner ) {

                if( strlen($cadastralUpdatePartner->cpf_cnpj) == 11  ) {

                    if( $cadastralUpdatePartner->document_front_s3_filename == null ) {
                        return "Frente do documento do sócio não encontrada.";
                    }
    
                    if( $cadastralUpdatePartner->document_verse_s3_filename == null ) {
                        return "Verso do documento do sócio não encontrado.";
                    }
    
                    if( $cadastralUpdatePartner->address_proof_s3_filename == null ) {
                        return "Comprovante de endereço do sócio não encontrado.";
                    }

                    array_push($filesDatas, [
                        (object) [
                            "filename" => $cadastralUpdatePartner->address_proof_s3_filename,
                            "prefixFileName" => "ComprovanteEndereço_".$cadastralUpdatePartner->address_proof_s3_filename,
                            "type" =>  8,
                        ],
                        (object) [
                            "filename" => $cadastralUpdatePartner->document_front_s3_filename,
                            "prefixFileName" => "DocumentoFrente_".$cadastralUpdatePartner->document_front_s3_filename,
                            "type"     => $cadastralUpdatePartner->document_front_type_id
                        ],
                        (object) [
                            "filename" => $cadastralUpdatePartner->document_verse_s3_filename,
                            "prefixFileName" => "DocumentoVerso_".$cadastralUpdatePartner->document_verse_s3_filename,
                            "type"     => $cadastralUpdatePartner->document_verse_type_id
                        ],
                    ]);

                } else if( strlen($cadastralUpdatePartner->cpf_cnpj) == 14 ) {

                    if( $cadastralUpdatePartner->address_proof_s3_filename == null ) {
                        return "Comprovante de endereço do sócio não encontrado.";
                    }
    
                    if( $cadastralUpdatePartner->invoicing_declaration_s3_filename == null ) {
                        return "Declaração de faturamento do sócio não encontrada.";
                    }
    
                    if( $cadastralUpdatePartner->social_contract_s3_filename == null ) {
                        return "Contrato social do sócio não encontrado.";
                    }

                    array_push($filesDatas, [
                        (object) [
                            "filename" => $cadastralUpdatePartner->social_contract_s3_filename,
                            "prefixFileName" => "ContratoSocial_".$cadastralUpdatePartner->social_contract_s3_filename,
                            "type" => 11
                        ],
                        (object) [
                            "filename" => $cadastralUpdatePartner->invoicing_declaration_s3_filename,
                            "prefixFileName" => "DeclaraçãoFaturamento_".$cadastralUpdatePartner->invoicing_declaration_s3_filename,
                            "type" => 14
                        ],
                        (object) [
                            "filename" => $cadastralUpdatePartner->address_proof_s3_filename,
                            "prefixFileName" => "ComprovanteEndereço_".$cadastralUpdatePartner->address_proof_s3_filename,
                            "type" =>  12,
                        ],
                    ]);
                } else {
                    return "Cadastro não localizado";
                }

            }


            $fileDataFinal = array_merge([], ...$filesDatas);

            
            $user = User::where('id', '=', Auth::user()->id)->first();

            $bodyMessage .="
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='50%'><b>Nome</b></td>
                        <td><b>CPF</b></td>
                    </tr>
                    <tr>
                        <td width='50%'>$cdstrUpdData->name</td>
                        <td>".$facilites->mask_cpf_cnpj($cdstrUpdData->cpf_cnpj)."</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>Tipo de Atualização</b></td>
                        <td width='25%'><b>Criado Por</b></td>
                        <td width='25%'><b>Observação</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$cdstrUpdData->cadastral_update_type_description</td>
                        <td width='25%'>$cdstrUpdData->created_by_user_name</td>
                        <td width='25%'>$cdstrUpdData->observation</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>ID Solicitação</b></td>
                        <td width='25%'><b>Iniciada Em</b></td>
                        <td width='25%'><b>Enviada Em</b></td>
                        <td width='25%'><b>Enviada Por</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$cdstrUpdData->uuid</td>
                        <td width='25%'>".\Carbon\Carbon::parse($cdstrUpdData->created_at)->format('d/m/Y H:i:s')."</td>
                        <td width='25%'>".\Carbon\Carbon::parse($cdstrUpdData->send_at)->format('d/m/Y H:i:s')."</td>
                        <td width='25%'>".$user->name."</td>
                    </tr>
                </table>
            ";
            
        }

        $bodyMessage .= "
            <br>
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td><hr></td>
                </tr>
            </table>
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td>
                        Em anexo os documentos coletados durante a solicitação.
                    </td>
                </tr>
            </table>
        ";


        if( isset($filesData) ) { //cadastral_update_type_id = 1
            $attachData = $this->createZipFile($filesData);
        } else { //cadastral_update_type_id = 2
            $attachData = $this->createZipFile($fileDataFinal);
        }

        $this->sendEmail('Atualização Cadastral Concluída', 'emails/confirmEmailAccount', $bodyMessage, $attachData->filename, $attachData->filepath, 1);

        Storage::disk('zip')->delete($attachData->filename);

        return true;
    }

    protected function sendEmail($subject, $email_layout, $bodyMessage, $attach_file, $attach_path)
    {
        $sendMail = new sendMail();
        $sendMail->to_mail      = 'compliance@ip4y.com.br';
        $sendMail->to_name      = null;
        $sendMail->send_cco     = 0;
        $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
        $sendMail->to_cco_name  = 'Ragazzi';
        $sendMail->attach_pdf   = 0;
        $sendMail->subject      = $subject;
        $sendMail->email_layout = $email_layout;
        $sendMail->bodyMessage  = $bodyMessage;
        $sendMail->attach       = 1;
        $sendMail->attach_file  = $attach_file;
        $sendMail->attach_path  = $attach_path;
        $sendMail->attach_mime  = 'application/zip';
        if ($sendMail->send()) {
            return (object) ["success" => true];
        }
        return (object) ["success" => false];
    }

    protected function createZipFile($filesData)
    {
        $SimpleZip       = new SimpleZip();
        $createZipFolder = $SimpleZip->createZipFolder();

        if ($createZipFolder->success) {

            foreach($filesData as $file) {
            
                if ($documentType = DocumentType::where('id', '=', $file->type)->first()) {                
                    $fileAmazon           = new AmazonS3();
                    $fileAmazon->path     = $documentType->s3_path;
                    $fileAmazon->fileName = $file->filename;

                    if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                        Storage::disk('zip')->put($createZipFolder->folderName.'/'.$file->prefixFileName.$file->filename, base64_decode($fileAmazon->file64));
                    }
                }
            }

            $SimpleZip->fileData = (object) [
                "folderName" => $createZipFolder->folderName,
                "deleteFiles" => true
            ];

            $createZipFile = $SimpleZip->createZipFile();

            if ($createZipFile->success) {
                Storage::disk('zip')->put($createZipFile->zipFileName, base64_decode($createZipFile->zipFile64));
            }
        }

        return (object) [
            "success" => true,
            "filepath" =>  '../storage/app/zip/'.$createZipFile->zipFileName,
            "filename" =>  $createZipFile->zipFileName,
        ];
    }

}
