<?php

namespace App\Http\Controllers;

use App\Models\UserAcceptedsTerm;
use App\Models\User;
use App\Models\AuthAttempt;
use Illuminate\Http\Request;

class UserAcceptedsTermController extends Controller
{
    protected function acceptTerm(Request $request){
        $user =  auth('api')->user();
        if(!isset($user->id)){
            return (object) array("success" => false, "message" => "Usuário não autenticado, por favor realize o login para continuar");
        }

        $auth_attempt = AuthAttempt::where('unique_id','=',$request->access);

        if( $auth_attempt->count() > 0 ){
            $auth_attempt = $auth_attempt->first();
            if(UserAcceptedsTerm::create([
                'unique_id' => md5(date('Ymd').time()).$user->id,
                'user_id' => $user->id,
                'ip' => $auth_attempt->ip,
                'latitude' => $auth_attempt->latitude,
                'longitude' => $auth_attempt->longitude,
                'accuracy' => $auth_attempt->accuracy,
                'altitude' => $auth_attempt->altitude,
                'altitude_accuracy' => $auth_attempt->altitude_accuracy,
                'heading' => $auth_attempt->heading,
                'speed' => $auth_attempt->speed,
                'created_at' => \Carbon\Carbon::now()
            ])){
                $usr = User::where('id','=',$user->id)->first();
                $usr->accepted_term = 1;
                if($usr->save()){
                    return response()->json(array("success" => "Termo de uso aceito com sucesso, agora já é possível acessar sua conta"));
                } else {
                    return response()->json(array("error" => "Poxa, não foi possível aceitar o termo de uso no momento, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("error" => "Poxa, não foi possível aceitar o termo de uso no momento, por favor tente novamente mais tarde"));
            }
        } else {
            if($user->cpf_cnpj == '11111111111'){

                $auth_attempt = AuthAttempt::where('user_id','=',$user->id)->latest()->first();
                UserAcceptedsTerm::create([
                    'unique_id' => md5(date('Ymd').time()).$user->id,
                    'user_id' => $user->id,
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

                $usr = User::where('id','=',$user->id)->first();
                $usr->accepted_term = 1;
                if($usr->save()){
                    return response()->json(array("success" => "Termo de uso aceito com sucesso, agora já é possível acessar sua conta"));
                } else {
                    return response()->json(array("error" => "Poxa, não foi possível aceitar o termo de uso no momento, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("error" => "Poxa, ocorreu um erro ao aceitar o termo de uso, por favor tente novamente mais tarde"));
            }
        }
    }
}
