<?php

namespace App\Http\Controllers;

use App\Models\PayrollCreditType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PayrollCreditTypeController extends Controller
{
    protected function get(Request $request)
    {
        $payroll_credit_type = new PayrollCreditType();
        return response()->json($payroll_credit_type->get());
    }
}
