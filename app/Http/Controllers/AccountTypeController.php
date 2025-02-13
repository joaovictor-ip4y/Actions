<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class AccountTypeController extends Controller
{
    protected function get()
    {
        $accountType = new AccountType();
        return response()->json($accountType->getAccountType());
    }
}
