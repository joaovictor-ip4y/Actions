<?php

namespace App\Http\Controllers;

use App\Models\LimitType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class LimitTypeController extends Controller
{
    protected function get()
    {
        $limitGroup = new LimitType();
        return response()->json($limitGroup->getLimitType());
    }
}
