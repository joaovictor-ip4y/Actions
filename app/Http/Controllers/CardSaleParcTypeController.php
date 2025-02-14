<?php

namespace App\Http\Controllers;

use App\Models\CardSaleParcType;
use Illuminate\Http\Request;

class CardSaleParcTypeController extends Controller
{
    public function index()
    {
        return response()->json(CardSaleParcType::latest()->get());
    }
}
