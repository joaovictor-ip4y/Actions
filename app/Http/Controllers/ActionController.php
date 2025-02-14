<?php

namespace App\Http\Controllers;

use App\Models\Action;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ActionController extends Controller
{
    protected function get()
    {
        $action = new Action();
        return response()->json($action->getAction());
    }
}
