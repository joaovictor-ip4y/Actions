<?php

namespace App\Http\Controllers;

use App\Models\PjBranchOfficeRelated;
use App\Models\RegisterMaster;
use App\Models\RegisterDataPj;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PjBranchOfficeRelatedController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if ( RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->count() > 0 ){
            $registerDataPj                             = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
            $pjBranchOfficeRelated                      = new PjBranchOfficeRelated();
            $pjBranchOfficeRelated->register_data_pj_id = $registerDataPj->id;
            $pjBranchOfficeRelated->onlyActive          = $request->onlyActive;
            return response()->json($pjBranchOfficeRelated->getPjBranchOfficeRelated());
        } else {
            return response()->json();
        }
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster            = RegisterMaster::where('id', '=', $request->register_master_id)->first();
        $registerDataPj            = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
        $branchRegisterMasterData  = app('App\Http\Controllers\RegisterController')->returnRegister($request->branch_cpf_cnpj, $request->branch_name, $request->header('masterId'));

        if($branchRegisterMasterData->status == 0 ){
            return response()->json(array("error" => $branchRegisterMasterData->error));
        } else {
            //Verifica se filial ou coligada já está associada ao cadastro
            if( PjBranchOfficeRelated::where('register_data_pj_id', '=', $registerDataPj->id)->where('register_master_id', '=', $branchRegisterMasterData->success->id)->whereNull('deleted_at')->count() == 0){
                if($pjBranchOfficeRelated = PjBranchOfficeRelated::Create([
                    'register_master_id'  => $branchRegisterMasterData->success->id,
                    'register_data_pj_id' => $registerDataPj->id,
                    'type_id'             => $request->branch_type_id,
                    'created_at'          => \Carbon\Carbon::now()
                ])){
                    return response()->json(array(
                        "success"                    => "Filial / Coligada cadastrada com sucesso",
                        "register_branch_related_id" => $pjBranchOfficeRelated->id
                    ));
                } else {
                    return response()->json(array("error" => "Ocorreu um erro ao cadastrar a filial / coligada"));
                }
            } else {
                return response()->json(array("error" => "Filial / Coligada já associada ao cadastro"));
            }
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDataPj                  = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
        $pjBranchOfficeRelated           = PjBranchOfficeRelated::where('register_data_pj_id','=',$registerDataPj->id)->where('id','=',$request->register_branch_related_id)->first();
        $pjBranchOfficeRelated->type_id  = $request->branch_type_id;
        if( $pjBranchOfficeRelated->save() ){
            return response()->json(array("success" => "Filial / Coligada atualizado com sucesso", "register_branch_related_id" =>  $pjBranchOfficeRelated->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a empresa Filial / Coligada"));
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerDataPj  = RegisterDataPj::where('register_master_id', '=',  $request->register_master_id)->first();
        $pjBranchOfficeRelated             = PjBranchOfficeRelated::where('register_data_pj_id','=',$registerDataPj->id)->where('id','=',$request->register_branch_related_id)->first();
        $pjBranchOfficeRelated->deleted_at = \Carbon\Carbon::now();
        if( $pjBranchOfficeRelated->save() ){
            return response()->json(array("success" => "Filial / Coligada excluída com sucesso", "register_branch_related_id" =>  $pjBranchOfficeRelated->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir a empresa Filial / Coligada"));
        }
    }

}
