<?php

namespace App\Http\Controllers;

use App\Models\MovementType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class MovementTypeController extends Controller
{
    protected function get(Request $request)
    {
        $movementType                = new MovementType();
        $movementType->onlyForManual = $request->onlyForManual;
        $movementType->onlyFee       = $request->onlyFee;
        return response()->json($movementType->getMovementType());
    }
}
