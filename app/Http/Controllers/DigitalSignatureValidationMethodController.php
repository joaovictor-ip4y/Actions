<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignatureValidationMethod;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class DigitalSignatureValidationMethodController extends Controller
{
    protected function get(Request $request)
    {
        $digitalSignatureValidationMethod = new DigitalSignatureValidationMethod();
        return response()->json($digitalSignatureValidationMethod->get());
    }
}
