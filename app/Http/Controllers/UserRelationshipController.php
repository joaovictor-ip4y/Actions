<?php

namespace App\Http\Controllers;

use App\Models\UserRelationship;
use App\Models\User;
use App\Models\UserMaster;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Models\AuthorizationToken;
use App\Models\Relationship;
use App\Models\SendSms;
use App\Models\RegisterMaster;
use App\Models\Register;
use App\Models\RegisterDetail;
use App\Models\RegisterPhone;
use App\Models\RegisterEmail;
use App\Models\SystemFunctionMaster;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\DigitalSignature\DigitalSignatureService;
use App\Http\Controllers\Controller;
use App\Models\AccountMovement;
use App\Models\Permission;
use App\Models\UsrRltnshpPrmssn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PDF;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Classes\Failure\MovimentationFailureClass;
use Illuminate\Support\Str;

class UserRelationshipController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [53, 390];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $userRelationship                 = new UserRelationship();
        $userRelationship->user_master_id = $request->user_master_id;
        $userRelationship->onlyActive     = $request->onlyActive;
        $userRelationship->account_id     = $checkAccount->account_id;
        $userRelationship->master_id      = $checkAccount->master_id;
        return response()->json($userRelationship->getUserRelationship());
    }

    protected function getForActive(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (! Auth::check()) {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login para continuar" )) ;
        }

        $user = Auth::user();

        $userRelationship              = new UserRelationship();
        $userRelationship->user_id     = $user->id;
        $userRelationship->onlyActive  = 1;
        return response()->json($userRelationship->getRelationshipForActive());
    }

    protected function getUserRelationshipProfile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!Auth::check()) {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login para continuar"));
        }

        $user = Auth::user();

        $userRelationship                 = new UserRelationship();
        $userRelationship->user_id        = $user->id;
        $userRelationship->onlyActive     = 1;
        return response()->json($userRelationship->getProfileUserRelationship());
    }

    protected function setActive(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
            'relationship_id' => ['required', 'integer'],
            'relationship_uuid' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }
        
        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();
            if( Hash::check(base64_decode($request->password), $usr->password) ){
                $user_master = UserMaster::where('user_id','=',$user->id)->whereNull('deleted_at')->first();
                
                if( ! $user_relationship = UserRelationship::where('id','=',$request->relationship_id)->where('uuid','=',$request->relationship_uuid)->where('user_master_id','=',$user_master->id)->whereNull('deleted_at')->first() ) {

                    $sendFailureAlert = new MovimentationFailureClass();
                    $sendFailureAlert->title = 'TENTATIVA DE ACESSO EM VÍNCULO NÃO AUTORIZADO';
                    $sendFailureAlert->errorMessage = 'Atenção, o usuário: '.$usr->name.', e-mail: '.$usr->email.', celular: '.$usr->phone.'<br/>
                    Tentou realizar acesso em um vínculo que não foi atribuído a ele. O usuário foi desativado.
                    Dados da requisição:<br/><br/>
                    user_id: '.$usr->id.'<br/>
                    user_master_id: '.$user_master->id.'<br/>
                    relationship_id: '.$request->relationship_id.'<br/>
                    relationship_uuid: '.$request->relationship_uuid.'<br/>
                    IP de origem: '.$request->header('ip').'<br/>';

                    $sendFailureAlert->sendFailures();

                    $usr->status = 3;

                    if ($usr->save()) {
                        $user_master->status_id = 3;
                        if ($user_master->save()) {
                            DB::table("oauth_access_tokens")->where('user_id','=',$usr->id)->delete();
                        }
                    }

                    return response()->json(array("error" => "Não foi possível logar nessa conta, por favor entre em contato com o suporte."));
                }

                sleep(5);

                UserRelationship::removeAllActiveRelationship($user_master->id);
                $user_relationship->active = 1;
                $user_relationship->active_at = \Carbon\Carbon::now();
                $user_relationship->save();
                
                $userMasterId          = null;
                $masterId              = null;
                $relationshipId        = null;
                $relationshipUuid      = null;
                $registerName          = null;
                $accountId             = null;
                $accountUniqueId       = null;
                $comercialId           = null;
                $registerId            = null;
                $relationshipHomeRoute = null;
                if ($user_master != '') {
                    $userMasterId = $user_master->id;
                    $masterId     = $user_master->master_id;
                }
                if ($user_relationship != '') {
                    $relationshipId        = $user_relationship->id;
                    $relationshipUuid      = $user_relationship->uuid;
                    $accountId             = $user_relationship->account_id;
                    if ($accountId != '') {
                        $accountUniqueId = Account::where('id', '=', $accountId)->first()->unique_id;
                    }

                    $relationshipHomeRoute = Relationship::where('id', '=', $user_relationship->relationship_id)->first()->home_route;

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
                    }
                    */
                }
                if ($accountId != null) {
                    $registerId = Account::where('id', '=', $accountId)->whereNull('deleted_at')->first()->register_master_id;
                    $registerName = RegisterDetail::where('register_master_id','=',$registerId)->first()->name;
                }

                $success = array(
                    "userName"             => $user->name,
                    "userAlias"            => $user->alias,
                    "userEmail"            => $user->email,
                    "userCpfCnpj"          => $user->cpf_cnpj,
                    "userStatus"           => $user->status,
                    "userId"               => $user->id,
                    "masterId"             => $masterId,
                    "userMasterId"         => $userMasterId,
                    "registerId"           => $registerId,
                    "registerName"         => $registerName,
                    "accountId"            => $accountId,
                    'accountUniqueId'      => $accountUniqueId,
                    'comercialId'          => $comercialId,
                    "userRelationshipId"   => $relationshipId,
                    "userRelationshipUuid" => $relationshipUuid,
                    "home_route"           => $relationshipHomeRoute,
                    "ip"                   => $request->header('ip'),
                    "token"                => $user->createToken('DigitalAccountToken')->accessToken
                );
                $user->api_token = $success['token'];
                $user->save();
                return response()->json(['success' => $success], 200);
            }else{

                return response()->json(['error'=>'Senha inválida']);
            }
        } else {
            return response()->json(['error' => 'Uncheck and Unauthorised'], 401);
        }
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [133];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        //checar se usuario que está cadastrando pertence ao master ou é admin
        //checar se usuario a cadastrar pertence ao master
        //checar se conta pertence ao master
        if (UserRelationship::where('user_master_id', '=', $request->user_master_id)->where('relationship_id', '=', $request->relationship_id)->where('account_id', '=', $request->account_id)->count() == 0) {
            if ($UserRelationship = UserRelationship::create([
                'user_master_id'  => $request->user_master_id,
                'account_id'      => $request->account_id,
                'relationship_id' => $request->relationship_id,
                'unique_id'       => Str::orderedUuid(),
                'uuid'            => Str::orderedUuid(),
                'created_at'      => \Carbon\Carbon::now(),
                'deleted_at'      => \Carbon\Carbon::now()
            ])) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $usr  = User::where('id', '=', $user->id)->first();
                    if (Hash::check(base64_decode($request->password), $usr->password)) {
                        $token = new Facilites();
                        $authorizationToken = AuthorizationToken::create([
                            'token_phone'       => $token->createApprovalToken(),
                            'token_email'       => $token->createApprovalToken(),
                            'type_id'           => 3,
                            'origin_id'         => $UserRelationship->id,
                            'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
                            'token_expired'     => 0,
                            'created_at'        => \Carbon\Carbon::now()
                        ]);
                        $UserRelationship->approval_token            = $authorizationToken->token_phone;
                        $UserRelationship->approval_token_expiration = $authorizationToken->token_expiration;
                        if ($UserRelationship->save()) {
                            $sendSMS = SendSms::create([
                                'external_id' => ("10" . (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('YmdHisu') . $UserRelationship->id),
                                'to'          => "55" . $usr->phone,
                                'message'     => "Token " . substr($authorizationToken->token_phone, 0, 4) . "-" . substr($authorizationToken->token_phone, 4, 4) . ". Gerado para aprovar vínculo de usuário",
                                'type_id'     => 10,
                                'origin_id'   => $UserRelationship->id,
                                'created_at'  => \Carbon\Carbon::now()
                            ]);
                            $apiConfig                     = new ApiConfig();
                            $apiConfig->master_id          = $checkAccount->master_id;
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
                            if ((SystemFunctionMaster::where('system_function_id', '=', 10)->where('master_id', '=', $request->header('masterId'))->first())->available == 1) {
                                $apiZenviaWhats            = new ApiZenviaWhatsapp();
                                $apiZenviaWhats->to_number = $sendSMS->to;
                                $apiZenviaWhats->token     = "*" . substr($authorizationToken->token_phone, 0, 4) . "-" . substr($authorizationToken->token_phone, 4, 4) . "*";
                                if (isset($apiZenviaWhats->sendToken()->success)) {
                                    return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário repita o procedimento para gerar outro token", "id" => $UserRelationship->id, "unique_id" => $UserRelationship->unique_id));
                                }
                            }

                            if (isset($apiZenviaSMS->sendShortSMS()->success)) {
                                return response()->json(array("success" => "Token enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo, se necessário repita o procedimento para gerar outro token", "id" => $UserRelationship->id, "unique_id" => $UserRelationship->unique_id));
                            } else {
                                return response()->json(array("error" => "Não foi possível enviar o token de aprovação, por favor tente novamente"));
                            }
                        } else {
                            return response()->json(array("error" => "Não foi possível gerar o token de aprovação, por favor tente novamente"));
                        }
                    } else {
                        return response()->json(array("error" => "Senha invalida"));
                    }
                } else {
                    return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao realizar o vínculo"));
            }
        } else {
            $UserRelationship = UserRelationship::where('user_master_id', '=', $request->user_master_id)->where('relationship_id', '=', $request->relationship_id)->where('account_id', '=', $request->account_id)->first();
            $UserRelationship->unique_id = md5(rand(1, 99999) . date('Ymd') . time());
            $UserRelationship->deleted_at = \Carbon\Carbon::now();
            if ($UserRelationship->save()) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $usr  = User::where('id', '=', $user->id)->first();
                    if (Hash::check(base64_decode($request->password), $usr->password)) {
                        $token = new Facilites();
                        $authorizationToken = AuthorizationToken::create([
                            'token_phone'       => $token->createApprovalToken(),
                            'token_email'       => $token->createApprovalToken(),
                            'type_id'           => 3,
                            'origin_id'         => $UserRelationship->id,
                            'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
                            'token_expired'     => 0,
                            'created_at'        => \Carbon\Carbon::now()
                        ]);
                        $UserRelationship->approval_token            = $authorizationToken->token_phone;
                        $UserRelationship->approval_token_expiration = $authorizationToken->token_expiration;
                        if ($UserRelationship->save()) {
                            $sendSMS = SendSms::create([
                                'external_id' => ("10" . (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('YmdHisu') . $UserRelationship->id),
                                'to'          => "55" . $usr->phone,
                                'message'     => "Token " . substr($authorizationToken->token_phone, 0, 4) . "-" . substr($authorizationToken->token_phone, 4, 4) . ". Gerado para aprovar vínculo de usuário",
                                'type_id'     => 10,
                                'origin_id'   => $UserRelationship->id,
                                'created_at'  => \Carbon\Carbon::now()
                            ]);
                            $apiConfig                     = new ApiConfig();
                            $apiConfig->master_id          = $checkAccount->master_id;
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
                            if ((SystemFunctionMaster::where('system_function_id', '=', 10)->where('master_id', '=', $request->header('masterId'))->first())->available == 1) {
                                $apiZenviaWhats            = new ApiZenviaWhatsapp();
                                $apiZenviaWhats->to_number = $sendSMS->to;
                                $apiZenviaWhats->token     = "*" . substr($authorizationToken->token_phone, 0, 4) . "-" . substr($authorizationToken->token_phone, 4, 4) . "*";
                                if (isset($apiZenviaWhats->sendToken()->success)) {
                                    return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário repita o procedimento para gerar outro token", "id" => $UserRelationship->id, "unique_id" => $UserRelationship->unique_id));
                                }
                            }

                            if (isset($apiZenviaSMS->sendShortSMS()->success)) {
                                return response()->json(array("success" => "Token enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo, se necessário repita o procedimento para gerar outro token", "id" => $UserRelationship->id, "unique_id" => $UserRelationship->unique_id));
                            } else {
                                return response()->json(array("error" => "Não foi possível enviar o token de aprovação, por favor tente novamente"));
                            }
                        } else {
                            return response()->json(array("error" => "Não foi possível gerar o token de aprovação, por favor tente novamente"));
                        }
                    } else {
                        return response()->json(array("error" => "Senha invalida"));
                    }
                } else {
                    return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
                }
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao reativar o vínculo"));
            }
        }
    }

    protected function checkToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [133];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if (UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->where('approval_token', '=', $request->token)->count() == 0) {
            return response()->json(array("error" => "Token inválido"));
        } else {
            $UserRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->where('approval_token', '=', $request->token)->first();
            if (\Carbon\Carbon::parse($UserRelationship->approval_token_expiration)->format('Y-m-d H:i:s') <= \Carbon\Carbon::now()->format('Y-m-d H:i:s')) {
                return response()->json(array("error" => "Token expirado, por favor repita o procedimento"));
            } else {
                $UserRelationship->deleted_at  = null;
                $UserRelationship->save();
                return response()->json(array("success" => "Vínculo realizado com sucesso"));
            }
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [135, 391];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        //checar se usuario que está excluindo pertence ao master ou é admin
        //checar se usuario a excluir pertence ao master
        //checar se conta pertence ao master
        $UserRelationship = UserRelationship::where('user_master_id', '=', $request->user_master_id)
            ->where('id', '=', $request->user_relationship_id)
            ->when($checkAccount->account_id, function ($query, $accountId) {
                return $query->where('account_id', '=', $accountId);
            })
            ->first();


        $UserRelationship->deleted_at = \Carbon\Carbon::now();
        if ($UserRelationship->save()) {
            return response()->json(array("success" => "Vínculo excluído com sucesso", "relationship_id", $UserRelationship->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o vínculo"));
        }
    }

    protected function pdfAuthorizationToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [136];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $UserRelationship = new UserRelationship();
        $UserRelationship->id               = $request->id;

        $relationshipData = $UserRelationship->getPdfTokenAutorization();

        $data = (object) [
            'id'                     =>  $relationshipData->id,
            'name'                   =>  $relationshipData->name,
            'cpf_cnpj'               =>  Facilites::mask_cpf_cnpj($relationshipData->cpf_cnpj),
            'register_description'   =>  $relationshipData->register_description,
            'account_number'         =>  Facilites::mask_account($relationshipData->account_number),
            'phone'                  =>  Facilites::mask_phone($relationshipData->phone),
            'email'                  =>  $relationshipData->email,
            'date_open_account'      =>  \Carbon\Carbon::parse($relationshipData->date_open_account)->format('d/m/Y'),
            'accnt_typs_description' =>  $relationshipData->accnt_typs_description,
            'rgstr_dtls_name'        =>  $relationshipData->rgstr_dtls_name,
            'rgstrs_cpf_cnpj'        =>  Facilites::mask_cpf_cnpj($relationshipData->rgstrs_cpf_cnpj),

        ];

        $file_name = "Autorizacao_de_Token_" . preg_replace('/[ -]+/', '_', $relationshipData->rgstr_dtls_name) . "" . preg_replace('/[ -]+/', '_', $relationshipData->name) . ".pdf";
        $pdf       = PDF::loadView('reports/account_responsibility_token', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf)));
    }

    public function pdfAuthorizationTokenToSign()
    {
        $signerUsersRelationships = UserRelationship::whereIn('relationship_id', [3, 4])->whereNull('deleted_at')->whereIn('id', [134, 147])->get();
        foreach ($signerUsersRelationships as $signerUsersRelationship) {
            $UserRelationship = new UserRelationship();
            $UserRelationship->id = $signerUsersRelationship->id;

            $relationshipData = $UserRelationship->getPdfTokenAutorization();

            $data = (object) [
                'id'                     =>  $relationshipData->id,
                'name'                   =>  $relationshipData->name,
                'cpf_cnpj'               =>  Facilites::mask_cpf_cnpj($relationshipData->cpf_cnpj),
                'register_description'   =>  $relationshipData->register_description,
                'account_number'         =>  Facilites::mask_account($relationshipData->account_number),
                'phone'                  =>  Facilites::mask_phone($relationshipData->phone),
                'email'                  =>  $relationshipData->email,
                'date_open_account'      =>  \Carbon\Carbon::parse($relationshipData->date_open_account)->format('d/m/Y'),
                'accnt_typs_description' =>  $relationshipData->accnt_typs_description,
                'rgstr_dtls_name'        =>  $relationshipData->rgstr_dtls_name,
                'rgstrs_cpf_cnpj'        =>  Facilites::mask_cpf_cnpj($relationshipData->rgstrs_cpf_cnpj),
            ];

            $file_name = "Autorização de Token - " . $relationshipData->name . ".pdf";
            $pdf = base64_encode(PDF::loadView('reports/account_responsibility_token', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']));


            if ($relationshipData->user_register_master_id == null) {
                return response()->json(array("error" => "Usuário não vínculado a cadastro, por favor verifique"));
            }

            $digitalSignature = new DigitalSignatureService();
            $digitalSignature->master_id = $relationshipData->master_id;
            $digitalSignature->register_master_id = $relationshipData->user_register_master_id;
            $digitalSignature->file_name = $file_name;

            //1 - Set Document to Register
            $setDocumentToRegister = $digitalSignature->setDocumentToRegister();
            if (!$setDocumentToRegister->success) {
                return response()->json(array("error" => $setDocumentToRegister->message));
            }

            //2 - Set Document Type
            $digitalSignature->document_id = $setDocumentToRegister->document_data->id;
            $digitalSignature->document_type_id = 2; //2 = Autorização de Token
            $setDocumentType = $digitalSignature->setDocumentType();
            if (!$setDocumentType->success) {
                return response()->json(array("error" => $setDocumentType->message));
            }

            //3 - Upload Local Document
            $digitalSignature->file64 = $pdf;
            $uploadLocalDocument = $digitalSignature->uploadLocalDocument();
            if (!$uploadLocalDocument->success) {
                return response()->json(array("error" => $uploadLocalDocument->message));
            }

            //4 - Set Document Signer
            $digitalSignature->signer_cpf_cnpj = $relationshipData->cpf_cnpj;
            $digitalSignature->signer_name = $relationshipData->name;
            $digitalSignature->signer_email = $relationshipData->email;
            $digitalSignature->signer_phone = $relationshipData->phone;
            $digitalSignature->signer_foreign = 0;
            $setDocumentSigner = $digitalSignature->setDocumentSigner();
            if (!$setDocumentSigner->success) {
                return response()->json(array("error" => $setDocumentSigner->message));
            }

            //5 - Set Document Signer Profile
            $digitalSignature->documnet_signer_id = $setDocumentSigner->document_signer_data->id;
            $digitalSignature->signer_id = $setDocumentSigner->document_signer_data->signer_id;
            $digitalSignature->signer_profile_id = 1; //1 = Assinar
            $setDocumentSignerProfile = $digitalSignature->setDocumentSignerProfile();
            if (!$setDocumentSignerProfile->success) {
                return response()->json(array("error" => $setDocumentSignerProfile->message));
            }

            //6 - Set Document Signer Validation Method
            $digitalSignature->validation_method_id = 3; //3 = Documento com foto e selfie
            $setDocumentSignerValidation = $digitalSignature->setDocumentSignerValidation();
            if (!$setDocumentSignerValidation->success) {
                return response()->json(array("error" => $setDocumentSignerValidation->message));
            }

            //7 - Set Document Signer Authentication Method
            $digitalSignature->auth_method_id = 1; //1 = E-Mail
            $setDocumentSignerAuthenticationMethod = $digitalSignature->setDocumentSignerAuthentication();
            if (!$setDocumentSignerAuthenticationMethod->success) {
                return response()->json(array("error" => $setDocumentSignerAuthenticationMethod->message));
            }

            //8 - Send Document to Sign
            $sendDocumentToSign = $digitalSignature->sendDocumentToSign();
            if (!$sendDocumentToSign->success) {
                return response()->json(array("error" => $sendDocumentToSign->message));
            }

            //return response()->json(array("success" => $sendDocumentToSign->message));
        }
        return response()->json(array("error" => "Não foram localizados cadastros para gerar a autorização de token, por favor verique os dados informados e tente novamente"));
    }

    public function sendSignAuthorizationToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [136];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $signerUsersRelationships = UserRelationship::whereIn('relationship_id', [3, 4])->whereNull('deleted_at')->where('id', '=', $request->id)->get();
        foreach ($signerUsersRelationships as $signerUsersRelationship) {
            $UserRelationship = new UserRelationship();
            $UserRelationship->id = $signerUsersRelationship->id;

            $relationshipData = $UserRelationship->getPdfTokenAutorization();

            $data = (object) [
                'id'                     =>  $relationshipData->id,
                'name'                   =>  $relationshipData->name,
                'cpf_cnpj'               =>  Facilites::mask_cpf_cnpj($relationshipData->cpf_cnpj),
                'register_description'   =>  $relationshipData->register_description,
                'account_number'         =>  Facilites::mask_account($relationshipData->account_number),
                'phone'                  =>  Facilites::mask_phone($relationshipData->phone),
                'email'                  =>  $relationshipData->email,
                'date_open_account'      =>  \Carbon\Carbon::parse($relationshipData->date_open_account)->format('d/m/Y'),
                'accnt_typs_description' =>  $relationshipData->accnt_typs_description,
                'rgstr_dtls_name'        =>  $relationshipData->rgstr_dtls_name,
                'rgstrs_cpf_cnpj'        =>  Facilites::mask_cpf_cnpj($relationshipData->rgstrs_cpf_cnpj),
            ];

            $file_name = "Autorização de Token - " . $relationshipData->name . ".pdf";
            $pdf = base64_encode(PDF::loadView('reports/account_responsibility_token', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']));

            if ($relationshipData->user_register_master_id == null) {
                return response()->json(array("error" => "Usuário não possui cadastro definido, por favor crie um cadastro com o CPF do usuário e vincule ao usuário"));
            }

            $digitalSignature = new DigitalSignatureService();
            $digitalSignature->master_id = $relationshipData->master_id;
            $digitalSignature->register_master_id = $relationshipData->user_register_master_id;
            $digitalSignature->file_name = $file_name;

            //1 - Set Document to Register
            //$setDocumentToRegister = $digitalSignature->setDocumentToRegister();
            $setDocumentToRegister = (object) json_decode(json_encode($digitalSignature->setDocumentToRegister()));
            if (!$setDocumentToRegister->success) {
                return response()->json(array("error" => $setDocumentToRegister->message));
            }

            //2 - Set Document Type
            $digitalSignature->document_id = $setDocumentToRegister->document_data->id;
            $digitalSignature->document_type_id = 2; //2 = Autorização de Token
            $setDocumentType = (object) json_decode(json_encode($digitalSignature->setDocumentType()));
            if (!$setDocumentType->success) {
                return response()->json(array("error" => $setDocumentType->message));
            }

            //3 - Upload Local Document
            $digitalSignature->file64 = $pdf;
            $uploadLocalDocument = $digitalSignature->uploadLocalDocument();
            if (!$uploadLocalDocument->success) {
                return response()->json(array("error" => $uploadLocalDocument->message));
            }

            //4 - Set Document Signer
            $digitalSignature->signer_cpf_cnpj = $relationshipData->cpf_cnpj;
            $digitalSignature->signer_name = $relationshipData->name;
            $digitalSignature->signer_email = $relationshipData->email;
            $digitalSignature->signer_phone = $relationshipData->phone;
            $digitalSignature->signer_foreign = 0;
            $setDocumentSigner = (object) json_decode(json_encode($digitalSignature->setDocumentSigner()));
            if (!$setDocumentSigner->success) {
                return response()->json(array("error" => $setDocumentSigner->message));
            }

            //5 - Set Document Signer Profile
            $digitalSignature->documnet_signer_id = $setDocumentSigner->document_signer_data->id;
            $digitalSignature->signer_id = $setDocumentSigner->document_signer_data->signer_id;
            $digitalSignature->signer_profile_id = 1; //1 = Assinar
            $setDocumentSignerProfile = (object) json_decode(json_encode($digitalSignature->setDocumentSignerProfile()));
            if (!$setDocumentSignerProfile->success) {
                return response()->json(array("error" => $setDocumentSignerProfile->message));
            }

            //6 - Set Document Signer Validation Method
            $digitalSignature->validation_method_id = 3; //3 = Documento com foto e selfie
            $setDocumentSignerValidation = (object) json_decode(json_encode($digitalSignature->setDocumentSignerValidation()));
            if (!$setDocumentSignerValidation->success) {
                return response()->json(array("error" => $setDocumentSignerValidation->message));
            }

            //7 - Set Document Signer Authentication Method
            $digitalSignature->auth_method_id = 1; //1 = E-Mail
            $setDocumentSignerAuthenticationMethod = (object) json_decode(json_encode($digitalSignature->setDocumentSignerAuthentication()));
            if (!$setDocumentSignerAuthenticationMethod->success) {
                return response()->json(array("error" => $setDocumentSignerAuthenticationMethod->message));
            }

            //8 - Send Document to Sign
            $sendDocumentToSign = $digitalSignature->sendDocumentToSign();
            if (!$sendDocumentToSign->success) {
                return response()->json(array("error" => $sendDocumentToSign->message));
            }

            return response()->json(array("success" => $sendDocumentToSign->message));
        }
        return response()->json(array("error" => "Não foram localizados cadastros para gerar a autorização de token, por favor verique os dados informados e tente novamente"));
    }

    protected function setMainRelationship(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $user = auth('api')->user();
        if (!isset($user->id)) {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login para continuar"));
        }
        if ($userMaster = UserMaster::where('user_id', '=', $user->id)->where('master_id', '=', $checkAccount->master_id)->whereNull('deleted_at')->first()) {
            if ($userRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->where('user_master_id', '=', $userMaster->id)->whereNull('deleted_at')->first()) {
                UserRelationship::removeAllMainRelationships($userMaster->id);
                $userRelationship->main = 1;
                if ($userRelationship->save()) {
                    return response()->json(array("success" => "Conta favorita definida com sucesso"));
                }
            } else {
                return response()->json(array("error" => "Conta não definida para o usuário"));
            }
        } else {
            return (object) array("error" => "Ocorreu uma falha ao checar o vínculo da conta do usuário com a empresa");
        }
    }

    protected function createRelationshipFromRegister(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [133];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster = RegisterMaster::where('id', '=', $request->register_master_id)->where('master_id', '=', $checkAccount->master_id)->whereNull('deleted_at')->first();
        $register       = Register::where('id', '=', $registerMaster->register_id)->whereNull('deleted_at')->first();
        $registerDetail = RegisterDetail::where('register_master_id', '=', $registerMaster->id)->whereNull('deleted_at')->first();
        $registerPhone  = RegisterPhone::where('register_master_id', '=', $registerMaster->id)->whereNull('deleted_at')->where('main', '=', 1)->first();
        $registerEmail  = RegisterEmail::where('register_master_id', '=', $registerMaster->id)->whereNull('deleted_at')->where('main', '=', 1)->first();
    }

    public function grantPermissions()
    {
        return response()->json(['error' => 'Método desabilitado']);

        /*$user_relationships = UserRelationship::whereNull('deleted_at')->get();

        foreach ($user_relationships as $usr_rltnshp) {
            if ($permission = Permission::where('relationship_id','=',$usr_rltnshp->relationship_id)->get()) {
                foreach ($permission as $prmssn) {
                    UsrRltnshpPrmssn::create([
                     'usr_rltnshp_id' => $usr_rltnshp->id,
                     'prmssn_grp_id'  => null,
                     'permission_id'  => $prmssn->id,
                     'created_at'     => \Carbon\Carbon::now(),
                    ]);
                }
            }
        } */
    }

    protected function setManualEntryDailyLimit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$userRelationship = UserRelationship::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o vínculo do usuário informado, por favor, tente mais tarde"));
        }

        $userRelationship->manual_entry_daily_limit = $request->manual_entry_daily_limit;

        if ($userRelationship->save()) {
            return response()->json(array("success" => "Limite diário definido para o vínculo do usuário com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir um limite diário para o vínculo do usuário, por favor, tente mais tarde"));
    }

    protected function setCheckDailyLimit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$userRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o vínculo do usuário informado, por favor, tente mais tarde"));
        }

        $userRelationship->check_daily_limit = $request->check_daily_limit;

        if ($userRelationship->save()) {
            return response()->json(array("success" => "Checagem de limite diário definida para o vínculo do usuário com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a checagem de limite diário para o vínculo do usuário, por favor, tente mais tarde"));
    }

    protected function setDailyLimit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$userRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o vínculo do usuário informado, por favor, tente mais tarde"));
        }

        $userRelationship->daily_limit = $request->daily_limit;

        if ($userRelationship->save()) {
            return response()->json(array("success" => "Limite diário definido para o vínculo do usuário com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite diário para o vínculo do usuário, por favor, tente mais tarde"));
    }

    protected function setCheckMonthlyLimit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$userRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o vínculo do usuário informado, por favor, tente mais tarde"));
        }

        $userRelationship->check_monthly_limit = $request->check_monthly_limit;

        if ($userRelationship->save()) {
            return response()->json(array("success" => "Checagem de limite mensal definida para o vínculo do usuário com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a checagem de limite mensal para o vínculo do usuário, por favor, tente mais tarde"));
    }

    protected function setMonthlyLimit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$userRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o vínculo do usuário informado, por favor, tente mais tarde"));
        }

        $userRelationship->monthly_limit = $request->monthly_limit;

        if ($userRelationship->save()) {
            return response()->json(array("success" => "Limite mensal definido para o vínculo do usuário com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite mensal para o vínculo do usuário, por favor, tente mais tarde"));
    }

    protected function setAdministrator(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$userRelationship = UserRelationship::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->first()) {
            return response()->json(array("error" => "Poxa, não foi possível localizar o vínculo do usuário informado, por favor, tente mais tarde"));
        }

        $userRelationship->is_administrator = $userRelationship->is_administrator ? 0 : 1;

        if ($userRelationship->save()) {
            return response()->json(array("success" => "Administrator definido para o vínculo do usuário com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o Administrator para o vínculo do usuário, por favor, tente mais tarde"), 500);
    }
}
