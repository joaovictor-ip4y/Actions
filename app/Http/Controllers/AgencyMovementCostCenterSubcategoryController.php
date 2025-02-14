<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AgencyMovementCostCenterSubcategory;
use App\Services\Account\AccountRelationshipCheckService;

class AgencyMovementCostCenterSubcategoryController extends Controller
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

        $subcategories = AgencyMovementCostCenterSubcategory::with(['costCenter', 'costCenterCategory', 'agencyMovements'])->get();
        return response()->json($subcategories);
    }

    public function create()
    {
        // Implementar se necessário
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

        $validator = Validator::make($request->all(),[
            'agency_movement_cost_center_id' => 'required',
            'agency_movement_cost_center_category_id' => 'required',
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $subcategory = AgencyMovementCostCenterSubcategory::create($request->all());

        return response()->json($subcategory, 200);
    }

    public function edit($id)
    {
        // Implementar se necessário
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

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'agency_movement_cost_center_id' => 'required',
            'agency_movement_cost_center_category_id' => 'required',
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $subcategory = AgencyMovementCostCenterSubcategory::withTrashed()->findOrFail($request->id);
        $subcategory->update($request->all());
        
        return response()->json(['success' => 'Subcategoria editada com sucesso'], 200);
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

        $subcategory = AgencyMovementCostCenterSubcategory::findOrFail($request->input('id'));
        $subcategory->delete();

        return response()->json(['success' => 'Subcategoria de centro de custo excluída com sucesso!'], 200);
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

        $subcategory = AgencyMovementCostCenterSubcategory::withTrashed()->findOrFail($id);
        $subcategory->restore();

        return response()->json($subcategory);
    }

    public function listMovementCostCenterSubcategory(Request $request) 
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
        
        $mvmnt_cost_center_subcategory = new AgencyMovementCostCenterSubcategory();
        $mvmnt_cost_center_subcategory->agency_movement_cost_center_category_id = $request->agency_movement_cost_center_category_id;
        return response()->json($mvmnt_cost_center_subcategory->getMovementCostCenterSubcategory());
    }
}
