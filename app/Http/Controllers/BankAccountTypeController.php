<?php

namespace App\Http\Controllers;

use App\Models\BankAccountType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class BankAccountTypeController extends Controller
{
    protected function get(){
        $bankAccountType = new BankAccountType();
        return response()->json($bankAccountType->getBankAccountType());
    }
}
