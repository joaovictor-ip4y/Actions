<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AgencyMovementCostCenter;
use Illuminate\Support\Facades\Validator;
use App\Models\AgencyMovementCostCenterCategory;
use App\Models\AgencyMovementCostCenterSubcategory;
use App\Services\Account\AccountRelationshipCheckService;

class AgencyMovementCostCenterCategoryController extends Controller
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

        $categories = AgencyMovementCostCenterCategory::with(['costCenter', 'agencyMovements'])->get();
        return response()->json($categories);
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

        $category = AgencyMovementCostCenterCategory::create($request->all());

        return response()->json($category, 201);
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
            'agency_movement_cost_center_id' => 'required|exists:agency_movement_cost_centers,id',
            'id' => 'required|exists:agency_movement_cost_center_categories,id',
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
        ]);

        if ($validatedData->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['success' => $validatedData->errors()], 200 /*@miqueias  'colocamos erro no 200 por conta que o front nos desloga em requisições que falham, tratamos o erro dentro do json para evitar esse problema' */);
        }

        $category = AgencyMovementCostCenterCategory::withTrashed()->findOrFail($request->id);
        $category->update([
            'description' => $request->description,
            'observation' => $request->observation,
        ]);

        return response()->json(['success' => 'categoria editada com sucesso'], 200);
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

        $category = AgencyMovementCostCenterCategory::findOrFail($request->id);
        $category->delete();

        return response()->json(['message' => 'Categoria de centro de custo excluída com sucesso!'], 200);
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

        $category = AgencyMovementCostCenterCategory::withTrashed()->findOrFail($id);
        $category->restore();

        return response()->json($category);
    }

    public function listMovementCostCenterCategory(Request $request) {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $mvmnt_cost_center_category = new AgencyMovementCostCenterCategory();

        $mvmnt_cost_center_category->agency_movement_cost_center_id = $request->agency_movement_cost_center_id;

        return response()->json($mvmnt_cost_center_category->getMovementCostCenterCategory());
    }
}
