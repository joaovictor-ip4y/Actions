<?php

namespace App\Http\Controllers;

use App\Models\FavoredAccount;
use App\Models\Account;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use PDF;

class FavoredAccountController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [19,189,270];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $favoredAccount = new FavoredAccount();
        
        $favoredAccount->register_master_id =  $request->register_master_id;
        
        if( $accountData = (Account::where('id', '=', $checkAccount->account_id)->first()) ) {
            $favoredAccount->register_master_id = $accountData->register_master_id;
        }
       
        $favoredAccount->master_id  = $checkAccount->master_id;
        $favoredAccount->onlyActive = $request->onlyActive;
        return response()->json($favoredAccount->getFavoredAccount());
    }

    protected function exportFavoredAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [19,189,270];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $favoredAccount   = new FavoredAccount();
        $favoredAccount->master_id  = $checkAccount->master_id;
        $favoredAccount->onlyActive = $request->onlyActive;

        $favoredAccount->register_master_id =  $request->register_master_id;
        
        if( $accountData = (Account::where('id', '=', $checkAccount->account_id)->first()) ) {
            $favoredAccount->register_master_id = $accountData->register_master_id;
        }

        $items = [];

        foreach($favoredAccount->getFavoredAccount() as $movementData){
            array_push($items, (object)[
                'favored_cpf_cnpj'      => Facilites::mask_cpf_cnpj($movementData->favored_cpf_cnpj),
                'favored_name'          => $movementData->favored_name,
                'bank_description'      => $movementData->bank_description,
                'bank_agency'           => $movementData->bank_agency,
                'bank_account'          => $movementData->bank_account,
                'bank_account_type'     => $movementData->bank_account_type,
            ]);
        }

        $data = (object) array(
            "movement_data"     => $items,
        );
        //return $items;
        $file_name = "Contas_Cadastradas.pdf";
        $pdf       = PDF::loadView('reports/favored_account', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));

    }
}
