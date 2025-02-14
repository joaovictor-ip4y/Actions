<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignatureDocumentType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class DigitalSignatureDocumentTypeController extends Controller
{
    protected function get(Request $request)
    {
        $digitalSignatureDocumentType = new DigitalSignatureDocumentType();
        return response()->json($digitalSignatureDocumentType->get());
    }
}
