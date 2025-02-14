<?php

namespace App\Http\Controllers;

use App\Models\ApiConfig;
use App\Libraries\ApiZenviaSMS;
use Illuminate\Http\Request;

class SendSmsController extends Controller
{ 
    protected function sendSMS()
    {
        $apiZenviaSMS = new ApiZenviaSMS();
        $apiZenviaSMS->api_address     = 'https://api-rest.zenvia.com/';
        $apiZenviaSMS->authorization   = 'ZGluYXJpLnNtc29ubGluZTpVa29haUJSSjND';
        $apiZenviaSMS->id              = "011";
        $apiZenviaSMS->aggregateId     = "001";
        $apiZenviaSMS->from            = "iP4y LTDA";
        $apiZenviaSMS->to              = "5511945140857";
        $apiZenviaSMS->msg             = "Teste envio de SMS por dentro do sistema";
        $apiZenviaSMS->schedule        = "2020-02-04T19:01:00";
        $apiZenviaSMS->callbackOption  = "NONE";
        return response()->json($apiZenviaSMS->sendShortSMS());
    }
}
