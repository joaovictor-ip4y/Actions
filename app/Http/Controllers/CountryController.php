<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CountryController extends Controller
{
    protected function get()
    {
        $country = new Country();
        return response()->json($country->getCountry());
    }
}
