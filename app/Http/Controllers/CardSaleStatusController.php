<?php

namespace App\Http\Controllers;

use App\Models\CardSaleStatus;

class CardSaleStatusController extends Controller
{
    protected function get()
    {
        $card_sale_status   = new CardSaleStatus();
        return response()->json($card_sale_status->getCardSaleStatus());
    }
}
