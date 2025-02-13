<?php

namespace App\Http\Controllers;

use App\Models\CardSaleModality;

class CardSaleModalityController extends Controller
{
    protected function get()
    {
        $card_sale_modality_controller  = new CardSaleModality();
        return response()->json($card_sale_modality_controller->getCardSaleModality());
    }

}
