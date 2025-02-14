<?php

namespace App\Http\Controllers;

use App\Models\MaritalStatus;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class MaritalStatusController extends Controller
{
    protected function get()
    {
        $maritalStatus = new MaritalStatus();
        return response()->json($maritalStatus->getMaritalStatus());
    }

}
