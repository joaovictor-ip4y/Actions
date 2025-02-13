<?php

namespace App\Http\Controllers;

use App\Models\PixKeyConfirmation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\Account\AccountRelationshipCheckService;
use App\Libraries\Facilites;
use Illuminate\Support\Facades\Validator;
use App\Classes\Token\TokenClass;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\ApiSendgrid;
use App\Models\SendSms;
use App\Models\ApiConfig;
use App\Models\SystemFunctionMaster;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class PixKeyConfirmationController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'key_type_id' => ['required', 'integer'],
            'key' => ['required', 'string'],
        ],[
            'key_type_id.required' => 'Informe o tipo de chave.',
            'key.required' => 'Informe a chave.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        switch ($request->key_type_id) {
            case 3:
                if (!Facilites::validatePhone($request->key)) {
                    return response()->josn(array("error" => "Número de celular inválido"));
                }

                $key = "+55".preg_replace('/[^0-9]/', '', $request->key);
                
                $pixKeyConfirmation = PixKeyConfirmation::create([
                    'uuid' => Str::orderedUuid(),
                    'account_id' => $checkAccount->account_id,
                    'pix_key_type_id' => $request->key_type_id,
                    'key' => $key,
                    'token_send_to_user_id' => $checkAccount->user_id,
                    'token_attempt' => 0,
                    'token_validated' => 0,
                    'ip' => $request->header('ip'),
                ]);

                $token = self::createToken($pixKeyConfirmation->id);

                $pixKeyConfirmation->approval_token = $token->data->token_phone;
                $pixKeyConfirmation->approval_token_expiration = $token->data->token_expiration;
                $pixKeyConfirmation->save();

                $sendTokenPhone = self::sendPhoneToken($pixKeyConfirmation->id, $checkAccount->master_id, $pixKeyConfirmation->approval_token, preg_replace('/[^0-9]/', '', $request->key), $checkAccount->user_name);

                if (!$sendTokenPhone) {
                    return response()->josn(array("error" => "Poxa, ocorreu uma falha ao enviar o token para o celular, por favor tente novamente mais tarde"));
                }

            break;
            case 4:
                if (!Facilites::validateEmail($request->key)) {
                    return response()->josn(array("error" => "E-Mail inválido"));
                }

                $key = $request->key;

                $pixKeyConfirmation = PixKeyConfirmation::create([
                    'uuid' => Str::orderedUuid(),
                    'account_id' => $checkAccount->account_id,
                    'pix_key_type_id' => $request->key_type_id,
                    'key' => $key,
                    'token_send_to_user_id' => $checkAccount->user_id,
                    'token_attempt' => 0,
                    'token_validated' => 0,
                    'ip' => $request->header('ip'),
                ]);

                $token = self::createToken($pixKeyConfirmation->id);

                $pixKeyConfirmation->approval_token = $token->data->token_email;
                $pixKeyConfirmation->approval_token_expiration = $token->data->token_expiration;
                $pixKeyConfirmation->save();

                $sendTokenMail = self::sendEmailToken($pixKeyConfirmation->id, $checkAccount->master_id, $pixKeyConfirmation->approval_token, $key, $checkAccount->user_name);

                if (! $sendTokenMail) {
                    return response()->josn(array("error" => "Poxa, ocorreu uma falha ao enviar o token para o e-mail, por favor tente novamente mais tarde"));
                }
            break;
            default:
                return response()->json(array("error" => "Tipo de chave Pix não permitda para confirmação"));
            break;
        }
        
        return response()->json(array(
            "success" => "Token enviado com sucesso, a partir de agora você tem 10 minutos para valida-lo, se necessário repita o procedimento", 
            "data" => ["id" => $pixKeyConfirmation->id, "uuid" => $pixKeyConfirmation->uuid]
        ));
    }

    protected static function sendPhoneToken($id, $masterId, $token_confirmation_phone, $phone, $name)
    {
        $message = "Ola ".(explode(" ",$name))[0].", o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para confirmar este numero como chave Pix na iP4y Instituicao de Pagamento LTDA.";

        $sendSMS = SendSms::create([
            'external_id' => "18-".Str::orderedUuid(),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 18,
            'origin_id'   => $id,
            'created_at'  => \Carbon\Carbon::now()
        ]);

        $apiConfig                     = new ApiConfig();
        $apiConfig->master_id          = $masterId;
        $apiConfig->api_id             = 3;
        $apiConfig->onlyActive         = 1;
        $apiData                       = $apiConfig->getApiConfig()[0];
        $apiZenviaSMS                  = new ApiZenviaSMS();
        $apiZenviaSMS->api_address     = Crypt::decryptString($apiData->api_address);
        $apiZenviaSMS->authorization   = Crypt::decryptString($apiData->api_authentication);
        $apiZenviaSMS->id              = $sendSMS->external_id;
        $apiZenviaSMS->aggregateId     = "001";
        $apiZenviaSMS->to              = $sendSMS->to;
        $apiZenviaSMS->msg             = $sendSMS->message;
        $apiZenviaSMS->callbackOption  = "NONE";

        //Check if should send token by whatsapp
        if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$masterId)->first())->available == 1 ){
            $apiZenviaWhats            = new ApiZenviaWhatsapp();
            $apiZenviaWhats->to_number = $sendSMS->to;
            $apiZenviaWhats->token     = "*".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)."*";
            if(isset( $apiZenviaWhats->sendToken()->success ) ){
                return true;
            }
        }

        if(isset($apiZenviaSMS->sendShortSMS()->success)){
            return true;
        } else {
            return false;
        }
    }

    protected function sendWhatsAppToken($id, $masterId, $token_confirmation_phone, $phone, $name)
    {
        $sendSMS = SendSms::create([
            'external_id' => "18-".Str::orderedUuid(),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 18,
            'origin_id'   => $id,
            'created_at'  => \Carbon\Carbon::now()
        ]);

        $apiZenviaWhats = new ApiZenviaWhatsapp();
        $apiZenviaWhats->to_number = $phone;
        $apiZenviaWhats->token = "*".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)."*";
        if(isset( $apiZenviaWhats->sendToken()->success ) ){
            return true;
        }
        return false;
    }


    protected function sendEmailToken($id, $masterId, $token_confirmation_email, $email, $name)
    {
        $message = "Olá $name, <br>O token ".substr($token_confirmation_email,0,4)."-".substr($token_confirmation_email,4,4)." foi gerado para confirmar este e-mail como chave Pix na iP4y Instituição de Pagamento LTDA.";

        $apiSendGrind = new ApiSendgrid();
        $apiSendGrind->to_email = $email;
        $apiSendGrind->to_name = $name;
        $apiSendGrind->to_cc_email = 'ragazzi@dinari.com.br';
        $apiSendGrind->to_cc_name  = 'Ragazzi';
        $apiSendGrind->subject = 'iP4y - Confirmação de cadastro para chave Pix';
        $apiSendGrind->content = $message;
        if($apiSendGrind->sendSimpleEmail()){
           return true;
        }
        return false;
    }

    protected static function createToken($id)
    {
        $createToken = new TokenClass();
        $createToken->data = (object) [
            "type_id" => 18,
            "origin_id" => $id,
            "minutes_to_expiration" => 10,
        ];
        return $createToken->createToken();
    }



}
