<?php

namespace App\Http\Controllers;

use App\Models\AssociateType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class AssociateTypeController extends Controller
{
    protected function get(){
        $associateType = new AssociateType();
        return response()->json($associateType->getAssociateType());
    }
}
