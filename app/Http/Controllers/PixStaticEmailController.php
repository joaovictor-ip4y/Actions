<?php

namespace App\Http\Controllers;

use App\Models\PixStaticEmail;
use App\Models\PixStaticReceive;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class PixStaticEmailController extends Controller
{
    protected function get(Request $request)
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

        $pix_static_email                = new PixStaticEmail();
        $pix_static_email->pix_static_id = $request->pix_static_id;
        $pix_static_email->account_id    = $checkAccount->account_id;
        $pix_static_email->email         = $request->email;
        $pix_static_email->onlyActive    = 1;

        return response()->json($pix_static_email->get());
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

        if (PixStaticReceive::where('id','=',$request->pix_static_id)->when($checkAccount->account_id, function ($query, $account_id) {return $query->where('account_id','=',$account_id);})->first()) {

            if (PixStaticEmail::create([
                'unique_id'     => Str::orderedUuid(),
                'pix_static_id' => $request->pix_static_id,
                'description'   => $request->description,
                'email'         => $request->email,
                'created_at'    => \Carbon\Carbon::now(),
            ])){
                return response()->json(array("success" => "E-Mail cadastrado para comunicação de liquidações do pix estático com sucesso"));
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
        if ($pix_static_email = PixStaticEmail::where('id','=',$request->id)->whereNull('deleted_at')->first()) {
            $pix_static_email->deleted_at = \Carbon\Carbon::now();
            if ($pix_static_email->save()) {
                return response()->json(array("success" => "E-Mail removido com sucesso"));
            }
        }
        return response()->json(array("error" => "Poxa, não foi possível remover o e-mail informado, por favor, tente mais tarde"));
    }
}
