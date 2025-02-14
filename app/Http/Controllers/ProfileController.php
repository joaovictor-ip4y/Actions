<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [164];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $profile            = new Profile();
        $profile->master_id = $checkAccount->master_id;
        return response()->json($profile->getProfile());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [163];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($profile = Profile::create([
            'description'       => $request->description,
            'service_basket_id' => $request->service_basket_id,
            'limit_group_id'    => $request->limit_group_id,
            'master_id'         => $checkAccount->master_id,
            'created_at'        => \Carbon\Carbon::now()
        ])){
            return response()->json(array("success" => "Perfil Cadastrado com Sucesso", "profile_id" => $profile->id));
        } else {
            return response()->json(array("error" => "Ocorreu um Erro ao Cadastrar o perfil"));
        }
    }
}
