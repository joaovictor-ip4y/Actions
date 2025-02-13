<?php

namespace App\Http\Controllers;

use App\Libraries\Facilites;
use App\Models\PixStaticReceive;
use App\Models\PixStaticWhatsapp;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PixStaticWhatsappController extends Controller
{

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [56, 177, 258];
       // $accountCheckService->permission_id  = [177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pix_static_whatsapp                = new PixStaticWhatsapp();
        $pix_static_whatsapp->pix_static_id = $request->pix_static_id;
        $pix_static_whatsapp->account_id    = $checkAccount->account_id;
        $pix_static_whatsapp->number        = preg_replace("/[^0-9]/",'',$request->number);
        $pix_static_whatsapp->onlyActive    = 1;

        return response()->json($pix_static_whatsapp->get());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [56, 177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if (PixStaticWhatsapp::where('pix_static_id','=',$request->pix_static_id)->whereNull('deleted_at')->count() >= 3) {
            return response()->json(array("error" => "Poxa, seu pix já possui 3 números para whastsapp cadastrados, por favor, insira outro pix ou tente mais tarde"));
        }

        if (PixStaticReceive::where('id','=',$request->pix_static_id)->when($checkAccount->account_id, function ($query, $account_id) {return $query->where('account_id','=', $account_id);})->first()) {

            if (PixStaticWhatsapp::create([
                'unique_id'         => Str::orderedUuid(),
                'pix_static_id'     => $request->pix_static_id,
                'description'       => $request->description,
                'number'            => preg_replace("/[^0-9]/",'',$request->number),
                'created_at'        => \Carbon\Carbon::now(),
            ])){
                return response()->json(array("success" => "Whatsapp cadastrado para comunicação de liquidações do pix estático com sucesso"));
            }
        }
        return response()->json(array("error" => "Poxa, não encontramos o pix estático informado, por favor, insira outro ou tente mais tarde"));
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [56, 177, 258];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        
        if ($pix_static_whatsapp = PixStaticWhatsapp::where('id','=',$request->id)->whereNull('deleted_at')->first()) {
            $pix_static_whatsapp->deleted_at = \Carbon\Carbon::now();
            if ($pix_static_whatsapp->save()) {
                return response()->json(array("success" => "Whatsapp removido com sucesso"));
            }
        }
        return response()->json(array("error" => "Poxa, não foi possível remover o whatsapp informado, por favor, tente mais tarde"));
    }
}
