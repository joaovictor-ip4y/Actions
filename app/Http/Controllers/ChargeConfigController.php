<?php

namespace App\Http\Controllers;

use App\Models\ChargeConfig;
use App\Models\DocumentType;
use App\Libraries\AmazonS3;
use App\Libraries\Facilites;
use App\Models\Charge;
use App\Models\PayerEmail;
use App\Models\PayerPhone;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; 

class ChargeConfigController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [246, 306];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(ChargeConfig::where('account_id','=',$checkAccount->account_id)->count() == 0){
            if(!$this->createChargeConfig($checkAccount->account_id)){
                return response()->json(array("error" => "Não foi possível criar a configuração de cobrança, por favor entre em contato com o suporte"));
            }
        }

        $chargeConfig             = new ChargeConfig();
        $chargeConfig->master_id  = $checkAccount->master_id;
        $chargeConfig->account_id = $checkAccount->account_id;
        return response()->json(array("success" => "", "data" => $chargeConfig->getAccountChargeConfig()[0]));
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [247, 307];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->send_email_copy_to != '') {
            if( ! Facilites::validateEmail($request->send_email_copy_to)) {
                return response()->json(["error" => "E-mail para envio de cópias inválido."]);
            }
        }

        $chargeConfig                                    = ChargeConfig::where('account_id','=',$checkAccount->account_id)->first();
        $chargeConfig->fine                              = $request->fine;
        $chargeConfig->interest                          = $request->interest;
        $chargeConfig->message1                          = $request->message1;
        $chargeConfig->message2                          = $request->message2;
        $chargeConfig->message3                          = $request->message3;
        $chargeConfig->message4                          = $request->message4;
        $chargeConfig->days_to_due_date                  = $request->days_to_due_date;
        $chargeConfig->nfs_city_id                       = $request->nfs_city_id;
        $chargeConfig->observation                       = $request->observation;
        $chargeConfig->header_name                       = $request->header_name;
        $chargeConfig->cnab_interest_fine                = $request->cnab_interest_fine;
        $chargeConfig->cnab_messages                     = $request->cnab_messages;
        $chargeConfig->days_before_due_date              = $request->days_before_due_date;
        $chargeConfig->days_after_due_date               = $request->days_after_due_date;
        $chargeConfig->send_email_copy_to                = $request->send_email_copy_to;

        if($request->mail_before_due_date == 0){
            $chargeConfig->mail_before_due_date  = null;
        } else {
            $chargeConfig->mail_before_due_date  = $request->mail_before_due_date;
        }

        if($request->mail_on_due_date == 0){
            $chargeConfig->mail_on_due_date  = null;
        } else {
            $chargeConfig->mail_on_due_date  = $request->mail_on_due_date;
        }

        if($request->mail_after_due_date == 0){
            $chargeConfig->mail_after_due_date  = null;
        } else {
            $chargeConfig->mail_after_due_date  = $request->mail_after_due_date;
        }

        if($request->cnab_interest_fine == 0){
            $chargeConfig->cnab_interest_fine  = null;
        } else {
            $chargeConfig->cnab_interest_fine  = $request->cnab_interest_fine;
        }

        if($request->cnab_messages == 0){
            $chargeConfig->cnab_messages  = null;
        } else {
            $chargeConfig->cnab_messages  = $request->cnab_messages;
        }


        if($chargeConfig->save()){
            return response()->json(array("success" => "Configurações de cobrança atualizadas com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar as configurações de cobrança, por favor tente novamente mais tarde"));
        }
    }

    public function createChargeConfig($account_id)
    {
        if(ChargeConfig::create([
            'account_id' => $account_id,
            'fine'       => 0,
            'interest'   => 0,
            'created_at' => \Carbon\Carbon::now()
        ])){
            return true;
        } else {
            return false;
        }
    }

    protected function setLogoS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [247, 307];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try{
            if (!$chargeConfig = ChargeConfig::where('account_id','=',$checkAccount->account_id)->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        //id 29 === Logo
        if (!$document_type = DocumentType::where('id', '=', 29)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $chargeConfig->logo_s3_filename, 's3_public'))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        $chargeConfig->logo_s3_filename = $fileName->data;

        if ($chargeConfig->save()) {
            return response()->json(array("success"=>"Logo enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a logo, por favor tente novamente mais tarde."));
    }

    protected function fileManagerS3($file_name_request, $file64, $document_type, $file_name_data, $disk = 's3')
    {
        // extensão do arquivo minuscula.
        $ext = strtolower(pathinfo($file_name_request, PATHINFO_EXTENSION));

        // verifica se é um arquivo com extensão permitida.
        if( ! in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf']) ){
            return (object) [
                "success" => false,
                "message" => "Formato de arquivo $ext não permitido, formatos permitidos: jpg, jpeg, png e pdf."
            ];
        }

        // cria um nome único para o arquivo, acrescentado a extensão ao final (XPTO.exe)
        $fileName = md5($document_type->id.date('Ymd').time()).'.'.$ext;

        // instancia amazonS3
        $amazons3 = new AmazonS3();
        $amazons3->disk = $disk;

        // exclui arquivo caso já esteja defindo o nome do mesmo no campo.
        if (empty($file_name_data)){
            $amazons3->fileName = $file_name_data;
            $amazons3->path     = $document_type->s3_path;
            $amazons3->fileDeleteAmazon();
        }

        // define os parâmetros do novo arquivo para realizar o upload.
        $amazons3->fileName = $fileName;
        $amazons3->file64   = base64_encode(file_get_contents($file64));;
        $amazons3->path     = $document_type->s3_path;

        // realiza o upload do arquivo.
        $upfile             = $amazons3->fileUpAmazon();

        // checa se não foi sucesso e por fim realiza os retorns necessários
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
    
    public function getLogoS3(Request $request)
    {
        
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [246, 306];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        
        if (!$chargeConfig = ChargeConfig::where('account_id','=',$checkAccount->account_id)->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }
        
        if( $chargeConfig->logo_s3_filename != null ) {
    
            $documentTypeId = 29;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();
    
            $fileAmazon = new AmazonS3();
            $fileAmazon->disk = 's3_public';
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $chargeConfig->logo_s3_filename;

        
            $ext = pathinfo($chargeConfig->logo_s3_filename, PATHINFO_EXTENSION);
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
                    'success' => 'Logo obtida com sucesso.',
                    'file_name'  => $chargeConfig->logo_s3_filename,
                    'mime_type' => $mimeType,
                    'base64'    => $fileAmazon->file64,
                ]);
            }
            return response()->json(array("error" => "Ocorreu uma falha ao obter o Logo, por favor tente novamente mais tarde."));
    
        } else {
            return response()->json(array("error" => "Logo não encontrado."), 404);
        }
        
    }
}
