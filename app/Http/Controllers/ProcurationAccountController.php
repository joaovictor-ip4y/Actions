<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Procuration;
use App\Models\ProcurationAccount;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class ProcurationAccountController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [66];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(Procuration::where('id','=',$request->procuration_id)->first()->released_at  == null){
            if($Account = Account::where('id','=',$request->account_id)->where('master_id','=',$request->header('masterId'))->first()){
                if(ProcurationAccount::where('account_id','=',$Account->id)->where('procuration_id','=', $request->procuration_id)->whereNull('deleted_at')->first()){
                    return response()->json(array("error"=>"Conta já vínculada a procuração"));
                }else{
                    ProcurationAccount::create(
                    [
                        'procuration_id'    => $request->procuration_id,
                        'account_id'        => $Account->id,
                        'created_at'        => \Carbon\Carbon::now(),
                    ]);
                    return response()->json(array("success"=>"Conta para procuração vinculada com sucesso!"));
                }
            }else{
                return response()->json(array("error"=>"Conta não localizada"));
            }
        }else{
            return response()->json(array("error"=>"Não é possível acrescentar conta a procuração liberada"));
        }
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [58];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $ProcurationAccount = new ProcurationAccount();
        $ProcurationAccount->id                 = $request->id;
        $ProcurationAccount->procuration_id     = $request->procuration_id;
        $ProcurationAccount->account_id         = $request->account_id;
        $ProcurationAccount->onlyActive         = 1;

        return response()->json($ProcurationAccount->getProcurationAccount());
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [65];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($ProcurationAccount = ProcurationAccount::where('id','=',$request->id)->first()){
            if($procuration = Procuration::where('id','=',$ProcurationAccount->procuration_id)->first()){
                if($procuration->released_at == null){
                    $ProcurationAccount->deleted_at = \Carbon\Carbon::now();
                    if($ProcurationAccount->save()){
                        return response()->json(array("success" => "conta para procuração excluída com sucesso"));
                    }else{
                        return response()->json(array("error" => "Erro ao excluir a conta para procuração"));
                    }
                } else {
                    return response()->json(array("error" => "Não é possível excluir conta para procuração liberada"));
                }
            }else{
                return response()->json(array("error" => "Procuração não encontrada"));
            }
        }else{
            return response()->json(array("error" => "Conta para procuração não encontrada"));
        }
    }
}
