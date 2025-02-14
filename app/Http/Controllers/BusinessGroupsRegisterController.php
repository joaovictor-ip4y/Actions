<?php

namespace App\Http\Controllers;

use App\Models\BusinessGroup;
use App\Models\BusinessGroupsRegister;
use App\Models\RegisterMaster;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BusinessGroupsRegisterController extends Controller
{
    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'business_group_id'  => 'required',
            'register_master_id' => 'required',
        ],[
            'business_group_id.required'    => 'O parâmetro Grupo empresarial é obrigatório',
            'register_master_id.required'   => 'O parâmetro Cadastro é obrigatório',
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if (!BusinessGroup::where('id', '=', $request->business_group_id)->first()) {
            return response()->json(array("error" => "Grupo empresarial não localizado, reveja os dados informados e tente novamente"));
        }

        if (!RegisterMaster::where('id', '=', $request->register_master_id)->first()) {
            return response()->json(array("error" =>  "Cadastro não localizado, reveja os dados informados e tente novamente"));
        }

        if (BusinessGroupsRegister::where('business_group_id', '=', $request->business_group_id)->where('register_master_id', '=', $request->register_master_id)->first()) {
            return response()->json(array("error" => "Cadastro já vinculado ao grupo empresarial"));
        }

        if (BusinessGroupsRegister::create([
            'uuid'                  => Str::orderedUuid(),
            'business_group_id'     => $request->business_group_id,
            'register_master_id'    => $request->register_master_id,
            'created_at'            => \Carbon\Carbon::now(),
        ])) {
            return response()->json(array("success" =>  "Cadastro vinculado ao grupo empresarial com sucesso"));
        }
        return response()->json(array("error" => "Não foi possível vincular cadastro ao grupo empresarial"));
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $businessGroupsRegister = new BusinessGroupsRegister();
        $businessGroupsRegister->id                 = $request->id;
        $businessGroupsRegister->business_group_id  = $request->business_group_id;
        $businessGroupsRegister->register_master_id = $request->register_master_id;
        $businessGroupsRegister->master_id          = $request->master_id;
        $businessGroupsRegister->register_id        = $request->register_id;
        $businessGroupsRegister->onlyActive         = $request->onlyActive;
        return response()->json($businessGroupsRegister->get());
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$businessGroupsRegister = BusinessGroupsRegister::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o vínculo de cadastro"));
        }

        $businessGroupsRegister->deleted_at = \Carbon\Carbon::now();

        if ($businessGroupsRegister->save()) {
            return response()->json(array("success" => "Vínculo de cadastro removido com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao remover o vínculo de cadastro"));
    }
}
