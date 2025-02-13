<?php

namespace App\Http\Controllers;

use App\Models\ChargeConfigNfsCity;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ChargeConfigNfsCityController extends Controller
{
    protected function get(Request $request)
    {
        $chargeConfig = new ChargeConfigNfsCity();
        $chargeConfig->onlyActive = $request->onlyActive;
        return response()->json($chargeConfig->getNfsCities());
    }
}
