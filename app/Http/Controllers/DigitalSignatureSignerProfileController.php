<?php

namespace App\Http\Controllers;

use App\Models\DigitalSignatureSignerProfile;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class DigitalSignatureSignerProfileController extends Controller
{
    protected function get(Request $request)
    {
        $digitalSignatureSignerProfile = new DigitalSignatureSignerProfile();
        return response()->json($digitalSignatureSignerProfile->get());
    }
}
