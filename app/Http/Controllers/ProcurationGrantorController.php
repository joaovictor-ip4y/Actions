<?php

namespace App\Http\Controllers;

use App\Models\Procuration;
use App\Models\ProcurationGrantor;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class ProcurationGrantorController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [62];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(Procuration::where('id','=',$request->procuration_id)->first()->released_at == null){
            if($getCpfMaster = ProcurationGrantor::getCpfMaster($request->cpf_cnpj, $checkAccount->master_id)){
                $registerMasterId = $getCpfMaster->id;
                if(ProcurationGrantor::where('procuration_id','=',$request->procuration_id)->where('grantor_id','=',$registerMasterId)->whereNull('deleted_at')->first()){
                    return response()->json(array("error" => "Outorgante já vínculado a procuração"));
                }else{
                    if(ProcurationGrantor::create(
                            [
                                'procuration_id' => $request->procuration_id,
                                'grantor_id'     => $registerMasterId,
                                'created_at'     => \Carbon\Carbon::now(),
                            ]
                        )){
                        return response()->json(array("success" => "Outorgante para procuração cadastrado com sucesso"));
                    }else{
                        return response()->json(array("error" => "Erro ao inserir o Outorgante"));
                    }
                }
            }else{
                return response()->json(array("error" => "CPF/CNPJ não possui cadastro, realize o cadastro do outorgante para continuar"));
            }
        }else{
            return response()->json(array("error" => "Não é possível acrescentar outorgante a procuração liberada"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [61];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($ProcurationGrantor = ProcurationGrantor::where('id','=',$request->id)->first()){
            if($procuration = Procuration::where('id','=',$ProcurationGrantor->procuration_id)->first()){
                if($procuration->released_at == null){
                    $ProcurationGrantor->deleted_at = \Carbon\Carbon::now();
                    if($ProcurationGrantor->save()){
                        return response()->json(array("success" => "Outorgante excluido da procuração com sucesso"));
                    }else{
                        return response()->json(array("error" => "Erro ao excluir Outorgante"));
                    }
                } else {
                    return response()->json(array("error" => "Não é possível excluir outorgante de procuração liberada"));
                }
            }else{
                return response()->json(array("error" => "Procuração não encontrada"));
            }
        }else{
            return response()->json(array("error" => "Outorgante não encontrado"));
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
        
        $ProcurationGrantor                   = new ProcurationGrantor();
        $ProcurationGrantor->id               = $request->id;
        $ProcurationGrantor->procuration_id   = $request->procuration_id;
        $ProcurationGrantor->grantor_id       = $request->grantor_id;
        $ProcurationGrantor->onlyActive       = 1;
        return response()->json($ProcurationGrantor->getProcurationGrantor());
    }
}
