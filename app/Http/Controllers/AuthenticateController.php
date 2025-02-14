<?php

namespace App\Http\Controllers;

use App\Classes\Failure\MovimentationFailureClass;
use App\Models\RedefinePassword;
use App\Models\User;
use App\Models\UserMaster;
use App\Models\UserRelationship;
use App\Models\Account;
use App\Models\Relationship;
use App\Models\UserCheck;
use App\Models\RegisterDetail;
use App\Models\SendSms;
use App\Models\ApiConfig;
use App\Models\AuthAttempt;
use App\Models\SystemFunctionMaster;
use App\Models\PayrollEmployee;
use App\Models\PayrollEmployeeDetail;
use App\Libraries\ApiGoogle;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\ApiSendgrid;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Models\AccountMovement;
use App\Models\RegisterMaster;
use App\Services\User\UserService;
use App\Classes\LexisNexis\Threatmetrix\ThreatMetrixSessionQueryAPI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;



class AuthenticateController extends Controller
{
    protected function Check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recaptcha_response' => ['required', 'string'],
            'cpf_cnpj' => ['required', 'string', 'size:11']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => "Dados inválidos, por favor verifique os dados informados"));
        }

        $apiGoogle = new ApiGoogle();
        $apiGoogle->secret      = config('services.recaptcha.key');
        $apiGoogle->response    = $request->recaptcha_response;
        $apiGoogle->response_ip = $request->header('ip');
        $checkRecaptcha         = $apiGoogle->checkRecaptcha();

        if(!$checkRecaptcha->body->success){
            return response()->json(array("error" => "reCaptcha Inválido, por favor selecione e preencha o reCaptcha corretamente", "recaptcha_error" => true));
        } 
        
        if(User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$request->cpf_cnpj))->count() > 0 ){
            $usr = User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$request->cpf_cnpj))->first();


            if($usr->status == 3){
                return response()->json(array("error" => "CPF inválido, por favor tente novamente ou entre em contato com o suporte",  "recaptcha_error" => false));
            }

            $usr->recaptcha_response = $request->recaptcha_response;
            $usr->save();

          

            $authAttempt = AuthAttempt::create([
                'unique_id' =>  md5($usr->id.date('Ymd').time().rand(1,999)),
                'session_identification' => $request->s_iden,
                'user_id' => $usr->id,
                'ip' => $request->header('ip'),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->accuracy,
                'altitude' => $request->altitude,
                'altitude_accuracy' => $request->altitude_accuracy,
                'heading' => $request->heading,
                'speed' => $request->speed,
                'geo_location_type_id' => $request->geo_location_type_id,
                'width' => $request->width,
                'height' => $request->height,
                'browser_id' => $request->browser_id,
                'created_at' => \Carbon\Carbon::now()
            ]);

            if($usr->validated_user == 1 && empty($usr->first_access)){

                $result = $this->sendFirstAccessSms($usr->cpf_cnpj, 1, $usr->ip);
                
                if(empty($result['success'])){
                    return response()->json(array("error" => "Ocorreu um erro ao validar seu acesso, por favor tente novamente ou entre em contato com o suporte", "recaptcha_error" => false));
                }

                return response()->json([
                    'success' => [
                        'user_cpf'               => $usr->cpf_cnpj,
                        'user_name'              => $usr->name,
                        'last_login'             => \Carbon\Carbon::parse($usr->unique_access_at)->format('Y-m-d H:i:s'),
                        'last_ip_access'         => $usr->ip,
                        'login_attempt'          => $usr->login_attempt,
                        'validated_user'         => $result,
                        'recaptcha_response'     => $usr->recaptcha_response,
                        'access_unique_id'       => $authAttempt->unique_id,
                    ]
                ]);
            }

            return response()->json([
                'success' => [
                    'user_cpf'               => $usr->cpf_cnpj,
                    'user_name'              => $usr->name,
                    'last_login'             => \Carbon\Carbon::parse($usr->unique_access_at)->format('Y-m-d H:i:s'),
                    'last_ip_access'         => $usr->ip,
                    'login_attempt'          => $usr->login_attempt,
                    'recaptcha_response'     => $usr->recaptcha_response,
                    'access_unique_id'       => $authAttempt->unique_id
                ]
            ]);
        } 

        $payrollEmployee = PayrollEmployee::where('cpf_cnpj', $request['cpf_cnpj'])->first();

        if ($payrollEmployee && PayrollEmployeeDetail::where('employee_id', $payrollEmployee->id)->where('accnt_tp_id', 7)->exists()) {
            return response()->json(["success" => ['is_employee' => true]]);
        }
        
        return response()->json(array("error" => "CPF inválido, por favor tente novamente ou entre em contato com o suporte", "recaptcha_error" => false));
    }

    protected function Auth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recaptcha_response' => ['required', 'string'],
            'cpf_cnpj' => ['required', 'string', 'size:11'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => "Dados inválidos, por favor verifique os dados informados"));
        }

        $appIdAuthorizeds = ['D1n4r120220309@!Web'];
        if(in_array( $request->header('AppID'), haystack: $appIdAuthorizeds  )){
            if(User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$request->cpf_cnpj))->count() > 0 ){

                if( ! $usr = User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$request->cpf_cnpj))->first() ) {
                    return response()->json(array("error" => "CPF inválido, por favor tente novamente ou entre em contato com o suporte",  "recaptcha_error" => false));
                }

                if($usr->status == 3){
                    return response()->json(array("error" => "CPF inválido, por favor tente novamente ou entre em contato com o suporte",  "recaptcha_error" => false));
                }

                if( ($usr->recaptcha_response <> $request->recaptcha_response) and ($usr->cpf_cnpj != '11111111111')  ){
                    return response()->json(array("error" => "Código recaptacha informado é inválido, por favor tente novamente", "recaptcha_error" => true));
                }

                if($usr->login_attempt >= 3){
                    return response()->json(array("error" => "CPF bloqueado para acesso, foram realizadas mais de 3 tentativas sem sucesso de acesso do IP: ".$usr->ip.". Redefina sua senha clicando em 'Esqueceu a senha?'", "recaptcha_error" => false));
                }

                if(Auth::attempt(['email' => $usr->email, 'password' => base64_decode($request->password)])){

                    //Remove all access
                    DB::table("oauth_access_tokens")->where('user_id','=',$usr->id)->delete();

                    // tratar se usuario nao está vinculado com master
                    $user_master = UserMaster::where('user_id','=',$usr->id)->whereNull('deleted_at')->first();

                    $authAttemptUniqueId = null;
                    if($usr->cpf_cnpj != '11111111111'){
                        if(AuthAttempt::where('user_id','=',$usr->id)->where('unique_id','=',$request->access_unique_id)->count() > 0){
                            $authAttempt = AuthAttempt::where('user_id','=',$usr->id)->where('unique_id','=',$request->access_unique_id)->first();
                            
                            //Call threatmetrix
                            $threatMetrix = ThreatMetrixSessionQueryAPI::execute('login',  $authAttempt->session_identification);
                            
                            $authAttempt->success = 1;
                            $authAttempt->save();
                            $authAttemptUniqueId = $authAttempt->unique_id;
                        } else {
                            return response()->json(array("error" => "ID de acesso unico não localizada"));
                        }
                    }

                    $user_relationship = UserRelationship::where('user_master_id','=',$user_master->id)->whereNull('deleted_at')->orderBy('main', 'desc')->first();

                    $userMasterId          = null;
                    $masterId              = null;
                    $relationshipId        = null;
                    $accountId             = null;
                    $accountUniqueId       = null;
                    $comercialId           = null;
                    $registerId            = null;
                    $registerName          = null;
                    $registerCPFCNPJ       = null;
                    $relationshipHomeRoute = null;
                    $nextRequest           = null;

                    if($user_master != ''){
                        $userMasterId = $user_master->id;
                        $masterId     = $user_master->master_id;
                    }

                    if($user_relationship != ''){
                        $relationshipId        = $user_relationship->id;
                        $accountId             = $user_relationship->account_id;
                        if($accountId != ''){
                            $accountUniqueId       = Account::where('id','=',$accountId)->first()->unique_id;
                        }
                        //if email or phone not verified, send email and phone tokens
                        if($usr->email_verified_at == '' or $usr->phone_verified_at == ''){
                            $usercheck = $this->craterUserCheck($usr, $request->header('ip'));
                            $this->sendEmailToken($usercheck->id, $masterId, $usercheck->token_confirmation_email, $usr->email, $usr->name);
                            $this->sendPhoneToken($usercheck->id, $masterId, $usercheck->token_confirmation_phone, $usr->phone, $usr->name);
                            $relationshipHomeRoute = 'userCheck';
                            $nextRequest = Relationship::where('id','=',$user_relationship->relationship_id)->first()->home_route;
                        }else if($usr->accepted_term == '' or $usr->accepted_term == null){
                            $relationshipHomeRoute = 'term';
                            $nextRequest = Relationship::where('id','=',$user_relationship->relationship_id)->first()->home_route;
                        }else{

                            $relationshipHomeRoute = Relationship::where('id','=',$user_relationship->relationship_id)->first()->home_route;

                            //active this when done front to validate pj and pf
                            /*if ($user_relationship->relationship_id != 2) {
                                $balance             = new AccountMovement();
                                $balance->account_id = $accountId;
                                $balance->master_id  = $masterId;
                                $balance->start_date = \Carbon\Carbon::now();

                                $balance_data = $balance->getAccountBalance()->balance;

                                $register_id = Account::where('id','=',$accountId)->whereNull('deleted_at')->first()->register_master_id;

                                $register_master = RegisterMaster::where('register_id','=',$register_id)->where('master_id','=',$masterId)->first();

                                if($user_relationship->relationship_id == 3){
                                    if ($balance_data >= 30000 && $register_master->status_id != 51) {
                                        $relationshipHomeRoute = 'validatePJ';
                                    }
                                }else if($user_relationship->relationship_id == 4){
                                    if($balance_data >= 3000 && $register_master->status_id != 51){
                                        $relationshipHomeRoute = 'validatePF';
                                    }
                                }
                            } */
                        }
                    }

                    if($accountId != null){
                        $registerId = Account::where('id','=',$accountId)->whereNull('deleted_at')->first()->register_master_id;
                        $registerName = RegisterDetail::where('register_master_id','=',$registerId)->first()->name;
                    }

                    $user = Auth::user();
                    $user->unique_access     = md5($usr->id.$usr->recaptcha_response.time());
                    $user->unique_access_at  = \Carbon\Carbon::now();
                    $user->ip               = $request->header('ip');
                    $user->login_attempt    = 0;

                    $success = array(
                        "userName"           => $user->name,
                        "userAlias"          => $user->alias,
                        "userEmail"          => $user->email,
                        "userCpfCnpj"        => $user->cpf_cnpj,
                        "userStatus"         => $user->status,
                        "twofa_required"     => $user->twofa_required,
                        "twofa_status"       => $user->twofa_status,
                        "userId"             => $user->id,
                        "masterId"           => $masterId,
                        "userMasterId"       => $userMasterId,
                        "registerId"         => $registerId,
                        "registerName"       => $registerName,
                        "accountId"          => $accountId,
                        'accountUniqueId'    => $accountUniqueId,
                        'comercialId'        => $comercialId,
                        "userRelationshipId" => $relationshipId,
                        "home_route"         => $relationshipHomeRoute,
                        "next_request"       => $nextRequest,
                        "acceptedTerm"       => $user->accepted_term,
                        "ip"                 => $request->header('ip'),
                        "accessUniqueId"     => $authAttemptUniqueId,
                        "token"              => $user->createToken('DigitalAccountToken')->accessToken
                    );
                    $user->api_token = $success['token'];

                    if($user->first_access == null){
                        $user->first_access = \Carbon\Carbon::now();
                    }

                    $user->save();

                    $user_relationship->active = 1;
                    $user_relationship->save();

                    return response()->json(['success' => $success], 200);
                } else{

                    if($usr->login_attempt == '' or $usr->login_attempt == null){
                        $usr->login_attempt = 1;
                    } else {
                        $usr->login_attempt = $usr->login_attempt + 1;
                    }
                    if($usr->login_attempt > 3){
                        $usr->status = 2;
                    }
                    $usr->ip            =  $request->header('ip');
                    $usr->save();
                    if($usr->login_attempt < 3){
                        return response()->json(['error'=> 'CPF ou senha inválidos. Restam '.(3 - $usr->login_attempt)." tentativa(s)"], 200); //Update to 401
                    } else {
                        return response()->json(array("error" => "CPF bloqueado para acesso, foram realizadas mais de 3 tentativas sem sucesso de acesso do IP: ".$usr->ip.". Redefina sua senha clicando em 'Esqueceu a senha?'", "recaptcha_error" => false));
                    }
                }
            } else{
                return response()->json(['error'=>'CPF inválido, por favor tente novamente ou entre em contato com o suporte', "recaptcha_error" => false], 200); //Update to 401
            }
        } else {
            return response()->json(['error'=>'App Unauthorised', "recaptcha_error" => false], 200); //Update to 401
        }
    }

    protected function DeAuth(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $user->api_token = null;
            $user->token()->revoke();
            $user->token()->delete();
            if($user->save()){
                return response()->json(['sucess'=>'Logout Success'], 200);
            } else {
                return response()->json(['error'=>'Unauthorised'], 401);
            }
        } else {
            return response()->json(['error'=>'Uncheck and Unauthorised'], 401);
        }
    }

    protected function Details(Request $request)
    {
        $user = Auth::user();
        return response()->json(['success' => $user], 200);
    }

    public function sendEmailToken($id, $masterId, $token_confirmation_email, $email, $name)
    {
        $message = "Olá $name, <br>O token ".substr($token_confirmation_email,0,4)."-".substr($token_confirmation_email,4,4)." foi gerado para a verificação de segurança do acesso da conta digital.";

        $apiSendGrind = new ApiSendgrid();
        $apiSendGrind->to_email    = $email;
        $apiSendGrind->to_name     = $name;
        $apiSendGrind->to_cc_email = 'ragazzi@dinari.com.br';
        $apiSendGrind->to_cc_name  = 'Ragazzi';
        $apiSendGrind->subject     = 'Token de Verificação';
        $apiSendGrind->content     = $message;

        if($apiSendGrind->sendSimpleEmail()){
           return true;
        } else {
           return false;
        }
    }

    public function sendPhoneToken($id, $masterId, $token_confirmation_phone, $phone, $name)
    {
        $message = "Ola ".(explode(" ",$name))[0].", o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para a verificação de segurança do acesso da conta digital.";

        $sendSMS = SendSms::create([
            'external_id' => ("8".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$id),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 8,
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

    public function sendWhatsAppToken($id, $masterId, $token_confirmation_phone, $phone, $name)
    {
        $message = "Ola ".(explode(" ",$name))[0].", o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para a verificação de segurança do acesso da conta digital.";

        $sendSMS = SendSms::create([
            'external_id' => ("8".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$id),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 8,
            'origin_id'   => $id,
            'created_at'  => \Carbon\Carbon::now()
        ]);
        
        $apiZenviaWhats            = new ApiZenviaWhatsapp();
        $apiZenviaWhats->to_number = $sendSMS->to;
        $apiZenviaWhats->token     = "*".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)."*";

        if(isset( $apiZenviaWhats->sendToken()->success )){
            return true;
        }

        return false;
    }

    /*protected function AppAuth(Request $request)
    {
        $appIdAuthorizeds = ['D1n4r120220309@!Web'];
        if(in_array( $request->header('AppID'), $appIdAuthorizeds  )){
            if(User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$request->cpf_cnpj))->count() > 0 ){
                $usr = User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$request->cpf_cnpj))->first();

                if( ($usr->recaptcha_response <> $request->recaptcha_response) and ($usr->cpf_cnpj != '11111111111')  ){
                    return response()->json(array("error" => "Código recaptacha informado é inválido, por favor tente novamente", "recaptcha_error" => true),401);
                }

                if($usr->login_attempt >= 3){
                    return response()->json(array("error" => "CPF bloqueado para acesso, foram realizadas mais de 3 tentativas sem sucesso de acesso do IP: ".$usr->ip.". Redefina sua senha clicando em 'Esqueceu a senha?'", "recaptcha_error" => false),401);
                }

                if(Auth::attempt(['email' => $usr->email, 'password' => $request->password])){
                    // tratar se usuario nao está vinculado com master
                    $user_master = UserMaster::where('user_id','=',$usr->id)->whereNull('deleted_at')->first();

                    $user_relationship = UserRelationship::where('user_master_id','=',$user_master->id)->whereNotIn('relationship_id',[1,2,5])->whereNull('deleted_at')->first();

                    $userMasterId          = null;
                    $masterId              = null;
                    $relationshipId        = null;
                    $accountId             = null;
                    $accountUniqueId       = null;
                    $comercialId           = null;
                    $registerId            = null;
                    $registerName          = null;
                    $relationshipHomeRoute = null;

                    if($user_master != ''){
                        $userMasterId = $user_master->id;
                        $masterId     = $user_master->master_id;
                    }

                    if($user_relationship != ''){
                        $relationshipId        = $user_relationship->id;
                        $relationshipUniqueId  = $user_relationship->unique_id;
                        $accountId             = $user_relationship->account_id;
                        if($accountId != ''){
                            $accountUniqueId       = Account::where('id','=',$accountId)->first()->unique_id;
                        }
                        //if email or phone not verified, send email and phone tokens
                        if($usr->email_verified_at == '' or $usr->phone_verified_at == ''){
                            $token = new Facilites();
                            UserCheck::where('user_id','=',$usr->id)->update(['deleted_at' => \Carbon\Carbon::now()]);
                            $usercheck = UserCheck::create([
                                'user_id'                  => $usr->id,
                                'token_confirmation_email' => $token->createApprovalToken(),
                                'token_email_confirmed'    => 0,
                                'token_confirmation_phone' => $token->createApprovalToken(),
                                'token_phone_confirmed'    => 0,
                                'term_accepted'            => 0,
                                'finished'                 => 0,
                                'ip'                       => $request->header('ip'),
                                'created_at'               => \Carbon\Carbon::now()
                            ]);
                            $this->sendEmailToken($usercheck->id, $masterId, $usercheck->token_confirmation_email, $usr->email, $usr->name);
                            $this->sendPhoneToken($usercheck->id, $masterId, $usercheck->token_confirmation_phone, $usr->phone, $usr->name);
                            $relationshipHomeRoute = 'userCheck';
                        } else {
                            $relationshipHomeRoute = Relationship::where('id','=',$user_relationship->relationship_id)->first()->home_route;
                        }
                    }

                    if($accountId != null){
                        $registerId = Account::where('id','=',$accountId)->whereNull('deleted_at')->first()->register_master_id;
                        $registerName = RegisterDetail::where('register_master_id','=',$registerId)->first()->name;
                    }

                    $user = Auth::user();
                    $user->unique_access     = md5($usr->id.$usr->recaptcha_response.time());
                    $user->unique_access_at  = \Carbon\Carbon::now();
                    $user->ip               = $request->header('ip');
                    $user->login_attempt    = 0;
                    $success = array(
                        "userName"                   => $user->name,
                        "userAlias"                  => $user->alias,
                        "userEmail"                  => $user->email,
                        "userCpfCnpj"                => $user->cpf_cnpj,
                        "userStatus"                 => $user->status,
                        "userId"                     => $user->id,
                        "userUniqueId"               => $user->unique_id,
                        "masterId"                   => $masterId,
                        "userMasterId"               => $userMasterId,
                        "registerId"                 => $registerId,
                        "registerName"               => $registerName,
                        "accountId"                  => $accountId,
                        'accountUniqueId'            => $accountUniqueId,
                        'comercialId'                => $comercialId,
                        "userRelationshipId"         => $relationshipId,
                        "userRelationshipUniqueId"   => $relationshipUniqueId,
                        "home_route"                 => $relationshipHomeRoute,
                        "ip"                         => $request->header('ip'),
                        "token"                      => $user->createToken('DigitalAccountToken')->accessToken
                    );
                    $user->api_token = $success['token'];
                    $user->save();
                    return response()->json(['success' => $success], 200);
                }
                else{

                    if($usr->login_attempt == '' or $usr->login_attempt == null){
                        $usr->login_attempt = 1;
                    } else {
                        $usr->login_attempt = $usr->login_attempt + 1;
                    }
                    if($usr->login_attempt > 3){
                        $usr->status = 2;
                    }
                    $usr->ip            =  $request->header('ip');
                    $usr->save();
                    if($usr->login_attempt < 3){
                        return response()->json(['error'=> 'CPF ou senha inválidos. Restam '.(3 - $usr->login_attempt)." tentativa(s)"], 401);
                    } else {
                        return response()->json(array("error" => "CPF bloqueado para acesso, foram realizadas mais de 3 tentativas sem sucesso de acesso do IP: ".$usr->ip.". Redefina sua senha clicando em 'Esqueceu a senha?'", "recaptcha_error" => false),401);
                    }
                }
            } else{
                return response()->json(['error'=>'CPF inválido, por favor tente novamente ou entre em contato com o suporte', "recaptcha_error" => false], 401);
            }
        } else {
            return response()->json(['error'=>'App Unauthorised', "recaptcha_error" => false], 401);
        }
    } */

    public function craterUserCheck($usr, $ip)
    {
        $token = new Facilites();
        UserCheck::where('user_id','=',$usr->id)->update(['deleted_at' => \Carbon\Carbon::now()]);
        return (UserCheck::create([
            'user_id'                  => $usr->id,
            'token_confirmation_email' => $token->createApprovalToken(),
            'token_email_confirmed'    => 0,
            'token_confirmation_phone' => $token->createApprovalToken(),
            'token_phone_confirmed'    => 0,
            'term_accepted'            => 0,
            'finished'                 => 0,
            'ip'                       => $ip,
            'created_at'               => \Carbon\Carbon::now()
        ]));
    }

    public function resendPhoneToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer']
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if(!$usr = User::where('id','=',$request->id)->first()){
            return response()->json(array("error" => "Usuário não localizado, por favor verifique os dados informados"));
        }

        if($usr->status == 3){
            return response()->json(array("error" => "CPF inválido, por favor tente novamente ou entre em contato com o suporte"));
        }

        if($usr->login_attempt >= 3){
            return response()->json(array("error" => "CPF bloqueado para acesso, foram realizadas mais de 3 tentativas sem sucesso de acesso do IP: ".$usr->ip.". Redefina sua senha clicando em 'Esqueceu a senha?'"));
        }

        if(!$lastUserCheck = UserCheck::where('user_id','=',$usr->id)->latest('created_at')->first()){
            return response()->json(array("error" => "Ainda não foram enviados tokens de validações para o CPF informado, por favor realize o login novamente, ou entre em contato com o administrador."));
        }

        if($lastUserCheck->token_phone_confirmed == 1){
            return response()->json(array("error" => "O celular já foi confirmado, em caso de duvidas entre em contato com o administrador."));
        }

        //Validar os 20 minutos do token
        if(!(\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->created_at)) > 1200){
            return response()->json(array("error" => "O acesso atual para reenvio do token expirou, por favor realize o acesso novamente.", "data" => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->updated_at))));
        }

        if($lastUserCheck->last_phone_token_send_at <> null){
            if((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->last_phone_token_send_at)) <= 60){
                return response()->json(array("error" => "Por favor aguarde 1 minuto para reenviar o token por whatsapp."));
            }
        } else {
            if((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->created_at)) <= 60){
                return response()->json(array("error" => "Por favor aguarde 1 minuto para reenviar o token por whatsapp."));
            }
        }


        $user_master = UserMaster::where('user_id','=',$usr->id)->whereNull('deleted_at')->first();

        if(!$this->sendWhatsAppToken($lastUserCheck->id, $user_master->id, $lastUserCheck->token_confirmation_phone, $usr->phone, $usr->name)){
            return response()->json(array("error" => "Poxa, não foi possível reenviar o token para o celular ".Facilites::mask_phone($usr->phone).", por favor tente novamente mais tarde, ou entre em contato com o administrador."));
        }

        $lastUserCheck->last_phone_token_send_at = \Carbon\Carbon::now();
        $lastUserCheck->save();

        return response()->json(array("success" => "Token reenviado para o celular: ".Facilites::mask_phone($usr->phone)." com sucesso"));
    }

    public function resendMailToken(Request $request)
    {        
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer']
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(!$usr = User::where('id','=',$request->id)->first()){
            return response()->json(array("error" => "Usuário não localizado, por favor verifique os dados informados"));
        }

        if($usr->status == 3){
            return response()->json(array("error" => "CPF inválido, por favor tente novamente ou entre em contato com o suporte"));
        }

        if($usr->login_attempt >= 3){
            return response()->json(array("error" => "CPF bloqueado para acesso, foram realizadas mais de 3 tentativas sem sucesso de acesso do IP: ".$usr->ip.". Redefina sua senha clicando em 'Esqueceu a senha?'"));
        }

        if(!$lastUserCheck = UserCheck::where('user_id','=',$usr->id)->latest('created_at')->first()){
            return response()->json(array("error" => "Ainda não foram enviados tokens de validações para o CPF informado, por favor realize o login novamente, ou entre em contato com o administrador."));
        }

        if($lastUserCheck->token_email_confirmed == 1){
            return response()->json(array("error" => "O e-mail já foi confirmado, em caso de duvidas entre em contato com o administrador."));
        }


        //Validar os 20 minutos do token
        if(!(\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->updated_at)) > 1200){
            return response()->json(array("error" => "O acesso atual para reenvio do token expirou, por favor realize o acesso novamente."));
        }

        if($lastUserCheck->last_email_token_send_at <> null){
            if((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->last_email_token_send_at)) <= 60){
                return response()->json(array("error" => "Por favor aguarde 1 minuto para reenviar o token por e-mail."));
            }
        } else {
            if((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($lastUserCheck->created_at)) <= 60){
                return response()->json(array("error" => "Por favor aguarde 1 minuto para reenviar o token por e-mail."));
            }
        }
        
        $user_master = UserMaster::where('user_id','=',$usr->id)->whereNull('deleted_at')->first();

        if(!$this->sendEmailToken($lastUserCheck->id, $user_master->id, $lastUserCheck->token_confirmation_email, $usr->email, $usr->name)){
            return response()->json(array("error" => "Poxa, não foi possível reenviar o token para o e-mail ".$usr->email.", por favor tente novamente mais tarde, ou entre em contato com o administrador."));
        }

        $lastUserCheck->last_email_token_send_at = \Carbon\Carbon::now();
        $lastUserCheck->save();

        return response()->json(array("success" => "Token reenviado para o e-mail: ".$usr->email." com sucesso"));
    }

    protected function setFirstAccessPassword(Request $request): mixed
    {

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'password' =>  ['required', 'string'],
            'password_confirm' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->count() > 0){
            
            $redefinePassword = RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->first();

            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefinePassword->created_at )->addMinutes(15))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Requisição de alteração de senha expirada, por favor recomece o processo de redefinição de senha"));
            }

            $usr = User::where('id','=',$redefinePassword->user_id)->first();
            if($usr->validated_user != 1){
                return response()->json(array("error" => "Requisição de alteração de senha inválida."));
            }

            if($redefinePassword->invalid == 1) {

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->passwordResetFailure($redefinePassword,$request->header('ip') );

                return response()->json(array("error" => "Requisição de alteração de senha inválida, os tokens de confirmações foram informados incorretamente por mais de 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }
        
            if($redefinePassword->token_email_confirmed == 0) {

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->passwordResetFailure($redefinePassword,$request->header('ip') );

                return response()->json(array("error" => "É necessário confirmar os tokens enviados para o CELULAR antes de redefinir a senha, por favor reinicie o processo de redefinição de senha."));
            }

            if($redefinePassword->token_phone_confirmed == 0){
                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->passwordResetFailure($redefinePassword,$request->header('ip') );

                return response()->json(array("error" => "É necessário confirmar os tokens enviados para o CELULAR antes de redefinir a senha, por favor reinicie o processo de redefinição de senha."));
            }

            if($request->password != $request->password_confirm){
                return response()->json(array("error" => "Senhas não coincidem"));
            }

            if( (strlen(base64_decode($request->password)) != 6)  ){
                return response()->json(array("error" => "A nova senha deve conter 6 caracteres"));
            }

            $passAllowedNumbers          = [0,1,2,3,4,5,6,7,8,9,'0','1','2','3','4','5','6','7','8','9'];
            $passAllowedSpecialCharacter = ['@','#','!','$','%','&','*'];
            $passArray                   = str_split(base64_decode($request->password));
            $passValidate                = true;
            $hasSpecialCharacter         = false;
            $hasNumber                   = false;
            $lastNumber                  = null;
            $lastSpecialCharacter        = null;

            foreach($passArray as $pass){
                if((in_array($pass, $passAllowedNumbers, true)) and $passValidate){
                    $hasNumber            = true;
                    $lastSpecialCharacter = null;
                    if($lastNumber == null){
                        $lastNumber   = (int) $pass;
                        $passValidate = true;
                    } 
                    $lastNumber = (int) $pass;
                } else if((in_array($pass, $passAllowedSpecialCharacter, true)) and $passValidate){
                    $hasSpecialCharacter = true;
                    $lastNumber          = null;
                    if($lastSpecialCharacter == null){
                        $lastSpecialCharacter = $pass;
                        $passValidate         = true;
                    } else {
                        if($lastSpecialCharacter == $pass){
                            return response()->json(array("error" => "A senha não pode conter caracteres iguais na sequência"));
                        }
                    }
                } else {
                    $passValidate = false;
                }
            }

            if(!$passValidate){
                return response()->json(array("error" => "A senha deve conter números de 0 a 9 e caracteres especais (@ # ! $ % & *) "));
            }

            if(!$hasNumber){
                return response()->json(array("error" => "A senha deve conter pelo menos um número"));
            }

            if(!$hasSpecialCharacter){
                return response()->json(array("error" => "A senha deve conter pelo menos um caracter especial"));
            }

            if(User::where('id','=',$redefinePassword->user_id)->count() > 0 ){
                $usr = User::where('id','=',$redefinePassword->user_id)->first();

                if($usr->status == 3){
                    return response()->json(array("error" => "Poxa, não foi possível redefinir sua senha, em caso de dúvidas, por favor entre em contato com nossa equipe de suporte"));
                }

                $usr->password      = Hash::make(base64_decode($request->password));
                $usr->status        = 1;
                $usr->login_attempt = 0;
                if($usr->save()){
                    $redefinePassword->finished = 1;
                    $redefinePassword->save();

                    return response()->json(array("success" => "Senha definida com sucesso."));
                } else {
                    return response()->json(array("error" => "Não foi possível redifinir a senha, por favor tente novamente"));
                }
            } else {
                return response()->json(array("error" => "Usuário não encontrado, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de senha não encontrada, por favor tente novamente"));
        }
    }

    protected function setFirstAccessUserTerm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'access_unique_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        $user = new UserService();
        $result = $user->setFirstAccessUserTerm($request->id, $request->unique_id, $request->header('ip'), $request->access_unique_id);
        
        return response()->json($result);
    }

    protected function sendFirstAccessSms($cpf_cnpj, $master_id, $ip)
    {
        $usr = User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$cpf_cnpj))->first();
        
        if($usr->status == 3){
            return array("error" => "Poxa, não foi possível iniciar a redefinição da sua senha, em caso de dúvidas, por favor entre em contato com nossa equipe de suporte",  "recaptcha_error" => false);
        }
        
        $token = new Facilites();
        if($redefinePassword = RedefinePassword::create([
            'unique_id'                 => Str::orderedUuid(),
            'user_id'                   => $usr->id,
            'master_id'                 => $master_id,
            'token_confirmation_email'  => $token->createApprovalToken(),
            'token_email_confirmed'     => 0,
            'token_confirmation_phone'  => $token->createApprovalToken(),
            'token_phone_confirmed'     => 0,
            'finished'                  => 0,
            'ip'                        => $ip,
            'phone_token_attempt'       => 0,
            'email_token_attempt'       => 0,
            'invalid'                   => 0,
            'created_at'                => \Carbon\Carbon::now()
        ]))
        {
            if(!$this->sendSmsValidateUser($redefinePassword->id, $master_id, $redefinePassword->token_confirmation_phone, $usr->phone, $usr->name)){
                return ["error" => "Não foi possível enviar o token para redefinição de senha, por favor tente novamente"];
            }
            return ["success" => "Redefinição de senha enviada com sucesso", "id" => $redefinePassword->id, "unique_id" => $redefinePassword->unique_id];

        } else {
            return ["error" => "Não foi possível criar a redefinição de senha, por favor tente mais tarde"];
        }
    }

    public function sendSmsValidateUser($id, $masterId, $token_confirmation_phone, $phone, $name): bool
    {
        $message = "Olá $name, o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para a verificação de sms da sua conta.";

        $sendSMS = SendSms::create([
            'external_id' => ("4".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$id),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 4,
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
}
