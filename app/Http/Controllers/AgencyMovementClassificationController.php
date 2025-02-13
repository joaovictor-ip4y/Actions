<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\AgencyMovementClassification;
use App\Services\Account\AccountRelationshipCheckService;

class AgencyMovementClassificationController extends Controller
{
    public function index()
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $classifications = AgencyMovementClassification::get();
        return response()->json($classifications);
    }

    public function store(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validatedData = Validator::make($request->all(), [
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
        ]);

        if ($validatedData->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['errors' => $validatedData->errors()], 422);
        }

        $request->merge(['uuid' => Str::uuid()->toString()]);

        $classification = AgencyMovementClassification::create($request->all());

        return response()->json($classification, Response::HTTP_CREATED);
    }

    public function show($id)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $classification = AgencyMovementClassification::withTrashed()->findOrFail($id);
        return response()->json($classification);
    }

    public function update(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validatedData = Validator::make($request->all(), [
            'id' => 'required|max:50',
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
        ]);

        if ($validatedData->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['errors' => $validatedData->errors()], 422);
        }

        $classification = AgencyMovementClassification::withTrashed()->findOrFail($request->id);
        $classification->update($request->all());

        return response()->json($classification, Response::HTTP_OK);
    }

    public function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $classification = AgencyMovementClassification::findOrFail($request->id);
        $classification->delete();

        return response()->json(['success' => 'classificação excluida com sucesso'], 200);
    }

    public function restore($id)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $classification = AgencyMovementClassification::withTrashed()->findOrFail($id);
        $classification->restore();

        return response()->json($classification, Response::HTTP_OK);
    }

    public function listMovementClassification() 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $mvmnt_classification = new AgencyMovementClassification();
        return response()->json($mvmnt_classification->getMovementClassification());
    }
}
