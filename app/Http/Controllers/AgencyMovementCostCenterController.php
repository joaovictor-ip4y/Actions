<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AgencyMovementCostCenter;
use Illuminate\Support\Facades\Validator;
use App\Models\AgencyMovementCostCenterCategory;
use App\Models\AgencyMovementCostCenterSubcategory;
use App\Services\Account\AccountRelationshipCheckService;

class AgencyMovementCostCenterController extends Controller
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

        $costCenters = AgencyMovementCostCenter::get();
        return response()->json($costCenters);
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

        $validator = Validator::make($request->all(), [
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $request->merge(['uuid' => Str::uuid()->toString()]);

        $costCenter = AgencyMovementCostCenter::create($request->all());

        return response()->json([ 'Success' => true, 'data' => $costCenter, 200]);
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
            'id'          => 'required|exists:agency_movement_cost_centers,id',
            'description' => 'required|max:50',
            'observation' => 'nullable|max:255',
	    ]);

        if ($validator->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if(isset($request->agency_movement_cost_center_subcategory_id)){
            $subcategory = AgencyMovementCostCenterSubcategory::withTrashed()
            ->findOrFail($request->agency_movement_cost_center_subcategory_id);

            $subcategory->update([
                'agency_movement_cost_center_id' => $request->id,
            ]);
        }
        if(isset($request->agency_movement_cost_center_category_id)){
            $category = AgencyMovementCostCenterCategory::withTrashed()
            ->findOrFail($request->agency_movement_cost_center_category_id);

            $category->update([
                'agency_movement_cost_center_id' => $request->id,
            ]);
        }

        $costCenter = AgencyMovementCostCenter::withTrashed()->findOrFail($request->id);
        $costCenter->update([
            'description' => $request->description,
            'observation' => $request->observation,
        ]);

        return response()->json(['success' => 'Centro de custo editado com sucesso.'], 200);
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

        $costCenter = AgencyMovementCostCenter::findOrFail($request->id);
        $costCenter->delete();

        return response()->json(['message' => 'Centro de custo excluído com sucesso!'], 200);
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

        $costCenter = AgencyMovementCostCenter::withTrashed()->findOrFail($id);
        $costCenter->restore();

        return response()->json($costCenter);
    }

    public function listMovementCostCenter() 
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
        
        $mvmnt_cost_center = new AgencyMovementCostCenter();
        return response()->json($mvmnt_cost_center->getMovementCostCenter());
    }
}
