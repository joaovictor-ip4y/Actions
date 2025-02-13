<?php

namespace App\Http\Controllers;

use App\Models\ActionType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ActionTypeController extends Controller
{
    protected function get()
    {
        $actionType = new ActionType();
        return response()->json($actionType->getActionType());
    }
}
