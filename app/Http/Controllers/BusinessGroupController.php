<?php

namespace App\Http\Controllers;

use App\Models\BusinessGroup;
use App\Models\Master;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessGroupController extends Controller
{
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

        $businessGroup              = new BusinessGroup();
        $businessGroup->id          = $request->id;
        $businessGroup->uuid        = $request->uuid;
        $businessGroup->master_id   = $request->master_id;
        $businessGroup->description = $request->description;
        $businessGroup->onlyActive  = $request->onlyActive;
        return response()->json($businessGroup->get());
    }

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

        if (BusinessGroup::where('description', '=' ,$request->description)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Descrição do grupo empresarial informada já está sendo utilizada"));
        }

        if (BusinessGroup::create([
            'master_id'     => $request->master_id,
            'description'   => $request->description,
            'uuid'          => Str::orderedUuid(),
            'created_at'    => \Carbon\Carbon::now()
        ])) {
            return response()->json(array("success" => "Grupo Empresarial criado com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao criar o grupo empresarial"));
    }

    protected function update(Request $request)
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
        // -------------- Finish Check Account Verification -------------- //
        if (!$businessGroup = BusinessGroup::where('id', '=' ,$request->id)->where('uuid', '=', $request->uuid)->where('master_id', '=', $request->master_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o grupo empresarial"));
        }

        if ($businessGroup->description == $request->description) {
            return response()->json(array("error" => "Descrição do grupo empresarial informada já está sendo utilizada"));
        }

        $businessGroup->description = $request->description;

        if ($businessGroup->save()) {
            return response()->json(array("success" => "Grupo Empresarial atualizado com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu um erro ao alterar o grupo empresarial"));

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

        // -------------- Finish Check Account Verification -------------- //
        if (!$businessGroup = BusinessGroup::where('id', '=' ,$request->id)->where('uuid', '=', $request->uuid)->where('master_id', '=', $request->master_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o grupo empresarial"));
        }

        $businessGroup->deleted_at = \Carbon\Carbon::now();

        if ($businessGroup->save()) {
            return response()->json(array("success" => "Grupo Empresarial excluído com sucesso"));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao excluir o grupo empresarial"));

    }
}
