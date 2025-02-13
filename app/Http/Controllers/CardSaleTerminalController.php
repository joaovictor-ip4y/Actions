<?php

namespace App\Http\Controllers;

use App\Models\CardSaleTerminal;
use App\Models\Account;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use PDF;

class CardSaleTerminalController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [114, 248];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_sale_terminal = new CardSaleTerminal();
        $card_sale_terminal->id                  = $request->id;
        $card_sale_terminal->master_id           = $checkAccount->master_id;
        $card_sale_terminal->register_master_id  = $request->register_master_id;
        $card_sale_terminal->terminal            = $request->terminal;
        $card_sale_terminal->account_id          = $checkAccount->account_id;

        return response()->json($card_sale_terminal->viewall());
    }

    protected function exportCardSaleTerminal(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [114, 248];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_sale_terminal = new CardSaleTerminal();
        $card_sale_terminal->id                  = $request->id;
        $card_sale_terminal->master_id           = $checkAccount->master_id;
        $card_sale_terminal->register_master_id  = $request->register_master_id;
        $card_sale_terminal->terminal            = $request->terminal;
        $card_sale_terminal->account_id          = $checkAccount->account_id;

        $items = [];

        foreach($card_sale_terminal->viewall() as $movementData){
            array_push($items, (object) [
                'terminal'                 =>   $movementData->terminal,
                'acquisition_date'         =>   \Carbon\Carbon::parse($movementData->acquisition_date)->format('d/m/Y'),
                'machine_type_description' =>   $movementData->machine_type_description
            ]);
        }

        $data = (object) array(
            "movement_data" => $items,
        );
        //return $items;
        $file_name = "Minhas_Maquinas.pdf";
        $pdf       = PDF::loadView('reports/card_terminal', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [116];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( CardSaleTerminal::where('terminal', '=', $request->terminal)->count() == 0)
        {
            if(CardSaleTerminal::create([
                'terminal'                  => $request->terminal,
                'account_id'                => $request->account_id,
                'acquisition_date'          => $request->acquisition_date,
                'mchn_tp_id'                => $request->type_id,
                'master_id'                 => $checkAccount->master_id,
                'visa_debit'                => $request->visa_debit,
                'visa_credit'               => $request->visa_credit,
                'visa_credit_2_6'           => $request->visa_credit_2_6,
                'visa_credit_7_12'          => $request->visa_credit_7_12,
                'visa_antecipation'         => $request->visa_antecipation,
                'mastercard_debit'          => $request->mastercard_debit,
                'mastercard_credit'         => $request->mastercard_credit,
                'mastercard_credit_2_6'     => $request->mastercard_credit_2_6,
                'mastercard_credit_7_12'    => $request->mastercard_credit_7_12,
                'mastercard_antecipation'   => $request->mastercard_antecipation,
                'elo_debit'                 => $request->elo_debit,
                'elo_credit'                => $request->elo_credit,
                'elo_credit_2_6'            => $request->elo_credit_2_6,
                'elo_credit_7_12'           => $request->elo_credit_7_12,
                'elo_antecipation'          => $request->elo_antecipation,
                'hiper_debit'               => $request->hiper_debit,
                'hiper_credit'              => $request->hiper_credit,
                'hiper_credit_2_6'          => $request->hiper_credit_2_6,
                'hiper_credit_7_12'         => $request->hiper_credit_7_12,
                'hiper_antecipation'        => $request->hiper_antecipation,
                'amex_debit'                => $request->amex_debit,
                'amex_credit'               => $request->amex_credit,
                'amex_credit_2_6'           => $request->amex_credit_2_6,
                'amex_credit_7_12'          => $request->amex_credit_7_12,
                'amex_antecipation'         => $request->amex_antecipation

            ])){
                return response()->json(['success' => 'Terminal cadastrado com sucesso']);
            } else {
                return response()->json(['error' => 'Ocorreu uma falha ao cadatrar o terminal, por favor tente mais tarde']);
            }
        } else{
                return response()->json(['error'=>'Terminal já cadastrado']);
        }
    }

    protected function delete(Request $request)
    {   
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [118];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $card_sale_terminal = CardSaleTerminal::where('id','=',$request->id)->where('account_id','=',$request->account_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $card_sale_terminal->deleted_at = \Carbon\Carbon::now();
            if($card_sale_terminal->save()){
                return response()->json(array("success" => "Terminal excluido com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao excluir o Terminal"));
            }
        }else{
            return response()->json(array("error" => "Nenhum Terminal foi localizado"));
        }
    }

    protected function updateCardSaleTerminal(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [115];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $card_sale_terminal = CardSaleTerminal::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first()){
            
            if($card_sale_terminal->terminal != $request->terminal){
                if( CardSaleTerminal::where('terminal', '=', $request->terminal)->whereNull('deleted_at')->count() > 0){
                    return response()->json(array("error" => "Terminal já cadastrado"));
                }
            }
            $card_sale_terminal->terminal           = $request->terminal;
            $card_sale_terminal->account_id         = $request->account_id;
            $card_sale_terminal->acquisition_date   = $request->acquisition_date;
            $card_sale_terminal->mchn_tp_id         = $request->type_id;
            if($card_sale_terminal->save()){
                return response()->json(array("success" => "Terminal atualizado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao atualizar o Terminal"));
            }
        }else{
            return response()->json(array("error" => "Nenhum Terminal foi localizado"));
        }
    }

}
