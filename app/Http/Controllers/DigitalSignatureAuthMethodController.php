<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignatureAuthMethod;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class DigitalSignatureAuthMethodController extends Controller
{
    protected function get(Request $request)
    {
        $digitalSignatureAuthMethod = new DigitalSignatureAuthMethod();
        return response()->json($digitalSignatureAuthMethod->get());
    }
}
