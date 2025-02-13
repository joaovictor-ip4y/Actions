<?php

namespace App\Http\Controllers;

use App\Models\LimitGroup;
use App\Models\Master;
use App\Models\ServiceBasket;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [1];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $master = new Master();
        return response()->json($master->getMaster());
    }
    
    protected function updateDefaultServiceBasket(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [154];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->status == 1){
            $master = Master::where('id','=', $checkAccount->master_id)->first();   
            $master->dflt_srvc_bkst_id = $request->service_basket_id;
            $master->save();
            ServiceBasket::removeDefaultServiceBasket($checkAccount->master_id);
            $ServiceBasket = ServiceBasket::where('master_id','=', $checkAccount->master_id)->where('id','=',$request->service_basket_id)->first();
            $ServiceBasket->default = 1;
            $ServiceBasket->save();
        }
        return response()->json(["success" => "Cesta de serviço padrão atualizada com sucesso"]);
    }

    protected function updateDefaultManagerServiceBasket(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [154];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->status == 1){
            $master = Master::where('id','=', $checkAccount->master_id)->first();   
            $master->dflt_mngr_srvc_bkst_id  = $request->service_basket_id;
            $master->save();
            ServiceBasket::removeDefaultManagerServiceBasket($checkAccount->master_id);
            $ServiceBasket = ServiceBasket::where('master_id','=', $checkAccount->master_id)->where('id','=',$request->service_basket_id)->first();
            $ServiceBasket->default_manager = 1;
            $ServiceBasket->save();
        }
        return response()->json(["success" => "Cesta de serviço padrão com gerente atualizada com sucesso"]);
    }

    protected function updateDefaultLimitGroup(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [162];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->status == 1){
            $master = Master::where('id','=', $checkAccount->master_id)->first();   
            $master->dflt_lmt_grp_id = $request->limit_group_id;         
            $master->save();
            LimitGroup::removeDefaultLimitGroup($checkAccount->master_id);
            $LimitGroup = LimitGroup::where('master_id','=', $checkAccount->master_id)->where('id','=',$request->limit_group_id)->first();
            $LimitGroup->default = 1;
            $LimitGroup->save();
        }
        return response()->json(["success" => "Grupo de limite padrão atualizado com sucesso"]);
    }

    protected function updateDefaultManagerLimitGroup(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [162];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->status == 1){
            $master = Master::where('id','=', $checkAccount->master_id)->first();   
            $master->dflt_mngr_lmt_grp_id = $request->limit_group_id;
            $master->save();
            LimitGroup::removeDefaultManagerLimitGroup($checkAccount->master_id);
            $LimitGroup = LimitGroup::where('master_id','=', $checkAccount->master_id)->where('id','=',$request->limit_group_id)->first();
            $LimitGroup->default_manager = 1;
            $LimitGroup->save();
        }
        return response()->json(["success" => "Grupo de limite padrão com gerente atualizado com sucesso"]);
    }

}
