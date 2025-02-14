<?php

namespace App\Http\Controllers;

use App\Models\DocumentIdentificationType;
use Illuminate\Http\Request;

class DocumentIdentificationTypeController extends Controller
{
    protected function documentIdentificationType()
    {
        $document_identification_types = new DocumentIdentificationType();
        return response()->json($document_identification_types->get());
    }

    protected function identificationType()
    {
        $document_identification_types = new DocumentIdentificationType();
        return response()->json($document_identification_types->get());
    }
}
