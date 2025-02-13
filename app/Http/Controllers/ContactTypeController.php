<?php

namespace App\Http\Controllers;

use App\Models\ContactType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ContactTypeController extends Controller
{
    protected function get()
    {
        $contactType = new ContactType();
        return response()->json($contactType->getContactType());
    }
}
