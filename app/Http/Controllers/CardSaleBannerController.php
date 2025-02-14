<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CardSaleBanner;
use Illuminate\Http\Request;

class CardSaleBannerController extends Controller
{

  public function index()
  {
    return response()->json(CardSaleBanner::latest()->get());
  }

  protected function get(Request $request)
  {
    $card_sale_banner = new CardSaleBanner();
    $card_sale_banner->description          = $request->description;
    $card_sale_banner->credit_tax           = $request->credit_tax;
    $card_sale_banner->credit_tax_2_6       = $request->credit_tax_2_6;
    $card_sale_banner->credit_tax_7_12      = $request->credit_tax_7_12;
    $card_sale_banner->tax_antecipation     = $request->tax_antecipation;
    $card_sale_banner->tax_administration   = $request->tax_administration;
    return response()->json($card_sale_banner->viewall());
  }
}
