<?php

namespace App\Http\Controllers;

use App\Models\FavoredAccountDetail;
use App\Models\Favored;
use App\Libraries\Facilites;
use App\Models\Account;
use App\Models\RegisterMaster;
use App\Models\Register;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FavoredAccountDetailController extends Controller
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

        $registerMasterId = (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id;

        $favoredAccountDetail                     = new FavoredAccountDetail();
        $favoredAccountDetail->master_id          = $checkAccount->master_id;
        $favoredAccountDetail->register_master_id = $registerMasterId;
        return response()->json($favoredAccountDetail->getFavoredAccountDetail());
    }

    protected function getFavored(Request $request)
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

        $favoredAccountDetail                     = new FavoredAccountDetail();
        $favoredAccountDetail->master_id          = $checkAccount->master_id;
        $favoredAccountDetail->onlyActive         = $request->onlyActive;
        $favoredAccountDetail->betweenAccount     = $request->betweenAccount;
        $favoredAccountDetail->otherBank          = $request->otherBank;
        $favoredAccountDetail->register_master_id = $request->header('registerId');
        $favoreds = [];
        foreach($favoredAccountDetail->getFavored() as $favored){
            array_push($favoreds,[ "id" => $favored->id, "description" => $favored->favored_name.' - '.Facilites::mask_cpf_cnpj($favored->cpf_cnpj) ]);
        }
        return response()->json($favoreds);
    }

    protected function getFavoredAccount(Request $request)
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

        $favoredAccountDetail                     = new FavoredAccountDetail();
        if($request->favored_id != null){
            $favoredAccountDetail->favored_account_id = $request->favored_id;
        }

        $favoredAccountDetail->master_id          = $checkAccount->master_id;
        $favoredAccountDetail->register_master_id = $request->header('registerId');
        $favoredAccountDetail->betweenAccount     = $request->betweenAccount;
        $favoredAccountDetail->otherBank          = $request->otherBank;
        $favoredAccountDetail->onlyActive         = 1;
        return response()->json($favoredAccountDetail->getFavoredAccountToTransfer());
    }

    protected function getFavoredTransfer(Request $request)
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

        $favoredAccountDetail = new FavoredAccountDetail();
        $favoredAccountDetail->master_id          = $checkAccount->master_id;
        $favoredAccountDetail->register_master_id = $request->header('registerId');
        return response()->json($favoredAccountDetail->getFavoredToTransfer());
    }

    protected function getFavoredAccountDetail($favored_account_detail_id)
    {

        $favoredAccountDetail = new FavoredAccountDetail();
        $favoredAccountDetail->id = $favored_account_detail_id;
        return (object) $favoredAccountDetail->getFavoredAccountDetail()[0];
    }

    protected function newFavoredAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [189, 270, 269];
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
        } else {
            $register_master_id = $request->header('registerId');
        }

        if( ! $favored = Favored::where('id', '=', $request->favoredId)->first() ){
            return response()->json(array("error" => "Favorecido não localizado"));
        }

        if($request->favored_bank_id == 1) {
            $tansfAccount = str_pad( str_replace('-','',str_replace('.','',$request->bank_account)) , 10, '0', STR_PAD_LEFT);
            if( Account::where('account_number','=',$tansfAccount)->whereNull('deleted_at')->count() > 0 ){
                $account        = Account::where('account_number','=',$tansfAccount)->whereNull('deleted_at')->first();
                $registerMaster = RegisterMaster::where('id','=',$account->register_master_id)->first();
                $register       = Register::where('id','=',$registerMaster->register_id)->first();

                if($register->cpf_cnpj != preg_replace( '/[^0-9]/', '', $favored->cpf_cnpj)){
                    return response()->json(array("error" => "Conta não localizada, verifique os dados informados."));
                }
            } else {
                return response()->json(array("error" => "Conta não localizada, verifique os dados informados."));
            }
        }

        if(FavoredAccountDetail::where('favored_account_id','=',$request->favoredId)->where('bank_id','=',$request->favored_bank_id)->where('bank_agency','=',$request->bank_agency)->where('bank_account','=',$request->bank_account)->count() == 0 ){
            if($favoredAccountDetail = FavoredAccountDetail::Create([
                'description'          => 'Cadastrada para realizar transferência',
                'favored_account_id'   => $request->favoredId,
                'bank_id'              => $request->favored_bank_id,
                'bank_agency'          => $request->bank_agency,
                'bank_account'         => $request->bank_account,
                'bank_account_type_id' => $request->bank_account_type_id,
                'main'                 => 0,
                'created_at'           => \Carbon\Carbon::now(),
                'uuid'                 => Str::orderedUuid(),
            ])){
                $accountDetail = $this->getFavoredAccountDetail($favoredAccountDetail->id);
                return response()->json(array(
                    "success"                   => "Conta cadastrada com sucesso",
                    "favored_id"                => $favoredAccountDetail->favored_account_id,
                    "favored_account_detail_id" => $favoredAccountDetail->id,
                    "favored_name"              => $accountDetail->favored_name,
                    "favored_cpf_cnpj"          => $accountDetail->favored_cpf_cnpj,
                    "type_description"          => $accountDetail->bank_account_type,
                    "bank_description"          => $accountDetail->bank_description,
                    "account_agency"            => $accountDetail->bank_agency,
                    "account_number"            => $accountDetail->bank_account
                ));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao cadastrar a conta"));
            }
        }
        $favoredAccountDetail = FavoredAccountDetail::where('favored_account_id','=',$request->favoredId)->where('bank_id','=',$request->favored_bank_id)->where('bank_agency','=',$request->bank_agency)->where('bank_account','=',$request->bank_account)->first();
        $favoredAccountDetail->description          = $request->favored_description;
        $favoredAccountDetail->bank_account_type_id = $request->bank_account_type_id;
        $favoredAccountDetail->deleted_at           = null;
        if($favoredAccountDetail->save()){
            $accountDetail = $this->getFavoredAccountDetail($favoredAccountDetail->id);
            return response()->json(array(
                "success"                   => "Conta atualizada com sucesso",
                "favored_id"                => $favoredAccountDetail->favored_account_id,
                "favored_account_detail_id" => $favoredAccountDetail->id,
                "favored_name"              => $accountDetail->favored_name,
                "favored_cpf_cnpj"          => $accountDetail->favored_cpf_cnpj,
                "type_description"          => $accountDetail->bank_account_type,
                "bank_description"          => $accountDetail->bank_description,
                "account_agency"            => $accountDetail->bank_agency,
                "account_number"            => $accountDetail->bank_account
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o favorecido"));
        }
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [22, 188, 269];
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
        } else {
            $register_master_id = $request->header('registerId');
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


        $favoredAccount = app('App\Http\Controllers\FavoredController')->returnFavoredAccount($request->favored_cpf_cnpj, $request->favored_name, $register_master_id);

        if(!isset($favoredAccount->success)){
            if(isset($favoredAccount->error)){
                return response()->json(array("error" => $favoredAccount->error));
            } else {
                return response()->json(array("error" => 'Ocorreu uma falha ao verificar o favorecido, por favor tente novamente em alguns minutos'));
            }
        }

      //  if( FavoredAccountDetail::where('favored_account_id','=',$favoredAccount->success->id)->where('bank_id','=',$request->favored_bank_id)->where('bank_agency','=',$request->bank_agency)->where('bank_account','=',$request->bank_account)->count() == 0 ){
            if($favoredAccountDetail = FavoredAccountDetail::Create([
                'description'          => $request->description,
                'favored_account_id'   => $favoredAccount->success->id,
                'bank_id'              => $request->favored_bank_id,
                'bank_agency'          => $request->bank_agency,
                'bank_account'         => $request->bank_account,
                'bank_account_type_id' => $request->bank_account_type_id,
                'main'                 => $request->favored_main,
                'created_at'           => \Carbon\Carbon::now(),
                'uuid'                 => Str::orderedUuid(),
            ])){
                return response()->json(array("success" => "Favorecido cadastrado com sucesso", "favored_account_detail_id" =>  $favoredAccountDetail->id));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao cadastrar o favorecido"));
            }
     //   }


     /*   $favoredAccountDetail = FavoredAccountDetail::where('favored_account_id','=',$favoredAccount->success->id)->where('bank_id','=',$request->favored_bank_id)->where('bank_agency','=',$request->bank_agency)->where('bank_account','=',$request->bank_account)->first();
        $favoredAccountDetail->description          = $request->favored_description;
        $favoredAccountDetail->bank_account_type_id = $request->bank_account_type_id;
        $favoredAccountDetail->deleted_at           = null;
        if($favoredAccountDetail->save()){
            return response()->json(array("success" => "Favorecido atualizado com sucesso", "favored_account_detail_id" =>  $favoredAccountDetail->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o favorecido"));
        }   */
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [190, 271];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        //validar se conta do favorecido pertence ao registro
        $favoredAccountDetail             = FavoredAccountDetail::where('id','=',$request->id)->first();
        $favoredAccountDetail->deleted_at = \Carbon\Carbon::now();
        if($favoredAccountDetail->save()){
            return response()->json(array("success" => "Conta do favorecido excluída com sucessso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir a conta do favorecido"));
        }
    }
}
