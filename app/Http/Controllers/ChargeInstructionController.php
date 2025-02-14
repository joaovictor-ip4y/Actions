<?php

namespace App\Http\Controllers;

use App\Models\ChargeInstruction;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class ChargeInstructionController extends Controller
{
   protected function getClientChargeInstruction(Request $request)
   {
       $chargeInstruction                      = new ChargeInstruction();
       $chargeInstruction->onlyActive          = 1;
       $chargeInstruction->onlyClientAvailable = 1;
       $chargeInstruction->id                  = $request->instruction_id;
       return response()->json($chargeInstruction->getChargeInstructions());
   }
}
