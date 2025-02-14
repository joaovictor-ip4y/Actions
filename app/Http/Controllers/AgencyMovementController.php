<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AgencyMovement;
use App\Models\RegisterDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\Account\AccountRelationshipCheckService;

class AgencyMovementController extends Controller
{
    public function index(Request $request)
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

        $query = AgencyMovement::with('classification', 'costCenter', 'costCenterCategory', 'costCenterSubcategory');

        // Beneficiary Filter
        if ($request->filled('beneficiary')) {
            $query->where('register_master_id', $request->beneficiary);
        }

        // Payment Date Filters
        if ($request->filled('payment_date_start')) {
            $query->whereDate('payment_date', '>=', $request->payment_date_start);
        }

        if ($request->filled('payment_date_end')) {
            $query->whereDate('payment_date', '<=', $request->payment_date_end);
        }

        // status Filter
        if ($request->status_id == 29) {
            $query->where('status_id', $request->status_id);
        }

        // Value Filters
        if ($request->filled('value_start')) {
            $query->where('value', '>=', $request->value_start);
        }

        if ($request->filled('value_end')) {
            $query->where('value', '<=', $request->value_end);
        }

        // Competence Date Filters
        if ($request->filled('competence_date_start')) {
            $query->whereDate('competence_date', '>=', $request->competence_date_start);
        }

        if ($request->filled('competence_date_end')) {
            $query->whereDate('competence_date', '<=', $request->competence_date_end);
        }

        // cost center Filters
        if ($request->filled('cost_center_id')) {
            $query->where('agency_movement_cost_center_id', $request->cost_center_id);
        }

        if ($request->filled('category_id')) {
            $query->where('agency_movement_cost_center_category_id', $request->category_id);
        }
        
        if ($request->filled('subcategory_id')) {
            $query->where('agency_movement_cost_center_subcategory_id', $request->subcategory_id);
        }



        // Add more conditions as needed for other filters

        $result = $query->get();

        return response()->json(['movements' => $result]);
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
            'observation' => 'nullable|string',
            'account' => 'nullable|required_if:pix_key,null|array:branch_code,number,bank_id,type_id',
            'payment_type_id' => [
                'required',
                'exists:agency_movement_payment_types,id',
            ],
            'account.branch_code' => [
                'required_with:account',
                'regex:/^\d{1,4}|NA$/',
            ],
            'account.number' => [
                'required_with:account',
                'regex:/^\d{1,20}|NA$/',
            ],
            'account.bank_id' => [
                'required_with:account',
                'regex:/^\d{1,20}|NA$/',
            ],
            'account.type_id' => [
                'required_with:account',
                'regex:/^\d{1,20}|NA$/',
                'exists:pix_account_types,id'
            ],
            'pix_key' => 'nullable|required_if:account,null|array:type,key',
            'pix_key.type' => 'required_with:pix_key',
            'pix_key.key' => 'required_with:pix_key|string|between:1,77',
            'amount' => 'required|string',
            'payment_method' => 'required|integer',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date',
            'classification' => 'required|integer|exists:agency_movement_classifications,id',
            'cost_center' => 'required|integer',
            'category' => 'nullable',
            'subcategory' => 'nullable',
            'billet' => 'nullable|string',
            'competence_date' => 'nullable|date',
            'pay_date' => 'nullable|date',
            'favored_id' => [
                'required'
            ],
        ]);

        
        
        if ($validatedData->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['error' => $validatedData->errors()], 200);
        }
        
        $registerDetailModel = new RegisterDetail();
        $registerDetailModel->register_master_id = $request->favored_id;
        $registerDetailedData = $registerDetailModel
        ->getRegisterDetailed()[0];
        
        // Create a new instance of AgencyMovement
        $agencyMovement = new AgencyMovement;

        // Set the values for each attribute based on the validated data

        $agencyMovement->uuid = Str::uuid()->toString();
        $agencyMovement->value = $request->amount;
        $agencyMovement->register_master_id = $request->favored_id;
        $agencyMovement->competence_date = $request->competence_date;
        $agencyMovement->due_date = $request->due_date;
        $agencyMovement->favored_cpf_cnpj = $registerDetailedData->cpf_cnpj;
        $agencyMovement->favored_name = $registerDetailedData->name;

        $agencyMovement->agency_movement_classification_id = $request->classification;
        $agencyMovement->agency_movement_cost_center_id = $request->cost_center;
        
        $agencyMovement->agency_movement_cost_center_category_id = $request->category;
        $agencyMovement->agency_movement_cost_center_subcategory_id = $request->subcategory;
        $agencyMovement->included_by_user_id = Auth::user()->id;

        if (isset($request->pix_key)) {
            $agencyMovement->pix_end_to_end = $request->pix_key['key'];
            $agencyMovement->pix_key_or_emv = $request->pix_key['key'];
            $agencyMovement->pix_favored_key = $request->pix_key['type'];
        }

        if (isset($request->billet)) {
            $agencyMovement->bill_digitable_line_or_bar_code = $request->billet;
            $agencyMovement->bill_digitable_line =$request->billet;
            $agencyMovement->bill_bar_code = $request->billet;
        }

        if (isset($request->account)) {
            $agencyMovement->favored_agency = $request->account['number'];
            $agencyMovement->favored_account = $request->account['branch_code'];
            $agencyMovement->bank_id = $request->account['bank_id'];
        }

        $agencyMovement->bill_total_value = $request->amount;
        $agencyMovement->competence_date = $request->competence_date;
        $agencyMovement->payment_date = $request->payment_date;
        $agencyMovement->agency_movement_payment_type_id = $request->payment_type_id;
        $agencyMovement->favored_account_type_id = $request->type_id;
        $agencyMovement->observation = $request->observation;
        
        // Save the AgencyMovement instance to the database
        $agencyMovement->save();

        // Optionally, you can return a response indicating success
        return response()->json(['message' => 'AgencyMovement created successfully'], 201);
    }


    public function payTransactions(Request $request)
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
            'payments' => ['required', 'array'],
            'payments.*.id' => 'required|integer',
            'payments.*.payment_type' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ids = collect($request->payments)->pluck('id');
        AgencyMovement::whereIn('id', $ids)->update(['status_id' => 29]);

        return response()->json(null, 201);
    }

    public function deleteTransactions(Request $request)
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
            'payments' => ['required', 'array'],
            'payments.*.id' => 'required|integer',
            'payments.*.payment_type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            // Faça algo quando a validação falhar, por exemplo, redirecione de volta com erros.
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ids = collect($request->payments)->pluck('id');
        AgencyMovement::whereIn('id', $ids)->delete();

        return response()->json(null, 201);
    }
}