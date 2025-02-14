<?php

namespace App\Http\Controllers;

use App\Classes\Account\AccountMovementClass;
use App\Models\AccountMovement;
use App\Models\Account;
use App\Models\ApiConfig;
use App\Models\MovementType;
use App\Models\StatementRendimentoBank;
use App\Models\StatementBrasilBank;
use App\Models\StatementMoneyPlusBank;
use App\Models\TitleStatementRendimentoBank;
use App\Models\AccntAddMoneyBankCharging;
use App\Models\SimpleCharge;
use App\Models\AntecipationCharge;
use App\Models\SimpleChargeHistory;
use App\Models\Master;
use App\Models\TransferPayment;
use App\Models\BillPayment;
use App\Models\PayrollRelease;
use App\Models\UserRelationship;
use App\Models\CardMovement;
use App\Models\CardSaleMovement;
use App\Models\SendSms;
use App\Models\RegisterDetail;
use App\Models\Register;
use App\Models\RegisterMaster;
use App\Models\Bank;
use App\Models\Holiday;
use App\Models\RegisterRequest;
use App\Models\PayrollEmployeeDetail;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\ApiCelCoin;
use App\Libraries\ApiMoneyPlus;
use App\Libraries\Facilites;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\QrCodeGenerator\QrCodeGenerator;
use App\Libraries\SimpleOFX;
use App\Libraries\sendMail;
use App\Libraries\ApiZenviaWhatsapp;
use App\Models\AccntAddMoneyPix;
use App\Models\AuthorizationToken;
use App\Models\PixPayment;
use App\Models\PixPaymentV2;
use App\Models\PixReceivePayment;
use App\Models\PixStaticReceivePayment;
use App\Models\User;
use App\Models\UsrRltnshpPrmssn;
use App\Models\PixStaticReceive;
use App\Models\SystemFunctionMaster;
use App\Models\IndirectPixAddressingKey;
use App\Classes\ExcelExportClass;
use App\Models\AccountingClassification;
use App\Services\Account\MovementTaxService;
use App\Services\Account\MovementService;
use App\Services\Failures\sendFailureAlert;
use App\Services\Account\AccountCheckService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Classes\Account\AccountMovementFutureClass;
use App\Classes\Failure\TransactionFailureClass;
use App\Classes\Edenred\EdenredClass;
use PDF;
use Illuminate\Support\Facades\Log;

class AccountMovementController extends Controller
{
    protected function getAccountBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [173,254];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();
        $accountBalance              = 0;
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $accountBalance = $accountMovement->getAccountBalance()->balance;
        }
        return response()->json(array("success" => "", "balance" => $accountBalance));
    }

    protected function getAccountMovement(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountMovement->date_start           = $start_date." 00:00:00.000";
        $accountMovement->date_end             = $end_date." 23:59:59.998";
        $accountMovement->onlyActive           = 1;
        $accountMovement->onlyIn               = $request->onlyIn;
        $accountMovement->onlyOut              = $request->onlyOut;
        $accountMovement->onlyTransaction      = $request->onlyTransaction;
        $accountMovement->onlyFee              = $request->onlyFee;
        $accountMovement->user_id              = $request->user_id;
        $accountMovement->user_relationship_id = $request->user_relationship_id;
        $accountMovement->type_id              = $request->type_id;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        return response()->json($accountMovement->getAccountMovement());
    }

    protected function getMarginAccountMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [77];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $master = Master::where('id','=',$checkAccount->master_id)->first();
        if($master->margin_accnt_id != ''){
            $accountMovement             = new AccountMovement();
            $accountMovement->account_id = $master->margin_accnt_id;
            $accountMovement->master_id  = $checkAccount->master_id;
            $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
            $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
            if($request->start_date != ''){
                $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
            }
            if($request->end_date != ''){
                $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
            }
            $accountMovement->date_start = $start_date." 00:00:00.000";
            $accountMovement->date_end   = $end_date." 23:59:59.998";
            $accountMovement->onlyActive = 1;
            $accountMovement->accnt_origin_id = $request->origin_account_id;
            return response()->json($accountMovement->getAccountMovement());
        } else {
            return response()->json([]);
        }
    }

    protected function exportAccountMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [252, 317];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }

        $accountMovement->onlyIn          = $request->onlyIn;
        $accountMovement->onlyOut         = $request->onlyOut;
        $accountMovement->onlyTransaction = $request->onlyTransaction;
        $accountMovement->onlyFee         = $request->onlyFee;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 90){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em PDF para períodos superiores a 90 dias, por favor verifique o período informado e tente novamente"));
        }

        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->onlyActive = 1;



        //account movement
        $items = [];
        foreach($accountMovement->getAccountMovement() as $movementData){
            array_push($items, (object) [
                'date'        => \Carbon\Carbon::parse($movementData->date)->format('d/m/Y H:i'),
                'value'       => number_format($movementData->value,2,',','.'),
                'balance'     => number_format($movementData->balance,2,',','.'),
                'description' => $movementData->description
            ]);
        }

        //account data
        $account            = new Account();
        $account->id        = $checkAccount->account_id;
        $account->master_id = $checkAccount->master_id;
        $accountData        = $account->getAccounts()[0];

        $facilities = new Facilites();
        $master_name    = $accountData->master_name;
        $name           = $accountData->name;
        $cpf_cnpj       = $facilities->mask_cpf_cnpj($accountData->cpf_cnpj);
        $agency         = '001';
        $account_number = $facilities->mask_account($accountData->account_number);

        //account balance
        $accountBalance             = new AccountMovement();
        $accountBalance->account_id = $checkAccount->account_id;
        $accountBalance->master_id  = $checkAccount->master_id;
        $accountBalance->start_date = $accountMovement->date_end;
        $balance                    = number_format(0,2,',','.');
        if(isset( $accountBalance->getAccountBalance()->balance )){
            $balance = number_format($accountBalance->getAccountBalance()->balance,2,',','.');
        }

        //account previous balance
        $accountPreviousBalance             = new AccountMovement();
        $accountPreviousBalance->account_id = $checkAccount->account_id;
        $accountPreviousBalance->master_id  = $checkAccount->master_id;
        $accountPreviousBalance->start_date = $accountMovement->date_start;
        $previousBalance                    = number_format(0,2,',','.');
        if(isset( $accountPreviousBalance->getAccountBalance()->balance )){
            $previousBalance = number_format($accountPreviousBalance->getAccountBalance()->balance,2,',','.');
        }

        $data = (object) array(
            "master_name"         => $master_name,
            "name"                => $name,
            "cpf_cnpj"            => $cpf_cnpj,
            "agency"              => $agency,
            "account"             => $account_number,
            "account_description" => $accountData->account_description,
            "start_date"          => \Carbon\Carbon::parse($start_date)->format('d/m/Y'),
            "end_date"            => \Carbon\Carbon::parse($end_date)->format('d/m/Y'),
            "previous_balance"    => $previousBalance,
            "balance"             => $balance,
            "movement_data"       => $items
        );
        $file_name = "Extrato_Conta_".$accountData->account_number."_De_".$request->start_date."_Ate_".$request->end_date.".pdf";
        $pdf       = PDF::loadView('reports/account_movement', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "base64" => base64_encode($pdf) ));
    }


    protected function exportAccountMovementExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [252, 317];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }

        $accountMovement->onlyIn          = $request->onlyIn;
        $accountMovement->onlyOut         = $request->onlyOut;
        $accountMovement->onlyTransaction = $request->onlyTransaction;
        $accountMovement->onlyFee         = $request->onlyFee;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 366){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em Excel para períodos superiores a um ano, por favor verifique o período informado e tente novamente"));
        }

        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->onlyActive = 1;

        //account movement
        $items = [];

        //account data
        $account            = new Account();
        $account->id        = $checkAccount->account_id;
        $account->master_id = $checkAccount->master_id;
        $accountData        = $account->getAccounts()[0];

        $facilities = new Facilites();
        $master_name    = $accountData->master_name;
        $name           = $accountData->name;
        $cpf_cnpj       = $facilities->mask_cpf_cnpj($accountData->cpf_cnpj);
        $agency         = '001';
        $account_number = $facilities->mask_account($accountData->account_number);

        //account balance
        $accountBalance             = new AccountMovement();
        $accountBalance->account_id = $checkAccount->account_id;
        $accountBalance->master_id  = $checkAccount->master_id;
        $accountBalance->start_date = $accountMovement->date_end;
        $balance                    = number_format(0,2,',','.');
        if(isset( $accountBalance->getAccountBalance()->balance )){
            $balance = $accountBalance->getAccountBalance()->balance;
        }

        //account previous balance
        $accountPreviousBalance             = new AccountMovement();
        $accountPreviousBalance->account_id = $checkAccount->account_id;
        $accountPreviousBalance->master_id  = $checkAccount->master_id;
        $accountPreviousBalance->start_date = $accountMovement->date_start;
        $previousBalance                    = number_format(0,2,',','.');
        if(isset( $accountPreviousBalance->getAccountBalance()->balance )){
            $previousBalance = $accountPreviousBalance->getAccountBalance()->balance;
        }

        array_push($items, (object) [
            'date'        => "Extrato Conta Digital ".$master_name,
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "Conta",
            'value'       => "",
            'balance'     => "",
            'description' => $account_number
        ]);

        array_push($items, (object) [
            'date'        => "Titular",
            'value'       => "",
            'balance'     => "",
            'description' => $name.' - '.$cpf_cnpj
        ]);

        array_push($items, (object) [
            'date'        => "Data Inicial",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($start_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "Data Final",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($end_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "Data",
            'value'       => "Valor",
            'balance'     => "Saldo",
            'description' => "Histórico"
        ]);

        array_push($items, (object) [
            'date'        => \Carbon\Carbon::parse($start_date)->format('d/m/Y')." 00:00",
            'value'       => $previousBalance,
            'balance'     => $previousBalance,
            'description' => "Saldo Anterior"
        ]);

        foreach($accountMovement->getAccountMovement() as $movementData){
            array_push($items, (object) [
                'date'        => \Carbon\Carbon::parse($movementData->date)->format('d/m/Y H:i'),
                'value'       => $movementData->value,
                'balance'     => $movementData->balance,
                'description' => $movementData->description
            ]);
        }

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha extrato gerada com sucesso",
            "file_name" => "Extrato.xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "base64"=>base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));

    }

    protected function exportAccountMovementExcelAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }

        $accountMovement->onlyIn          = $request->onlyIn;
        $accountMovement->onlyOut         = $request->onlyOut;
        $accountMovement->onlyTransaction = $request->onlyTransaction;
        $accountMovement->onlyFee         = $request->onlyFee;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 366){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em Excel para períodos superiores a um ano, por favor verifique o período informado e tente novamente"));
        }

        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->type_id    = $request->type_id;
        $accountMovement->onlyActive = 1;

        //account movement
        $items = [];

        //account data
        $account            = new Account();
        $account->id        = $checkAccount->account_id;
        $account->master_id = $checkAccount->master_id;
        $accountData        = $account->getAccounts()[0];

        $facilities = new Facilites();
        $master_name    = $accountData->master_name;
        $name           = $accountData->name;
        $cpf_cnpj       = $facilities->mask_cpf_cnpj($accountData->cpf_cnpj);
        $agency         = '001';
        $account_number = $facilities->mask_account($accountData->account_number);

        //account balance
        $accountBalance             = new AccountMovement();
        $accountBalance->account_id = $checkAccount->account_id;
        $accountBalance->master_id  = $checkAccount->master_id;
        $accountBalance->start_date = $accountMovement->date_end;
        $balance                    = number_format(0,2,',','.');
        if(isset( $accountBalance->getAccountBalance()->balance )){
            $balance = $accountBalance->getAccountBalance()->balance;
        }

        //account previous balance
        $accountPreviousBalance             = new AccountMovement();
        $accountPreviousBalance->account_id = $checkAccount->account_id;
        $accountPreviousBalance->master_id  = $checkAccount->master_id;
        $accountPreviousBalance->start_date = $accountMovement->date_start;
        $previousBalance                    = number_format(0,2,',','.');
        if(isset( $accountPreviousBalance->getAccountBalance()->balance )){
            $previousBalance = $accountPreviousBalance->getAccountBalance()->balance;
        }

        array_push($items, (object) [
            'date'        => "Extrato Conta Digital ".$master_name,
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        if(isset($request->type_id)) {
            $mvmnt_tps = '';
            if(count($request->type_id) >= 1) {
                foreach($request->type_id as $tpId) {
                    if($mvmnt_type = MovementType::where('id', '=', $tpId)->first()) {
                        $mvmnt_tps.= $mvmnt_type->description.', ';
                    }
                }
                $mvmnt_tps = mb_substr($mvmnt_tps, 0, -2);
            }

            array_push($items, (object) [
                'date'        => "Tipo(s) de Lançamento(s)",
                'value'       => "",
                'balance'     => "",
                'description' => $mvmnt_tps
            ]);

        }

        array_push($items, (object) [
            'date'        => "Data Inicial",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($start_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "Data Final",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($end_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'                 => "Data",
            'register_description' => "Cadastro",
            'account_description'  => "Conta",
            'value'                => "Valor",
            'balance'              => "Saldo",
            'description'          => "Histórico"
        ]);

        array_push($items, (object) [
            'date'        => \Carbon\Carbon::parse($start_date)->format('d/m/Y')." 00:00",
            'value'       => $previousBalance,
            'balance'     => $previousBalance,
            'description' => "Saldo Anterior"
        ]);

        foreach($accountMovement->getAccountMovement() as $movementData){
            array_push($items, (object) [
                'date'                 => \Carbon\Carbon::parse($movementData->date)->format('d/m/Y H:i'),
                'register_description' => $movementData->register_description,
                'account_description'  => $movementData->account_description,
                'value'                => $movementData->value,
                'balance'              => $movementData->balance,
                'description'          => $movementData->description
            ]);
        }
        //data, cadastro, conta, valor, saldo conta, histórico

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha extrato gerada com sucesso",
            "file_name" => "Extrato.xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "base64"=>base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));

    }

    protected function exportAccountMovementPdfAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }

        $accountMovement->onlyIn          = $request->onlyIn;
        $accountMovement->onlyOut         = $request->onlyOut;
        $accountMovement->onlyTransaction = $request->onlyTransaction;
        $accountMovement->onlyFee         = $request->onlyFee;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 366){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em Excel para períodos superiores a um ano, por favor verifique o período informado e tente novamente"));
        }

        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->type_id    = $request->type_id;
        $accountMovement->onlyActive = 1;


        $accountData = null;

        //account data
        if(!empty($request->account_id)){
            $account            = new Account();
            $account->id        = $request->account_id;
            $accountData        = $account->getAccounts()[0];
        }

        //account movement
        $items = [];

        $facilites = new Facilites();

        foreach ($accountMovement->getAccountMovement() as $accMovementData) {

            array_push($items, (object) [
                'date'                 => \Carbon\Carbon::parse($accMovementData->date)->format('d/m/Y H:i'),
                'register_description' => $accMovementData->register_description,
                'account_description'  => $accMovementData->account_description,
                'value'                => $accMovementData->value,
                'balance'              => $accMovementData->balance,
                'description'          => $accMovementData->description
            ]);

        }

        $mvmnt_tps_array = [];

        if(isset($request->type_id)) {
            $mvmnt_tps = '';
            if(count($request->type_id) >= 1) {
                foreach($request->type_id as $tpId) {
                    if($mvmnt_type = MovementType::where('id', '=', $tpId)->first()) {
                        $mvmnt_tps.= $mvmnt_type->description.', ';
                    }
                }
                $mvmnt_tps = mb_substr($mvmnt_tps, 0, -2);
            }

            array_push($mvmnt_tps_array, (object) [
                'description' => $mvmnt_tps
            ]);

        }


        $data = (object) array(
            "account_movement_data"  => $items,
            "date_start"             => $accountMovement->date_start,
            "date_end"               => $accountMovement->date_end,
            "mvmnt_tps"              => !empty($mvmnt_tps_array) ? $mvmnt_tps_array[0]->description : null,
            "account_description"    => !empty($accountData->description) ? $accountData->description : null
        );

        $file_name = "Extrato_Conta_Digital.pdf";
        $pdf       = PDF::loadView('reports/account_movement_extrato_conta_digital', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getMasterAccountMovement(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }

        if($request->onlyNotReversal == 1){
            $accountCheckService->permission_id = [55];
            $checkAccount                       = $accountCheckService->checkAccount();
            if(!$checkAccount->success){
                return response()->json(array("error" => $checkAccount->message));
            }
        }

        // -------------- Finish Check Account Verification -------------- //

        $accountMovement              = new AccountMovement();
        //$accountMovement->master_id   = $checkAccount->master_id;
        //$accountMovement->register_id = $request->register_id;
        $accountMovement->account_id  = $checkAccount->account_id;
        $start_date                   = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                     = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";

        $accountMovement->onlyActive = 1;

        $accountMovement->onlyIn     = $request->onlyIn;
        $accountMovement->onlyOut    = $request->onlyOut;

        $accountMovement->onlyTransaction = $request->onlyTransaction;
        $accountMovement->onlyFee         = $request->onlyFee;

        $accountMovement->type_id         = $request->type_id;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        return response()->json( $accountMovement->getMasterAccountMovement() );
    }

    protected function getMasterCardMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new CardMovement();
        $card_movement = $accountMovement->getCardMovement()->card_movement;
        return response()->json( array("success" => "", "card_movement" => $card_movement) );
    }

    protected function exportMasterAccountMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [77];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement              = new AccountMovement();
        $accountMovement->master_id   = $checkAccount->master_id;
        $accountMovement->register_id = $request->register_id;
        $accountMovement->account_id  = $request->account_id;
        $start_date                   = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                     = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->onlyActive = 1;

        $accountMovement->onlyIn          = $request->onlyIn;
        $accountMovement->onlyOut         = $request->onlyOut;
        $accountMovement->onlyTransaction = $request->onlyTransaction;
        $accountMovement->onlyFee         = $request->onlyFee;

        if($request->onlyIn == 1 && $request->onlyOut == 1){
            $accountMovement->onlyIn  = null;
            $accountMovement->onlyOut = null;
        }

        if($request->onlyTransaction == 1 && $request->onlyFee == 1){
            $accountMovement->onlyTransaction = null;
            $accountMovement->onlyFee         = null;
        }

        //account movement
        $items = [];
        foreach($accountMovement->getAccountMovement() as $movementData){
            array_push($items, (object) [
                'date'        => \Carbon\Carbon::parse($movementData->date)->format('d/m/Y H:i'),
                'value'       => number_format($movementData->value,2,',','.'),
                'balance'     => number_format($movementData->balance,2,',','.'),
                'description' => $movementData->description
            ]);
        }
        //account data
        $account            = new Account();
        $account->master_id = $checkAccount->master_id;
        $accountData        = $account->getAccounts()[0];
        $facilities = new Facilites();
        $master_name    = $accountData->master_name;
        $name           = $accountData->name;
        $cpf_cnpj       = $facilities->mask_cpf_cnpj($accountData->cpf_cnpj);
        $agency         = '001';
        $account_number = $facilities->mask_account($accountData->account_number);

        //account balance
        $accountBalance             = new AccountMovement();
        $accountBalance->master_id  = $checkAccount->master_id;
        $accountBalance->start_date = $accountMovement->date_end;
        $balance                    = number_format(0,2,',','.');
        if(isset( $accountBalance->getAccountBalance()->balance )){
            $balance = number_format($accountBalance->getAccountBalance()->balance,2,',','.');
        }

        //account previous balance
        $accountPreviousBalance             = new AccountMovement();
        $accountPreviousBalance->master_id  = $checkAccount->master_id;
        $accountPreviousBalance->start_date = $accountMovement->date_start;
        $previousBalance                    = number_format(0,2,',','.');
        if(isset( $accountPreviousBalance->getAccountBalance()->balance )){
            $previousBalance = number_format($accountPreviousBalance->getAccountBalance()->balance,2,',','.');
        }

        $data = (object) array(
            "master_name"       => $master_name,
            "name"              => $name,
            "cpf_cnpj"          => $cpf_cnpj,
            "agency"            => $agency,
            "account"           => $account_number,
            "start_date"        => \Carbon\Carbon::parse($start_date)->format('d/m/Y'),
            "end_date"          => \Carbon\Carbon::parse($end_date)->format('d/m/Y'),
            "previous_balance"  => $previousBalance,
            "balance"           => $balance,
            "movement_data"     => $items
        );
        $file_name = "Extrato_Conta_".$accountData->account_number."_De_".$request->start_date."_Ate_".$request->end_date.".pdf";
        $pdf       = PDF::loadView('reports/account_movement', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "base64" => base64_encode($pdf) ));
    }

    protected function getDayInputValues(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [173,254];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accountMovement->end_date   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        $dayInputValue               = 0;
        if(isset( $accountMovement->getDayInputValues()->dayInputValue )){
            $dayInputValue = $accountMovement->getDayInputValues()->dayInputValue;
        }
        return response()->json(array("success" => "", "dayInputValue" => $dayInputValue));
    }

    protected function getDayOutputValues(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [173,254];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accountMovement->end_date   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        $dayOutputValue              = 0;
        if(isset( $accountMovement->getDayOutputValues()->dayOutputValue )){
            $dayOutputValue = $accountMovement->getDayOutputValues()->dayOutputValue;
        }
        return response()->json(array("success" => "", "dayOutputValue" => $dayOutputValue));
    }

    protected function getMasterDayInputValues(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accountMovement->end_date   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        $dayInputValue               = 0;
        if(isset( $accountMovement->getDayInputValues()->dayInputValue )){
            $dayInputValue = $accountMovement->getDayInputValues()->dayInputValue;
        }
        return response()->json(array("success" => "", "dayInputValue" => $dayInputValue));
    }

    protected function getMasterDayOutputValues(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accountMovement->end_date   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        $dayOutputValue              = 0;
        if(isset( $accountMovement->getDayOutputValues()->dayOutputValue )){
            $dayOutputValue = $accountMovement->getDayOutputValues()->dayOutputValue;
        }
        return response()->json(array("success" => "", "dayOutputValue" => $dayOutputValue));
    }

    protected function getMasterAccountBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();
        $accountBalance              = 0;
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $accountBalance = $accountMovement->getAccountBalance()->balance;
        }
        return response()->json(array("success" => "", "balance" => $accountBalance));
    }

    protected function getMarginAccountBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountBalance = 0;
        $master         = Master::where('id','=',$checkAccount->master_id)->first();
        if($master->margin_accnt_id != ''){
            $accountMovement             = new AccountMovement();
            $accountMovement->account_id = $master->margin_accnt_id;
            $accountMovement->master_id  = $checkAccount->master_id;
            $accountMovement->start_date = \Carbon\Carbon::now();
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $accountBalance = $accountMovement->getAccountBalance()->balance;
            }
        }
        return response()->json(array("success" => "", "balance" => $accountBalance));
    }

    protected function getMasterAccountBancoRendimentoBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

       /* $apiConfig                                          = new ApiConfig();
        $apiConfig->master_id                               = $checkAccount->master_id;
        $apiConfig->api_id                                  = 1;
        $apiConfig->onlyActive                              = 1;
        $apiData                                            = $apiConfig->getApiConfig()[0];
        $apiRendimento                                      = new ApiBancoRendimento();
        $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
        $rendimentoBalance_securitizadora                   = $apiRendimento->saldoConsultar();
        $balance_securitizadora                             = 0;
        $balance_pagamentos                                 = 0;
        $balance                                            = 0;
        if(isset($rendimentoBalance_securitizadora->body->value->saldoDisponivel)){
            $balance_securitizadora = $rendimentoBalance_securitizadora->body->value->saldoDisponivel;
        } */

        $balance_securitizadora = 0;

        $apiConfig                                          = new ApiConfig();
        $apiConfig->master_id                               = $checkAccount->master_id;
        $apiConfig->api_id                                  = 10;
        $apiConfig->onlyActive                              = 1;
        $apiData                                            = $apiConfig->getApiConfig()[0];
        $apiRendimento                                      = new ApiBancoRendimento();
        $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
        $rendimentoBalance_pagamentos                       = $apiRendimento->saldoConsultar();
        if(isset($rendimentoBalance_pagamentos->body->value->saldoDisponivel)){
            $balance_pagamentos = $rendimentoBalance_pagamentos->body->value->saldoDisponivel;
        }

        return response()->json(array(
            "success" => "",
            "balance" => $balance,
            "balance_securitizadora" => $balance_securitizadora,
            "balance_pagamentos" => $balance_pagamentos,
            "saldos" => $rendimentoBalance
        ));
    }

    protected function getMasterAccountBancoRendimentoMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig                                          = new ApiConfig();
        $apiConfig->master_id                               = $checkAccount->master_id;
        $apiConfig->api_id                                  = 1;
        $apiConfig->onlyActive                              = 1;
        $apiData                                            = $apiConfig->getApiConfig()[0];
        $apiRendimento                                      = new ApiBancoRendimento();
        $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
        $apiRendimento->con_data_inicio                     = $request->start_dt;
        $apiRendimento->con_data_fim                        = $request->end_dt;
        $rendimentoBalance                                  = $apiRendimento->extratoConsultar();
        return response()->json(array("success" => "", "movement" => $rendimentoBalance));
    }

    protected function getMasterAccountBancoRendimentoMovementTitle(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig                                          = new ApiConfig();
        $apiConfig->master_id                               = $checkAccount->master_id;
        $apiConfig->api_id                                  = 1;
        $apiConfig->onlyActive                              = 1;
        $apiData                                            = $apiConfig->getApiConfig()[0];
        $apiRendimento                                      = new ApiBancoRendimento();
        $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
        $apiRendimento->tit_data_inicio                     = $request->start_dt;
        $apiRendimento->tit_data_fim                        = $request->end_dt;
        $rendimentoBalance                                  = $apiRendimento->tituloConsultarFrancesinha();
        return response()->json(array("success" => "", "movement" => $rendimentoBalance));
    }

    public function createMovementRendimento()
    {
        $apiConfig                                          = new ApiConfig();
        $apiConfig->api_id                                  = 10;
        $apiConfig->onlyActive                              = 1;

        foreach($apiConfig->getApiConfig() as $apiData){
            $apiRendimento                                      = new ApiBancoRendimento();
            $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
            $apiRendimento->con_data_inicio                     = (\Carbon\Carbon::parse( (\Carbon\Carbon::now() )->addDays(-1) ))->format('Y-m-d'); // $request->start_dt;//
            $apiRendimento->con_data_fim                        = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d'); // $request->end_dt;
            $rendimentoBalance                                  = $apiRendimento->extratoConsultar();
            if(isset($rendimentoBalance->body->isSuccess)){
                if($rendimentoBalance->body->isSuccess){
                    $movement = [];
                    if(sizeOf($rendimentoBalance->body->value->itens) > 0){
                        $i             = 0;
                        $day           = null;
                        $tedMovementId = null;
                        foreach($rendimentoBalance->body->value->itens as $item){

                            //Define id do lcto baseado no dia e no tipo
                            if($day == null){
                                $day = $item->dataLancto;
                                $d                    = 1;
                                $d_tar_inst_banc      = 1;
                                $d_tar_reg_tit        = 1;
                                $d_tar_bx_tit         = 1;
                                $d_tit_liq            = 1;
                                $d_pag_comp           = 1;
                                $d_pag_conv           = 1;
                                $d_ted_dev            = 1;
                                $d_ted_rec            = 1;
                                $d_ted_env            = 1;
                                $d_tar_ted            = 1;
                                $d_pag_rend           = 1;
                                $d_pgt_reg            = 1;
                                $d_f_pgt_reg          = 1;
                                $d_tit_cob_reg        = 1;
                                $d_pag_trib_mun       = 1;
                                $d_env_pgto_pix       = 1;
                                $d_rec_pgto_pix       = 1;
                                $d_est_pgto_bol_prp   = 1;
                                $d_rcto_cobranca      = 1;
                                $d_conv_tim           = 1;
                                $d_pag_conv_2         = 1;
                                $d_dare_sc            = 1;
                                $d_ted_rec_lq         = 1;
                                $d_ted_env_lq         = 1;
                                $d_ted_dev_lq         = 1;
                                $d_tit_cob_reg_mp     = 1;
                                $d_compr_cdb          = 1;
                                $d_remun_setup        = 1;
                                $d_cred_pgto_rej      = 1;
                                $d_transf_m_titular   = 1;
                                $d_transf_int_rec_pix = 1;
                                $d_transf_int_env_pix = 1;
                                $d_resg_cdb_cdi       = 1;
                                $d_compr_tit          = 1;
                                $d_vend_tit           = 1;
                                $d_est_resg_cdb_cdi   = 1;
                                $d_cst_selic_cetip    = 1;
                                $d_rec_desp_div       = 1;
                                $d_rem_franq_min      = 1;
                            }

                            if($day != $item->dataLancto){
                                $day = $item->dataLancto;
                                $d                    = 1;
                                $d_tar_inst_banc      = 1;
                                $d_tar_reg_tit        = 1;
                                $d_tar_bx_tit         = 1;
                                $d_tit_liq            = 1;
                                $d_pag_comp           = 1;
                                $d_pag_conv           = 1;
                                $d_ted_dev            = 1;
                                $d_ted_rec            = 1;
                                $d_ted_env            = 1;
                                $d_tar_ted            = 1;
                                $d_pag_rend           = 1;
                                $d_pgt_reg            = 1;
                                $d_f_pgt_reg          = 1;
                                $d_tit_cob_reg        = 1;
                                $d_pag_trib_mun       = 1;
                                $d_env_pgto_pix       = 1;
                                $d_rec_pgto_pix       = 1;
                                $d_est_pgto_bol_prp   = 1;
                                $d_rcto_cobranca      = 1;
                                $d_conv_tim           = 1;
                                $d_pag_conv_2         = 1;
                                $d_dare_sc            = 1;
                                $d_ted_rec_lq         = 1;
                                $d_ted_env_lq         = 1;
                                $d_ted_dev_lq         = 1;
                                $d_tit_cob_reg_mp     = 1;
                                $d_compr_cdb          = 1;
                                $d_remun_setup        = 1;
                                $d_cred_pgto_rej      = 1;
                                $d_transf_m_titular   = 1;
                                $d_transf_int_rec_pix = 1;
                                $d_transf_int_env_pix = 1;
                                $d_resg_cdb_cdi       = 1;
                                $d_compr_tit          = 1;
                                $d_vend_tit           = 1;
                                $d_est_resg_cdb_cdi   = 1;
                                $d_cst_selic_cetip    = 1;
                                $d_rec_desp_div       = 1;
                                $d_rem_franq_min      = 1;
                            }

                            //Em caso de ted recebida,define dados bancarios de quem enviou ted
                            $bank          = null;
                            $agency        = null;
                            $account       = null;
                            $account_owner = null;

                            //define movement id, se for ted recebida, vincular a primeira transf encontrada com id dia, trazer sempre por ordem?
                            $movementId = null;
                            if($item->historico->descricao == "PAGTO DE CONTAS DE CONVENIO" or $item->historico->descricao == "PAGTO FICHAS DE COMPENSACAO" or $item->historico->descricao == "TED ENVIADA"){
                                if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                    $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                }
                            }

                            if($item->natureza == "C"){
                                $value = $item->valor;
                            } else{
                                $value = ($item->valor * -1);
                            }

                            switch($item->historico->codigo){
                                case '00058': //tarifa instrução bancária
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1058'. $d_tar_inst_banc;
                                    $d_tar_inst_banc++;
                                break;
                                case '00081': //tarifa registro título
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1081'.$d_tar_reg_tit;
                                    $d_tar_reg_tit++;
                                break;
                                case '00084': //tarifa baixa título
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1084'.$d_tar_bx_tit;
                                    $d_tar_bx_tit++;
                                break;
                                case '00073': //título liquidado
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1073'.$d_tit_liq;
                                    $d_tit_liq++;
                                break;
                                case '02000': //pagamento ficha compensação
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10200'.$d_pag_comp;
                                    $d_pag_comp++;

                                    if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                        $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                    }
                                break;
                                case '02001': //pagamento conta convenio
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10201'.$d_pag_conv;
                                    $d_pag_conv++;

                                    if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                        $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                    }
                                break;
                                case '07561': //pagamento conta convenio
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'107561'.$d_pag_conv;
                                    $d_pag_conv++;

                                    if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                        $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                    }
                                break;
                                case '03411': //pagamento conta convenio
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'103411'.$d_pag_conv;
                                    $d_pag_conv++;

                                    if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                        $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                    }
                                break;
                                case '00758': //pagamento boleto rendimento
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10758'.$d_pag_rend;
                                    $d_pag_rend++;

                                    if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                        $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                    }
                                break;
                                case '07563': //crédito pagto rejeitado - estorno pagamento
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'107563'.$d_pgt_reg;
                                    $d_pgt_reg++;

                                break;
                                case '00752': //EST DE PAGTO FICHA COMPENSACAO
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10752'.$d_f_pgt_reg;
                                    $d_f_pgt_reg++;

                                break;
                                case '00701': //devolução de ted
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10701'.$d_ted_dev;
                                    $d_ted_dev++;
                                break;
                                case '00702': //ted recebida
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10702'.$d_ted_rec;
                                    $d_ted_rec++;

                                    //Verifica dados de ted recebida
                                    if (isset((explode("-",substr(trim($item->historico->complemento), 3)))[0])){
                                        $bank = ltrim((explode("-",substr(trim($item->historico->complemento), 3)))[0],0);
                                    }
                                    if (isset((explode("-",substr(trim($item->historico->complemento), 3)))[1])){
                                        $agency = ltrim((explode("-",substr(trim($item->historico->complemento), 3)))[1],0);
                                    }
                                    if (isset((explode("-",substr(trim($item->historico->complemento), 3)))[2])){
                                        $account = ltrim((explode("-",substr(trim($item->historico->complemento), 3)))[2],0);
                                    }
                                    if (isset((explode("-",substr(trim($item->historico->complemento), 3)))[3])){
                                        $account_owner = (explode("-",substr(trim($item->historico->complemento), 3)))[3];
                                    }
                                break;
                                case '00700': //ted enviada
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'10700'.$d_ted_env;
                                    $d_ted_env++;

                                    if(isset((array_reverse(explode(" ",trim($item->historico->complemento))))[0])){
                                        $movementId = (int) (array_reverse(explode(" ",trim($item->historico->complemento))))[0];
                                        $tedMovementId = $movementId;
                                    }
                                break;
                                case '00233': //tarifa de ted
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1000233'.$d_tar_ted;
                                    $d_tar_ted++;
                                    $movementId = $tedMovementId;
                                break;
                                case '00679': //tit cob regularizada - Estorno
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1000679'.$d_tit_cob_reg;
                                    $d_tit_cob_reg++;
                                break;
                                case '02200': //pagamento tributo municipal
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1002200'.$d_pag_trib_mun;
                                    $d_pag_trib_mun++;
                                break;
                                case '01987': //Env Pgto Pix
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1001987'.$d_env_pgto_pix;
                                    $d_env_pgto_pix++;
                                break;
                                case '01989': //Rec Pgto Pix
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1001989'.$d_rec_pgto_pix;
                                    $d_rec_pgto_pix++;
                                break;
                                case '00759': //Est Pgto Boleto Próprio
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00759'.$d_est_pgto_bol_prp;
                                    $d_est_pgto_bol_prp++;
                                break;
                                case '00039': //Recto Cobranca
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00039'.$d_rcto_cobranca;
                                    $d_rcto_cobranca++;
                                break;
                                case '07610': //Convenio Tim
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'07610'.$d_conv_tim;
                                    $d_conv_tim++;
                                break;
                                case '05000': //Convenio
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'07610'.$d_pag_conv_2;
                                    $d_pag_conv_2++;
                                break;
                                case '00798': //Dare  SC
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'07610'.$d_dare_sc;
                                    $d_dare_sc++;
                                break;
                                case '05552': //ted lq recebida
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'05552'.$d_ted_rec_lq;
                                    $d_ted_rec_lq++;
                                break;
                                case '05550': //ted lq enviada
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'05550'.$d_ted_env_lq;
                                    $d_ted_env_lq++;
                                break;
                                case '05551': //ted lq devolvida
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'05551'.$d_ted_dev_lq;
                                    $d_ted_dev_lq++;
                                break;
                                case '05553': //ted lq devolvida
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'05553'.$d_ted_dev_lq;
                                    $d_ted_dev_lq++;
                                break;
                                case '07600': //TIT COB REGULARIZADA MP
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'07600'.$d_tit_cob_reg_mp;
                                    $d_tit_cob_reg_mp++;
                                break;
                                case '00089': //Compra de CDB
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00089'.$d_compr_cdb;
                                    $d_compr_cdb++;
                                break;
                                case '07700': //Remuneração de Setup
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'07700'.$d_remun_setup;
                                    $d_remun_setup++;
                                break;
                                case '03413': //Credito pgto rejeitado
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'03413'.$d_cred_pgto_rej;
                                    $d_remun_setup++;
                                break;
                                case '00120': //Transf mesma titularidade
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00120'.$d_transf_m_titular;
                                    $d_transf_m_titular++;
                                break;
                                case '01986': //Transf Int Rec Pgto Pix
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'01986'.$d_transf_int_rec_pix;
                                    $d_transf_int_rec_pix++;
                                break;
                                case '01985': //Transf Int Env Pgto Pix
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'01985'.$d_transf_int_env_pix;
                                    $d_transf_int_env_pix++;
                                break;
                                case '00020': //Resgate cdb/cdi
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00020'.$d_resg_cdb_cdi;
                                    $d_resg_cdb_cdi++;
                                break;
                                case '00088': //Compra título
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00088'.$d_compr_tit;
                                    $d_compr_tit++;
                                break;
                                case '00087': //Venda título
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00087'.$d_vend_tit;
                                    $d_vend_tit++;
                                break;
                                case '00256': //Est Resg CDB/CDI
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00256'.$d_est_resg_cdb_cdi;
                                    $d_est_resg_cdb_cdi++;
                                break;
                                case '00202': //Custo Selic / CETIP
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'00202'.$d_cst_selic_cetip;
                                    $d_cst_selic_cetip++;
                                break;
                                case '08600': //Custo Selic / CETIP
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'08600'.$d_rec_desp_div;
                                    $d_rec_desp_div++;
                                break;
                                case '07716': //REMUNERACAO DE FRANQUIA MINIMA
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'07716'.$d_rem_franq_min;
                                    $d_rem_franq_min++;
                                break;
                                default:
                                    $dayId = (\Carbon\Carbon::parse( $day ))->format('Ymd').'1000'.($d);
                                    $d++;
                                    $sendFailureAlert               = new sendFailureAlert();
                                    $sendFailureAlert->title        = 'Extrato Rendimento';
                                    $sendFailureAlert->errorMessage = 'Tipo de código lançamento não definido para conta pagamentos | id lcto: '.$dayId.' | Código: '.$item->historico->codigo.' | Descrição '.$item->historico->descricao.' | Valor: '. $value;
                                    $sendFailureAlert->sendFailures();
                                break;
                            }

                            if($movementId == 0){
                                $movementId = null;
                            }

                            if( StatementRendimentoBank::where('day_id','=',$dayId)->count() == 0 ){
                                switch($item->historico->codigo){
                                    case '07563 ':
                                        $sendFailureAlert               = new TransactionFailureClass();
                                        $sendFailureAlert->title        = 'Pagamento Rejeitado';
                                        $sendFailureAlert->errorMessage = 'Atenção, pagamento rejeitado no Banco Rendimento | id lcto: '.$dayId.' | Código: '.$item->historico->codigo.' | Descrição '.$item->historico->descricao.' | Valor: '.$value;
                                        $sendFailureAlert->sendFailures();
                                    break;
                                    case '00752':
                                        $sendFailureAlert               = new TransactionFailureClass();
                                        $sendFailureAlert->title        = 'Pagamento Rejeitado';
                                        $sendFailureAlert->errorMessage = 'Atenção, pagamento rejeitado no Banco Rendimento | id lcto: '.$dayId.' | Código: '.$item->historico->codigo.' | Descrição '.$item->historico->descricao.' | Valor: '.$value;
                                        $sendFailureAlert->sendFailures();
                                    break;
                                    case '03413':
                                        $sendFailureAlert               = new TransactionFailureClass();
                                        $sendFailureAlert->title        = 'Pagamento Rejeitado';
                                        $sendFailureAlert->errorMessage = 'Atenção, pagamento rejeitado no Banco Rendimento | id lcto: '.$dayId.' | Código: '.$item->historico->codigo.' | Descrição '.$item->historico->descricao.' | Valor: '.$value;
                                        $sendFailureAlert->sendFailures();
                                    break;
                                    case '00701': //ted devolvida

                                        $sendMail               = new sendMail();
                                        $sendMail->to_mail      = 'regularizacao@ip4y.com.br';
                                        $sendMail->to_name      = 'Regularizacao';
                                        $sendMail->send_cc      = 0;
                                        $sendMail->to_cc_mail   = '';
                                        $sendMail->to_cc_name   = '';
                                        $sendMail->send_cco     = 0;
                                        $sendMail->to_cco_mail  = '';
                                        $sendMail->to_cco_name  = '';
                                        $sendMail->attach_pdf   = 0;
                                        $sendMail->attach_path  = '';
                                        $sendMail->attach_file  = '';
                                        $sendMail->subject      = 'Falha Conta Digital - TED Devolvida';
                                        $sendMail->email_layout = 'emails/confirmEmailAccount';
                                        $sendMail->bodyMessage  = "
                                            Atenção, a TED abaixo foi devolvida pelo Banco Rendimento.<br><br>
                                            <strong>Dados da TED: </strong>".trim($item->historico->complemento)."<br>
                                            <strong>Valor: </strong>".number_format($item->valor, 2, ',','.')."<br><br>
                                            Verifique os dados para realizar a devolução ao cliente.
                                        ";
                                        $sendMail->send();

                                        $apiConfigSMS                  = new ApiConfig();
                                        $apiConfigSMS->master_id       = (int) $apiData->master_id;
                                        $apiConfigSMS->api_id          = 3;
                                        $apiConfigSMS->onlyActive      = 1;
                                        $apiDataZenvia                 = $apiConfigSMS->getApiConfig()[0];
                                        $apiZenviaSMS                  = new ApiZenviaSMS();
                                        $apiZenviaSMS->api_address     = Crypt::decryptString($apiDataZenvia->api_address);
                                        $apiZenviaSMS->authorization   = Crypt::decryptString($apiDataZenvia->api_authentication);

                                        /*$sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."1"),
                                            'to'          => "5514981909839",
                                            'message'     => "Alerta de TED devolvida na conta digital ".trim($item->historico->complemento).". Valor: ".$item->valor,
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS(); */

                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."2"),
                                            'to'          => "5511981164946",
                                            'message'     => "Alerta de TED devolvida na conta digital ".trim($item->historico->complemento).". Valor: ".$item->valor,
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS();

                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."3"),
                                            'to'          => "5511992448841",
                                            'message'     => "Alerta de TED devolvida na conta digital ".trim($item->historico->complemento).". Valor: ".$item->valor,
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS();
                                    break;
                                    case '00702': //ted recebida
                                        /*$apiConfigSMS                  = new ApiConfig();
                                        $apiConfigSMS->master_id       = (int) $apiData->master_id;
                                        $apiConfigSMS->api_id          = 3;
                                        $apiConfigSMS->onlyActive      = 1;
                                        $apiDataZenvia                 = $apiConfigSMS->getApiConfig()[0];
                                        $apiZenviaSMS                  = new ApiZenviaSMS();
                                        $apiZenviaSMS->api_address     = Crypt::decryptString($apiDataZenvia->api_address);
                                        $apiZenviaSMS->authorization   = Crypt::decryptString($apiDataZenvia->api_authentication);
                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."1"),
                                            'to'          => "5514981909839", //Mike
                                            'message'     => "TED Recebida na Conta Digital, valor ".$item->valor." - ".trim($item->historico->complemento),
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS();

                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."2"),
                                            'to'          => "5511981164946", //Fernando
                                            'message'     => "TED Recebida na Conta Digital, valor ".$item->valor." - ".trim($item->historico->complemento),
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS();

                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."3"),
                                            'to'          => "5511992448841", //Enzo
                                            'message'     => "TED Recebida na Conta Digital, valor ".$item->valor." - ".trim($item->historico->complemento),
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS(); */
                                    break;
                                    case '01989': //Rec Pgto Pix
                                        /*$apiConfigSMS                  = new ApiConfig();
                                        $apiConfigSMS->master_id       = (int) $apiData->master_id;
                                        $apiConfigSMS->api_id          = 3;
                                        $apiConfigSMS->onlyActive      = 1;
                                        $apiDataZenvia                 = $apiConfigSMS->getApiConfig()[0];
                                        $apiZenviaSMS                  = new ApiZenviaSMS();
                                        $apiZenviaSMS->api_address     = Crypt::decryptString($apiDataZenvia->api_address);
                                        $apiZenviaSMS->authorization   = Crypt::decryptString($apiDataZenvia->api_authentication);
                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."1"),
                                            'to'          => "5514981909839", //Mike
                                            'message'     => "PIX Recebido no Banco Rendimento, valor ".$item->valor." - ".trim($item->historico->complemento),
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS();

                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."2"),
                                            'to'          => "5511981164946", //Fernando
                                            'message'     => "PIX Recebido no Banco Rendimento, valor ".$item->valor." - ".trim($item->historico->complemento),
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS();

                                        $sendSMS = SendSms::create([
                                            'external_id' => ("9".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu')."3"),
                                            'to'          => "5511992448841", //Enzo
                                            'message'     => "PIX Recebido no Banco Rendimento, valor ".$item->valor." - ".trim($item->historico->complemento),
                                            'type_id'     => 9,
                                            'origin_id'   => null,
                                            'created_at'  => \Carbon\Carbon::now()
                                        ]);
                                        $apiZenviaSMS->id              = $sendSMS->external_id;
                                        $apiZenviaSMS->aggregateId     = "001";
                                        $apiZenviaSMS->to              = $sendSMS->to;
                                        $apiZenviaSMS->msg             = $sendSMS->message;
                                        $apiZenviaSMS->callbackOption  = "NONE";
                                        $apiZenviaSMS->sendShortSMS(); */
                                    break;
                                    case '05552': //ted recebida lq
                                        if (isset(explode("/",trim($item->historico->complemento))[1])){
                                            $account_credit = explode("/",trim($item->historico->complemento));

                                            if( $account_to_credit = Account::whereRaw('convert(int,alias_account_number) = ?', [$account_credit[1]])->first() ){

                                                $movementService = new MovementService();
                                                $movementService->movementData = (object)[
                                                    'account_id'    => $account_to_credit->id,
                                                    'master_id'     => $account_to_credit->master_id,
                                                    'origin_id'     => null,
                                                    'mvmnt_type_id' => 50,
                                                    'value'         => round($item->valor,2),
                                                    'description'   => 'TED Recebida | De CPF/CNPJ: '.$account_credit[0],
                                                ];
                                                if(!$movementService->create()){
                                                    $sendFailureAlert               = new sendFailureAlert();
                                                    $sendFailureAlert->title        = 'Falha crédito de TED LQ';
                                                    $sendFailureAlert->errorMessage = 'TED não creditada para conta '.$account_credit[1].' | Valor: '. $item->valor .' | Falha na inserção' ;
                                                    $sendFailureAlert->sendFailures();
                                                } else {
                                                    //tarifa

                                                    $tax = 0;
                                                    $getTax = Account::getTax($account_to_credit->id, 29, $account_to_credit->master_id);
                                                    if($getTax->value > 0){
                                                        $tax = $getTax->value;
                                                    } else if($getTax->percentage > 0){
                                                        if(round($item->valor,2) > 0){
                                                            $tax = round(( ($getTax->percentage/100) * round($item->valor,2)),2);
                                                        }
                                                    }
                                                    if($tax > 0){
                                                        //create movement for payment tax value
                                                        $movementTax = new MovementTaxService();
                                                        $movementTax->movementData = (object) [
                                                            'account_id'    => $account_to_credit->id,
                                                            'master_id'     => $account_to_credit->master_id,
                                                            'origin_id'     => null,
                                                            'mvmnt_type_id' => 51,
                                                            'value'         => $tax,
                                                            'description'   => 'Tarifa de TED Recebida |  De CPF/CNPJ: '.$account_credit[0],
                                                        ];
                                                        if(!$movementTax->create()){
                                                            $sendFailureAlert               = new sendFailureAlert();
                                                            $sendFailureAlert->title        = 'Falha lançamentos de tarifa de TED LQ';
                                                            $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de TED LQ na conta '.$account_credit[1];
                                                            $sendFailureAlert->sendFailures();
                                                        }
                                                    }
                                                }
                                            }else{
                                                $sendFailureAlert               = new sendFailureAlert();
                                                $sendFailureAlert->title        = 'Falha crédito de TED LQ';
                                                $sendFailureAlert->errorMessage = 'TED não creditada para conta '.$account_credit[1].' | Valor: '. $item->valor.' | Conta não localizada em nossa base';
                                                $sendFailureAlert->sendFailures();
                                            }

                                        }
                                    break;
                                }


                                StatementRendimentoBank::create([
                                    "day_id"              => $dayId,
                                    "movement_id"         => $movementId,
                                    "code"                => $item->historico->codigo,
                                    "description"         => $item->historico->descricao,
                                    "complement"          => trim($item->historico->complemento),
                                    "bank"                => $bank,
                                    "agency"              => $agency,
                                    "account"             => $account,
                                    "account_owner"       => $account_owner,
                                    "category"            => $item->historico->categoria,
                                    "nature"              => $item->natureza,
                                    "entry_date"          => (\Carbon\Carbon::parse( $item->dataLancto ))->format('Y-m-d'),
                                    "document_number"     => $item->nrDocumento,
                                    "cpf_cnpj"            => $item->cpfCnpj,
                                    "counterpart_name"    => $item->nomeContraparte,
                                    "counterpart_agency"  => ltrim($item->agenciaContraparte,0),
                                    "counterpart_account" => ltrim($item->contaContraparte,0),
                                    "previous_balance"    => $item->saldoAnterior,
                                    "value"               => $value,
                                    "current_balance"     => $item->saldoAtual,
                                    "operation_type"      => $item->tipoOperacao,
                                    "master_id"           => (int) $apiData->master_id,
                                    "bank_id"             => 137,
                                    "api_id"              => 10
                                ]);
                                $originAccountId = null;
                                switch($item->historico->codigo){
                                    case '00058': //tarifa instrução bancária
                                        $this->createTaxMarginAccountMovement($apiData->master_id, 17, $value, 'Banco Rendimento | Tarifa de Instrução', $originAccountId);
                                    break;
                                    case '00081': //tarifa registro título
                                        $this->createTaxMarginAccountMovement($apiData->master_id, 5, $value, 'Banco Rendimento | Tarifa de Registro de Título', $originAccountId);
                                    break;
                                    case '00084': //tarifa baixa título
                                        $this->createTaxMarginAccountMovement($apiData->master_id, 17, $value, 'Banco Rendimento | Tarifa de Baixa', $originAccountId);
                                    break;
                                    case '00233': //tarifa de ted
                                        /*if($movementId != null){
                                            $originAccountId = (TransferPayment::where('id','=',$movementId)->first())->account_id;
                                        } */
                                        $this->createTaxMarginAccountMovement($apiData->master_id, 4, $value, 'Banco Rendimento | Tarifa de TED', $originAccountId);
                                    break;
                                    case '07700': //tarifa remuneração de setup
                                        $this->createTaxMarginAccountMovement($apiData->master_id, 45, $value, 'Banco Rendimento | Tarifa de Remuneração de Setup', $originAccountId);
                                    break;
                                }
                            } else {
                                $sttmtRendimento = StatementRendimentoBank::where('day_id','=',$dayId)->first();
                                if( $sttmtRendimento->value != $value) {
                                    $sendFailureAlert               = new sendFailureAlert();
                                    $sendFailureAlert->title        = 'Extrato Rendimento';
                                    $sendFailureAlert->errorMessage = 'ID diário não adicionado para lançamento '.$dayId.' - '.$item->historico->descricao.' - '.$value;
                                    //$sendFailureAlert->sendFailures();
                                }
                            }
                            $movement[$i] = [
                                "day_id"              => $dayId,
                                "movement_id"         => $movementId,
                                "code"                => $item->historico->codigo,
                                "description"         => $item->historico->descricao,
                                "complement"          => trim($item->historico->complemento),
                                "bank"                => $bank,
                                "agency"              => $agency,
                                "account"             => $account,
                                "account_owner"       => $account_owner,
                                "category"            => $item->historico->categoria,
                                "nature"              => $item->natureza,
                                "entry_date"          => (\Carbon\Carbon::parse( $item->dataLancto ))->format('Y-m-d'),
                                "document_number"     => $item->nrDocumento,
                                "cpf_cnpj"            => $item->cpfCnpj,
                                "counterpart_name"    => $item->nomeContraparte,
                                "counterpart_agency"  => ltrim($item->agenciaContraparte,0),
                                "counterpart_account" => ltrim($item->contaContraparte,0),
                                "previous_balance"    => $item->saldoAnterior,
                                "value"               => $value,
                                "current_balance"     => $item->saldoAtual,
                                "operation_type"      => $item->tipoOperacao,
                                "master_id"           => (int) $apiData->master_id,
                                "bank_id"             => 137
                            ];
                            $i++;
                        }
                    }
                    //return response()->json($movement);
                }
            }
        }






    }

    protected function createTaxMarginAccountMovement($master_id, $movementType, $value, $movementTaxDescription, $originAccountId)
    {
        $movementTax = new MovementTaxService();
        $movementTax->movementData = (object) [
            'account_id'    => $originAccountId,
            'master_id'     => $master_id,
            'origin_id'     => null,
            'mvmnt_type_id' => $movementType,
            'value'         => $value,
            'description'   => $movementTaxDescription
        ];
        if(!$movementTax->createTaxMarginAccount()){
            $sendFailureAlert               = new sendFailureAlert();
            $sendFailureAlert->title        = 'Extrato Rendimento - Lcto Margem';
            $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa na conta margem';
            $sendFailureAlert->sendFailures();
        }
    }

    protected function getDigitalAccountMovimentationResume(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement                               = new AccountMovement();
        $accountMovement->created_at_start             = $request->created_at_start." 00:00:00.000";;
        $accountMovement->created_at_end               = $request->created_at_end." 23:59:59.998";
        $accountMovement->account_id                   = $checkAccount->account_id;
        $accountMovement->type_id                      = $request->type_id;
        $accountMovement->movement_type_id             = $request->movement_type_id;
        $accountMovement->document                     = $request->document;
        $accountMovement->favored                      = $request->favored;

        $accountMovement->schedule_date_start          = $request->schedule_date_start;
        $accountMovement->schedule_date_end            = $request->schedule_date_end;
        $accountMovement->payment_date_start           = $request->payment_date_start;
        $accountMovement->payment_date_end             = $request->payment_date_end;
        $accountMovement->account_first_movement_start = $request->account_first_movement_start;
        $accountMovement->account_first_movement_end   = $request->account_first_movement_end;
        $accountMovement->value_start                  = $request->value_start;
        $accountMovement->value_end                    = $request->value_end;
        $accountMovement->payment_value_start          = $request->payment_value_start;
        $accountMovement->payment_value_end            = $request->payment_value_end;
        $accountMovement->tax_value_start              = $request->tax_value_start;
        $accountMovement->tax_value_end                = $request->tax_value_end;
        $accountMovement->status_id                    = $request->status_id;
        $accountMovement->transaction_id               = $request->transaction_id;
        $accountMovement->bank_transaction_id          = $request->bank_transaction_id;
        return response()->json($accountMovement->getDigitalAccountResume());
    }

    protected function getDigitalAccountMovimentationResumeAccounts(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        return response()->json($accountMovement->getDigitalAccountMovimentationResumeAccounts());
    }

    protected function getDigitalAccountMovimentationResumeTypes(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        return response()->json($accountMovement->getDigitalAccountMovimentationResumeTypes());
    }

    protected function getDigitalAccountMovimentationResumeMovementTypes(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        return response()->json($accountMovement->getDigitalAccountMovimentationResumeMovementTypes());
    }

    protected function getDigitalAccountMovimentationStatus(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        return response()->json($accountMovement->getDigitalAccountMovimentationStatus());
    }

    protected function getAccountMovementResume(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [1];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        // -------------- Get digital account balance --------------//
        $digital_account_balance     = 0;
        $accountMovement             = new AccountMovement();
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $digital_account_balance = $accountMovement->getAccountBalance()->balance;
        }
        // -------------- Finish get digital account balance -------------- //

        // -------------- Get Rendimento Bank Balance -------------- //
        $rendimento_bank_balance = 0;
        /*
        $rendimento_bank_balance = 0;
        $rendimento_bank_balance                            = 0;
        $apiConfig                                          = new ApiConfig();
        $apiConfig->master_id                               = $checkAccount->master_id;
        $apiConfig->api_id                                  = 1;
        $apiConfig->onlyActive                              = 1;
        $apiData                                            = $apiConfig->getApiConfig()[0];
        $apiRendimento                                      = new ApiBancoRendimento();
        $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
        $rendimentoBalance                                  = $apiRendimento->saldoConsultar();
        if(isset($rendimentoBalance->body->value->saldoDisponivel)){
            $rendimento_bank_balance = $rendimentoBalance->body->value->saldoDisponivel;
        } else {
            //enviar e-mail de erro no saldo
            $sendFailureAlert               = new sendFailureAlert();
            $sendFailureAlert->title        = 'FALHA COMUNICAÇÂO API RENDIMENTO - Saldo';
            $sendFailureAlert->errorMessage = 'Ocorreu uma falha ao recuperar o saldo do Banco Rendimento, isso pode implicar em falhas nos outros recursos da API.<br>Caso continue recebendo este e-mail, é aconselhável desativar as funções para evitar problemas futuros.';
           // $sendFailureAlert->sendFailures();
        } */
        // -------------- Finish get Rendimento Bank Balance -------------- //

        // -------------- Get Rendimento Bank Balance Pagamentos -------------- //
        $rendimento_bank_balance_pagamentos                 = 0;
        $apiConfig                                          = new ApiConfig();
        $apiConfig->master_id                               = $checkAccount->master_id;
        $apiConfig->api_id                                  = 10;
        $apiConfig->onlyActive                              = 1;
        $apiData                                            = $apiConfig->getApiConfig()[0];
        $apiRendimento                                      = new ApiBancoRendimento();
        $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
        $rendimentoBalance_pagamentos                       = $apiRendimento->saldoConsultar();
        if(isset($rendimentoBalance_pagamentos->body->value->saldoDisponivel)){
            $rendimento_bank_balance_pagamentos = $rendimentoBalance_pagamentos->body->value->saldoDisponivel;
        }
        // -------------- Finish get Rendimento Bank Balance -------------- //

        // -------------- Get CelCoin Balance -------------- //
        $apiConfig->api_id         = 8;
        $api_cel_coin              = $apiConfig->getApiConfig()[0];
        $apiCelCoin                = new ApiCelCoin();
        $apiCelCoin->api_address   = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id     = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type    = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret = Crypt::decryptString($api_cel_coin->api_authentication);
        $celcoin                   = $apiCelCoin->merchantBalance();
        $celcoin_balance           = 0;

        if(isset($celcoin->data->balance)){
            $celcoin_balance = $celcoin->data->balance - $celcoin->data->credit;
        } else {
            //enviar e-mail de erro no saldo
            $sendFailureAlert               = new sendFailureAlert();
            $sendFailureAlert->title        = 'FALHA COMUNICAÇÂO API CELCOIN - Saldo';
            $sendFailureAlert->errorMessage = 'Ocorreu uma falha ao recuperar o saldo da CelCoin, isso pode implicar em falhas nos outros recursos da API.<br>Caso continue recebendo este e-mail, é aconselhável desativar as funções para evitar problemas futuros.';
           // $sendFailureAlert->sendFailures();
        }
        // -------------- Finish get CelCoin Balance -------------- //

        // -------------- Get BMP Balance -------------- //
        $bmp_balance = $this->getTotalBalanceMoneyPlus();

        /*$apiConfig->api_id            = 15;
        $api_bmp_data                 = $apiConfig->getApiConfig()[0];
        $apiBMP                       = new ApiMoneyPlus();
        $apiBMP->api_address          = Crypt::decryptString($api_bmp_data->api_address);
        $apiBMP->client_id            = Crypt::decryptString($api_bmp_data->api_client_id);
        $apiBMP->alias_account_agency = "00018";
        $apiBMP->alias_account_number = "00790584";

        $bmp                          = $apiBMP->checkBalance();
        $bmp_balance                  = 0;

        if(isset($bmp->data->vlrSaldo)){
            $bmp_balance = $bmp->data->vlrSaldo;
        } else {
            //enviar e-mail de erro no saldo
            $sendFailureAlert               = new sendFailureAlert();
            $sendFailureAlert->title        = 'FALHA COMUNICAÇÂO API BMP - Saldo';
            $sendFailureAlert->errorMessage = 'Ocorreu uma falha ao recuperar o saldo da BMP';
           // $sendFailureAlert->sendFailures();
        } */



        // -------------- Finish get CelCoin Balance -------------- //

        // -------------- Get Edenred Balcance --------------//

        $edenred_balance = 0;
        $edenred = new EdenredClass;
        $edenredCheckControlCardBalance = $edenred->checkControlCardBalance();
        if( $edenredCheckControlCardBalance->success ) {
            if(isset($edenredCheckControlCardBalance->data->saldo)) {
                $edenred_balance = $edenredCheckControlCardBalance->data->saldo;
            }
        }

        // -------------- Finish get Edenred Balcance --------------//

        // -------------- Get masters infos --------------//
        $master = Master::where('id','=',$checkAccount->master_id)->first();

        // -------------- Finish get masters infos --------------//

        //Set AccountMovement Instance
        $accountMovement             = new AccountMovement();
        $accountMovement->master_id  = $checkAccount->master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();


        // -------------- Get Margin Account Balance ------------ //
        $margin_account_balance = 0;
        if($master->margin_accnt_id != ''){
            $accountMovement->account_id = $master->margin_accnt_id;
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $margin_account_balance = $accountMovement->getAccountBalance()->balance;
            }
        }
        // -------------- Finish get Margin Account Balance ------------ //

        // -------------- Get Old Margin Account Balance ------------ //
        $old_margin_account_balance = 0;

        $accountMovement->account_id = 80;
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $old_margin_account_balance = $accountMovement->getAccountBalance()->balance;
        }
        // -------------- Finish Old get Margin Account Balance ------------ //

        // -------------- Get CDB Account Balance ------------ //
        $cdb_account_balance = 0;
        $accountMovement->account_id = 430;
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $cdb_account_balance = ($accountMovement->getAccountBalance()->balance);
            if($cdb_account_balance < 0){
                $cdb_account_balance = $cdb_account_balance * (-1);
            }
        }
        // -------------- Finish get CDB Account Balance ------------ //

        // -------------- Get Card Account Balance ------------ //
        $card_account_balance = 0;
        if($master->card_accnt_id != ''){
            $accountMovement->account_id = $master->card_accnt_id;
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $card_account_balance = $accountMovement->getAccountBalance()->balance;
            }
        }
        // -------------- Finish get Card Account Balance ------------ //

        // -------------- Get Aqui Card Account Balance ------------ //
        $aqui_card_account_balance = 0;
        if($master->aquicard_accnt_id != ''){
            $accountMovement->account_id = $master->aquicard_accnt_id;
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $aqui_card_account_balance = $accountMovement->getAccountBalance()->balance;
            }
        }
        // -------------- Finish get Card Account Balance ------------ //

        // -------------- get Master day input value ------------ //
        $day_input_value             = 0;
        $accountMovement->account_id = null;
        $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accountMovement->end_date   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        if(isset( $accountMovement->getMasterDayInputValues()->dayInputValue )){
            $day_input_value = $accountMovement->getMasterDayInputValues()->dayInputValue;
        }
        // -------------- Finish Get Master day input value --------------- //

        // -------------- get Master day output value ------------ //
        $day_output_value            = 0;
        $accountMovement->account_id = null;
        $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 00:00:00.000";
        $accountMovement->end_date   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'))." 23:59:59.998";
        if(isset( $accountMovement->getMasterDayOutputValues()->dayOutputValue )){
            $day_output_value = $accountMovement->getMasterDayOutputValues()->dayOutputValue;
        }

        $accountMovement->account_id = null;
        $accountMovement->start_date = null;
        $accountMovement->end_date   = null;
        // -------------- Finish Get Master day output value ------------ //

        // -------------- Card Sale Movement Resume ------------ //
        $card_sale_movement_qtd   = 0;
        $card_sale_movement_value = 0;
        $card_sale_movement           = new CardSaleMovement();
        if($request->date == ''){
            $card_sale_movement->date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d'));
        } else {
            $card_sale_movement->date      = $request->date;
        }
        $card_sale_movement_resume = ($card_sale_movement->resumeCardSaleMovement()[0]);
        $card_sale_movement_qtd    = $card_sale_movement_resume->qtd;
        $card_sale_movement_value  = $card_sale_movement_resume->total;
        // -------------- Finish Card Sale Movement Resume ------------ //

        // -------------- AddMoney Resume ------------ //

        $addMoney             = new AccntAddMoneyBankCharging();
        $addMoney->master_id  = $checkAccount->master_id;

        //period created
        if($request->created_at_start != ''){
            $addMoney->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $addMoney->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->created_at_end != ''){
            $addMoney->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $addMoney->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $add_money_period_created       = $addMoney->addMoneyChargeAnalitic()[0];
        $addMoney->created_at_start     = null;
        $addMoney->created_at_end       = null;

        //----

        //period liquidated
        if($request->payment_occurrence_date_start != ''){
            $addMoney->payment_occurrence_date_start = $request->payment_occurrence_date_start." 00:00:00.000";
        } else {
            $addMoney->payment_occurrence_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_occurrence_date_end != ''){
            $addMoney->payment_occurrence_date_end = $request->payment_occurrence_date_end." 23:59:59.998";
        } else {
            $addMoney->payment_occurrence_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $add_money_period_liquidated      = $addMoney->addMoneyChargeAnalitic()[0];
        $addMoney->payment_occurrence_date_start     = null;
        $addMoney->payment_occurrence_date_end       = null;

        //----

        //period down
        if($request->payment_down_date_start != ''){
            $addMoney->payment_down_date_start = $request->payment_down_date_start." 00:00:00.000";
        } else {
            $addMoney->payment_down_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_down_date_end != ''){
            $addMoney->payment_down_date_end = $request->payment_down_date_end." 23:59:59.998";
        } else {
            $addMoney->payment_down_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $add_money_period_down                 = $addMoney->addMoneyChargeAnalitic()[0];
        $addMoney->payment_down_date_start     = null;
        $addMoney->payment_down_date_end       = null;

        //-----

        // -------------- Finish AddMoney Resume ------------ //

        // -------------- AntecipationCharge Resume ------------ //
        $antecipationCharge             = new AntecipationCharge();
        $antecipationCharge->master_id  = $checkAccount->master_id;

        //period created
        if($request->created_at_start != ''){
            $antecipationCharge->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $antecipationCharge->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->created_at_end != ''){
            $antecipationCharge->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $antecipationCharge->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $antecipation_charge_period_created                       = $antecipationCharge->antecipationChargeAnalitic()[0];
        $antecipationCharge->created_at_start = null;
        $antecipationCharge->created_at_end   = null;
        //----

        //period liquidated
        if($request->payment_occurrence_date_start != ''){
            $antecipationCharge->payment_occurrence_date_start = $request->payment_occurrence_date_start." 00:00:00.000";
        } else {
            $antecipationCharge->payment_occurrence_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_occurrence_date_end != ''){
            $antecipationCharge->payment_occurrence_date_end = $request->payment_occurrence_date_end." 23:59:59.998";
        } else {
            $antecipationCharge->payment_occurrence_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $antecipation_charge_period_liquidated                      = $antecipationCharge->antecipationChargeAnalitic()[0];
        $antecipationCharge->payment_occurrence_date_start = null;
        $antecipationCharge->payment_occurrence_date_end   = null;
        //----

        //period down
        if($request->payment_down_date_start != ''){
            $antecipationCharge->payment_down_date_start = $request->payment_down_date_start." 00:00:00.000";
        } else {
            $antecipationCharge->payment_down_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_down_date_end != ''){
            $antecipationCharge->payment_down_date_end = $request->payment_down_date_end." 23:59:59.998";
        } else {
            $antecipationCharge->payment_down_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $antecipation_charge_period_down                                 = $antecipationCharge->antecipationChargeAnalitic()[0];
        $antecipationCharge->payment_down_date_start = null;
        $antecipationCharge->payment_down_date_end   = null;

        // -------------- Finish AntecipationCharge Resume ------------ //

        // -------------- SimpleCharge Resume ------------ //
        $simpleCharge             = new SimpleCharge();
        $simpleCharge->master_id  = $checkAccount->master_id;

        //period created
        if($request->created_at_start != ''){
            $simpleCharge->created_at_start = $request->created_at_start." 00:00:00.000";
        } else {
            $simpleCharge->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->created_at_end != ''){
            $simpleCharge->created_at_end = $request->created_at_end." 23:59:59.998";
        } else {
            $simpleCharge->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $simple_charge_period_created                 = $simpleCharge->simpleChargeAnalitic()[0];
        $simpleCharge->created_at_start = null;
        $simpleCharge->created_at_end   = null;
        //----

        //period liquidated
        if($request->payment_occurrence_date_start != ''){
            $simpleCharge->payment_datpayment_occurrence_date_starte_start = $request->payment_occurrence_date_start." 00:00:00.000";
        } else {
            $simpleCharge->payment_occurrence_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_occurrence_date_end != ''){
            $simpleCharge->payment_occurrence_date_end = $request->payment_occurrence_date_end." 23:59:59.998";
        } else {
            $simpleCharge->payment_occurrence_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $simple_charge_period_liquidated                = $simpleCharge->simpleChargeAnalitic()[0];
        $simpleCharge->payment_occurrence_date_start = null;
        $simpleCharge->payment_occurrence_date_end   = null;
        //----

        //period down
        if($request->payment_down_date_start != ''){
            $simpleCharge->payment_down_date_start = $request->payment_down_date_start." 00:00:00.000";
        } else {
            $simpleCharge->payment_down_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_down_date_end != ''){
            $simpleCharge->payment_down_date_end = $request->payment_down_date_end." 23:59:59.998";
        } else {
            $simpleCharge->payment_down_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $simple_charge_period_down                           = $simpleCharge->simpleChargeAnalitic()[0];
        $simpleCharge->payment_down_date_start = null;
        $simpleCharge->payment_down_date_end   = null;
        // -------------- Finish SimpleCharge Resume ------------ //

        // -------------- Bill Payment Resume ------------ //

        $billPayment             = new BillPayment();
        $billPayment->master_id  = $checkAccount->master_id;

        //period liquidated
        $billPayment->status_id = 37;

        if($request->payment_date_start != ''){
            $billPayment->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $billPayment->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";;
        }

        if($request->payment_date_end != ''){
            $billPayment->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $billPayment->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $bill_payment_period_liquidated                = $billPayment->BillPaymentAnalitic()[0];
        $billPayment->payment_date_start = null;
        $billPayment->payment_date_end   = null;
        $billPayment->status_id          = null;
        //----

        //period schedule
        $billPayment->status_id = 7;
        if($request->payment_schedule_date_start != ''){
            $billPayment->payment_schedule_date_start = $request->payment_schedule_date_start." 00:00:00.000";
        } else {
            $billPayment->payment_schedule_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_schedule_date_end != ''){
            $billPayment->payment_schedule_date_end = $request->payment_schedule_date_end." 23:59:59.998";
        } else {
            $billPayment->payment_schedule_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $bill_payment_period_schedule                          = $billPayment->BillPaymentAnalitic()[0];
        $billPayment->payment_schedule_date_start = null;
        $billPayment->payment_schedule_date_end   = null;
        $billPayment->status_id                   = null;
        //-----

        //schedule
        $billPayment->status_id = 7;

        $bill_payment_schedule                    = $billPayment->BillPaymentAnalitic()[0];
        $billPayment->status_id                   = null;
        //-----

        // -------------- Finish Bill Payment Resume ------------ //

        // -------------- Payroll Payment Resume ------------ //
        $payrollPayment             = new PayrollRelease();
        $payrollPayment->master_id  = $checkAccount->master_id;

        //period liquidated
        $payrollPayment->status_id = 37;

        if($request->payment_date_start != ''){
            $payrollPayment->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $payrollPayment->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $payrollPayment->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $payrollPayment->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $payroll_period_liquidated                  = $payrollPayment->PayrollPaymentAnalitic()[0];
        $payrollPayment->payment_date_start = null;
        $payrollPayment->payment_date_end   = null;
        $payrollPayment->status_id          = null;
        //----

        //period schedule
        $payrollPayment->status_id = 7;
        if($request->payment_schedule_date_start != ''){
            $payrollPayment->payment_schedule_date_start = $request->payment_schedule_date_start." 00:00:00.000";
        } else {
            $payrollPayment->payment_schedule_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_schedule_date_end != ''){
            $payrollPayment->payment_schedule_date_end = $request->payment_schedule_date_end." 23:59:59.998";
        } else {
            $payrollPayment->payment_schedule_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $payroll_period_schedule                             = $payrollPayment->PayrollPaymentAnalitic()[0];
        $payrollPayment->payment_schedule_date_start = null;
        $payrollPayment->payment_schedule_date_end   = null;
        $payrollPayment->status_id                   = null;
        //-----

        //schedule
        $payrollPayment->status_id = 7;

        $payroll_schedule                                = $payrollPayment->PayrollPaymentAnalitic()[0];
        $payrollPayment->status_id               = null;

        // -------------- Finish Payroll Payment Resume ------------ //

        // -------------- Transfer Payment Resume ------------ //

        $transferPayment             = new TransferPayment();
        $transferPayment->master_id  = $checkAccount->master_id;

        //period liquidated
        $transferPayment->status_id = 37;

        if($request->payment_date_start != ''){
            $transferPayment->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $transferPayment->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $transferPayment->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $transferPayment->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $transfer_payment_liquidated  = $transferPayment->TransferPaymentAnalitic()[0];
        $transferPayment->payment_date_start = null;
        $transferPayment->payment_date_end   = null;
        $transferPayment->status_id          = null;
        //----

        //period schedule
        $transferPayment->status_id = 7;
        if($request->payment_schedule_date_start != ''){
            $transferPayment->payment_schedule_date_start = $request->payment_schedule_date_start." 00:00:00.000";
        } else {
            $transferPayment->payment_schedule_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_schedule_date_end != ''){
            $transferPayment->payment_schedule_date_end = $request->payment_schedule_date_end." 23:59:59.998";
        } else {
            $transferPayment->payment_schedule_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $transfer_payment_period_schedule             = $transferPayment->TransferPaymentAnalitic()[0];
        $transferPayment->payment_schedule_date_start = null;
        $transferPayment->payment_schedule_date_end   = null;
        $transferPayment->status_id                   = null;
        //-----

        //schedule
        $transferPayment->status_id = 7;

        $transfer_payment_schedule                = $transferPayment->TransferPaymentAnalitic()[0];
        $transferPayment->status_id               = null;
        //-----

        // -------------- Finish Transfer Payment Resume ------------ //

        // -------------- Card Movement Resume ------------ //
        $cardMovement             = new CardMovement();
        $cardMovement->master_id  = $checkAccount->master_id;

        if($request->payment_date_start != ''){
            $cardMovement->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $cardMovement->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $cardMovement->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $cardMovement->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $card_movement = $cardMovement->CardMovementsAnalitic()[0];
        // -------------- Finish Card Movement Resume ------------ //

        // -------------- Margin Account Tax ------------ //
        $margin_account_tax_value    = 0;
        $margin_account_tax_qtd      = 0;
        $accountMovement             = new AccountMovement();
        $accountMovement->master_id  = $checkAccount->master_id;

        if($request->payment_date_start != ''){
            $accountMovement->created_at_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $accountMovement->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $accountMovement->created_at_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $accountMovement->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $marginAccountTax            = $accountMovement->getMarginAccountTaxAnalitic()[0];
        if(isset( $marginAccountTax->total )){
            $margin_account_tax_value     = $marginAccountTax->total;
            $margin_account_tax_qtd       = $marginAccountTax->qtd;
        }
        // -------------- Finish Margin Account Tax ------------ //

        // -------------- Margin Account Reversal Tax ------------ //
        $margin_account_reversal_tax_value    = 0;
        $margin_account_reversal_tax_qtd      = 0;
        $marginAccountReversalTax             = $accountMovement->getMarginAccountTaxReversalAnalitic()[0];
        if(isset( $marginAccountReversalTax->total )){
            $margin_account_reversal_tax_value     = $marginAccountReversalTax->total;
            $margin_account_reversal_tax_qtd       = $marginAccountReversalTax->qtd;
        }
        // -------------- Finish Margin Account Reversal Tax ------------ //

        // -------------- Rendimento Bank Tax ------------ //
        $rendimento_bank_tax_value    = 0;
        $rendimento_bank_tax_qtd      = 0;
        $rendimentoBankTax            = $accountMovement->getRendimentoBankTaxAnalitic()[0];
        if(isset( $rendimentoBankTax->total )){
            $rendimento_bank_tax_value     = $rendimentoBankTax->total;
            $rendimento_bank_tax_qtd       = $rendimentoBankTax->qtd;
        }
        // -------------- Finish Rendimento Bank Tax ------------ //

        // -------------- Rendimento Bank Tax ------------ //
        $tax_pay_or_reversal_value    = 0;
        $tax_pay_or_reversal_qtd      = 0;
        $accountMovement->master_id  = $checkAccount->master_id;

        if($request->payment_date_start != ''){
            $accountMovement->created_at_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $accountMovement->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $accountMovement->created_at_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $accountMovement->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }
        $taxPayOrReversal             = $accountMovement->getTaxPayOrReversal()[0];
        if(isset( $rendimentoBankTax->total )){
            $tax_pay_or_reversal_value     = $taxPayOrReversal->total;
            $tax_pay_or_reversal_qtd       = $taxPayOrReversal->qtd;
        }
        // -------------- Finish Rendimento Bank Tax ------------ //


        // -------------- Transfer Received Resume ------------ //
        $transfer_received_value = 0;
        $transfer_received_qtt   = 0;

        if($request->start_date != ''){
            $accountMovement->created_at_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $accountMovement->created_at_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->end_date != ''){
            $accountMovement->created_at_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $accountMovement->created_at_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $transferReceived = $accountMovement->getTransferReceived()[0];
        if(isset( $transferReceived->total )){
            $transfer_received_value = $transferReceived->total;
            $transfer_received_qtt = $transferReceived->qtd;
        }
        // -------------- Finish Transfer Received Resume ------------ //

        // -------------- AccntAddMoneyPix Resume ------------ //
        $pix_add_money_received_qtd      = 0;
        $pix_add_money_received_value    = 0;
        $pix_add_money_down_qtd          = 0;
        $pix_add_money_down_value        = 0;
        $pix_add_money_created_value     = 0;
        $pix_add_money_created_qtd       = 0;

        $accnt_add_money_pix             = new AccntAddMoneyPix();

        if($request->start_date != ''){
            $accnt_add_money_pix->start_date = $request->start_date." 00:00:00.000";
        } else {
            $accnt_add_money_pix->start_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }
        if($request->end_date != ''){
            $accnt_add_money_pix->end_date = $request->end_date." 23:59:59.998";
        } else {
            $accnt_add_money_pix->end_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }
        $pix_add_money_created_resume  = ($accnt_add_money_pix->createdPixAddMoney()[0]);
        $pix_add_money_created_qtd     = $pix_add_money_created_resume->qtd;
        $pix_add_money_created_value   = $pix_add_money_created_resume->total;

        $pix_add_money_down_resume     = ($accnt_add_money_pix->downPixAddMoney()[0]);
        $pix_add_money_down_qtd        = $pix_add_money_down_resume->qtd;
        $pix_add_money_down_value      = $pix_add_money_down_resume->total;

        $pix_add_money_received_resume  = ($accnt_add_money_pix->receivedPixAddMoney()[0]);
        $pix_add_money_received_qtd     = $pix_add_money_received_resume->qtd;
        $pix_add_money_received_value   = $pix_add_money_received_resume->total;

        // -------------- Finish AccntAddMoneyPix Resume ------------ //

        // -------------- PixReceivePayment Resume ------------ //
        $pix_receive_created_value  = 0;
        $pix_receive_created_qtd    = 0;
        $pix_receive_received_value = 0;
        $pix_receive_received_qtd   = 0;

        $pix_receive_payment            = new PixReceivePayment();

        if($request->start_date != ''){
            $pix_receive_payment->start_date = $request->start_date." 00:00:00.000";
        } else {
            $pix_receive_payment->start_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }
        if($request->end_date != ''){
            $pix_receive_payment->end_date = $request->end_date." 23:59:59.998";
        } else {
            $pix_receive_payment->end_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }
        $pix_receive_payment_resume   = ($pix_receive_payment->createdPixReceive()[0]);
        $pix_receive_created_qtd      = $pix_receive_payment_resume->qtd;
        $pix_receive_created_value    = $pix_receive_payment_resume->total;

        $pix_receive_received_resume  = ($pix_receive_payment->receivedPixReceive()[0]);
        $pix_receive_received_qtd     = $pix_receive_received_resume->qtd;
        $pix_receive_received_value   = $pix_receive_received_resume->total;

        $pix_receive_down_resume      = ($pix_receive_payment->downPixReceive()[0]);
        $pix_receive_down_qtd         = $pix_receive_down_resume->qtd;
        $pix_receive_down_value       = $pix_receive_down_resume->total;
        // -------------- Finish PixReceivePayment Resume ------------ //

        // -------------- StaticPixReceive Resume ------------ //
        $pix_static_received_qtd     = 0;
        $pix_static_received_value   = 0;
        $pix_static_received_payment = new PixStaticReceivePayment();

        if($request->start_date != ''){
            $pix_static_received_payment->start_date = $request->start_date." 00:00:00.000";
        } else {
            $pix_static_received_payment->start_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }
        if($request->end_date != ''){
            $pix_static_received_payment->end_date = $request->end_date." 23:59:59.998";
        } else {
            $pix_static_received_payment->end_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $pix_static_received_payment_resume = $pix_static_received_payment->staticReceived()[0];
        $pix_static_received_qtd   = $pix_static_received_payment_resume->qtd;
        $pix_static_received_value = $pix_static_received_payment_resume->total;
        // -------------- Finish StaticPixReceive Resume ------------ //

        // -------------- PixPayment Resume ------------ //
        $pix_realized_value  = 0;
        $pix_realized_qtd    = 0;

        $pix_payment            = new PixPayment();

        if($request->start_date != ''){
            $pix_payment->start_date = $request->start_date." 00:00:00.000";
        } else {
            $pix_payment->start_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }
        if($request->end_date != ''){
            $pix_payment->end_date = $request->end_date." 23:59:59.998";
        } else {
            $pix_payment->end_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }
        $pix_realized_resume          = ($pix_payment->realizedPixPayment()[0]);
        $pix_realized_qtd             = $pix_realized_resume->qtd;
        $pix_realized_value           = $pix_realized_resume->total;


        $pix_payment_v2            = new PixPaymentV2();

        if($request->start_date != ''){
            $pix_payment_v2->start_date = $request->start_date." 00:00:00.000";
        } else {
            $pix_payment_v2->start_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }
        if($request->end_date != ''){
            $pix_payment_v2->end_date = $request->end_date." 23:59:59.998";
        } else {
            $pix_payment_v2->end_date = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $pix_realized_resume_v2        = ($pix_payment_v2->realizedPixPayment()[0]);
        $pix_realized_qtd             += $pix_realized_resume_v2->qtd;
        $pix_realized_value           += $pix_realized_resume_v2->total;


        // -------------- Finish PixPayment Resume ------------ //

        // -------------- Parameters of pix for sum ------------ //
        $pix_created_value  = ($pix_add_money_created_value  + $pix_receive_created_value);
        $pix_created_qtd    = ($pix_add_money_created_qtd    + $pix_receive_created_qtd);
        $pix_received_value = ($pix_add_money_received_value + $pix_receive_received_value + $pix_static_received_value);
        $pix_received_qtd   = ($pix_add_money_received_qtd   + $pix_receive_received_qtd + $pix_static_received_qtd);
        $pix_down_value     = ($pix_add_money_down_value     + $pix_receive_down_value);
        $pix_down_qtd       = ($pix_add_money_down_qtd       + $pix_receive_down_qtd);
        // -------------- Finish parameters of pix for sum ------------ //

        // -------------- Get Banco do Brasil Balance -------------- //
        $brasil_bank_balance             = 0;
        $statementBrasilBank             = new StatementBrasilBank();
        $statementBrasilBank->master_id  = $checkAccount->master_id;
        $statementBrasilBank->onlyActive = 1;
        $brasil_bank_balance             = ($statementBrasilBank->getBalance())->balance;
        // -------------- Finish Banco do Brasil Balance -------------- //

        return response()->json([
            'success'                              => '',
            'digital_account_balance'              => round($digital_account_balance + $cdb_account_balance,2),
            'rendimento_bank_balance'              => round($rendimento_bank_balance,2),
            'rendimento_bank_balance_pagamentos'   => round($rendimento_bank_balance_pagamentos,2),
            'brasil_bank_balance'                  => round($brasil_bank_balance,2),
            'celcoin_balance'                      => round($celcoin_balance,2),
            'bmp_balance'                          => round($bmp_balance,2),
            'margin_account_balance'               => round(($margin_account_balance + $old_margin_account_balance),2),
            'cdb_account_balance'                  => round($cdb_account_balance,2),
            'card_account_balance'                 => round($card_account_balance,2),
            'aqui_card_account_balance'            => round($aqui_card_account_balance,2),
            'edenred_balance'                      => round($edenred_balance,2),
            'divergence_value'                     => round(($rendimento_bank_balance + $rendimento_bank_balance_pagamentos - $digital_account_balance + $aqui_card_account_balance + $celcoin_balance + $brasil_bank_balance + $bmp_balance),2),

            'input_value'                          => round($day_input_value,2),
            'output_value'                         => round($day_output_value,2),

            'digital_account_fee_value'            => round($margin_account_tax_value,2),
            'digital_account_fee_qtd'              => round($margin_account_tax_qtd,2),
            'digital_account_reversal_fee_value'   => round($tax_pay_or_reversal_value,2),
            'digital_account_reversal_fee_qtd'     => round($tax_pay_or_reversal_qtd,2),
            'rendimento_bank_fee_value'            => round($rendimento_bank_tax_value,2),
            'rendimento_bank_fee_qtd'              => round($rendimento_bank_tax_qtd,2),

            'add_money_created_value'              => round($add_money_period_created->total,2),
            'add_money_created_qtd'                => round($add_money_period_created->qtd,2),
            'add_money_liquidated_value'           => round($add_money_period_liquidated->total,2),
            'add_money_liquidated_qtd'             => round($add_money_period_liquidated->qtd,2),
            'add_money_down_value'                 => round($add_money_period_down->total,2),
            'add_money_down_qtd'                   => round($add_money_period_down->qtd,2),

            'antecipation_charge_created_value'    => round($antecipation_charge_period_created->total,2),
            'antecipation_charge_created_qtd'      => round($antecipation_charge_period_created->qtd,2),
            'antecipation_charge_liquidated_value' => round($antecipation_charge_period_liquidated->total,2),
            'antecipation_charge_liquidated_qtd'   => round($antecipation_charge_period_liquidated->qtd,2),
            'antecipation_charge_down_value'       => round($antecipation_charge_period_down->total,2),
            'antecipation_charge_down_qtd'         => round($antecipation_charge_period_down->qtd,2),

            'simple_charge_created_value'          => round($simple_charge_period_created->total,2),
            'simple_charge_created_qtd'            => round($simple_charge_period_created->qtd,2),
            'simple_charge_liquidated_value'       => round($simple_charge_period_liquidated->total,2),
            'simple_charge_liquidated_qtd'         => round($simple_charge_period_liquidated->qtd,2),
            'simple_charge_down_value'             => round($simple_charge_period_down->total,2),
            'simple_charge_down_qtd'               => round($simple_charge_period_down->qtd,2),

            'bill_payment_liquidated_value'        => round($bill_payment_period_liquidated->total,2),
            'bill_payment_liquidated_qtd'          => round($bill_payment_period_liquidated->qtd,2),

            'bill_payment_schedule_value'          => round($bill_payment_schedule->total_value,2),
            'bill_payment_schedule_qtd'            => round($bill_payment_schedule->qtd,2),

            'payroll_payment_liquidated_value'     => round($payroll_period_liquidated->total,2),
            'payroll_payment_liquidated_qtd'       => round($payroll_period_liquidated->qtd,2),

            'payroll_payment_schedule_value'       => round($payroll_schedule->total,2),
            'payroll_payment_schedule_qtd'         => round($payroll_schedule->qtd,2),

            'transfer_payment_liquidated_value'    => round($transfer_payment_liquidated->total,2),
            'transfer_payment_liquidated_qtd'      => round($transfer_payment_liquidated->qtd,2),

            'transfer_payment_schedule_value'      => round($transfer_payment_schedule->total_value,2),
            'transfer_payment_schedule_qtd'        => round($transfer_payment_schedule->qtd,2),

            'transfer_received_value'              => round($transfer_received_value,2),
            'transfer_received_qtd'                => round($transfer_received_qtt,2),

            'card_movement_value'                  => round($card_movement->total,2),
            'card_movement_qtd'                    => round($card_movement->qtd,2),

            'card_sale_movement_qtd'               => round($card_sale_movement_qtd,2),
            'card_sale_movement_value'             => round($card_sale_movement_value,2),

            'pix_add_money_received_value'         => round($pix_add_money_received_value,2),
            'pix_add_money_received_qtd'           => round($pix_add_money_received_qtd,2),

            'pix_add_money_created_value'          => round($pix_add_money_created_value,2),
            'pix_add_money_created_qtd'            => round($pix_add_money_created_qtd,2),

            'pix_add_money_down_value'             => round($pix_add_money_down_value,2),
            'pix_add_money_down_qtd'               => round($pix_add_money_down_qtd,2),

            'pix_receive_created_value'            => round($pix_receive_created_value,2),
            'pix_receive_created_qtd'              => round($pix_receive_created_qtd,2),

            'pix_receive_received_value'           => round($pix_receive_received_value,2),
            'pix_receive_received_qtd'             => round($pix_receive_received_qtd,2),

            'pix_receive_down_value'               => round($pix_receive_down_value,2),
            'pix_receive_down_qtd'                 => round($pix_receive_down_qtd,2),

            'pix_realized_value'                   => round($pix_realized_value,2),
            'pix_realized_qtd'                     => round($pix_realized_qtd,2),

            'pix_created_value'                    =>round($pix_created_value, 2),
            'pix_created_qtd'                      =>round($pix_created_qtd, 2),
            'pix_received_value'                   =>round($pix_received_value, 2),
            'pix_received_qtd'                     =>round($pix_received_qtd, 2),
            'pix_down_value'                       =>round($pix_down_value, 2),
            'pix_down_qtd'                         =>round($pix_down_qtd, 2),

        ]);

    }

    protected function getAccountMovementRealized(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $occurrence_date_start = null;
        $occurrence_date_end   = null;

        if($request->occurrence_date_start != ''){
            $occurrence_date_start  = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $occurrence_date_end = $request->occurrence_date_end." 23:59:59.998";
        }

        // --- Get Transfer Payment Data ---
        $transferPayment = new TransferPayment();
        $transferPayment->master_id                    = $checkAccount->master_id;
        $transferPayment->account_id                   = $checkAccount->account_id;
        $transferPayment->status_id                    = $request->status_id;
        $transferPayment->onlyActive                   = $request->onlyActive;
        $transferPayment->occurrence_date_start        = $occurrence_date_start;
        $transferPayment->occurrence_date_end          = $occurrence_date_end;
        // --- Finish Transfer Payment Data ---

        // --- Get Payroll Payment Data ---
        $payrollPayment                                   = new PayrollRelease();
        $payrollPayment->master_id                        = $checkAccount->master_id;
        $payrollPayment->account_id                       = $checkAccount->account_id;
        $payrollPayment->status_id                        = $request->status_id;
        $payrollPayment->onlyActive                       = $request->onlyActive;
        $payrollPayment->occurrence_date_start            = $occurrence_date_start;
        $payrollPayment->occurrence_date_end              = $occurrence_date_end;

        // --- Finish Get Payroll Payment Data ---

        // --- Get Bill Payment Data ---
        $billPayment                                   = new BillPayment();
        $billPayment->master_id                        = $checkAccount->master_id;
        $billPayment->account_id                       = $checkAccount->account_id;
        $billPayment->status_id                        = $request->status_id;
        $billPayment->onlyActive                       = $request->onlyActive;
        $billPayment->occurrence_date_start            = $occurrence_date_start;
        $billPayment->occurrence_date_end              = $occurrence_date_end;

        // --- Finish Get Bill Payment Data ---

        // --- Get Card Sale Movement Data ---
        $cardSaleMovement                                   = new CardSaleMovement();
        $cardSaleMovement->master_id                        = $checkAccount->master_id;
        $cardSaleMovement->account_id                       = $checkAccount->account_id;
        $cardSaleMovement->onlyActive                       = $request->onlyActive;
        $cardSaleMovement->occurrence_date_start            = $occurrence_date_start;
        $cardSaleMovement->occurrence_date_end              = $occurrence_date_end;

        // --- Finish Get Card Sale Movement Data ---

        // --- Get Card Movement Data ---
        $cardMovement                                   = new CardMovement();
        $cardMovement->master_id                        = $checkAccount->master_id;
        $cardMovement->account_id                       = $checkAccount->account_id;
        $cardMovement->onlyActive                       = $request->onlyActive;
        $cardMovement->occurrence_date_start            = $occurrence_date_start;
        $cardMovement->occurrence_date_end              = $occurrence_date_end;

        // --- Finish Get Card Movement Data ---

        // --- Get Simple Charge Data ---
        $simpleCharge                                   = new SimpleCharge();
        $simpleCharge->master_id                        = $checkAccount->master_id;
        $simpleCharge->account_id                       = $checkAccount->account_id;
        $simpleCharge->status_id                        = $request->status_id;
        $simpleCharge->onlyActive                       = $request->onlyActive;
        $simpleCharge->occurrence_date_start            = $occurrence_date_start;
        $simpleCharge->occurrence_date_end              = $occurrence_date_end;

        // --- Finish Get Simple Charge Data ---

        // --- Get Antecipation Charge Data ---
        $antecipationCharge                                   = new AntecipationCharge();
        $antecipationCharge->master_id                        = $checkAccount->master_id;
        $antecipationCharge->account_id                       = $checkAccount->account_id;
        $antecipationCharge->status_id                        = $request->status_id;
        $antecipationCharge->onlyActive                       = $request->onlyActive;
        $antecipationCharge->occurrence_date_start            = $occurrence_date_start;
        $antecipationCharge->occurrence_date_end              = $occurrence_date_end;

        // --- Finish Get Antecipation Charge Data ---

        // --- Get Add Money Charge Data ---
        $addMoney                                   = new AccntAddMoneyBankCharging();
        $addMoney->master_id                        = $checkAccount->master_id;
        $addMoney->account_id                       = $checkAccount->account_id;
        $addMoney->status_id                        = $request->status_id;
        $addMoney->onlyActive                       = $request->onlyActive;
        $addMoney->occurrence_date_start            = $occurrence_date_start;
        $addMoney->occurrence_date_end              = $occurrence_date_end;
        // --- Finish Get Add Money Charge Data ---

        return response()->json(array(
            'success'                               => '',
            'transfer_payment_by_type'              => $transferPayment->transferPaymentRealizedByType(),
            'transfer_payment_by_bank'              => $transferPayment->transferPaymentRealizedByBank(),
            'payroll_payment_by_type'               => $payrollPayment->payrollPaymentRealizedByType(),
            'payroll_payment_by_bank'               => $payrollPayment->payrollPaymentRealizedByBank(),
            'bill_payment_by_type'                  => $billPayment->billPaymentRealizedByType(),
            'card_movement_by_type'                 => $cardMovement->cardMovementByType(),
            'card_sale_movement_approved_by_type'   => $cardSaleMovement->cardSaleMovementApprovedByType(),
            'card_sale_movement_refused_by_type'    => $cardSaleMovement->cardSaleMovementRefusedByType(),
            'card_sale_movement_payed_by_type'      => $cardSaleMovement->cardSaleMovementPayedByType(),
            'card_sale_movement_approved_by_banner' => $cardSaleMovement->cardSaleMovementApprovedByBanner(),
            'card_sale_movement_refused_by_banner'  => $cardSaleMovement->cardSaleMovementRefusedByBanner(),
            'card_sale_movement_payed_by_banner'    => $cardSaleMovement->cardSaleMovementPayedByBanner(),
            'simple_charge'                         => $simpleCharge->simpleChargeByIncluded()->merge( $simpleCharge->simpleChargeByLiquidated()->merge( $simpleCharge->simpleChargeByDown() ) ),
            'antecipation_charge'                   => $antecipationCharge->antecipationChargeByIncluded()->merge( $antecipationCharge->antecipationChargeByLiquidated()->merge( $antecipationCharge->antecipationChargeByDown() ) ),
            'add_money_charge'                      => $addMoney->addMoneyChargeByIncluded()->merge( $addMoney->addMoneyChargeByLiquidated()->merge( $addMoney->addMoneyChargeByDown() ) ),
        ));
    }

    protected function getMarginAccountEvolution(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [76];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $start_date =   null;
        $end_date   =   null;
        if($request->start_date == ''){
            $start_date  = \Carbon\Carbon::now()->addDays(-30)->format('Y-m-d');
        }
        if($request->start_date != ''){
            $start_date  = $request->start_date;
        }
        if($request->end_date != ''){
            $end_date = $request->end_date;
        }
         if($request->end_date == ''){
            $end_date = \Carbon\Carbon::now()->format('Y-m-d');
        }
        $account_movement = new AccountMovement();
        $account_movement->start_date  = $start_date;
        $account_movement->end_date    = $end_date;
       return response()->json(array("success" => "", "monthValue" => $account_movement->MarginAccountEvolution()));
    }

    protected function exportOFX(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [253, 318];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $checkAccount->account_id;
        $accountMovement->master_id  = $checkAccount->master_id;
        $start_date                  = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                    = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountMovement->date_start = $start_date." 00:00:00.000";
        $accountMovement->date_end   = $end_date." 23:59:59.998";
        $accountMovement->onlyActive = 1;

        $accountData = Account::where('id','=',$checkAccount->account_id)->first();

        $simpleOFX = new SimpleOFX();
        $simpleOFX->ofx_data = (object) [
            'account_number' => $accountData->account_number,
            'start_date'     => $start_date,
            'end_date'       => $end_date,
            'movements'      => $accountMovement->getAccountMovement(),
            'path_file'      => Storage::disk('public')->path('/')
        ];
        $createOFX = $simpleOFX->writeOFX();
        if($createOFX->success){
            $base64File = base64_encode(Storage::disk('public')->get($createOFX->file_name));
            \File::delete($createOFX->file_path);
            return response()->json(array(
                "success"    => "Arquivo de OFX gerado com sucesso",
                "file_name"  => $createOFX->file_name,
                "mime_type"  => "text/plain",
                "base64"     => $base64File
            ));
        } else {
            return response()->json(array("error" => "Não foi possível gerar o arquivo ofx, por favore tente novamente mais tarde"));
        }
    }

    protected function getMarginAccountProvision(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [78];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();

        $accountMovement->start_date         = $request->start_date." 00:00:00.000";
        $accountMovement->end_date           = $request->end_date." 23:59:59.998";
        $accountMovement->origin_account_id  = $request->origin_account_id;
        $accountMovement->movement_type_id   = $request->movement_type_id;
        $accountMovement->master_id          = $checkAccount->master_id;

        return response()->json($accountMovement->getMarginAccountProvision());
    }

    protected function reversalRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [57];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();
            if(Hash::check(base64_decode($request->password), $usr->password) ){
                $account_movements = accountMovement::whereIn('id',$request->id)->where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->get();
                if($account_movements != ''){
                    $token = new Facilites();
                    $authorizationToken = AuthorizationToken::create([
                        'token_phone'       => $token->createApprovalToken(),
                        'token_email'       => $token->createApprovalToken(),
                        'type_id'           => 5,
                        'origin_id'         => null,
                        'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
                        'token_expired'     => 0,
                        'created_at'        => \Carbon\Carbon::now()
                    ]);
                    foreach($account_movements as $account_movement){
                        $account_movement->reversal_approval_token     = $authorizationToken->token_phone;
                        $account_movement->rvrsl_apprvl_tkn_expiration = $authorizationToken->token_expiration;
                        if($account_movement->save()){
                            $sendSMS = SendSms::create([
                                'external_id' => ("13".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$account_movement->id),
                                'to'          => "55".$usr->phone,
                                'message'     => "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para aprovar estorno no valor de R$ ".number_format($account_movement->value, 2, ',','.')."",
                                'type_id'     => 13,
                                'origin_id'   => null,
                                'created_at'  => \Carbon\Carbon::now()
                            ]);
                        }
                    }

                    //Check if should send token by whatsapp
                    if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$request->header('masterId'))->first())->available == 1 ){
                        $apiZenviaWhats            = new ApiZenviaWhatsapp();
                        $apiZenviaWhats->to_number = $sendSMS->to;
                        $apiZenviaWhats->token     = "*".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4)."*";
                        if(isset( $apiZenviaWhats->sendToken()->success ) ){
                            return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove a transferência novamente para gerar outro token"));
                        }
                    }

                    $apiConfig                     = new ApiConfig();
                    $apiConfig->master_id          = $request->header('masterId');
                    $apiConfig->api_id             = 3;
                    $apiConfig->onlyActive         = 1;
                    $apiData                       = $apiConfig->getApiConfig()[0];
                    $apiZenviaSMS                  = new ApiZenviaSMS();
                    $apiZenviaSMS->api_address     = Crypt::decryptString($apiData->api_address);
                    $apiZenviaSMS->authorization   = Crypt::decryptString($apiData->api_authentication);
                    $apiZenviaSMS->id              = $sendSMS->external_id;
                    $apiZenviaSMS->aggregateId     = "001";
                    $apiZenviaSMS->to              = $sendSMS->to;
                    $apiZenviaSMS->msg             = $sendSMS->message;
                    $apiZenviaSMS->callbackOption  = "NONE";
                    if(isset( $apiZenviaSMS->sendShortSMS()->success ) ){
                        return response()->json(array("success" => "Token enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo, se necessário inicie o estorno novamente para gerar outro token"));
                    } else {
                        return response()->json(array("error" => "Não foi possível enviar o token de aprovação, por favor tente novamente"));
                    }
                } else {
                    return response()->json(array("error" => "Ocorreu um erro, pix já realizado ou não localizado"));
                }
            } else {
                return response()->json(array("error" => "Senha Inválida"));
            }
        } else {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
        }
    }

    protected function reversalLiberation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [57];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'array'],
            'approval_token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        $account_movements = accountMovement::whereIn('id',$request->id)->where('master_id','=',$checkAccount->master_id)->where('reversal_approval_token', '=', $request->approval_token)->whereNull('reversed')->whereNull('deleted_at')->get();
        $error = [];
        $items = [];
        $tokenExpireCheck = 0;

        if(sizeof($account_movements) == 0){
            return response()->json(array('error' => "Token Inválido ou lançamento(s) já estornado(s), por favor verifique e tente novamente"));
        }

        foreach($account_movements as $account_movement){

            $reversedOnCompetence = null;
            $reverseMovementCompetence = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m');
            $movementCompetence = (\Carbon\Carbon::parse( $account_movement->date )->format('Y-m'));

            if($reverseMovementCompetence == $movementCompetence){
                $reversedOnCompetence = 1;
            }

            if($account_movement->reversal_approval_token != $request->approval_token){
                return response()->json(array('error' => "Token Inválido"));
            }

            if($tokenExpireCheck == 0){
                if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse( $account_movement->rvrsl_apprvl_tkn_expiration )->format('Y-m-d H:i:s')) ){
                    $authorizationToken = AuthorizationToken::where('type_id','=',5)->where('token_phone','=',$account_movement->reversal_approval_token)->first();
                    $authorizationToken->token_expired = 1;
                    $authorizationToken->save();

                    return response()->json(array('error' => "Token inválido, token gerado a mais de 5 minutos, cancele ou inicie o estorno novamente para gerar outro token"));
                }
            }
            $tokenExpireCheck = 1;

            $accountMovement             = new AccountMovement();
            $accountMovement->account_id = $account_movement->account_id;
            $accountMovement->master_id  = $account_movement->master_id;
            $accountMovement->start_date = \Carbon\Carbon::now();
            $accountBalance              = 0;
            $accountMasterBalance        = 0;
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $accountBalance = $accountMovement->getAccountBalance()->balance;
            }
            if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
                $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
            }

            $account_movement->description     = 'Estorno | '.$account_movement->description;
            $account_movement->value           = $account_movement->value * -1;
            $account_movement->bank_fee_value  = $account_movement->bank_fee_value * -1;
            $account_movement->cofins          = $account_movement->cofins * -1;
            $account_movement->pis             = $account_movement->pis * -1;
            $account_movement->irpj            = $account_movement->irpj * -1;
            $account_movement->cssl            = $account_movement->cssl * -1;

            if($account_movement->reversed != 1){
                if(AccountMovement::create([
                    'account_id' => $account_movement->account_id,
                    'master_id' => $account_movement->master_id,
                    'origin_id' => $account_movement->origin_id,
                    'mvmnt_type_id' => $account_movement->mvmnt_type_id,
                    'date' => \Carbon\Carbon::now(),
                    'value' => $account_movement->value,
                    'balance' => ($accountBalance + $account_movement->value),
                    'master_balance' => ($accountMasterBalance + $account_movement->value),
                    'description' => $account_movement->description,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now(),
                    'accnt_origin_id' => $account_movement->accnt_origin_id,
                    'sttmnt_day_id' => $account_movement->sttmnt_day_id,
                    'bank_id' => $account_movement->bank_id,
                    'bank_fee_value' => $account_movement->bank_fee_value,
                    'tax_percentage' => $account_movement->tax_percentage,
                    'cofins' => $account_movement->cofins,
                    'pis' => $account_movement->pis,
                    'irpj' => $account_movement->irpj,
                    'cssl' => $account_movement->cssl,
                    'comission_tax_percentage' => $account_movement->comission_tax_percentage,
                    'comission_fgts_percentage' => $account_movement->comission_fgts_percentage,
                    'reverse_inserted_on_competence' => $reversedOnCompetence,
                    'uuid' => Str::orderedUuid()
                ])){
                    $accountMovementUpdated = accountMovement::where('id', '=', $account_movement->id)->first();
                    $accountMovementUpdated->reversed = 1;
                    $accountMovementUpdated->reversed_on_competence = $reversedOnCompetence;
                    $accountMovementUpdated->reversed_at = \Carbon\Carbon::now();
                    $accountMovementUpdated->save();

                    $movementType = MovementType::where('id', '=', $account_movement->mvmnt_type_id)->first();

                    if($movementType->is_fee == 1){
                        $master = Master::where('id','=',$account_movement->master_id)->first();

                        if($master->margin_accnt_id != $account_movement->account_id){

                            if($master->margin_accnt_id != ''){

                                $marginAccountMovement             = new AccountMovement();
                                $marginAccountMovement->account_id = $master->margin_accnt_id;
                                $marginAccountMovement->master_id  = $account_movement->master_id;
                                $marginAccountMovement->start_date = \Carbon\Carbon::now();
                                $marginAccountBalance              = 0;
                                $marginAccountMasterBalance        = 0;
                                if(isset( $marginAccountMovement->getAccountBalance()->balance )){
                                    $marginAccountBalance = $marginAccountMovement->getAccountBalance()->balance;
                                }
                                if(isset( $marginAccountMovement->getMasterAccountBalance()->master_balance )){
                                    $marginAccountMasterBalance = $marginAccountMovement->getMasterAccountBalance()->master_balance;
                                }

                                $masterAccountMovementUpdated = accountMovement::where('account_id', '=', $master->margin_accnt_id)
                                ->where('accnt_origin_id', '=', $account_movement->account_id)
                                ->where('mvmnt_type_id', '=', $account_movement->mvmnt_type_id)
                                ->where('origin_id', '=', $account_movement->origin_id)->first();

                                $masterAccountMovementUpdated->reversed = 1;
                                $masterAccountMovementUpdated->reversed_on_competence = $reversedOnCompetence;
                                $masterAccountMovementUpdated->reversed_at = \Carbon\Carbon::now();
                                $masterAccountMovementUpdated->save();

                                $account_movement->value           = $account_movement->value * -1;
                                $account_movement->bank_fee_value  = $account_movement->bank_fee_value * -1;
                                $account_movement->cofins          = $account_movement->cofins * -1;
                                $account_movement->pis             = $account_movement->pis * -1;
                                $account_movement->irpj            = $account_movement->irpj * -1;
                                $account_movement->cssl            = $account_movement->cssl * -1;

                                AccountMovement::create([
                                    'account_id' => $master->margin_accnt_id,
                                    'master_id' => $account_movement->master_id,
                                    'origin_id' => $account_movement->origin_id,
                                    'mvmnt_type_id' => $account_movement->mvmnt_type_id,
                                    'date' => \Carbon\Carbon::now(),
                                    'value' => $account_movement->value,
                                    'balance' => ($marginAccountBalance + $account_movement->value),
                                    'master_balance' => ($marginAccountMasterBalance + $account_movement->value),
                                    'description' => $account_movement->description,
                                    'created_at' => \Carbon\Carbon::now(),
                                    'updated_at' => \Carbon\Carbon::now(),
                                    'accnt_origin_id' => $account_movement->account_id,
                                    'sttmnt_day_id' => $account_movement->sttmnt_day_id,
                                    'bank_id' => $account_movement->bank_id,
                                    'bank_fee_value' => $account_movement->bank_fee_value,
                                    'tax_percentage' => $account_movement->tax_percentage,
                                    'cofins' => $account_movement->cofins,
                                    'pis' => $account_movement->pis,
                                    'irpj' => $account_movement->irpj,
                                    'cssl' => $account_movement->cssl,
                                    'comission_tax_percentage' => $account_movement->comission_tax_percentage,
                                    'comission_fgts_percentage' => $account_movement->comission_fgts_percentage,
                                    'reverse_inserted_on_competence' => $reversedOnCompetence,
                                    'uuid' => Str::orderedUuid()
                                ]);



                            }
                        }
                    }
                }
            }
        }
        return response()->json(array('success' => 'Estorno(s) realizado(s) com sucesso'));
    }

    protected function getCelcoinBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig                 = new ApiConfig();
        $apiConfig->master_id      = $checkAccount->master_id;
        $apiConfig->onlyActive     = 1;
        $apiConfig->api_id         = 8;
        $api_cel_coin              = $apiConfig->getApiConfig()[0];

        $apiCelCoin                = new ApiCelCoin();
        $apiCelCoin->api_address   = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id     = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type    = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret = Crypt::decryptString($api_cel_coin->api_authentication);

        return response()->json($apiCelCoin->merchantBalance());
    }

    protected function getReceivedFeeValues(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account_movement = new AccountMovement();

        $account_movement->master_id     = $checkAccount->master_id;
        $account_movement->account_id    = $checkAccount->account_id;
        $account_movement->manager_id    = $request->manager_id;
        $account_movement->status_id     = $request->status_id;

        if($request->occurrence_date_start != ''){
            $account_movement->created_at_start = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $account_movement->created_at_end = $request->occurrence_date_end." 23:59:59.998";
        }
        return response()->json($account_movement->getReceivedFeeValues());
    }

    protected function accountResume(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountBalance      = 0;
        $accountBalance      = 0;
        $dayInputValue       = 0;
        $dayOutputValue      = 0;
        $scheduleValueTrans  = 0;
        $scheduleValue       = 0;
        $aliasAccountData = [
            "is_alias_account" => false,
            "bank" => null,
            "bank_number" => null,
            "agency" => null,
            "account" => null,
            "cpf_cnpj" => null,
            "name" => null
        ];
        $pixData = [
            "base64"        => null,
            'description'   => null,
            "id"            => null,
            "trasaction_id" => null,
            "mime_type"     => null,
            'emvqrps'       => null,
        ];

        //check permission to see values
        if(UsrRltnshpPrmssn::whereIn('permission_id',[173,254,411])->where('usr_rltnshp_id','=',$checkAccount->user_relationship_id)->whereNull('deleted_at')->count() > 0){
            $accountMovement             = new AccountMovement();
            $accountMovement->account_id = $checkAccount->account_id;
            $accountMovement->master_id  = $checkAccount->master_id;
            $accountMovement->start_date =  \Carbon\Carbon::now();
    
            if (isset( $accountMovement->getAccountBalance()->balance )) {
                $accountBalance = $accountMovement->getAccountBalance()->balance;
            }

            $accountMovement->start_date = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
            if (isset( $accountMovement->getDayInputValues()->dayInputValue )) {
                $dayInputValue = $accountMovement->getDayInputValues()->dayInputValue;
            }
            if (isset( $accountMovement->getDayOutputValues()->dayOutputValue )) {
                $dayOutputValue = $accountMovement->getDayOutputValues()->dayOutputValue;
            }

            $transferPayment             = new TransferPayment();
            $transferPayment->master_id  = $checkAccount->master_id;
            $transferPayment->account_id = $checkAccount->account_id;

            if (isset($transferPayment->getTransferPaymentScheduledValue()->scheduleValue)) {
                $scheduleValueTrans = $transferPayment->getTransferPaymentScheduledValue()->scheduleValue;
            }

            $billPayment                 = new BillPayment();
            $billPayment->master_id      = $checkAccount->master_id;
            $billPayment->account_id     = $checkAccount->account_id;

            if (isset($billPayment->getBillPaymentScheduledValue()->scheduleValue)) {
                $scheduleValue = $billPayment->getBillPaymentScheduledValue()->scheduleValue;
            }
        }

        $aliasAccountCheck = Account::where('id', '=', $checkAccount->account_id)->where('is_alias_account', '=', 1);

        if($aliasAccountCheck->count() > 0){
            $aliasAccount = $aliasAccountCheck->first();
            $aliasAccountBank = Bank::where('id','=',$aliasAccount->alias_account_bank_id)->first();

            $register_detail = RegisterDetail::where('register_master_id', '=', $aliasAccount->register_master_id)->first();
            $register_master = RegisterMaster::where('id', '=', $aliasAccount->register_master_id)->first();
            $register = Register::where("id", "=", $register_master->register_id)->first();

            $aliasAccountData = [
                "is_alias_account" => true,
                "bank" => $aliasAccountBank->name." - ".$aliasAccountBank->number,
                "bank_number" => $aliasAccountBank->number,
                "agency" => substr( $aliasAccount->alias_account_agency, 0, -1),
                "account" =>  ( (int) substr($aliasAccount->alias_account_number,0,-1) )."-".substr( $aliasAccount->alias_account_number,-1),
                "cpf_cnpj" => $register->cpf_cnpj,
                "name" => $register_detail->name
            ];

        }

        $staticPix = PixStaticReceive::where('account_id','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->where('status_id','=',4)->where('only_for_account','=',1)->whereNull('deleted_at');

        if ($staticPix->count() > 0) {
            $pix_static_receive = new PixStaticReceive();

            $pix_static_receive->id = $staticPix->first()->id;
            $pix_static_receive->onlyForAccount = 1;

            $pix_static_receives    = $pix_static_receive->get()[0];

            if (isset($pix_static_receives->emvqrps)) {

                $qrCodeGenerator        = new QrCodeGenerator();
                $qrCodeGenerator->data  = $pix_static_receives->emvqrps;

                $pixData = [
                    "description"   => $pix_static_receives->description,
                    "emvqrps"       => $pix_static_receives->emvqrps,
                    "id"            => $pix_static_receives->id,
                    "trasaction_id" => $pix_static_receives->transaction_id,
                    "register_name" => $pix_static_receives->register_name,
                    "mime_type"     => "image/jpeg",
                    "base64"        => substr($qrCodeGenerator->createQrCode()->base64, 22),
                ];
            }
        }

        $ip4PixData = [
            "is_ip4y_pix" => false,
            "bank" => null,
            "bank_number" => null,
            "agency" => null,
            "account" => null,
            "cpf_cnpj" => null,
            "name" => null
        ];

        if ($ip4yPixCheck = IndirectPixAddressingKey::where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->latest()->first() ) {
            $ip4PixData = [
                "is_ip4y_pix" => true,
                "bank" => 'IP4Y INSTITUIÇÃO DE PAGAMENTO LTDA - '.config('bancoRendimentoPixIndireto.ispb'),
                "bank_number" => config('bancoRendimentoPixIndireto.ispb'),
                "agency" => $ip4yPixCheck->account_agency,
                "account" =>  ( (int) substr($ip4yPixCheck->account_number,0,-1) )."-".substr( $ip4yPixCheck->account_number,-1),
                "cpf_cnpj" => $ip4yPixCheck->owner_cpf_cnpj,
                "name" => $ip4yPixCheck->owner_name
            ];
        }
        
        $userRelationship = UserRelationship::where('id', $checkAccount->user_relationship_id)->first();
        if( $userRelationship->relationship_id == 7){
            $account  = Account::where('id', $checkAccount->account_id)->first();
            $employee = PayrollEmployeeDetail::where('bank_account', $account->account_number)->first();
            $registerRequest = RegisterRequest::where('id', $employee->register_request_id)->where('register_request_type_id', 3)->whereNull('deleted_at')->first();


            return response()->json(
                array(
                    "success" => "",
                    "balance_value"                   => $accountBalance,
                    "day_input_value"                 => $dayInputValue,
                    "day_output_value"                => $dayOutputValue,
                    "transfer_payment_schedule_value" => $scheduleValueTrans,
                    "bill_payment_schedule_value"     => $scheduleValue,
                    "alias_account_data"              => $aliasAccountData,
                    "pix_data"                        => $pixData,
                    "ip4y_pix_data"                   => $ip4PixData,
                    "account_status"                  => $registerRequest['action_status_id'],
                )
            );
        }

        return response()->json(
            array(
                "success" => "",
                "balance_value"                   => $accountBalance,
                "day_input_value"                 => $dayInputValue,
                "day_output_value"                => $dayOutputValue,
                "transfer_payment_schedule_value" => $scheduleValueTrans,
                "bill_payment_schedule_value"     => $scheduleValue,
                "ip4y_pix_data"                   => $ip4PixData,
                "alias_account_data"              => $aliasAccountData,
                "pix_data"                        => $pixData,
                "is_account_approved"             => $checkAccount,
            )
        );
    }

    protected function getAccountDailyBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $dailyBalance       = new AccountMovementClass();
        $dailyBalance->data = $request;
        $dailyBalanceData   = $dailyBalance->getPeriodDailyBalance();
        return response()->json(array("success" => $dailyBalanceData->message_pt_br, "data" => $dailyBalanceData));
    }

    protected function getAccountStatistics(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountStatistic = new AccountMovementClass();
        $accountStatistic->data = $request;
        $accountStatistic = $accountStatistic->getStatistics();

        return response()->json(array("success" => $accountStatistic->message_pt_br, "data" => $accountStatistic));
    }

    protected function getCelcoinMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig                      = new ApiConfig();
        $apiConfig->master_id           = $checkAccount->master_id;
        $apiConfig->onlyActive          = 1;
        $apiConfig->api_id              = 8;
        $api_cel_coin                   = $apiConfig->getApiConfig()[0];

        $apiCelCoin                     = new ApiCelCoin();
        $apiCelCoin->api_address        = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id          = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type         = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret      = Crypt::decryptString($api_cel_coin->api_authentication);

        $apiCelCoin->transactionId      = $request->transactionId;
        $apiCelCoin->externalNSU        = $request->externalNSU;
        $apiCelCoin->externalTerminal   = $request->externalTerminal;
        $apiCelCoin->operationDate      = $request->operationDate;

        return response()->json($apiCelCoin->transactionConsult());
    }

    protected function getCelcoinMovementOccurrency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig                 = new ApiConfig();
        $apiConfig->master_id      = $checkAccount->master_id;
        $apiConfig->onlyActive     = 1;
        $apiConfig->api_id         = 8;
        $api_cel_coin              = $apiConfig->getApiConfig()[0];

        $apiCelCoin                = new ApiCelCoin();
        $apiCelCoin->api_address   = Crypt::decryptString($api_cel_coin->api_address);
        $apiCelCoin->client_id     = Crypt::decryptString($api_cel_coin->api_client_id);
        $apiCelCoin->grant_type    = Crypt::decryptString($api_cel_coin->api_key);
        $apiCelCoin->client_secret = Crypt::decryptString($api_cel_coin->api_authentication);

        $apiCelCoin->DataInicio    = $request->DataInicio ? $request->DataInicio."T23:00:00": "";
        $apiCelCoin->DataFim       = $request->DataFim ? $request->DataFim."T23:00:00": "";

        return response()->json($apiCelCoin->transactionConsultByOccurrency());
    }

    protected function getMarginCalculationInFirstLevel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;
        $accountMovement->master_id = $checkAccount->master_id;
        return response()->json($accountMovement->getMarginCalculationInFirstLevel());
    }

    protected function getMarginCalculationOutFirstLevel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;
        $accountMovement->master_id = $checkAccount->master_id;
        return response()->json($accountMovement->getMarginCalculationOutFirstLevel());
    }

    protected function getMarginCalculationInSecondLevel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;
        $accountMovement->master_id = $checkAccount->master_id;
        $accountMovement->movement_type_id = $request->movement_type_id;
        return response()->json($accountMovement->getMarginCalculationInSecondLevel());
    }

    protected function getMarginCalculationOutSecondLevel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;
        $accountMovement->master_id = $checkAccount->master_id;
        $accountMovement->movement_type_id = $request->movement_type_id;
        return response()->json($accountMovement->getMarginCalculationOutSecondLevel());
    }

    protected function getMarginCalculationInThirdLevel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;
        $accountMovement->master_id = $checkAccount->master_id;
        $accountMovement->movement_type_id = $request->movement_type_id;
        $accountMovement->account_id = $request->account_id;
        return response()->json($accountMovement->getMarginCalculationInThirdLevel());
    }

    protected function getMarginCalculationOutThirdLevel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;
        $accountMovement->master_id = $checkAccount->master_id;
        $accountMovement->movement_type_id = $request->movement_type_id;
        $accountMovement->account_id = $request->account_id;
        return response()->json($accountMovement->getMarginCalculationOutThirdLevel());
    }

    protected function getMarginCalculation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountMovement = new AccountMovement();
        $accountMovement->start_date = $request->start_date;
        $accountMovement->end_date = $request->end_date;

        $in = ($accountMovement->getMarginCalculationIn())->in_value;
        $out = ($accountMovement->getMarginCalculationOut())->out_value;

        return response()->json(array( "success" => [
            'in' => (float) $in,
            'out' => (float) ($out * -1),
            'result' => (float) $in - ($out * -1)
        ]));

    }

    protected function importOFXStatement(Request $request)
    {
        $fileName = strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName);
        if(Storage::disk('ofx_upload')->put($fileName, base64_decode($request->file64))){
            $path = Storage::disk('ofx_upload')->path($fileName);
            if(\File::extension($path) <> 'ofx' and \File::extension($path) <> 'OFX'){
                Storage::disk('ofx_upload')->delete($fileName);
                return response()->json(array("error" => "Tipos de arquivo não permitido para importação OFX"));
            }

            $simpleOFX = new SimpleOFX();
            $simpleOFX->pathFile = $path;
            $importOFXFile = $simpleOFX->readOFX();

            Storage::disk('ofx_upload')->delete($fileName);
            if(!$importOFXFile->success){
                return response()->json(array("error" => "Não foi possível importar o arquivo OFX, por favor tente novamente mais tarde.", "data" => $importOFXFile));
            }
            return response()->json(array("success" => "Arquivo OFX importado com sucesso", "data" => $importOFXFile->data));
        }
        return response()->json(array("error" => "Não foi possível importar o arquivo OFX, por favor tente novamsssente mais tarde."));
    }


    public function getTotalBalanceMoneyPlus()
    {

        $moneyPlusBalance = 0;

        $getMoneyPlusAccounts = Account::where('alias_account_bank_id', '=', 161)->get();

        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];
        $apiMoneyPlus = new ApiMoneyPlus();

        foreach($getMoneyPlusAccounts as $aliasAccount){
            $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);
            $apiMoneyPlus->alias_account_agency = $aliasAccount->alias_account_agency;
            $apiMoneyPlus->alias_account_number = $aliasAccount->alias_account_number;

            $checkBalance = $apiMoneyPlus->checkBalance();


            if ( isset( $checkBalance->data->vlrSaldo ) ) {
                $moneyPlusBalance += $checkBalance->data->vlrSaldo;
            }

            if ( isset( $checkBalance->data->vlrBloqueado ) ) {
                if( $checkBalance->data->vlrBloqueado > 0 ) {
                    $moneyPlusBalance -= $checkBalance->data->vlrSaldo;
                }
            }



        }

        return $moneyPlusBalance;
    }

    public function createMovementMoneyPlus()
    {

       /*$apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];

        $apiMoneyPlusTransfer = new ApiMoneyPlus();
        $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);
        $apiMoneyPlusTransfer->alias_account_agency = "00018";
        $apiMoneyPlusTransfer->alias_account_number = "02258721"; //00790584

        $apiMoneyPlusTransfer->favored_agency = "00018";
        $apiMoneyPlusTransfer->favored_account = "00790584"; //Conta Recebedora BMP
        $apiMoneyPlusTransfer->favored_account_type = 3;

        $apiMoneyPlusTransfer->value = 1601.13;

        $apiMoneyPlusTransfer->id = Str::orderedUuid();//$sttmntMoneyPlus->uuid;

        $transfer = $apiMoneyPlusTransfer->transferBetweenAccounts();

        return true; */



        $getMoneyPlusAccounts = Account::where('alias_account_bank_id', '=', 161)->get();

        foreach($getMoneyPlusAccounts as $aliasAccount){

            $apiConfig = new ApiConfig();
            $apiConfig->master_id = $aliasAccount->master_id;
            $apiConfig->api_id = 15;
            $apiConfig->onlyActive = 1;
            $apiData = $apiConfig->getApiConfig()[0];
            $apiMoneyPlus = new ApiMoneyPlus();
            $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);
            $apiMoneyPlus->alias_account_agency = $aliasAccount->alias_account_agency;
            $apiMoneyPlus->alias_account_number = $aliasAccount->alias_account_number;


            $apiMoneyPlus->year = (int) \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y');
            $apiMoneyPlus->month = (int) \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('m');
            $apiMoneyPlus->start_day = (int) \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('d');
            $apiMoneyPlus->end_day = (int) \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('d');

            $movements = $apiMoneyPlus->checkExtract();
            $blockedValue = 0;
            if(isset($movements->data->movimentos)){
                foreach($movements->data->movimentos as $movement){

                    if($movement->descricaoOperacao == "SALDO BLOQUEADO"){
                        $blockedValue = $movement->vlrMovimento;
                        /*if( $blockedValue > 0 ) {
                            $sendFailureAlert = new sendFailureAlert();
                            $sendFailureAlert->title  = 'Falha Transferencia para conta principal BMP - Valor Bloqueado';
                            $sendFailureAlert->errorMessage = 'Atenção, ocorreu uma falha ao transferir da conta '. $aliasAccount->account_number.' (BMP '.$aliasAccount->alias_account_number.'), a conta possuí o valor de '.$blockedValue.' bloqueado pelo Banco BMP.' ;
                            $sendFailureAlert->sendFailures();
                        } */
                    }

                    if (isset($movement->tipoLancamento)) {
                        if( $movement->tipoLancamento == "C" or $movement->tipoLancamento == "D"  ){

                            $transactionSuccess = true;

                            $account_balance = StatementMoneyPlusBank::where('account_id', '=', $aliasAccount->id)->sum('value');

                            if( StatementMoneyPlusBank::where('code', '=', $movement->codigo)->where('account_id', '=', $aliasAccount->id)->count() == 0 ){
                                $sttmntMoneyPlus = StatementMoneyPlusBank::create([
                                    'uuid' => Str::orderedUuid(),
                                    'account_id' => $aliasAccount->id,
                                    'code' => $movement->codigo,
                                    'transaction_code' => $movement->codigoTransacao,
                                    'transaction_identificator' => $movement->identificadorOperacao,
                                    'nsu' => $movement->nsu,
                                    'type' => $movement->tipoLancamento,
                                    'date' => $movement->dtMovimento,
                                    'value' => ($movement->tipoLancamento == 'C') ? $movement->vlrMovimento : ($movement->vlrMovimento * -1),
                                    'account_balance' => ($movement->tipoLancamento == 'C') ? ($movement->vlrMovimento + $account_balance) : (($movement->vlrMovimento * -1) + $account_balance),
                                    'transaction_description' => $movement->descricaoOperacao,
                                    'client_observation' => $movement->descCliente,
                                    'cpf_cnpj' => $movement->documentoFederal,
                                    'name' => $movement->nome,
                                    'origin_transaction' => $movement->origemTransacao,
                                    'origin_bank' => $movement->bancoOrigem,
                                    'origin_agency' => $movement->agenciaOrigem,
                                    'origin_account' => $movement->contaOrigem,
                                    'created_at' => \Carbon\Carbon::now()
                                ]);




                                //Transfere para conta BMP Dinari (Se conta diferente da Conta Principal)
                                if( $movement->identificadorOperacao == "TED REC" or $movement->identificadorOperacao == "TRAC" or $movement->identificadorOperacao == "PIXREC" or $movement->identificadorOperacao == "SPLITPCT_C"){


                                    if ($aliasAccount->id != 356){
                                        if(  $blockedValue == 0 or $blockedValue == null ) {

                                            if( $aliasAccount->alias_account_keep_balance != 1 ) {

                                                $apiMoneyPlusTransfer = new ApiMoneyPlus();
                                                $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
                                                $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);
                                                $apiMoneyPlusTransfer->alias_account_agency = $aliasAccount->alias_account_agency;
                                                $apiMoneyPlusTransfer->alias_account_number = $aliasAccount->alias_account_number;

                                                $apiMoneyPlusTransfer->favored_agency = "00018";
                                                $apiMoneyPlusTransfer->favored_account = "00790584"; //Conta Recebedora BMP
                                                $apiMoneyPlusTransfer->favored_account_type = 3;

                                                $apiMoneyPlusTransfer->value = $movement->vlrMovimento;

                                                $apiMoneyPlusTransfer->id = Str::orderedUuid();//$sttmntMoneyPlus->uuid;

                                                $transfer = $apiMoneyPlusTransfer->transferBetweenAccounts();

                                                if( !$transfer->success ){

                                                    $errorMessage = null;
                                                    if( isset( $transfer->data->mensagem ) ) {
                                                        $errorMessage = $transfer->data->mensagem;
                                                    }

                                                    //Delete fail transfer from bmp statement
                                                    StatementMoneyPlusBank::where('id', '=', $sttmntMoneyPlus->id)->where('uuid', '=', $sttmntMoneyPlus->uuid)->delete();

                                                    $sendFailureAlert               = new sendFailureAlert();
                                                    $sendFailureAlert->title        = 'Falha Transferencia para conta principal BMP';
                                                    $sendFailureAlert->errorMessage = 'Atenção, ocorreu uma falha ao transferir da conta BMP '.$aliasAccount->alias_account_number.', para conta principal. Realize o processo de transferência e credite manualmente a conta destino. Erro '.$errorMessage ;
                                                    $sendFailureAlert->sendFailures();

                                                    $transactionSuccess = false;
                                                }

                                            }

                                            if($transactionSuccess == true) {
                                                $fromCpfCnpj = '';
                                                if( isset($sttmntMoneyPlus->cpf_cnpj) ) {
                                                // $fromCpfCnpj = ' | CPF/CNPJ: '.Facilites::hideCpfCnpj($sttmntMoneyPlus->cpf_cnpj);
                                                    $fromCpfCnpj = ' | CPF/CNPJ: '.Facilites::mask_cpf_cnpj($sttmntMoneyPlus->cpf_cnpj);
                                                }

                                                //Create Movement
                                                $movementDescription = 'TED Recebida | De: '.(isset($sttmntMoneyPlus->name) ? $sttmntMoneyPlus->name : $sttmntMoneyPlus->client_observation).$fromCpfCnpj;
                                                $movementCode = 50;
                                                $movementTaxDescription = 'Tarifa de TED Recebida |  De: '.(isset($sttmntMoneyPlus->name) ? $sttmntMoneyPlus->name : $sttmntMoneyPlus->client_observation).$fromCpfCnpj;
                                                $movementTaxCode = 51;

                                                if( $movement->identificadorOperacao == "PIXREC" ){
                                                    $movementDescription = 'PIX Transferência Recebido | De: '.$sttmntMoneyPlus->name.$fromCpfCnpj;
                                                    $movementCode = 41;
                                                    $movementTaxDescription = 'Tarifa de PIX Transferência Recebido |  De: '.$sttmntMoneyPlus->name.$fromCpfCnpj;
                                                    $movementTaxCode = 33;
                                                }


                                                $movementService = new MovementService();
                                                $movementService->movementData = (object)[
                                                    'account_id'    => $sttmntMoneyPlus->account_id,
                                                    'master_id'     => $aliasAccount->master_id,
                                                    'origin_id'     => $sttmntMoneyPlus->id,
                                                    'mvmnt_type_id' => $movementCode,
                                                    'value'         => round($sttmntMoneyPlus->value,2),
                                                    'description'   => $movementDescription,
                                                ];
                                                if(!$movementService->create()){
                                                    $sendFailureAlert               = new sendFailureAlert();
                                                    $sendFailureAlert->title        = 'Falha crédito de TED/PIX';
                                                    $sendFailureAlert->errorMessage = 'TED/PIX não creditada para conta '.$sttmntMoneyPlus->account_id.' | Valor: '. $sttmntMoneyPlus->value .' | Falha na inserção' ;
                                                    $sendFailureAlert->sendFailures();
                                                } else {
                                                    //tarifa

                                                    $availablePaymentValue = $sttmntMoneyPlus->value;
                                                    //check if account has movement future to debit
                                                    $accountMovementFuture = new AccountMovementFutureClass();
                                                    $accountMovementFuture->account_id = $sttmntMoneyPlus->account_id;
                                                    $accountMovementFuture->master_id = $aliasAccount->master_id;

                                                    foreach ($accountMovementFuture->get() as $movementFuture) {
                                                        if ($availablePaymentValue > 0) {
                                                            $accountMovementFuture->id = $movementFuture->id;
                                                            $accountMovementFuture->uuid = $movementFuture->uuid;
                                                            $accountMovementFuture->available_value = $availablePaymentValue;

                                                            $payMovementFuture = $accountMovementFuture->payMovementFuture();

                                                            $availablePaymentValue = $payMovementFuture->available_value;
                                                        }
                                                    }

                                                    $tax = 0;
                                                    $getTax = Account::getTax($sttmntMoneyPlus->account_id, 29, $aliasAccount->master_id);
                                                    if($getTax->value > 0){
                                                        $tax = $getTax->value;
                                                    } else if($getTax->percentage > 0){
                                                        if(round($sttmntMoneyPlus->value,2) > 0){
                                                            $tax = round(( ($getTax->percentage/100) * round($sttmntMoneyPlus->value,2)),2);
                                                        }
                                                    }
                                                    if($tax > 0  and $availablePaymentValue >= $tax){
                                                        //create movement for payment tax value
                                                        $movementTax = new MovementTaxService();
                                                        $movementTax->movementData = (object) [
                                                            'account_id'    => $sttmntMoneyPlus->account_id,
                                                            'master_id'     => $aliasAccount->master_id,
                                                            'origin_id'     => $sttmntMoneyPlus->id,
                                                            'mvmnt_type_id' => $movementTaxCode,
                                                            'value'         => $tax,
                                                            'description'   => $movementTaxDescription,
                                                        ];
                                                        if(!$movementTax->create()){
                                                            $sendFailureAlert               = new sendFailureAlert();
                                                            $sendFailureAlert->title        = 'Falha lançamentos de tarifa de TED';
                                                            $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de TED na conta '.$sttmntMoneyPlus->account_id;
                                                            $sendFailureAlert->sendFailures();
                                                        }
                                                    }
                                                }

                                            }

                                        }
                                    }
                                }

                                //Debita Conta Cliente Em Caso de Devolução de PIX
                                if( $movement->identificadorOperacao == "PIXDEV" ) {
                                    // Apenas para Débito
                                    if(  $movement->tipoLancamento == "D" ) {
                                        $movementDescription = $movement->descricaoOperacao.' | NSU: '.$movement->nsu;
                                        $movementCode = 41;
                                        $movementService = new MovementService();
                                        $movementService->movementData = (object)[
                                            'account_id'    => $sttmntMoneyPlus->account_id,
                                            'master_id'     => $aliasAccount->master_id,
                                            'origin_id'     => $sttmntMoneyPlus->id,
                                            'mvmnt_type_id' => $movementCode,
                                            'value'         => round($sttmntMoneyPlus->value,2),
                                            'description'   => $movementDescription,
                                        ];
                                        if(!$movementService->create()){
                                            $sendFailureAlert               = new sendFailureAlert();
                                            $sendFailureAlert->title        = 'Falha débito de TED/PIX';
                                            $sendFailureAlert->errorMessage = 'TED/PIX não debitado para conta '.$sttmntMoneyPlus->account_id.' | Valor: '. $sttmntMoneyPlus->value .' | Falha na inserção' ;
                                            $sendFailureAlert->sendFailures();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function checkYesterdayMovementMoneyPlus()
    {

        $getMoneyPlusAccounts = Account::where('alias_account_bank_id', '=', 161)->get();

        foreach($getMoneyPlusAccounts as $aliasAccount){

            $apiConfig = new ApiConfig();
            $apiConfig->master_id = $aliasAccount->master_id;
            $apiConfig->api_id = 15;
            $apiConfig->onlyActive = 1;
            $apiData = $apiConfig->getApiConfig()[0];
            $apiMoneyPlus = new ApiMoneyPlus();
            $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);
            $apiMoneyPlus->alias_account_agency = $aliasAccount->alias_account_agency;
            $apiMoneyPlus->alias_account_number = $aliasAccount->alias_account_number;


            $apiMoneyPlus->year = (int) \Carbon\Carbon::parse( \Carbon\Carbon::yesterday() )->format('Y');
            $apiMoneyPlus->month = (int) \Carbon\Carbon::parse( \Carbon\Carbon::yesterday() )->format('m');
            $apiMoneyPlus->start_day = (int) \Carbon\Carbon::parse( \Carbon\Carbon::yesterday() )->format('d');
            $apiMoneyPlus->end_day = (int) \Carbon\Carbon::parse( \Carbon\Carbon::yesterday() )->format('d');

            $movements = $apiMoneyPlus->checkExtract();
            $blockedValue = 0;
            if(isset($movements->data->movimentos)){
                foreach($movements->data->movimentos as $movement){


                    if($movement->descricaoOperacao == "SALDO BLOQUEADO"){
                        $blockedValue = $movement->vlrMovimento;
                        /*if( $blockedValue > 0 ) {
                            $sendFailureAlert = new sendFailureAlert();
                            $sendFailureAlert->title  = 'Falha Transferencia para conta principal BMP - Valor Bloqueado';
                            $sendFailureAlert->errorMessage = 'Atenção, ocorreu uma falha ao transferir da conta '. $aliasAccount->account_number.' (BMP '.$aliasAccount->alias_account_number.'), a conta possuí o valor de '.$blockedValue.' bloqueado pelo Banco BMP.' ;
                            $sendFailureAlert->sendFailures();
                        } */
                    }


                    if (isset($movement->tipoLancamento)) {
                        if( $movement->tipoLancamento == "C" or $movement->tipoLancamento == "D"  ){

                            $account_balance = StatementMoneyPlusBank::where('account_id', '=', $aliasAccount->id)->sum('value');

                            if( StatementMoneyPlusBank::where('code', '=', $movement->codigo)->where('account_id', '=', $aliasAccount->id)->count() == 0 ){
                                $sttmntMoneyPlus = StatementMoneyPlusBank::create([
                                    'uuid' => Str::orderedUuid(),
                                    'account_id' => $aliasAccount->id,
                                    'code' => $movement->codigo,
                                    'transaction_code' => $movement->codigoTransacao,
                                    'transaction_identificator' => $movement->identificadorOperacao,
                                    'nsu' => $movement->nsu,
                                    'type' => $movement->tipoLancamento,
                                    'date' => $movement->dtMovimento,
                                    'value' => ($movement->tipoLancamento == 'C') ? $movement->vlrMovimento : ($movement->vlrMovimento * -1),
                                    'account_balance' => ($movement->tipoLancamento == 'C') ? ($movement->vlrMovimento + $account_balance) : (($movement->vlrMovimento * -1) + $account_balance),
                                    'transaction_description' => $movement->descricaoOperacao,
                                    'client_observation' => $movement->descCliente,
                                    'cpf_cnpj' => $movement->documentoFederal,
                                    'name' => $movement->nome,
                                    'origin_transaction' => $movement->origemTransacao,
                                    'origin_bank' => $movement->bancoOrigem,
                                    'origin_agency' => $movement->agenciaOrigem,
                                    'origin_account' => $movement->contaOrigem,
                                    'created_at' => \Carbon\Carbon::now()
                                ]);


                                //Transfere para conta BMP Dinari (Se conta diferente da Conta Principal)
                                if( $movement->identificadorOperacao == "TED REC" or $movement->identificadorOperacao == "TRAC" or $movement->identificadorOperacao == "PIXREC" or $movement->identificadorOperacao == "SPLITPCT_C"){




                                    if ($aliasAccount->id != 356){
                                        //if( $movement->documentoFederal != '11491029000130' and  $movement->identificadorOperacao != 'TRAC'  ) {

                                            if( $aliasAccount->alias_account_keep_balance != 1 ) {
                                                $apiMoneyPlusTransfer = new ApiMoneyPlus();
                                                $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
                                                $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);
                                                $apiMoneyPlusTransfer->alias_account_agency = $aliasAccount->alias_account_agency;
                                                $apiMoneyPlusTransfer->alias_account_number = $aliasAccount->alias_account_number;

                                                $apiMoneyPlusTransfer->favored_agency = "00018";
                                                $apiMoneyPlusTransfer->favored_account = "00790584"; //Conta Recebedora BMP
                                                $apiMoneyPlusTransfer->favored_account_type = 3;

                                                $apiMoneyPlusTransfer->value = $movement->vlrMovimento;

                                                $apiMoneyPlusTransfer->id = Str::orderedUuid();//$sttmntMoneyPlus->uuid;

                                                $transfer = $apiMoneyPlusTransfer->transferBetweenAccounts();

                                                if( !$transfer->success ){

                                                    //Delete fail transfer from bmp statement
                                                    StatementMoneyPlusBank::where('id', '=', $sttmntMoneyPlus->id)->where('uuid', '=', $sttmntMoneyPlus->uuid)->delete();

                                                    $sendFailureAlert               = new sendFailureAlert();
                                                    $sendFailureAlert->title        = 'Falha Transferencia para conta principal BMP';
                                                    $sendFailureAlert->errorMessage = 'Atenção, ocorreu uma falha ao transferir da conta BMP '.$aliasAccount->alias_account_number.', para conta principal. Realize o processo de transferência e credite manualmente a conta destino. Erro '.$transfer->data ;
                                                    $sendFailureAlert->sendFailures();
                                                    return false;
                                                }
                                            }

                                            //Create Movement
                                            $movementDescription = 'TED Recebida | De: '.(isset($sttmntMoneyPlus->name) ? $sttmntMoneyPlus->name : $sttmntMoneyPlus->client_observation);
                                            $movementCode = 50;
                                            $movementTaxDescription = 'Tarifa de TED Recebida |  De: '.(isset($sttmntMoneyPlus->name) ? $sttmntMoneyPlus->name : $sttmntMoneyPlus->client_observation);
                                            $movementTaxCode = 51;

                                            if( $movement->identificadorOperacao == "PIXREC" ){
                                                $movementDescription = 'PIX Transferência Recebido | De: '.$sttmntMoneyPlus->name;
                                                $movementCode = 41;
                                                $movementTaxDescription = 'Tarifa de PIX Transferência Recebido |  De: '.$sttmntMoneyPlus->name;
                                                $movementTaxCode = 33;
                                            }


                                            $movementService = new MovementService();
                                            $movementService->movementData = (object)[
                                                'account_id'    => $sttmntMoneyPlus->account_id,
                                                'master_id'     => $aliasAccount->master_id,
                                                'origin_id'     => $sttmntMoneyPlus->id,
                                                'mvmnt_type_id' => $movementCode,
                                                'value'         => round($sttmntMoneyPlus->value,2),
                                                'description'   => $movementDescription,
                                            ];
                                            if(!$movementService->create()){
                                                $sendFailureAlert               = new sendFailureAlert();
                                                $sendFailureAlert->title        = 'Falha crédito de TED/PIX';
                                                $sendFailureAlert->errorMessage = 'TED/PIX não creditada para conta '.$sttmntMoneyPlus->account_id.' | Valor: '. $sttmntMoneyPlus->value .' | Falha na inserção' ;
                                                $sendFailureAlert->sendFailures();
                                            } else {
                                                //tarifa

                                                $availablePaymentValue = $sttmntMoneyPlus->value;
                                                //check if account has movement future to debit
                                                $accountMovementFuture = new AccountMovementFutureClass();
                                                $accountMovementFuture->account_id = $sttmntMoneyPlus->account_id;
                                                $accountMovementFuture->master_id = $aliasAccount->master_id;

                                                foreach ($accountMovementFuture->get() as $movementFuture) {
                                                    if ($availablePaymentValue > 0) {
                                                        $accountMovementFuture->id = $movementFuture->id;
                                                        $accountMovementFuture->uuid = $movementFuture->uuid;
                                                        $accountMovementFuture->available_value = $availablePaymentValue;

                                                        $payMovementFuture = $accountMovementFuture->payMovementFuture();

                                                        $availablePaymentValue = $payMovementFuture->available_value;
                                                    }
                                                }

                                                $tax = 0;
                                                $getTax = Account::getTax($sttmntMoneyPlus->account_id, 29, $aliasAccount->master_id);
                                                if($getTax->value > 0){
                                                    $tax = $getTax->value;
                                                } else if($getTax->percentage > 0){
                                                    if(round($sttmntMoneyPlus->value,2) > 0){
                                                        $tax = round(( ($getTax->percentage/100) * round($sttmntMoneyPlus->value,2)),2);
                                                    }
                                                }
                                                if($tax > 0  and $availablePaymentValue >= $tax){
                                                    //create movement for payment tax value
                                                    $movementTax = new MovementTaxService();
                                                    $movementTax->movementData = (object) [
                                                        'account_id'    => $sttmntMoneyPlus->account_id,
                                                        'master_id'     => $aliasAccount->master_id,
                                                        'origin_id'     => $sttmntMoneyPlus->id,
                                                        'mvmnt_type_id' => $movementTaxCode,
                                                        'value'         => $tax,
                                                        'description'   => $movementTaxDescription,
                                                    ];
                                                    if(!$movementTax->create()){
                                                        $sendFailureAlert               = new sendFailureAlert();
                                                        $sendFailureAlert->title        = 'Falha lançamentos de tarifa de TED';
                                                        $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de TED na conta '.$sttmntMoneyPlus->account_id;
                                                        $sendFailureAlert->sendFailures();
                                                    }
                                                }
                                            }

                                        //}
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function sendTransferBMPtoRendimento()
    {

        if( ( \Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') == '2022-12-30' ) {
            if( (\Carbon\Carbon::now())->toTimeString() > '10:59:54' ){
               return false;
            }
        }

        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];

        $apiMoneyPlusTransfer = new ApiMoneyPlus();
        $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);
        $apiMoneyPlusTransfer->alias_account_agency = "00018";
        $apiMoneyPlusTransfer->alias_account_number = "00790584";

        $checkBalance = $apiMoneyPlusTransfer->checkBalance();
        $bmp_balance = 0;
        if(isset($checkBalance->data->vlrSaldo)){
            $bmp_balance = $checkBalance->data->vlrSaldo;
        }

        if( $bmp_balance >= 200000 ) {
            $apiMoneyPlusTransfer->favored_cpf_cnpj = "11491029000130";
            $apiMoneyPlusTransfer->favored_name = "IP4Y INSTITUICAO DE PAGAMENTO LTDA";

            //RENDIMENTO
            $apiMoneyPlusTransfer->favored_bank_number = 633;
            $apiMoneyPlusTransfer->favored_agency = "00019";
            $apiMoneyPlusTransfer->favored_account = "0549169005";
            $apiMoneyPlusTransfer->favored_account_type = 1;


            //CELCOIN
            //$apiMoneyPlusTransfer->favored_bank_number = 509;
            //$apiMoneyPlusTransfer->favored_agency = "00019";
            //$apiMoneyPlusTransfer->favored_account = "100538032";
            //$apiMoneyPlusTransfer->favored_account_type = 1;

            $apiMoneyPlusTransfer->value = 200000;

            $apiMoneyPlusTransfer->id =  Str::orderedUuid();

            return $transfer = $apiMoneyPlusTransfer->transferOtherBank();
        }
    }

    public function sendPixBMPToCelcoin()
    {
        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 16;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];

        $apiMoneyPlusTransfer = new ApiMoneyPlus();
        $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);

        $apiMoneyPlusTransfer->alias_account_agency = "00018";
        $apiMoneyPlusTransfer->alias_account_number = "00790584";

        //CELCOIN
        /*$apiMoneyPlusTransfer->credit_bank = "13935893";
        $apiMoneyPlusTransfer->credit_branch = "0001";
        $apiMoneyPlusTransfer->credit_account = "100538032";
        $apiMoneyPlusTransfer->credit_accountType = 1;
        $apiMoneyPlusTransfer->credit_cpf_cnpj = "11491029000130";
        $apiMoneyPlusTransfer->credit_name = "IP4Y INSTITUICAO DE PAGAMENTO LTDA";*/

        //IP4Y
        $apiMoneyPlusTransfer->credit_bank = "11491029";
        $apiMoneyPlusTransfer->credit_branch = "0001";
        $apiMoneyPlusTransfer->credit_account = "99016";
        $apiMoneyPlusTransfer->credit_accountType = 1;
        $apiMoneyPlusTransfer->credit_cpf_cnpj = "11491029000130";
        $apiMoneyPlusTransfer->credit_name = "IP4Y INSTITUICAO DE PAGAMENTO LTDA";


        $apiMoneyPlusTransfer->clientCode = Str::orderedUuid();
        $apiMoneyPlusTransfer->amount = 12.34;

        return $sendPix = $apiMoneyPlusTransfer->sendPixToAccount();
    }

    protected function setDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                => ['required', 'integer'],
            'uuid'              => ['required', 'string'],
            'document'          => ['required', 'string']
        ],[
            'id.required'       => 'É obrigatório informar o id',
            'uuid.required'     => 'É obrigatório informar o uuid',
            'document.required' => 'É obrigatório informar o document'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if( !$accountMovement = AccountMovement::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('master_id', '=', $checkAccount->master_id)
        ->whereNull('deleted_at')
        ->first()) {
            return response()->json(["error" => "Lançamento não localizado, por favor verifique e tente novamente."]);
        }


        $accountMovement->document = $request->document;

        if ($accountMovement->save()) {
            return response()->json(["success" => "Documento atualizado com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar o documento."]);
    }

    protected function setAccountingClassification(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                    => ['required', 'integer'],
            'uuid'                                  => ['required', 'string'],
            'accounting_classification_id'          => ['required', 'integer']
        ],[
            'id.required'                           => 'É obrigatório informar o id',
            'uuid.required'                         => 'É obrigatório informar o uuid',
            'accounting_classification_id.required' => 'É obrigatório informar o accounting_classification_id'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if( !$accountingClassification = AccountingClassification::where('id', '=', $request->accounting_classification_id)
        ->where('account_id', '=', $checkAccount->account_id)
        ->whereNull('deleted_at')
        ->first()) {
            return response()->json(["error" => "Classificação contábil não localizada, por favor verifique e tente novamente."]);
        }


        if( !$accountMovement = AccountMovement::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('master_id', '=', $checkAccount->master_id)
        ->whereNull('deleted_at')
        ->first()) {
            return response()->json(["error" => "Lançamento não localizado, por favor verifique e tente novamente."]);
        }


        $accountingClassification->accounting_classification_id = $request->accounting_classification_id;

        if ($accountingClassification->save()) {
            return response()->json(["success" => "Classificação contábil atualizada com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar a classificação contábil."]);
    }


    protected function getAccountingVerification(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [77];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date']
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $accountMovementClass = new AccountMovementClass();
        $accountMovementClass->payload = $request;
        return $accountMovementClass->getAccountingVerification();
    }

    protected function exportAccountingVerificationExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [77];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date']
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $items = [];

        array_push($items, (object) [
            'competence' => "Competência",
            'operation' => "Operação",
            'date_on_bank' => "Data",
            'classification' => "Classificação",
            'client_provider' => "Cliente / Fornecedor",
            'value' => "Valor",
            'payment_form' => "Forma de Pagamento",
            'observation' => "Observação"
        ]);

        $accountMovementClass = new AccountMovementClass();
        $accountMovementClass->payload = $request;
        foreach($accountMovementClass->getAccountingVerification() as $data){
            array_push($items, (object) [
                'competence' => $data->competence,
                'operation' => $data->operation,
                'date_on_bank' => \Carbon\Carbon::parse($data->date_on_bank)->format('d/m/Y H:i'),
                'classification' => $data->classification,
                'client_provider' => $data->client_provider,
                'value' => $data->value,
                'payment_form' => $data->payment_form,
                'observation' => $data->observation
            ]);
        }

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha contábil gerada com sucesso",
            "file_name" => "Planilha_Contabil_".$request->start_date."__".$request->end_date.".xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "base64" => base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));

    }





    protected function getAccountPeriodMovimentation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [77];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //



        $currentDate = strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') );

        if(
            ((date("w", $currentDate )) == 1) or
            ((date("w", $currentDate )) == 2) or
            ((date("w", $currentDate )) == 3) or
            ((date("w", $currentDate )) == 4) or
            ((date("w", $currentDate )) == 5)
        ){
            if(
                ( ! Holiday::isHoliday( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ) ) and
                ((\Carbon\Carbon::now())->toTimeString() > '08:00:00') and
                ((\Carbon\Carbon::now())->toTimeString() < '17:00:00')
            ){
                return response()->json(array("error" => "Função disponível antes das 08h e após as 17h em dias úteis."));
            }
        }

        $startDate = \Carbon\Carbon::createFromDate($request->start_date);
        $endDate = \Carbon\Carbon::createFromDate($request->end_date);

        if($startDate->diffInDays($endDate) > 100){
            return response()->json(array("error" => "Devido ao cálculo do saldo médio, é permitido selecionar no máximo um range de 100 dias"));
        }



        $accountMovementClass = new AccountMovementClass();
        $accountMovementClass->payload = $request;
        return $accountMovementClass->getAccountPeriodMovimentation();
    }


    public function createMinimumAliasAccountValuePreviousMonthToFutureMovement()
    {
        $accountMovement = new AccountMovement();

        $competence = \Carbon\Carbon::parse( \Carbon\Carbon::now()->subMonth() )->format('m/Y');

        foreach( $accountMovement->getMinimumAliasAccountValuePreviousMonthToFutureMovement() as $dataToMovementFuture ) {
            $accountMovementFuture = new AccountMovementFutureClass();
            $accountMovementFuture->account_id = $dataToMovementFuture->account_id;
            $accountMovementFuture->master_id = $dataToMovementFuture->master_id;
            $accountMovementFuture->mvmnt_type_id = 39;
            $accountMovementFuture->description = 'MENSALIDADE MÍNIMA PARA MANUTENÇÃO DE DADOS BANCÁRIOS | '.$competence;
            $accountMovementFuture->value = $dataToMovementFuture->pending_value;
            $accountMovementFuture->create();
        }

    }

    protected function exportBMPMovementExcelAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $bmpMovement             = new StatementMoneyPlusBank();
        $bmpMovement->account_id = $checkAccount->account_id;
        $start_date              = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }


        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 366){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em Excel para períodos superiores a um ano, por favor verifique o período informado e tente novamente"));
        }

        $bmpMovement->date_start = $start_date." 00:00:00.000";
        $bmpMovement->date_end   = $end_date." 23:59:59.998";
        $bmpMovement->onlyActive = 1;

        //bmp movement
        $items = [];

        array_push($items, (object) [
            'date'        => "Extrato Conta BMP",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "Data Inicial",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($start_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "Data Final",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($end_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'                        => "Data",
            'value'                       => "Valor",
            'account_balance'             => "Saldo Conta",
            'register_description'        => "Titular",
            'transaction_description'     => "Descrição",
            'code'                        => "Código",
            'transaction_identificator'   => "Transação",
            'name'                        => "Nome",
            'cpf_cnpj'                    => "CPF/CNPJ",
        ]);

        foreach($bmpMovement->get() as $movementData){
            array_push($items, (object) [
                'date'                      => \Carbon\Carbon::parse($movementData->date)->format('d/m/Y H:i'),
                'value'                     => $movementData->value,
                'account_balance'           => $movementData->account_balance,
                'register_description'      => $movementData->register_description,
                'transaction_description'   => $movementData->transaction_description,
                'code'                      => $movementData->code,
                'transaction_identificator' => $movementData->transaction_identificator,
                'name'                      => $movementData->name,
                'cpf_cnpj'                  => Facilites::mask_cpf_cnpj($movementData->cpf_cnpj),
            ]);
        }
        //data, valor, saldo conta, titular, descrição, código, transação, nome, cpf/cnpj

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha extrato gerada com sucesso",
            "file_name" => "Extrato BMP.xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "base64"=>base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));

    }

    protected function exportBancoBrasilMovementExcelAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $bbMovement             = new StatementBrasilBank();
        $bbMovement->account_id = $checkAccount->account_id;
        $start_date              = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }


        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 366){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em Excel para períodos superiores a um ano, por favor verifique o período informado e tente novamente"));
        }

        $bbMovement->date_start = $start_date." 00:00:00.000";
        $bbMovement->date_end   = $end_date." 23:59:59.998";
        $bbMovement->onlyActive = 1;

        //bb movement
        $items = [];

        array_push($items, (object) [
            'date'        => "Extrato Conta Banco do Brasil",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "Data Inicial",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($start_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "Data Final",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($end_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date' => "Data",
            'value' => "Valor",
            'description' => "Descrição",
            'balance' => "Saldo",
            'code' => "Código",
            'register_description' => "Checagem",
            'created_at' => "Importado Em",
        ]);

        foreach($bbMovement->get() as $movementData){
            array_push($items, (object) [
                'date' => \Carbon\Carbon::parse($movementData->date)->format('d/m/Y H:i'),
                'value' => $movementData->value,
                'description' => $movementData->description,
                'balance' => $movementData->balance,
                'transaction_description' => $movementData->transaction_description,
                'code' => $movementData->code,
                'transaction_identificator' => \Carbon\Carbon::parse($movementData->transaction_identificator)->format('d/m/Y H:i'),
            ]);
        }
        //data, valor, descrição, saldo, código, checagem, importado em

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha extrato gerada com sucesso",
            "file_name" => "Extrato Banco do Brasil.xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "base64"=>base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));

    }

    protected function exportRendimentoMovementExcelAgency(Request $request) {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [86];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $rendimentoMovement             = new StatementRendimentoBank();
        $rendimentoMovement->account_id = $checkAccount->account_id;
        $start_date              = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }


        if( (\Carbon\Carbon::parse($request->start_date))->diffInDays(\Carbon\Carbon::parse($request->end_date)) > 366){
            return response()->json(array("error" => "Poxa, não é possível gerar o extrato em Excel para períodos superiores a um ano, por favor verifique o período informado e tente novamente"));
        }

        $rendimentoMovement->created_at_start = $start_date." 00:00:00.000";
        $rendimentoMovement->created_at_end   = $end_date." 23:59:59.998";
        $rendimentoMovement->onlyActive = 1;

        //rendimento movement
        $items = [];

        array_push($items, (object) [
            'date'        => "Extrato Conta Rendimento",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'date'        => "Data Inicial",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($start_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "Data Final",
            'value'       => "",
            'balance'     => "",
            'description' => \Carbon\Carbon::parse($end_date)->format('d/m/Y')
        ]);

        array_push($items, (object) [
            'date'        => "",
            'value'       => "",
            'balance'     => "",
            'description' => ""
        ]);

        array_push($items, (object) [
            'entry_date' => "Data",
            'value' => "Valor",
            'current_balance' => "Saldo",
            'description' => "Descrição",
            'complement' => "Complemento",
            'document_number' => "Documento",
            'operation_type' => "Tipo Operação",
            'code' => "Código",
        ]);

        foreach($rendimentoMovement->getStatement() as $movementData){
            array_push($items, (object) [
                'entry_date' => \Carbon\Carbon::parse($movementData->entry_date)->format('d/m/Y H:i'),
                'value' => $movementData->value,
                'current_balance' => $movementData->current_balance,
                'description' => $movementData->description,
                'complement' => $movementData->complement,
                'document_number' => $movementData->document_number,
                'operation_type' => $movementData->operation_type,
                'code' => $movementData->code,
            ]);
        }
        //data, valor, saldo conta, titular, descrição, código, transação, nome, cpf/cnpj

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha extrato gerada com sucesso",
            "file_name" => "Extrato Rendimento.xlsx",
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "base64"=>base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));

    }
}
