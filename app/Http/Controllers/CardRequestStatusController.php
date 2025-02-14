<?php

namespace App\Http\Controllers;

use App\Models\CardRequestStatus;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class CardRequestStatusController extends Controller
{
    protected function get()
    {
        $card_request_status = new CardRequestStatus();
        return response()->json($card_request_status->getCardRequestStatus());
    }
}
