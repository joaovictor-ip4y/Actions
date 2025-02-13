<?php

namespace App\Http\Controllers;

use App\Models\Limit;
use App\Models\Action;
use App\Models\LimitGroup;
use App\Models\LmtGrpItm;
use App\Models\RegisterMaster;
use App\Models\RgstrLmtVlItm;
use App\Models\Account;
use App\Models\AccntLmtVlItm;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class LimitController extends Controller
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

        $limit = new Limit();
        $limit->master_id = $checkAccount->master_id;
        $limit->onlyActive = $request->onlyActive;
        return response()->json($limit->getLimit());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [156];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $request->default_value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
        
        if( $request->default_percentage < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar porcentagem negativa."));
        }

        if( Limit::where('master_id','=',$checkAccount->master_id)->where('action_id','=',$request->action_id)->where('limit_type_id','=',$request->type_id)->whereNull('deleted_at')->count() > 0 ){
            return response()->json(array("error" => "Já foi cadastrado um limite para checar em '".Action::where('id','=',$request->action_id)->first()->description."' com o tipo escolhido. Escolha outra forma de checagem/tipo, ou, crie grupos de limite para definir valores diferentes para o mesmo limite."  ));
        }

        if($limit = Limit::create([
            'description'        => $request->description,
            'default_value'      => $request->default_value,
            'default_percentage' => $request->default_percentage,
            'limit_type_id'      => $request->type_id,
            'action_id'          => $request->action_id,
            'master_id'          => $checkAccount->master_id,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            $limitGroups = LimitGroup::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($limitGroups as $limitGroup){
                LmtGrpItm::create([
                    'limit_group_id'     => $limitGroup->id,
                    'limit_id'           => $limit->id,
                    'default_value'      => $limit->default_value,
                    'default_percentage' => $limit->default_percentage,
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            $rgstrsMstrs = RegisterMaster::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($rgstrsMstrs as $rgstrMstr){
                $lmtGrpItm = LmtGrpItm::where('limit_group_id','=',$rgstrMstr->limit_group_id)->where('limit_id','=',$limit->id)->first();
                $lmtGrpItmId = null;
                if ($lmtGrpItm != ''){
                    $lmtGrpItmId = $lmtGrpItm->id;
                }
                RgstrLmtVlItm::create([
                    'rgstr_id'       => $rgstrMstr->id,
                    'lmt_grp_itm_id' => $lmtGrpItmId,
                    'limit_id'       => $limit->id,
                    'value'          => $limit->default_value,
                    'percentage'     => $limit->default_percentage,
                    'created_at'     => \Carbon\Carbon::now()
                ]);
            }
            $accounts = Account::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($accounts as $account){
                $lmtGrpItm = LmtGrpItm::where('limit_group_id','=',$account->limit_group_id)->where('limit_id','=',$limit->id)->first();
                $lmtGrpItmId = null;
                if ($lmtGrpItm != ''){
                    $lmtGrpItmId = $lmtGrpItm->id;
                }
                AccntLmtVlItm::create([
                    'accnt_id'       => $account->id,
                    'lmt_grp_itm_id' => $lmtGrpItmId,
                    'limit_id'       => $limit->id,
                    'value'          => $limit->default_value,
                    'percentage'     => $limit->default_percentage,
                    'created_at'     => \Carbon\Carbon::now()
                ]);
            }
            return response()->json(array("success" => "Limite Cadastrado com Sucesso", "id" => $limit->id));
        } else {
            return response()->json(array("error" => "Ocorreu um Erro ao Cadastrar o Limite"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [158];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $request->default_value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
        
        if( $request->default_percentage < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar porcentagem negativa."));
        }
        
        $limit                     = Limit::where('master_id','=',$checkAccount->master_id )->where('id','=',$request->id)->first();
        $limit->default_value      = $request->default_value;
        $limit->default_percentage = $request->default_percentage;
        $limit->action_id          = $request->action_id;
        $limit->limit_type_id      = $request->type_id;
        if( $limit->save() ){
            return response()->json(array("success" => "Limite atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o limite"));
        }
    }

}
