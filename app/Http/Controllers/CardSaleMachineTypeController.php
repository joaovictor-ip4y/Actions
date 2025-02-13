<?php

namespace App\Http\Controllers;

use App\Models\CardSaleMachineType;
use Illuminate\Http\Request;

class CardSaleMachineTypeController extends Controller
{
    protected function get(Request $request)
    {
        $cardSaleMachineType = new CardSaleMachineType;
        return response()->json($cardSaleMachineType->getMachineTypes());
    }
}
