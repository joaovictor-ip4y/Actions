<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class BankController extends Controller
{
    protected function get(){
        $bank = new Bank();
        return response()->json($bank->getBank());
    }
}
