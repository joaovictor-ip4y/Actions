<?php

namespace App\Http\Controllers;

use App\Models\UserRelationshipRequestType;
use Illuminate\Http\Request;

class UserRelationshipRequestTypeController extends Controller
{
    protected function show(Request $request)
    {
        $userRelationshipRequestType = new UserRelationshipRequestType();
        return response()->json($userRelationshipRequestType->get());
    }
}
