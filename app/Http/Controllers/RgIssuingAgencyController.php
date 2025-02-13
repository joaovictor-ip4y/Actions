<?php

namespace App\Http\Controllers;

use App\Models\RgIssuingAgency;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class RgIssuingAgencyController extends Controller
{
    protected function get()
    {
        $rgIssuingAgency = new RgIssuingAgency();
        return response()->json($rgIssuingAgency->getRgIssuingAgency());
    }
}
