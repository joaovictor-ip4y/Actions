<?php

namespace App\Http\Controllers;

use App\Classes\ExcelExportClass;
use App\Libraries\Facilites;
use App\Models\PixStaticReceivePayment;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class PixStaticReceivePaymentController extends Controller
{
    protected function get(Request $request)
    {
       // ----------------- Check Account Verification ----------------- //
       $accountCheckService           = new AccountRelationshipCheckService();
       $accountCheckService->request  = $request;
       $accountCheckService->permission_id = [179, 260];
       $checkAccount                  = $accountCheckService->checkAccount();
       if(!$checkAccount->success){
           return response()->json(array("error" => $checkAccount->message));
       }
       // -------------- Finish Check Account Verification -------------- //


        $pix_static_receive_payment                 = new PixStaticReceivePayment();
        $pix_static_receive_payment->master_id      = $checkAccount->master_id;
        $pix_static_receive_payment->account_id     = $checkAccount->account_id;
        $pix_static_receive_payment->id             = $request->id;
        $pix_static_receive_payment->pix_static_id  = $request->pix_static_id;

        if($request->created_at_start != ''){
            $pix_static_receive_payment->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $pix_static_receive_payment->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->created_at_end != ''){
            $pix_static_receive_payment->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $pix_static_receive_payment->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        return response()->json($pix_static_receive_payment->get());
    }

    protected function getDetailed(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixStaticReceive             = new PixStaticReceivePayment();
        $pixStaticReceive->master_id  = $checkAccount->master_id;
        $pixStaticReceive->account_id = $checkAccount->account_id;
        $pixStaticReceive->status_id  = $request->status_id;
        $pixStaticReceive->onlyActive = $request->onlyActive;
        $pixStaticReceive->type_id    = $request->type_id;
        $pixStaticReceive->manager_id = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $pixStaticReceive->occurrence_date_start = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $pixStaticReceive->occurrence_date_end = $request->occurrence_date_end." 23:59:59.998";
        }

        return response()->json( $pixStaticReceive->pixStaticReceivePaymentsDetailed() );
    }

    protected function getPdf(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
       $accountCheckService           = new AccountRelationshipCheckService();
       $accountCheckService->request  = $request;
       $accountCheckService->permission_id = [179, 260];
       $checkAccount                  = $accountCheckService->checkAccount();
       if(!$checkAccount->success){
           return response()->json(array("error" => $checkAccount->message));
       }
       // -------------- Finish Check Account Verification -------------- //
      
        $pix_static_receive_payment                 = new PixStaticReceivePayment();
        $pix_static_receive_payment->master_id      = $checkAccount->master_id;
        $pix_static_receive_payment->account_id     = $checkAccount->account_id;
        $pix_static_receive_payment->id             = $request->id;
        $pix_static_receive_payment->pix_static_id  = $request->pix_static_id;

        if ($request->created_at_start != '') {
            $pix_static_receive_payment->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $pix_static_receive_payment->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if ($request->created_at_end != '') {
            $pix_static_receive_payment->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $pix_static_receive_payment->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $data = [];

        $facilites = new Facilites();

        foreach ($pix_static_receive_payment->get() as $item) {

            array_push($data, (object) [
                'payment_date'                  => $item->payment_date ? (\Carbon\Carbon::parse($item->payment_date)->format('d/m/Y')) : "",
                'value'                         => $item->value,
                'transaction_identification'    => $item->transaction_identification,
                'payer_name'                    => $item->payer_name,
                'payer_cpf_cnpj'                => $facilites->mask_cpf_cnpj($item->payer_cpf_cnpj)
            ]);
        }

        $file_name = "Ponto_Venda_Pix.pdf";
        $pdf       = PDF::loadView('reports/receipt_pix_static_payment', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [179, 260];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pix_static_receive_payment                 = new PixStaticReceivePayment();
        $pix_static_receive_payment->master_id      = $checkAccount->master_id;
        $pix_static_receive_payment->account_id     = $checkAccount->account_id;
        $pix_static_receive_payment->id             = $request->id;
        $pix_static_receive_payment->pix_static_id  = $request->pix_static_id;

        if ($request->created_at_start != '') {
            $pix_static_receive_payment->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $pix_static_receive_payment->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if ($request->created_at_end != '') {
            $pix_static_receive_payment->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $pix_static_receive_payment->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $header = collect([
            [
                'payment_date'                  => 'Data',
                'value'                         => 'Valor',
                'transaction_identification'    => 'Transação',
                'payer_name'                    => 'Pago Por',
            ]
        ]);

        $data = [];

        $facilites = new Facilites();

        foreach ($pix_static_receive_payment->get() as $item) {

            array_push($data, (object) [
                'payment_date'                  => $item->payment_date ? (\Carbon\Carbon::parse($item->payment_date)->format('d/m/Y')) : "",
                'value'                         => number_format($item->value ,2,',','.'),
                'transaction_identification'    => $item->transaction_identification,
                'payer_name'                    => $item->payer_name.' - '.$facilites->mask_cpf_cnpj($item->payer_cpf_cnpj),
            ]);
        }

        $pix_static_export         = new ExcelExportClass();
        $pix_static_export->value  = $header->merge($data);

        return response()->json(array("success" => "Planilha gerada com sucesso", "file_name" => "Movimentação em Ponto de Venda Pix.xlsx", "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "base64"=>base64_encode(Excel::raw($pix_static_export, \Maatwebsite\Excel\Excel::XLSX))));
    }
}



