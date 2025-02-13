<?php

namespace App\Http\Controllers;

use App\Models\CardSaleBuyer;
use Illuminate\Http\Request;

class CardSaleBuyerController extends Controller
{
    public function index()
    {
        return response()->json(CardSaleBuyer::latest()->get());
    }

}
