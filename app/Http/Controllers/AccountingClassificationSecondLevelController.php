<?php

namespace App\Http\Controllers;

use App\Models\AccountingClassificationSecondLevel;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccountingClassificationSecondLevelController extends Controller
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


        $accountingClassificationSecondLevel                 = new AccountingClassificationSecondLevel();
        $accountingClassificationSecondLevel->id             = $request->id;
        $accountingClassificationSecondLevel->first_level_id = $request->first_level_id;
        $accountingClassificationSecondLevel->onlyActive     = $request->onlyActive;
        return response()->json($accountingClassificationSecondLevel->get());
    }

    protected function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_level_id'           => ['required', 'integer'],
            'number'                   => ['required', 'string'],
            'description'              => ['required', 'string']
        ],[
            'first_level_id.required'  => 'É obrigatório informar o first_level_id',
            'number.required'          => 'É obrigatório informar o number',
            'description.required'     => 'É obrigatório informar o description'
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


        if($accountingClassificationSecondLevel = AccountingClassificationSecondLevel::create([
            'uuid'            => Str::orderedUuid(),
            'first_level_id'  => $request->first_level_id,
            'number'          => $request->number,
            'description'     => $request->description,
            'created_at'      => \Carbon\Carbon::now()
        ])){
            return response()->json(["success" => "Classificação contábil de segundo nível incluída com sucesso.", "data" => $accountingClassificationSecondLevel]);
        } 
        return response()->json(["error" => "Poxa, não foi possível incluir a classificação contábil de segundo nível no momento, por favor tente novamente mais tarde."]);
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
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'first_level_id'           => ['required', 'integer'],
            'number'                   => ['required', 'string'],
            'description'              => ['required', 'string']
        ],[
            'id.required'              => 'É obrigatório informar o id',
            'uuid.required'            => 'É obrigatório informar o uuid',
            'first_level_id.required'  => 'É obrigatório informar o first_level_id',
            'number.required'          => 'É obrigatório informar o number',
            'description.required'     => 'É obrigatório informar o description'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        if ( !$accountingClassificationSecondLevel = AccountingClassificationSecondLevel::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Classificação contábil de segundo nível não localizada, por favor verifique e tente novamente."]);
        }

        
        $accountingClassificationSecondLevel->description    = $request->description;
        $accountingClassificationSecondLevel->number         = $request->number;
        $accountingClassificationSecondLevel->first_level_id = $request->first_level_id;

        if ($accountingClassificationSecondLevel->save()) {
            return response()->json(["success" => "Classificação contábil de segundo nível atualizada com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar a classificação contábil de segundo nível."]);
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


        if ( !$accountingClassificationSecondLevel = AccountingClassificationSecondLevel::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Classificação contábil de segundo nível não localizada, por favor verifique e tente novamente."]);
        }

        $accountingClassificationSecondLevel->deleted_at = \Carbon\Carbon::now();

        if ($accountingClassificationSecondLevel->save()) {
            return response()->json(["success" => "Classificação contábil de segundo nível excluída com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao excluir a classificação contábil de segundo nível."]);
    }
}
