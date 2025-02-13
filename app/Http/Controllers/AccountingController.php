<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Accounting;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;
use App\Classes\ExcelExportClass;
use Maatwebsite\Excel\Facades\Excel;
use App\Libraries\Facilites;

class AccountingController extends Controller
{
    public function getMovementData(Request $request)
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        return response()->json(Accounting::getMovementData($request->start_date, $request->end_date));
    }

    protected function getMovementDataExcel(Request $request)
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        $data = Accounting::getMovementData($request->start_date, $request->end_date);

        $items = [];

        array_push($items, (object) [
            'occurence_date' => 'Data Ocorrência',
            'occurrence_time' => 'Hora Ocorrência',
            'accounting_date' => 'Data Contábil',
            'value' => 'Valor',
            'balance' => 'Saldo',
            'movement_type' => 'Tipo de Movimento',
            'sub_movement_type' => 'Subtipo de Movimento',
            'fee_type' => 'Tipo de Tarifa',
            'account_number' => 'Conta',
            'register_name' => 'Cadastro',
            'register_cpf_cnpj' => 'CPF/CNPJ',
        ]);
        
        foreach($data as $info){
            array_push($items, (object) [
                'occurence_date' => \Carbon\Carbon::parse($info->occurence_date)->format('d/m/Y'),
                'occurrence_time' => $info->occurrence_time,
                'accounting_date' => \Carbon\Carbon::parse($info->accounting_date)->format('d/m/Y'),
                'value' => $info->value,
                'balance' => $info->balance,
                'movement_type' => $info->movement_type,
                'sub_movement_type' => $info->sub_movement_type,
                'fee_type' => $info->fee_type,
                'account_number' => (string) $info->account_number,
                'register_name' => $info->register_name,
                'register_cpf_cnpj' => Facilites::mask_cpf_cnpj($info->register_cpf_cnpj),
            ]);
        }

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha de movimentação gerada com sucesso", 
            "file_name" => "Movimentacao_".$request->start_date."__".$request->end_date.".xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", 
            "base64" => base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));
    }
}
