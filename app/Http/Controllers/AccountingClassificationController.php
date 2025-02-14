<?php

namespace App\Http\Controllers;

use App\Models\AccountingClassification;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccountingClassificationController extends Controller
{
    
    protected function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description'          => ['required', 'string']
        ],[
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


        if($accountingClassification = AccountingClassification::create([
            'uuid'        => Str::orderedUuid(),
            'account_id'  => $checkAccount->account_id,
            'description' => $request->description,
            'created_at'  => \Carbon\Carbon::now()
        ])){
            return response()->json(["success" => "Classificação contábil incluída com sucesso.", "data" => $accountingClassification]);
        } 
        return response()->json(["error" => "Poxa, não foi possível incluir a classificação contábil no momento, por favor tente novamente mais tarde."]);
    }
    
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


        $accountingClassification = new AccountingClassification();
        $accountingClassification->account_id = $checkAccount->account_id;
        return response()->json($accountingClassification->get());
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
            'description'          => ['required', 'string']
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'description.required' => 'É obrigatório informar o description'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        if ( !$accountingClassification = AccountingClassification::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Classificação contábil não localizada, por favor verifique e tente novamente."]);
        }

        
        $accountingClassification->description = $request->description;

        if ($accountingClassification->save()) {
            return response()->json(["success" => "Classificação contábil atualizada com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar a classificação contábil."]);
    }

    public function destroy(Request $request)
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

        if ( !$accountingClassification = AccountingClassification::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Classificação contábil não localizada, por favor verifique e tente novamente."]);
        }

        $accountingClassification->deleted_at = \Carbon\Carbon::now();

        if ($accountingClassification->save()) {
            return response()->json(["success" => "Classificação contábil excluída com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao excluir a classificação contábil."]);
    }
}
