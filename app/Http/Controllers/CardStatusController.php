<?php

namespace App\Http\Controllers;

use App\Models\CardStatus;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CardStatusController extends Controller
{
    protected function get(){
        $card_status = new CardStatus();
        return response()->json($card_status->getCardStatus());
    }
}
