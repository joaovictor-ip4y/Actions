<?php

namespace App\Http\Controllers;

use App\Models\Gender;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class GenderController extends Controller
{
    protected function get()
    {
        $gender = new Gender();
        return response()->json($gender->getGender());
    }
}
