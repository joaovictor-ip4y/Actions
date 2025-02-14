<?php

namespace App\Http\Controllers;

use App\Models\DocumentRne;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class DocumentRneController extends Controller
{
    protected function get()
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [38, 39];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $documentRne = new DocumentRne();
        return response()->json($documentRne->getDocumentRne());
    }
}
