<?php

namespace App\Http\Controllers;

use App\Models\AccountingClassificationFirstLevel;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccountingClassificationFirstLevelController extends Controller
{
    protected function show(Request $request) 
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id = [4];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $accountingClassificationFirstLevel             = new AccountingClassificationFirstLevel();
        $accountingClassificationFirstLevel->id         = $request->id;
        $accountingClassificationFirstLevel->account_id = $checkAccount->account_id;
        $accountingClassificationFirstLevel->onlyActive = $request->onlyActive;
        return response()->json($accountingClassificationFirstLevel->get());
    }

    protected function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number'               => ['required', 'string'],
            'description'          => ['required', 'string']
        ],[
            'number.required'      => 'É obrigatório informar o number',
            'description.required' => 'É obrigatório informar o description'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if($accountingClassificationFirstLevel = AccountingClassificationFirstLevel::create([
            'uuid'            => Str::orderedUuid(),
            'account_id'      => $checkAccount->account_id,
            'number'          => $request->number,
            'description'     => $request->description,
            'created_at'      => \Carbon\Carbon::now()
        ])){
            return response()->json(["success" => "Classificação contábil de primeiro nível incluída com sucesso.", "data" => $accountingClassificationFirstLevel]);
        } 
        return response()->json(["error" => "Poxa, não foi possível incluir a classificação contábil de primeiro nível no momento, por favor tente novamente mais tarde."]);
    }

    protected function update(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id = [4];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string'],
            'number'               => ['required', 'string'],
            'description'          => ['required', 'string']
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'number.required'      => 'É obrigatório informar o number',
            'description.required' => 'É obrigatório informar o description'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        if ( !$accountingClassificationFirstLevel = AccountingClassificationFirstLevel::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Classificação contábil de primeiro nível não localizada, por favor verifique e tente novamente."]);
        }

        
        $accountingClassificationFirstLevel->description = $request->description;
        $accountingClassificationFirstLevel->number = $request->number;

        if ($accountingClassificationFirstLevel->save()) {
            return response()->json(["success" => "Classificação contábil de primeiro nível atualizada com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar a classificação contábil de primeiro nível."]);
    }

    protected function destroy(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id = [4];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //
         
        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string']
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        if ( !$accountingClassificationFirstLevel = AccountingClassificationFirstLevel::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Classificação contábil de primeiro nível não localizada, por favor verifique e tente novamente."]);
        }

        $accountingClassificationFirstLevel->deleted_at = \Carbon\Carbon::now();

        if ($accountingClassificationFirstLevel->save()) {
            return response()->json(["success" => "Classificação contábil de primeiro nível excluída com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao excluir a classificação contábil de primeiro nível."]);
    }
}
