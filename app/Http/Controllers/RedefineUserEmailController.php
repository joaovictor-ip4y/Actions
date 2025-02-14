<?php

namespace App\Http\Controllers;

use App\Models\RedefineUserEmail;
use App\Models\User;
use App\Models\SendSms;
use App\Models\ApiConfig;
use App\Models\SystemFunctionMaster;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Hash;

class RedefineUserEmailController extends Controller
{   /*
    protected function userUpdateEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();
            if( Hash::check( base64_decode($request->current_password) , $usr->password) ){
                $token = new Facilites();
                if($redefineEmail = RedefineUserEmail::create([
                    'user_id'                  => $usr->id,
                    'unique_id'                => md5($usr->id.date('Ymd').time()),
                    'master_id'                => $checkAccount->master_id,
                    'old_email'                => $usr->email,
                    'token_confirmation_phone' => $token->createApprovalToken(),
                    'token_confirmation_email' => $token->createApprovalToken(),
                    'token_email_confirmed'    => 0,
                    'token_phone_confirmed'    => 0,
                    'finished'                 => 0,
                    'ip'                       => $request->header('ip'),
                    'created_at'               => \Carbon\Carbon::now(),
                ])){
                    if($this->sendPhoneToken($redefineEmail->id, $redefineEmail->master_id, $redefineEmail->token_confirmation_phone, $usr->phone, $usr->name)){
                        return response()->json(array("success" => "Token enviado com sucesso", "id" => $redefineEmail->id, "unique_id" => $redefineEmail->unique_id));
                    } else {
                        return response()->json(array("error" => "Ocorreu uma falha ao enviar o token para o celular, por favor tente novamente"));
                    }
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao iniciar a redefinição do e-mail, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("error" => "Senha atual inválida"));
            }
        } else {
            return response()->json(array("error" => "Ocorreu uma falha de autenticação"));
        }
    }

    protected function updateEmailConfirmPhoneToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(RedefineUserEmail::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->count() > 0){
            $redefineEmail = RedefineUserEmail::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->first();
            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefineEmail->created_at )->addMinutes(20))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Token expirado, por favor recomece o processo de redefinição de e-mail"));
            }
            if($redefineEmail->token_confirmation_phone != $request->phone_token){
                return response()->json(array("error" => "Token informado não confere com o token enviado por SMS"));
            } else {
                $redefineEmail->token_phone_confirmed = 1;
                if($redefineEmail->save()){
                    return response()->json(array("success" => "Token enviado por SMS confirmado com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente"));
                }
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de e-mail não encontrada"));
        }
    }

    protected function updateEmailSetNew(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(RedefineUserEmail::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->count() > 0){
            $redefineEmail = RedefineUserEmail::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->first();
            if($redefineEmail->token_phone_confirmed == 0){
                return response()->json(array("error" => "É necessário confirmar o token enviado por SMS para continuar"));
            }
            if(User::where('email','=',$request->email)->count() > 0){
                return response()->json(array("error" => "Por favor informe outro e-mail, o e-mail informado pertence a outro usuário"));
            }
            $redefineEmail->new_email = $request->email;
            if($redefineEmail->save()){
                $user = User::where('id','=',$redefineEmail->user_id)->first();
                if($this->sendEmailToken($redefineEmail->id, $redefineEmail->master_id, $redefineEmail->token_confirmation_email, $redefineEmail->new_email, $user->name)){
                    return response()->json(array("success" => "Token de confirmação enviado para o novo e-mail com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao enviar o token para o novo e-mail, por favor tente novamente"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao definir o novo e-mail, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de e-mail não encontrada"));
        }
    }

    protected function updateEmailFinish(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(RedefineUserEmail::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->count() > 0){
            $redefineEmail = RedefineUserEmail::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->first();
            if($redefineEmail->new_email == '' or $redefineEmail->new_email == null){
                return response()->json(array("error" => "É necessário definir um novo e-mail para continuar"));
            }
            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefineEmail->updated_at )->addMinutes(20))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Token expirado, por favor recomece o processo de redefinição de e-mail"));
            }
            if($redefineEmail->token_confirmation_email != $request->email_token){
                return response()->json(array("error" => "Token informado não confere com o token enviado para o novo e-mail"));
            } else {            
                if(User::where('email','=', $redefineEmail->new_email)->count() > 0){
                    return response()->json(array("error" => "Por favor informe outro e-mail, o e-mail informado pertence a outro usuário (e-mail pertence a usuário cadastrado/alterado nos últimos 20 minutos)"));
                }
                $user = User::where('id','=',$redefineEmail->user_id)->first();
                $user->email = $redefineEmail->new_email;
                
                if($user->save()){
                    $redefineEmail->token_email_confirmed = 1;
                    $redefineEmail->finished              = 1;
                    $redefineEmail->save();
                    return response()->json(array("success" => "E-mail alterado com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao alterar o e-mail, por favor tente novamente"));
                }
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de e-mail não encontrada"));
        }
    }

    public function sendEmailToken($id, $masterId, $token_confirmation_email, $email, $name)
    {
        $message = "Olá $name, <br>O token ".substr($token_confirmation_email,0,4)."-".substr($token_confirmation_email,4,4)." foi gerado para a verificação de redefinição do seu e-mail da conta digital.";
        $sendMail = new sendMail();
        $sendMail->to_mail      = $email;
        $sendMail->to_name      = $name;
        $sendMail->send_cco     = 0;
        $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
        $sendMail->to_cco_name  = 'Ragazzi';
        $sendMail->attach_pdf   = 0;
        $sendMail->subject      = 'Token para redefinição de e-mail';
        $sendMail->email_layout = 'emails/confirmEmailAccount';
        $sendMail->bodyMessage  = $message;
        if($sendMail->send()){
           return true;
        } else {
           return false;
        }
    }
  
    public function sendPhoneToken($id, $masterId, $token_confirmation_phone, $phone, $name)
    {
        $message = "Olá $name, o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para a verificação de redefinição do seu e-mail da conta digital.";
    
        $sendSMS = SendSms::create([
            'external_id' => ("5".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$id),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 5,
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
            $apiZenviaWhats->token     = "*".substr($token_confirmation_phone)."-".substr($token_confirmation_phone,4,4)."*";                     
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

    */

}
