<?php

namespace App\Http\Controllers;

use App\Models\CardSaleType;
use Illuminate\Http\Request;

class CardSaleTypeController extends Controller
{
    public function index()
    {
        return response()->json(CardSaleType::latest()->get());
    }
}
