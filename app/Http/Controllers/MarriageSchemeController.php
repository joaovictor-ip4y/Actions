<?php

namespace App\Http\Controllers;

use App\Models\MarriageScheme;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class MarriageSchemeController extends Controller
{
    protected function get()
    {
        $marriageScheme = new MarriageScheme();
        return response()->json($marriageScheme->getMarriageScheme());
    }
}
