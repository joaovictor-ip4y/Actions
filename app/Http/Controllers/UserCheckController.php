<?php

namespace App\Http\Controllers;

use App\Models\UserCheck;
use App\Models\User;
use App\Models\AuthAttempt;
use App\Models\UserAcceptedsTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserCheckController extends Controller
{
    protected function checkTokens(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unique_id' => ['required', 'string'],
            'phone_token' => ['required', 'string', 'size:8'],
            'email_token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if(UserCheck::where('user_id','=',$request->unique_id)->whereNull('deleted_at')->count() > 0){
            $userCheck = UserCheck::where('user_id','=',$request->unique_id)->whereNull('deleted_at')->first();

            if($userCheck->token_confirmation_phone == null){
                return response()->json(array("error" => "Token informado não corresponde com o token enviado para o celular"));
            }

            if($userCheck->token_confirmation_phone != $request->phone_token){
                return response()->json(array("error" => "Token informado não corresponde com o token enviado para o celular"));
            }

            if($userCheck->token_confirmation_email == null){
                return response()->json(array("error" => "Token informado não corresponde com o token enviado para o e-mail"));
            }

            if($userCheck->token_confirmation_email != $request->email_token){
                return response()->json(array("error" => "Token informado não corresponde com o token enviado para o e-mail"));
            }

            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $userCheck->created_at )->addMinutes(20))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Tokens expirado, por favor recomece o processo de login"));
            }

            $userCheck->token_email_confirmed = 1;  
            $userCheck->token_phone_confirmed = 1;
            
            if($userCheck->save()){
                return response()->json(array("success" => "Tokens verificados com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao verificar os tokens, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Requisição de verificação de acesso não localizada"));
        }
    }

    protected function termAccept(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unique_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if(UserCheck::where('user_id','=',$request->unique_id)->whereNull('deleted_at')->count() > 0){
            $userCheck = UserCheck::where('user_id','=',$request->unique_id)->whereNull('deleted_at')->first();
            if($userCheck->token_email_confirmed != 1 or $userCheck->token_phone_confirmed != 1){
                return response()->json(array("error" => "É necessário confirmar os tokens antes de continuar"));
            }
            if($request->term_accept != 1){
                return response()->json(array("error" => "É necessário concordar com o termo de uso para continuar"));
            }
            $userCheck->term_accepted = $request->term_accept;
            if($userCheck->save()){

                $auth_attempt = AuthAttempt::where('user_id','=',$request->unique_id)->latest()->first();
                UserAcceptedsTerm::create([
                    'unique_id' => md5(date('Ymd').time()).$auth_attempt->id,
                    'user_id' => $userCheck->user_id,
                    'ip' => $auth_attempt->ip,
                    'latitude' => $auth_attempt->latitude,
                    'longitude' => $auth_attempt->longitude,
                    'accuracy' => $auth_attempt->accuracy,
                    'altitude' => $auth_attempt->altitude,
                    'altitude_accuracy' => $auth_attempt->altitude_accuracy,
                    'heading' => $auth_attempt->heading,
                    'speed' => $auth_attempt->speed,
                    'created_at' => \Carbon\Carbon::now()
                ]);

                return response()->json(array("success" => "Termo de uso aceito com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao aceitar o termo de uso, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Requisição de verificação de acesso não localizada"));
        }
    }

    protected function redefinePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unique_id' => ['required', 'string'],
            'password' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if(UserCheck::where('user_id','=',$request->unique_id)->whereNull('deleted_at')->where('finished', '=', 0)->count() > 0){
            $userCheck = UserCheck::where('user_id','=',$request->unique_id)->whereNull('deleted_at')->where('finished', '=', 0)->first();
            if($userCheck->token_email_confirmed != 1 or $userCheck->token_phone_confirmed != 1){
                return response()->json(array("error" => "É necessário confirmar os tokens antes de continuar"));
            }

            if($userCheck->term_accepted != 1){
                return response()->json(array("error" => "É necessário concordar com o termo de uso antes de continuar"));
            }

            if(base64_decode($request->password) != base64_decode($request->confirm_password)){
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
                    } /*else {
                        if($lastNumber == (int) $pass){
                            return response()->json(array("error" => "A senha não pode conter caracteres iguais na sequência"));
                        }
                        if(($lastNumber + 1) == (int) $pass){
                            $passValidate = false;
                            return response()->json(array("error" => "A senha não pode conter números sequenciais crescentes (Como 123...)"));
                        }
                        if(($lastNumber - 1) == (int) $pass){
                            $passValidate = false;
                            return response()->json(array("error" => "A senha não pode conter números sequenciais decrescente (Como 321...)"));
                        }
                    }*/
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


            $userCheck->finished = 1;
            $userCheck->save();

            $user                    = User::where('id','=',$userCheck->user_id)->first();
            $user->password          = Hash::make(base64_decode($request->password));
            $user->email_verified_at = \Carbon\Carbon::now();
            $user->phone_verified_at = \Carbon\Carbon::now();
            $user->accepted_term     = 1;
            if($user->save()){

                $userCheck->finished = 1;
                $userCheck->save();

                return response()->json(array("success" => "Verificação de acesso concluída com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu uma falha ao atualizar a nova senha, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Requisição de verificação de acesso não localizada"));
        }
    }
}
