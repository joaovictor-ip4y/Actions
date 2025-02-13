<?php

namespace App\Http\Controllers;

use App\Models\PhoneType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PhoneTypeController extends Controller
{
    protected function get()
    {
        $phoneType = new PhoneType();
        return response()->json($phoneType->getPhoneType());
    }
}
