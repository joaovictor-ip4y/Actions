<?php

namespace App\Http\Controllers;

use App\Models\Favored;
use App\Models\FavoredAccount;
use App\Models\FavoredAccountDetail;
use App\Models\Account;
use App\Models\RegisterMaster;
use App\Models\Register;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FavoredController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [19,189,270];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        /*
        $favored = new Favored();
        return response()->json($favored->getFavored()); */
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [188, 269];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( strlen($request->bank_account) > 12 ) {
            return response()->json(array("error" => "Conta e dígito do favorecido não pode ter mais de 12 caracteres"));
        }

        $registerId = $request->header('registerId');
        if($registerId == ''){
            $register_master_id = $request->register_master_id;
            $favored_account_description = $request->description;
            $favored_account_main        = $request->main;
        } else {
            $register_master_id = $request->header('registerId');
            $favored_account_description = 'Conta cadastrada para transferência';
            $favored_account_main        = 0;
        }

        if($request->favored_bank_id == 1) {
            $tansfAccount = str_pad( str_replace('-','',str_replace('.','',$request->bank_account)) , 10, '0', STR_PAD_LEFT);
            if( Account::where('account_number','=',$tansfAccount)->whereNull('deleted_at')->count() > 0 ){
                $account        = Account::where('account_number','=',$tansfAccount)->whereNull('deleted_at')->first();
                $registerMaster = RegisterMaster::where('id','=',$account->register_master_id)->first();
                $register       = Register::where('id','=',$registerMaster->register_id)->first();

                if($register->cpf_cnpj != preg_replace( '/[^0-9]/', '', $request->favored_cpf_cnpj)){
                    return response()->json(array("error" => "Conta não localizada, verifique os dados informados."));
                }
            } else {
                return response()->json(array("error" => "Conta não localizada, verifique os dados informados."));
            }
        }

        $favoredData = $this->returnFavoredAccount($request->favored_cpf_cnpj, $request->favored_name, $registerId);
        if($favoredData->status == 0 ){
            return response()->json(array("error" => $favoredData->error));
        } else {
            if($request->bank_agency != '' and $request->bank_account != '' and $request->favored_bank_id != ''){
                if( FavoredAccountDetail::where('favored_account_id','=',$favoredData->success->id)->where('bank_id','=',$request->favored_bank_id)->where('bank_agency','=',$request->bank_agency)->where('bank_account','=',$request->bank_account)->count() == 0 ){
                    if($favoredAccountDetail = FavoredAccountDetail::Create([
                        'description'          => $favored_account_description,
                        'favored_account_id'   => $favoredData->success->id,
                        'bank_id'              => $request->favored_bank_id,
                        'bank_agency'          => $request->bank_agency,
                        'bank_account'         => $request->bank_account,
                        'bank_account_type_id' => $request->bank_account_type_id,
                        'main'                 => $favored_account_main,
                        'created_at'           => \Carbon\Carbon::now(),
                        'uuid'                 => Str::orderedUuid(),
                    ])){
                        $accountDetail = $this->getFavoredAccountDetail($favoredAccountDetail->id);
                        return response()->json(array(
                            "success"                   => "Favorecido atualizado com sucesso",  
                            "favored_id"                => $favoredData->success->id, 
                            "favored_account_detail_id" => $favoredAccountDetail->id,
                            "favored_name"              => $accountDetail->favored_name,
                            "favored_cpf_cnpj"          => $accountDetail->favored_cpf_cnpj,
                            "type_description"          => $accountDetail->bank_account_type,
                            "bank_description"          => $accountDetail->bank_description,
                            "account_agency"            => $accountDetail->bank_agency,
                            "account_number"            => $accountDetail->bank_account
                        ));
                    } else {
                        return response()->json(array("error" => "Ocorreu um erro ao cadastrar a conta do favorecido"));
                    }
                } else {
                    $favoredAccountDetail = FavoredAccountDetail::where('favored_account_id','=',$favoredData->success->id)->where('bank_id','=',$request->favored_bank_id)->where('bank_agency','=',$request->bank_agency)->where('bank_account','=',$request->bank_account)->first();
                    $favoredAccountDetail->description          = $favored_account_description;
                    $favoredAccountDetail->bank_account_type_id = $request->bank_account_type_id;
                    $favoredAccountDetail->deleted_at           = null;
                    if($favoredAccountDetail->save()){
                        $accountDetail = $this->getFavoredAccountDetail($favoredAccountDetail->id);
                        return response()->json(array(
                            "success"                   => "Favorecido atualizado com sucesso",  
                            "favored_id"                => $favoredData->success->id, 
                            "favored_account_detail_id" => $favoredAccountDetail->id,
                            "favored_name"              => $accountDetail->favored_name,
                            "favored_cpf_cnpj"          => $accountDetail->favored_cpf_cnpj,
                            "type_description"          => $accountDetail->bank_account_type,
                            "bank_description"          => $accountDetail->bank_description,
                            "account_agency"            => $accountDetail->bank_agency,
                            "account_number"            => $accountDetail->bank_account
                        ));
                    } else {
                        return response()->json(array("error" => "Ocorreu um erro ao cadastrar a conta do favorecido"));
                    }
                }
            } else {
                return response()->json(array("success" => "Favorecido cadastrado com sucesso", "favored_id" =>  $favoredData->success->id)); 
            }
        }

    }

    protected function getFavoredAccountDetail($favored_account_detail_id)
    {
        $favoredAccountDetail = new FavoredAccountDetail();
        $favoredAccountDetail->id = $favored_account_detail_id;
        return (object) $favoredAccountDetail->getFavoredAccountDetail()[0];
    }
    
    public function returnFavoredAccount($fav_cpf_cnpj, $name, $register_master_id)
    {
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $fav_cpf_cnpj);

        //Verifica se é cpf e valida
        if(strlen($cpf_cnpj) == 11) {
            if( !  app('App\Http\Controllers\RegisterController')->validateCPF($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CPF inválido");
            }
        //Verifica se é cnpj e valida
        } else if(strlen($cpf_cnpj) == 14){
            if( ! app('App\Http\Controllers\RegisterController')->validateCNPJ($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CNPJ inválido");
            }
        //Retorna erro se não for cpf ou cnpj
        } else {
            return (object) array("status" => 0, "error" => "CPF ou CNPJ inválido");
        }

        //Verifica se favorecido existe
        if( Favored::where('cpf_cnpj', '=', $cpf_cnpj)->count() == 0 ){
            if(
                $favored = Favored::Create([
                    'cpf_cnpj'   => $cpf_cnpj,
                    'created_at' => \Carbon\Carbon::now(),
                    'uuid'       => Str::orderedUuid(),
                ])
            ){
                if($favored_account = FavoredAccount::Create([
                    'favored_id'          => $favored->id,
                    'register_master_id'  => $register_master_id,
                    'favored_name'        => $name,
                    'created_at'          => \Carbon\Carbon::now(),
                    'uuid'                => Str::orderedUuid(),
                ])){
                    return (object) array("status" => 1, "success" => $favored_account);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao vincular o novo favorecido");
                }
            } else {
                return (object) array("status" => 0, "error" => "Ocorreu um erro ao realizar o cadastro do novo favorecido");
            }
        } else {
            $favored = Favored::where('cpf_cnpj', '=', $cpf_cnpj)->first();
            //Verifica se favorecido já pertence ao cadastro
            if( FavoredAccount::where('register_master_id', '=', $register_master_id)->where('favored_id', '=', $favored->id)->count() == 0 ){
                if($favored_account = FavoredAccount::Create([
                    'favored_id'          => $favored->id,
                    'register_master_id'  => $register_master_id,
                    'favored_name'        => $name,
                    'created_at'          => \Carbon\Carbon::now(),
                    'uuid'                => Str::orderedUuid(),
                ])){
                    return (object) array("status" => 1, "success" => $favored_account);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao vincular o novo favorecido");
                }
            } else {
                $favored_account = FavoredAccount::where('register_master_id', '=', $register_master_id)->where('favored_id', '=', $favored->id)->first();
                $favored_account->deleted_at = null;
                if( $favored_account->save() ){
                    return (object) array("status" => 1, "success" => $favored_account);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao reativar favorecido");
                }
            }
        }
    }
}
