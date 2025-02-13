<?php

namespace App\Http\Controllers;

use App\Models\PayrollIdentify;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PayrollIdentifyController extends Controller
{
    protected function get(Request $request)
    {
        $payroll_identify = new PayrollIdentify();
        return response()->json($payroll_identify->get());
    }
}
