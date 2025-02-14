<?php

namespace App\Http\Controllers;

use App\Models\AccntMvmntConfirm;
use App\Models\ApiConfig;
use App\Models\AuthorizationToken;
use App\Models\SendSms;
use App\Models\User;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\Facilites;
use App\Services\Account\MovementService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AccntMvmntConfirmController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [56];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if($request->value <= 0) {
            return response()->json(array("error" => "Informe um valor maior que 0 para continuar."));
        }

        $arrayType1 = [2,4,5,6,7,8,16,17,21,22,23];
        $arrayType2 = [9,10,11,12,13,14,15,20,41];       
        if(in_array($request->type_id, $arrayType1)){
            $value = $request->value * -1;            
        }else if(in_array($request->type_id, $arrayType2)){                        
            $value = $request->value;
        }else{
            return response()->json(['error'=>'Tipo de lançamento não permitido']);
        }
        if($accnt_mvmnt_confirm =  AccntMvmntConfirm::create([
            'account_id'      => $request->account_id,
            'type_id'         => $request->type_id,
            'value'           => $value,
            'description'     => $request->description,
            'status_id'       => $request->status_id,
            'unique_id'       => md5(rand(1, 99999).$request->account_id.date('Ymd').time()),
            'master_id'       => $checkAccount->master_id,
            'user_id'         => $request->header('userId'),
            'created_at'      => \Carbon\Carbon::now()
            ])){
            if (Auth::check()) {
                $user = Auth::user();
                $usr  = User::where('id','=',$user->id)->first();
                if( Hash::check(base64_decode($request->password), $usr->password) ){
                    $token = new Facilites();
                    $authorizationToken     =  AuthorizationToken::create([
                        'token_phone'       => $token->createApprovalToken(),
                        'token_email'       => $token->createApprovalToken(),
                        'type_id'           => 4,
                        'origin_id'         => $accnt_mvmnt_confirm->id,
                        'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
                        'token_expired'     => 0,
                        'created_at'        => \Carbon\Carbon::now()
                    ]);
                    $accnt_mvmnt_confirm->approval_token            = $authorizationToken->token_phone;
                    $accnt_mvmnt_confirm->approval_token_expiration = $authorizationToken->token_expiration;                

                    if($accnt_mvmnt_confirm->save()){
                        $sendSMS = SendSms::create([
                            'external_id' => ("11".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$accnt_mvmnt_confirm->id),
                            'to'          => "55".$usr->phone,
                            'message'     => "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para adicionar o valor de ".$accnt_mvmnt_confirm->value." a conta ".$accnt_mvmnt_confirm->account_id."",
                            'type_id'     => 11,
                            'origin_id'   => $accnt_mvmnt_confirm->id,
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
                        if(isset( $apiZenviaSMS->sendShortSMS()->success ) ){
                            return response()->json(array("success" => "Token enviado, a partir de agora você tem 5 minutos para utiliza-lo", "id" => $accnt_mvmnt_confirm->id, "unique_id" => $accnt_mvmnt_confirm->unique_id));
                        } else {
                            return response()->json(array("error" => "Ocorreu uma falha ao enviar o token, por favor tente novamente mais tarde"));
                        }
                    } else {
                        return response()->json(array("error" => "Ocorreu uma falha ao enviar o token, por favor tente novamente mais tarde"));
                    }
                } else {
                    return response()->json(array("error" => "Senha invalida"));
                }
            } else {
                return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
            }
        }
    }

    protected function checkToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [56];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
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

        if(AccntMvmntConfirm::where('id','=',$request->id)->where('unique_id','=', $request->unique_id)->where('approval_token','=',$request->token)->count() == 0 ){
            return response()->json(array("error" => "Token inválido"));
        }else{           
            $AccntMvmntConfirm = AccntMvmntConfirm::where('id','=', $request->id)->where('unique_id','=', $request->unique_id)->where('approval_token','=', $request->token)->first();
            if(\Carbon\Carbon::parse($AccntMvmntConfirm->approval_token_expiration)->format('Y-m-d H:i:s') <= \Carbon\Carbon::now()->format('Y-m-d H:i:s')){
                return response()->json(array("error" => "Token expirado"));
            }else{
                $movementService = new MovementService();
                $movementService->movementData = (object)[
                    'account_id'    => $AccntMvmntConfirm->account_id,
                    'master_id'     => $AccntMvmntConfirm->master_id,
                    'origin_id'     => ((\Carbon\Carbon::now()->format('ymdHi')).$AccntMvmntConfirm->id),
                    'mvmnt_type_id' => $AccntMvmntConfirm->type_id,
                    'value'         => round($AccntMvmntConfirm->value,2),
                    'description'   => $AccntMvmntConfirm->description,
                ];
                if($movementService->create()){
                    $AccntMvmntConfirm->approved_at = \Carbon\Carbon::now();
                    $AccntMvmntConfirm->save();                
                    return response()->json(array("success" => "Lançamento inserido com sucesso"));
                }else{
                    return response()->json(array("error" => "Não foi possível inserir o lançamento"));
                }
            }
        }
    }
}
