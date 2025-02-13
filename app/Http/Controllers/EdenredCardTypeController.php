<?php

namespace App\Http\Controllers;

use App\Models\EdenredCardType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class EdenredCardTypeController extends Controller
{
    public function show()
    {
        $edenredCardType = new EdenredCardType();
        return response()->json($edenredCardType->get());
    }
}
