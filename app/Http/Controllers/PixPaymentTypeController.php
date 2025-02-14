<?php

namespace App\Http\Controllers;

use App\Models\PixPaymentType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PixPaymentTypeController extends Controller
{
    
    protected function show(Request $request)
    {
        $pixPaymentType = new PixPaymentType();
        return response()->json($pixPaymentType->get());
    }
    
}
