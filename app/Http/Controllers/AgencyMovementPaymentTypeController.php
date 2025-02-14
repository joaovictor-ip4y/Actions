<?php

namespace App\Http\Controllers;

use App\Models\AgencyMovementPaymentType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class AgencyMovementPaymentTypeController extends Controller
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

        $paymentTypes = AgencyMovementPaymentType::withTrashed()->get();
        return view('payment_types.index', compact('paymentTypes'));
    }

    public function create()
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

        return view('payment_types.create');
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

        $request->validate([
            'description' => 'required|max:50',
        ]);

        AgencyMovementPaymentType::create($request->all());

        return redirect()->route('payment_types.index')->with('success', 'Tipo de pagamento criado com sucesso!');
    }

    public function edit($id)
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

        $paymentType = AgencyMovementPaymentType::withTrashed()->findOrFail($id);
        return view('payment_types.edit', compact('paymentType'));
    }

    public function update(Request $request, $id)
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

        $request->validate([
            'description' => 'required|max:50',
        ]);

        $paymentType = AgencyMovementPaymentType::withTrashed()->findOrFail($id);
        $paymentType->update($request->all());

        return redirect()->route('payment_types.index')->with('success', 'Tipo de pagamento atualizado com sucesso!');
    }

    public function destroy($id)
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

        $paymentType = AgencyMovementPaymentType::findOrFail($id);
        $paymentType->delete();

        return redirect()->route('payment_types.index')->with('success', 'Tipo de pagamento excluído com sucesso!');
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
        
        $paymentType = AgencyMovementPaymentType::withTrashed()->findOrFail($id);
        $paymentType->restore();

        return redirect()->route('payment_types.index')->with('success', 'Tipo de pagamento restaurado com sucesso!');
    }
}
