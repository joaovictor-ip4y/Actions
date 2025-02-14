<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use App\Models\Action;
use App\Models\ServiceBasket;
use App\Models\SrvcBsktGrpItm;
use App\Models\RegisterMaster;
use App\Models\RgstrTxVlItm;
use App\Models\Account;
use App\Models\AccntTxVlItms;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [147];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $tax = new Tax();
        $tax->master_id = $checkAccount->master_id;
        return response()->json($tax->getTax());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [148];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( Tax::where('master_id','=',$checkAccount->master_id)->where('action_id','=',$request->action_id)->whereNull('deleted_at')->count() > 0 ){
            return response()->json(array("error" => "Já foi cadastrada uma tarifa para cobrar em '".Action::where('id','=',$request->action_id)->first()->description."'. Escolha outra forma de cobrança, ou, crie cestas de serviço para definir valores diferentes para a mesma tarifa."  ));
        }

        if( $request->default_value < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar valor negativo."));
        }
        
        if( $request->default_percentage < 0 ) {
            return response()->json(array("error" => "Não é permitido utilizar porcentagem negativa."));
        }

        if($tax = Tax::create([
            'description'        => $request->description,
            'default_value'      => $request->default_value,
            'default_percentage' => $request->default_percentage,
            'action_id'          => $request->action_id,
            'master_id'          => $checkAccount->master_id,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            $srvcBskts = ServiceBasket::where('master_id','=',$checkAccount->master_id)->get();
            foreach($srvcBskts as $srvcBskt){
                SrvcBsktGrpItm::create([
                    'service_basket_id'  => $srvcBskt->id,
                    'tax_id'             => $tax->id,
                    'default_value'      => $tax->default_value,
                    'default_percentage' => $tax->default_percentage,
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            $rgstrsMstrs = RegisterMaster::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($rgstrsMstrs as $rgstrMstr){
                $srvcBsktGrpItm = SrvcBsktGrpItm::where('service_basket_id','=',$rgstrMstr->srvc_bskt_id)->where('tax_id','=',$tax->id)->first();
                $srvcBsktGrpItmId = null;
                if ($srvcBsktGrpItm != ''){
                    $srvcBsktGrpItmId = $srvcBsktGrpItm->id;
                }
                RgstrTxVlItm::create([
                    'rgstr_id'             => $rgstrMstr->id,
                    'srvc_bskt_grp_itm_id' => $srvcBsktGrpItmId,
                    'taxe_id'              => $tax->id,
                    'value'                => $tax->default_value,
                    'percentage'           => $tax->default_percentage,
                    'created_at'           => \Carbon\Carbon::now()
                ]);
            }
            $accounts = Account::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($accounts as $account){
                $srvcBsktGrpItm = SrvcBsktGrpItm::where('service_basket_id','=',$account->srvc_bskt_id)->where('tax_id','=',$tax->id)->first();
                $srvcBsktGrpItmId = null;
                if ($srvcBsktGrpItm != ''){
                    $srvcBsktGrpItmId = $srvcBsktGrpItm->id;
                }
                AccntTxVlItms::create([
                    'accnt_id'             => $account->id,
                    'srvc_bskt_grp_itm_id' => $srvcBsktGrpItmId,
                    'tax_id'               => $tax->id,
                    'value'                => $tax->default_value,
                    'percentage'           => $tax->default_percentage,
                    'created_at'           => \Carbon\Carbon::now()                    
                ]);
            }
            return response()->json(array("success" => "Tarifa Cadastrada com Sucesso", "id" => $tax->id));
        } else {
            return response()->json(array("error" => "Ocorreu um Erro ao Cadastrar a Tarifa"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [150];
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

        $tax                     = Tax::where('master_id','=',$checkAccount->master_id)->where('id','=',$request->id)->first();
        $tax->default_value      = $request->default_value;
        $tax->default_percentage = $request->default_percentage;
        $tax->action_id          = $request->action_id;
        if( $tax->save() ){
            if($request->all_registers == 1){
                $taxUpdateds = RgstrTxVlItm::where('taxe_id','=',$tax->id)->get();
                foreach($taxUpdateds as $taxUpdated){
                    $taxUpdate             = RgstrTxVlItm::where('id','=',$taxUpdated->id)->first();
                    $taxUpdate->value      = $tax->default_value;
                    $taxUpdate->percentage = $tax->default_percentage;
                    $taxUpdate->save();
                }
            }
            if($request->all_accounts == 1){
                $taxUpdateds = AccntTxVlItms::where('tax_id','=',$tax->id)->get();
                foreach($taxUpdateds as $taxUpdated){
                    $taxUpdate             = AccntTxVlItms::where('id','=',$taxUpdated->id)->first();
                    $taxUpdate->value      = $tax->default_value;
                    $taxUpdate->percentage = $tax->default_percentage;
                    $taxUpdate->save();
                }
            }
            if($request->all_service_basket == 1){
                $taxUpdateds = SrvcBsktGrpItm::where('tax_id','=',$tax->id)->get();
                foreach($taxUpdateds as $taxUpdated){
                    $taxUpdate                     = SrvcBsktGrpItm::where('id','=',$taxUpdated->id)->first();
                    $taxUpdate->default_value      = $tax->default_value;
                    $taxUpdate->default_percentage = $tax->default_percentage;
                    $taxUpdate->save();
                }
            }
            return response()->json(array("success" => "Tarifa atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a tarifa"));
        }
    }
}
