<?php

namespace App\Http\Controllers;

use App\Models\ServiceBasket;
use App\Models\SrvcBsktGrpItm;
use App\Models\Tax;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class ServiceBasketController extends Controller
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

        $serviceBasket            =  new ServiceBasket();
        $serviceBasket->master_id =  $checkAccount->master_id;
        return response()->json($serviceBasket->getServiceBasket());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [152];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        //Create Service Basket
        if($serviceBasket = ServiceBasket::create([
            'description'        => $request->description,
            'master_id'          => $checkAccount->master_id,
            'created_at'         => \Carbon\Carbon::now()
        ])){
            //Link Tax in Service Basket
            $taxes = Tax::where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
            foreach($taxes as $tax){
                SrvcBsktGrpItm::create([
                    'service_basket_id'  => $serviceBasket->id,
                    'tax_id'             => $tax->id,
                    'default_value'      => $tax->default_value,
                    'default_percentage' => $tax->default_percentage,
                    'created_at'         => \Carbon\Carbon::now()
                ]);
            }
            return response()->json(array("success" => "Cesta de Serviço Cadastrada com Sucesso", "service_basket_id" => $serviceBasket->id));
        } else {
            return response()->json(array("error" => "Ocorreu um Erro ao Cadastrar a Cesta de Serviço"));
        }
    }

    protected function updateServiceBasket(Request $request)
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

        if($service_basket = ServiceBasket::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first()){
            $service_basket->description = $request->description;
            if($service_basket->save()){
                return response()->json(array("success" => "Cesta de serviço alterada com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu uma falha ao alterar a cesta de serviço, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Cesta de Serviço não localizada"));
        }
    }

}
