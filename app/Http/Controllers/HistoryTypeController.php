<?php

namespace App\Http\Controllers;

use App\Models\HistoryType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class HistoryTypeController extends Controller
{
    protected function get()
    {
        $historyType = new HistoryType();
        return response()->json($historyType->getHistoryType());
    }
}
