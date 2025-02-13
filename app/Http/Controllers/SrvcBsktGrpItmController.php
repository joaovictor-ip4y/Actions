<?php

namespace App\Http\Controllers;

use App\Models\SrvcBsktGrpItm;
use App\Models\Account;
use App\Models\AccntTxVlItms;
use App\Models\RegisterMaster;
use App\Models\RgstrTxVlItm;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class SrvcBsktGrpItmController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [151];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $srvcBsktGrpItm = new SrvcBsktGrpItm();
        $srvcBsktGrpItm->service_basket_id = $request->service_basket_id;
        $srvcBsktGrpItm->onlyActive        = $request->onlyActive;
        $srvcBsktGrpItm->master_id         = $checkAccount->master_id;
        return response()->json($srvcBsktGrpItm->getSrvcBsktGrpItm());
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [154];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $request->value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
        
        if( $request->percentage < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar porcentagem negativa."));
        }
        
        $srvcBsktGrpItm                     = SrvcBsktGrpItm::where('id','=',$request->service_basket_item_id)->where('service_basket_id',$request->service_basket_id)->first();
        $srvcBsktGrpItm->default_value      = $request->value;
        $srvcBsktGrpItm->default_percentage = $request->percentage;
        if( $srvcBsktGrpItm->save() ){
            if($request->update_registers == 1){
                $registerMasters = RegisterMaster::where('srvc_bskt_id','=',$request->service_basket_id)->get();
                foreach($registerMasters as $registerMaster){
                    $tax = RgstrTxVlItm::where('rgstr_id','=',$registerMaster->id)->where('taxe_id','=',$srvcBsktGrpItm->tax_id)->first();
                    $tax->value      = $request->value;
                    $tax->percentage = $request->percentage;
                    $tax->save();
                }
            }
            if($request->update_accounts == 1){
                $accounts = Account::where('srvc_bskt_id','=',$request->service_basket_id)->get();
                foreach($accounts as $account){
                    $tax = AccntTxVlItms::where('accnt_id','=',$account->id)->where('tax_id','=',$srvcBsktGrpItm->tax_id)->first();
                    $tax->value      = $request->value;
                    $tax->percentage = $request->percentage;
                    $tax->save();
                }
            }
            return response()->json(array("success" => "Tarifa da cesta de serviço atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a tarifa da cesta de serviço"));
        }
    }
}
