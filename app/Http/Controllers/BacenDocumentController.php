<?php

namespace App\Http\Controllers;

use App\Models\BacenDocument;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;
use SimpleXMLElement;

class BacenDocumentController extends Controller
{

    public function getDocument4111Data(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'base_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        return response()->json(BacenDocument::getDocument4111($request->base_date));
    }

    public function getDocument4111Xml(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'base_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        $data = BacenDocument::getDocument4111($request->base_date);

        $documentCode = "4111";
        $cnpj = "11491029";
        $baseDate = $request->base_date;
        $remittanceType = "I";
        $accounts = [];

        /*foreach($data as $info){
            array_push($accounts, [
                'codigoConta' => $info->account_code,
                'saldoDia' => $info->account_balance
            ]);
        } */

        // Criar objeto SimpleXMLElement
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><documento></documento>');

        // Adicionar atributos ao elemento <documento>
        $xml->addAttribute('codigoDocumento', $documentCode);
        $xml->addAttribute('cnpj', $cnpj);
        $xml->addAttribute('dataBase', $baseDate);
        $xml->addAttribute('tipoRemessa', $remittanceType);

        // Criar elemento <contas>
        $contasNode = $xml->addChild('contas');

        // Adicionar cada <conta> dentro de <contas>
        /*foreach ($accounts as $account) {
            $contaNode = $contasNode->addChild('conta');
            $contaNode->addAttribute('codigoConta', $account['codigoConta']);
            $contaNode->addAttribute('saldoDia', $account['saldoDia']);
        }*/

        foreach ($data as $info) {
            $contaNode = $contasNode->addChild('conta');
            $contaNode->addAttribute('codigoConta', $info->account_code);
            $contaNode->addAttribute('saldoDia', $info->account_balance == null ? sprintf("%.2f", 0) : sprintf("%.2f", $info->account_balance));
        }

        // Gerar o XML como string
        $xmlString = $xml->asXML();

        // Codificar a string XML em base64
        $xmlBase64 = base64_encode($xmlString);

        return response()->json([
            "success" => "Arquivo gerado com sucesso",
            "file_name" => "BACEN_4111_$request->base_date.xml",
            "mime_type" => "application/octet-stream",
            "base64" => $xmlBase64
        ]);

    }

}
