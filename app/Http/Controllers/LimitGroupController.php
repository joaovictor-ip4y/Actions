<?php

namespace App\Http\Controllers;

use App\Models\LimitGroup;
use App\Models\Limit;
use App\Models\LmtGrpItm;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LimitGroupController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [159];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $limitGroup            = new LimitGroup();
        $limitGroup->master_id = $checkAccount->master_id;
        return response()->json($limitGroup->getLimitGroup());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [160];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        //Create Limit Group
        if($limitGroup = LimitGroup::create([
            'description'        => $request->description,
            'master_id'          => $checkAccount->master_id,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            //Link Limits in Limit Group
            $limits = Limit::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($limits as $limit){
                LmtGrpItm::create([
                    'limit_group_id'     => $limitGroup->id,
                    'limit_id'           => $limit->id,
                    'default_value'      => $limit->default_value,
                    'default_percentage' => $limit->default_percentage,
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            return response()->json(array("success" => "Grupo de Limite Cadastrado com Sucesso", "limit_group_id" => $limitGroup->id));
        } else {
            return response()->json(array("error" => "Ocorreu um Erro ao Cadastrar o Grupo de Limite"));
        }
    }

    protected function updateLimitGroup(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [162];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        if($limit_group = LimitGroup::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first()){
            $limit_group->description = $request->description;
            if($limit_group->save()){
                return response()->json(array("success" => "Grupo de limite alterado com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar o grupo de limite, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Grupo de Limite Não Atualizado"));
        }
    }

}
