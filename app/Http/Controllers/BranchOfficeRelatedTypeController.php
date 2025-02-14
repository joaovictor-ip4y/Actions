<?php

namespace App\Http\Controllers;

use App\Models\BranchOfficeRelatedType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class BranchOfficeRelatedTypeController extends Controller
{
    protected function get(Request $request)
    {
        $branchOfficeRelatedType = new BranchOfficeRelatedType();
        return response()->json($branchOfficeRelatedType->getBranchOfficeRelatedType());
    }
}
