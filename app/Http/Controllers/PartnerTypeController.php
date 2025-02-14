<?php

namespace App\Http\Controllers;

use App\Models\PartnerType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class PartnerTypeController extends Controller
{
    protected function get()
    {
        $partnerType = new PartnerType();
        return response()->json($partnerType->getPartnerType());
    }
}
