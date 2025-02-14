<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class DocumentTypeController extends Controller
{
    protected function get(Request $request)
    {
       $documentTypes = new DocumentType();
       return response()->json($documentTypes->getDocumentTypes());
    }

    protected function getRegisterDocumentType(Request $request)
    {
        $documentTypes = new DocumentType();
        return response()->json($documentTypes->getRegisterDocumentTypes());
    }
}
