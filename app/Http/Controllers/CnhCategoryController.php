<?php

namespace App\Http\Controllers;

use App\Models\CnhCategory;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CnhCategoryController extends Controller
{
    protected function get(){
        $cnhCategory = new CnhCategory();
        return response()->json($cnhCategory->getCnhCategory());
    }
}
