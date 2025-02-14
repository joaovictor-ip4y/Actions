<?php

namespace App\Http\Controllers;

use App\Models\States;
use Illuminate\Http\Request;

class StatesController extends Controller
{
    protected function get()
    {
        $states = new States();
        return response()->json($states->getStates());
    }
}
