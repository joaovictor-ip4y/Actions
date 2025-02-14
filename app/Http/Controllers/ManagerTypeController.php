<?php

namespace App\Http\Controllers;

use App\Models\ManagerType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ManagerTypeController extends Controller
{
    protected function get()
    {
        $managerType = new ManagerType();
        return response()->json($managerType->get());
    }
}
