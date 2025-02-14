<?php

namespace App\Http\Controllers;

use App\Models\CardSaleTerminalTax;
use Illuminate\Http\Request;

class CardSaleTerminalTaxController extends Controller
{
    protected function get(Request $request)
    {
        $card_sale_terminal_tax                       = new CardSaleTerminalTax();
        $card_sale_terminal_tax->terminal_id          = $request->terminal_id;
        $card_sale_terminal_tax->banner_id            = $request->banner_id;
        $card_sale_terminal_tax->credit_tax_2_6       = $request->credit_tax_2_6;
        $card_sale_terminal_tax->credit_tax_7_12      = $request->credit_tax_7_12;
        $card_sale_terminal_tax->tax_antecipation     = $request->tax_antecipation;
        $card_sale_terminal_tax->tax_administration   = $request->tax_administration;
        return response()->json($card_sale_terminal_tax->viewall());
    }
}
