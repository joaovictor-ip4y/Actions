<?php

namespace App\Http\Controllers;

use App\Models\ComissionTax;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ComissionTaxController extends Controller
{
    protected function get()
    {
        $comission_tax = new ComissionTax();
        return response()->json($comission_tax->get());
    }
}
