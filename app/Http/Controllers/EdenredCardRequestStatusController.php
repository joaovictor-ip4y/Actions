<?php

namespace App\Http\Controllers;

use App\Models\EdenredCardRequestStatus;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class EdenredCardRequestStatusController extends Controller
{
    public function show()
    {
        $edenredCardRequestStatus = new EdenredCardRequestStatus();
        return response()->json($edenredCardRequestStatus->get());
    }
}
