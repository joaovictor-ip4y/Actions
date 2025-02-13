<?php

namespace App\Http\Controllers;

use App\Models\SimpleCharge;
use App\Models\ApiConfig;
use App\Models\Account;
use App\Models\Charge;
use App\Models\AccountMovement;
use App\Models\Remittance;
use App\Models\ChargeConfig;
use App\Models\ChargeInstruction;
use App\Models\SimpleChargeHistory;
use App\Models\SimpleChrgMvmnt;
use App\Models\User;
use App\Models\Holiday;
use App\Models\Master;
use App\Models\SystemFunctionMaster;
use App\Models\ApiToken;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\AmazonS3;
use App\Libraries\ApiSendgrid;
use App\Libraries\sendMail;
use App\Libraries\Facilites;
use App\Libraries\BilletGenerator;
use App\Libraries\SimpleZip;
use App\Libraries\ApiMoneyPlus;
use App\Libraries\QrCodeGenerator\QrCodeGenerator;
use App\Services\BilletInsertion\SimpleChargeBilletInsertionService;
use App\Services\BilletLiquidation\SimpleChargeBilletLiquidationService;
use App\Services\BilletInstruction\SimpleChargeBilletInstructionService;
use App\Services\Charge\SimpleChargeDeadLineDown;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\BancoRendimento\BancoRendimentoClass;
use App\Classes\Banking\BilletClass;
use App\Models\DocumentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use ZipArchive;
use File;
use PDF;
use QrCode;
use Illuminate\Support\Facades\Validator;
use App\Services\Failures\sendFailureAlert;
use Illuminate\Support\Str;

class SimpleChargeController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge                       = new SimpleCharge();
        $simpleCharge->master_id            = $checkAccount->master_id;
        $simpleCharge->account_id           = $checkAccount->account_id;
        $simpleCharge->onlyActive           = $request->onlyActive;
        $simpleCharge->due_date_start       = $request->due_date_start;
        $simpleCharge->due_date_end         = $request->due_date_end;
        $simpleCharge->payment_date_start   = $request->payment_date_start;
        $simpleCharge->payment_date_end     = $request->payment_date_end;
        $simpleCharge->inclusion_date_start = $request->inclusion_date_start;
        $simpleCharge->inclusion_date_end   = $request->inclusion_date_end;
        $simpleCharge->value_start          = $request->value_start;
        $simpleCharge->value_end            = $request->value_end;
        $simpleCharge->payment_value_start  = $request->payment_value_start;
        $simpleCharge->payment_value_end    = $request->payment_value_end;
        $simpleCharge->document             = $request->document;
        $simpleCharge->our_number           = $request->our_number;
        $simpleCharge->payer_id_in          = $request->payer_id;
        $simpleCharge->status_id_in         = $request->status_id;
        $simpleCharge->is_recurrency        = $request->is_recurrency;
        $simpleCharge->recurrency_code      = $request->recurrency_code;
        $simpleCharge->manager_id           = $request->manager_id;
        $simpleCharge->api_id               = $request->api_id;

        return response()->json($simpleCharge->getSimpleCharge());
    }

    protected function getAnalitic(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge             = new SimpleCharge();
        $simpleCharge->master_id  = $checkAccount->master_id;
        $simpleCharge->account_id = $checkAccount->account_id;


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

        $period_created                 = $simpleCharge->simpleChargeAnalitic();
        $simpleCharge->created_at_start = null;
        $simpleCharge->created_at_end   = null;
        //----

        //period liquidated
        if($request->payment_date_start != ''){
            $simpleCharge->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $simpleCharge->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $simpleCharge->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $simpleCharge->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $period_liquidated                = $simpleCharge->simpleChargeAnalitic();
        $simpleCharge->payment_date_start = null;
        $simpleCharge->payment_date_end   = null;
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

        $period_down                           = $simpleCharge->simpleChargeAnalitic();
        $simpleCharge->payment_down_date_start = null;
        $simpleCharge->payment_down_date_end   = null;
        //-----

        return response()->json(array(
            'period_created'    => $period_created,
            'period_liquidated' => $period_liquidated,
            'period_down'       => $period_down
        ));
    }

    protected function getDetailed(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge                                   = new SimpleCharge();
        $simpleCharge->master_id                        = $checkAccount->master_id;
        $simpleCharge->account_id                       = $checkAccount->account_id;
        $simpleCharge->status_id                        = $request->status_id;
        $simpleCharge->onlyActive                       = $request->onlyActive;
        $simpleCharge->type_id                          = $request->type_id;
        $simpleCharge->manager_id                       = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $simpleCharge->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $simpleCharge->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $simpleCharge->created_at_start             = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $simpleCharge->created_at_end               = $request->created_at_end." 23:59:59.998";
        }
        if($request->payment_date_start != ''){
            $simpleCharge->payment_date_start           = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $simpleCharge->payment_date_end             = $request->payment_date_end." 23:59:59.998";
        }
        if($request->down_date_start != ''){
            $simpleCharge->down_date_start              = $request->down_date_start." 00:00:00.000";
        }
        if($request->down_date_end != ''){
            $simpleCharge->down_date_end                = $request->down_date_end." 23:59:59.998";
        }
        return response()->json( $simpleCharge->simpleChargeDetailed() );
    }

    protected function exportSimpleCharge(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [100, 229, 295];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge                       = new SimpleCharge();
        $simpleCharge->master_id            = $checkAccount->master_id;
        $simpleCharge->account_id           = $checkAccount->account_id;
        $simpleCharge->onlyActive           = $request->onlyActive;
        $simpleCharge->due_date_start       = $request->due_date_start;
        $simpleCharge->due_date_end         = $request->due_date_end;
        $simpleCharge->payment_date_start   = $request->payment_date_start;
        $simpleCharge->payment_date_end     = $request->payment_date_end;
        $simpleCharge->inclusion_date_start = $request->inclusion_date_start;
        $simpleCharge->inclusion_date_end   = $request->inclusion_date_end;
        $simpleCharge->value_start          = $request->value_start;
        $simpleCharge->value_end            = $request->value_end;
        $simpleCharge->payment_value_start  = $request->payment_value_start;
        $simpleCharge->payment_value_end    = $request->payment_value_end;
        $simpleCharge->document             = $request->document;
        $simpleCharge->our_number           = $request->our_number;
        $simpleCharge->payer_id_in          = $request->payer_id;
        $simpleCharge->status_id_in         = $request->status_id;
        $simpleCharge->manager_id           = $request->manager_id;

        $items = [];
        foreach($simpleCharge->getSimpleCharge() as $movementData){
            array_push($items, (object) [
                'document'                          =>   $movementData->document,
                'due_date'                          =>   \Carbon\Carbon::parse($movementData->due_date)->format('d/m/Y'),
                'value'                             =>   $movementData->value,
                'beneficiary_name'                  =>   $movementData->beneficiary_name,
                'payer_cpf_cnpj'                    =>   Facilites::mask_cpf_cnpj($movementData->payer_cpf_cnpj),
                'payer_name'                        =>   $movementData->payer_name,
                'status_description'                =>   $movementData->status_description,
                'payment_date'                      =>   $movementData->payment_date ? \Carbon\Carbon::parse($movementData->payment_date)->format('d/m/Y') : null,
                'payment_value'                     =>   $movementData->payment_value,
                'antecipation_status_description'   =>   $movementData->antecipation_status_description,
                'created_at'                        =>   \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );
        $file_name = "Titulos_Carteira_Simples.pdf";
        $pdf       = PDF::loadView('reports/simple_charge', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getPayerList(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge             = new SimpleCharge();
        $simpleCharge->master_id  = $checkAccount->master_id;
        $simpleCharge->account_id = $checkAccount->account_id;

        return response()->json($simpleCharge->getPayerList());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [221, 287, 380];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "error_list" => array(["message" => $checkAccount->message]), "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        if( (SystemFunctionMaster::where('system_function_id','=',6)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }


        $simpleChargeInsert = new SimpleChargeBilletInsertionService();
        $simpleChargeInsert->titleData = (object) [
            'master_id'   => $checkAccount->master_id,
            'account_id'  => $checkAccount->account_id,
            'title_id_in' => $request->title,
            'user_id'     => $checkAccount->user_id,
            'ip'          => $request->header('ip')
        ];

        $titleInsert = $simpleChargeInsert->createSimpleChargeFromChargeInsert();

        if($titleInsert->success){
            return response()->json(array("success" => $titleInsert->message, "zipFile" => $titleInsert->zipFile, "zipFileName" => $titleInsert->zipFileName, "remittanceId" => $titleInsert->remittance_id, "successList" => $titleInsert->success_list));
        } else {
            return response()->json(array("error" => $titleInsert->message, "error_list" => $titleInsert->error_list, "zipFile" => $titleInsert->zipFile, "zipFileName" => $titleInsert->zipFileName, "remittanceId" => $titleInsert->remittance_id, "successList" => $titleInsert->success_list ));
        }
    }

    protected function sendInstruction(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [95, 226, 292];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $accountId = $checkAccount->account_id;
        if($checkAccount->account_id == null){
            $accountId = (SimpleCharge::where('id','=',$request->id)->first())->account_id;
        }

        $titleDataObj = (object) [
            'id'             => $request->id,
            'master_id'      => $checkAccount->master_id,
            'account_id'     => $accountId,
            'instruction'    => $request->instruction,
            'new_due_date'   => $request->new_due_date,
            'discount_value' => $request->discount_value,
            'user_id'        => $checkAccount->user_id,
            'ip'             => $request->ip(),
            'description'    => $request->description
        ];
        $titleSendInstruction = new SimpleChargeBilletInstructionService();
        $titleSendInstruction->titleData = $titleDataObj;
        $instruction = $titleSendInstruction->sendInstruction();

        if($instruction->success){
            return response()->json(array("success" => $instruction->message));
        } else {
            return response()->json(array("error" => $instruction->message));
        }


    }

    public function billetLiquidation()
    {
        $i            = 0;
        $searchTitles = null;
        $errors = [];

        //Get business day to liquidation
        $day = app('App\Http\Controllers\HolidayController')->returnBusinessDay((\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d'));
        if(isset($day->businessDayPrevious)){
            $paymentDate = $day->businessDayPrevious;
        } else {
            $paymentDate = (\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d');
        }

        $titles = SimpleCharge::whereNotNull('our_number')->whereNull('payment_date')->whereNull('down_date')->whereNotIn('status_id',[41])->get();
        foreach($titles as $title){
            if( $title->api_id == 1 or $title->api_id == 10 ) {
                $apiConfig                                          = new ApiConfig();
                $apiConfig->api_id                                  = $title->api_id;
                $apiConfig->onlyActive                              = 1;
                $apiConfig->master_id                               = $title->master_id;
                $apiData                                            = $apiConfig->getApiConfig()[0];
                $apiRendimento                                      = new ApiBancoRendimento();
                $apiRendimento->id_cliente                          = Crypt::decryptString($apiData->api_client_id);
                $apiRendimento->chave_acesso                        = Crypt::decryptString($apiData->api_key);
                $apiRendimento->autenticacao                        = Crypt::decryptString($apiData->api_authentication);
                $apiRendimento->endereco_api                        = Crypt::decryptString($apiData->api_address);
                $apiRendimento->agencia                             = Crypt::decryptString($apiData->api_agency);
                $apiRendimento->conta_corrente                      = Crypt::decryptString($apiData->api_account);
                $apiRendimento->tit_nosso_numero                    = $title->our_number;

                $token = null;

                $apiToken = ApiToken::where('api_id', '=', $title->api_id)->whereNull('expired');

                if( $apiToken->count() == 0 ){
                    $bancoRendimentoClass = new BancoRendimentoClass();
                    $bancoRendimentoClass->setApiToken();
                }

                $apiToken = ApiToken::where('api_id', '=', $title->api_id)->whereNull('expired');
                if( $apiToken->count() > 0 ) {
                    $token = Crypt::decryptString($apiToken->first()->token);
                }

                $apiRendimento->token = $token;

                $titleData = $apiRendimento->tituloConsultar();
                if(isset($titleData->body->value[0]->codigoSituacao)){
                    if($titleData->body->value[0]->codigoSituacao == 'Pago'){

                        $titleDataObj = (object) [
                            'our_number'            => $titleData->body->value[0]->nossoNumero,
                            'wallet_number'         => $titleData->body->value[0]->numeroCarteira,
                            'bank_code'             => $title->bank_code,
                            'payment_date'          => $paymentDate,
                            'payment_value'         => $titleData->body->value[0]->valorTitulo,
                            'api_id'                => $title->api_id,
                            'account_id'            => $title->account_id,
                            'concliliation_success' => false
                        ];

                        $billetConciliation = new SimpleChargeBilletLiquidationService();
                        $billetConciliation->our_number = $titleData->body->value[0]->nossoNumero;
                        $billetConciliation->master_id = $title->master_id;
                        $billetConciliation->api_id = $title->api_id;
                        $concliliation = $billetConciliation->billetConciliation();

                        if($concliliation->success){
                            $titleDataObj = null;
                            $titleDataObj = (object) [
                                'our_number'            => $titleData->body->value[0]->nossoNumero,
                                'wallet_number'         => $titleData->body->value[0]->numeroCarteira,
                                'bank_code'             => $title->bank_code,
                                'payment_date'          => $concliliation->data->datapagtobaixa,
                                'payment_value'         => $concliliation->data->valorpgto,
                                'api_id'                => $title->api_id,
                                'account_id'            => $title->account_id,
                                'concliliation_success' => true
                            ];
                        }

                        $simpleChargeLiquidation = new SimpleChargeBilletLiquidationService();
                        $simpleChargeLiquidation->paymentData = $titleDataObj;
                        $liquidationData = $simpleChargeLiquidation->billetLiquidation();
                        if(!$liquidationData->success){
                            array_push($errors, $liquidationData->message);
                        }
                    }
                    $searchTitles[$i] = $titleData;
                    $i++;
                }
            }
        }
        return response()->json($searchTitles);
    }


    public function bancoBmpBilletLiquidation()
    {
        $i = 0;
        $searchTitles = null;
        $errors = [];

        //Get business day to liquidation
        $day = app('App\Http\Controllers\HolidayController')->returnBusinessDay((\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d'));
        if(isset($day->businessDayPrevious)){
            $paymentDate = $day->businessDayPrevious;
        } else {
            $paymentDate = (\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d');
        }

        $titles = SimpleCharge::whereNotNull('our_number')->whereNull('payment_date')->whereNull('down_date')->whereNotIn('status_id',[41])->where('api_id', '=', 15)->get();

        foreach($titles as $title){
            // Banco BMP

            $token = null;
            $apiToken = ApiToken::where('api_id', '=', 15)->whereNull('expired');
            if( $apiToken->count() > 0 ) {
                $token = Crypt::decryptString($apiToken->first()->token);
            }

            if( $title->api_id == 15 ) {
                $apiConfig = new ApiConfig();
                $apiConfig->master_id = $title->master_id;
                $apiConfig->api_id = $title->api_id;
                $apiConfig->onlyActive = 1;
                $apiData = $apiConfig->getApiConfig()[0];

                $apiMoneyPlus = new ApiMoneyPlus();
                $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
                $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);
                $apiMoneyPlus->agency = Crypt::decryptString($apiData->api_agency);
                $apiMoneyPlus->token = $token;
                $apiMoneyPlus->tit_numero_controle = $title->control_number;
                $apiMoneyPlus->tit_codigo_barras = $title->bar_code;

                $titleData = $apiMoneyPlus->checkTitle();
                if(isset($titleData->data->sucesso)){
                    if($titleData->data->sucesso == true) {
                        if(isset($titleData->data->situacaoBoleto)){
                            if($titleData->data->situacaoBoleto == 4){
                                if(isset($titleData->data->dadosBaixa->vlrBaixaOperacTit)) {

                                    if( $aliasAccount = Account::where('id', '=', $title->account_id)->where('alias_account_bank_id', '=', 161)->first()){

                                        if( $aliasAccount->alias_account_keep_balance != 1 ) {

                                            $apiMoneyPlusTransfer = new ApiMoneyPlus();
                                            $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
                                            $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);
                                            $apiMoneyPlusTransfer->alias_account_agency = $aliasAccount->alias_account_agency;
                                            $apiMoneyPlusTransfer->alias_account_number = $aliasAccount->alias_account_number;

                                            $apiMoneyPlusTransfer->favored_agency = "00018";
                                            $apiMoneyPlusTransfer->favored_account = "00790584";
                                            $apiMoneyPlusTransfer->favored_account_type = 3;

                                            $apiMoneyPlusTransfer->value = $titleData->data->dadosBaixa->vlrBaixaOperacTit;

                                            $apiMoneyPlusTransfer->id = Str::orderedUuid();//$sttmntMoneyPlus->uuid;

                                            $transfer = $apiMoneyPlusTransfer->transferBetweenAccounts();

                                            if( $transfer->success ){
                                                $titleDataObj = (object) [
                                                    'our_number'            => str_pad($titleData->data->identdNossoNum, 12, '0', STR_PAD_LEFT),
                                                    'wallet_number'         => $titleData->data->codCartTit,
                                                    'bank_code'             => '0274',
                                                    'payment_date'          => $paymentDate,
                                                    'payment_value'         => $titleData->data->dadosBaixa->vlrBaixaOperacTit,
                                                    'api_id'                => $title->api_id,
                                                    'account_id'            => $title->account_id
                                                ];

                                                $simpleChargeLiquidation = new SimpleChargeBilletLiquidationService();
                                                $simpleChargeLiquidation->paymentData = $titleDataObj;
                                                $liquidationData = $simpleChargeLiquidation->billetLiquidation();
                                                if(!$liquidationData->success){
                                                    array_push($errors, $liquidationData->message);
                                                }
                                            } else {
                                                $sendFailureAlert               = new sendFailureAlert();
                                                $sendFailureAlert->title        = 'Falha Transferencia de liquidação de boleto para conta principal BMP';
                                                $sendFailureAlert->errorMessage = 'Atenção, ocorreu uma falha ao transferir da conta BMP '.$aliasAccount->alias_account_number.', para conta principal. Realize o processo de transferência e credite manualmente a conta destino.' ;
                                                $sendFailureAlert->sendFailures();
                                            }
                                        } else {

                                            $titleDataObj = (object) [
                                                'our_number'            => str_pad($titleData->data->identdNossoNum, 12, '0', STR_PAD_LEFT),
                                                'wallet_number'         => $titleData->data->codCartTit,
                                                'bank_code'             => '0274',
                                                'payment_date'          => $paymentDate,
                                                'payment_value'         => $titleData->data->dadosBaixa->vlrBaixaOperacTit,
                                                'api_id'                => $title->api_id,
                                                'account_id'            => $title->account_id
                                            ];

                                            $simpleChargeLiquidation = new SimpleChargeBilletLiquidationService();
                                            $simpleChargeLiquidation->paymentData = $titleDataObj;
                                            $liquidationData = $simpleChargeLiquidation->billetLiquidation();
                                            if(!$liquidationData->success){
                                                array_push($errors, $liquidationData->message);
                                            }

                                        }
                                    }
                                }
                            }

                            $searchTitles[$i] = $titleData;
                            $i++;
                        }
                    }
                }
            }
        }
    }


    public function bancoRendimentoBilletLiquidation()
    {
        // Get Rendimento Access Token to IP4Y Account
        $token = null;

        $apiToken = ApiToken::where('api_id', '=', 10)->whereNull('expired');

        if( $apiToken->count() == 0 ){
            $bancoRendimentoClass = new BancoRendimentoClass();
            $bancoRendimentoClass->setApiToken();
        }

        $apiToken = ApiToken::where('api_id', '=', 10)->whereNull('expired');
        if( $apiToken->count() > 0 ) {
            $token = Crypt::decryptString($apiToken->first()->token);
        }



    }



    protected function getStatistic(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'start_date'=> ['nullable', 'date'],
            'end_date'=> ['nullable', 'date']
        ],[
            'start_date.date' => 'Informe uma data válida.',
            'end_date.date' => 'Informe uma data válida.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $accountStatistic             = new SimpleCharge();
        $accountStatistic->account_id = $checkAccount->account_id;
        $accountStatistic->master_id  = $checkAccount->master_id;
        $start_date                   = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        $end_date                     = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        if($request->start_date != ''){
            $start_date = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
        }
        if($request->end_date != ''){
            $end_date = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');
        }
        $accountStatistic->date_start = $start_date." 00:00:00.000";
        $accountStatistic->date_end   = $end_date." 23:59:59.998";
        $accountStatistic->onlyActive = 1;

        $simpleChargeMovement    = new SimpleChrgMvmnt();
        $simpleChargeMovement->account_id = $checkAccount->account_id;
        $simpleChargeMovement->master_id  = $checkAccount->master_id;

        $checkQtd                = $accountStatistic->getQtd();
        $checkAvgTerm            = $accountStatistic->averageTerm();
        $checkOpenValue          = $accountStatistic->getOpenValue();
        $checkToLiquidateValue   = $accountStatistic->getToLiquidateValue();
        $checkPastValue          = $accountStatistic->getPastValue();
        $checkLiquidationValue   = $accountStatistic->getLiquidation();

        $qtd                      = 0;
        $avgValue                 = 0;
        $avgTerm                  = 0;
        $openValue                = 0;
        $toLiquidateValue         = 0;
        $pastValue                = 0;
        $liquidationValue         = 0;
        $percentageLiquidation    = 0;
        $monthValuePosition       = $simpleChargeMovement->monthValuePosition();
        $monthLiquidationPosition = $accountStatistic->monthLiquidationPosition();

        if(isset($checkOpenValue->value)){
            $openValue = $checkOpenValue->value;
        }

        if(isset($checkToLiquidateValue->value)){
            $toLiquidateValue = $checkToLiquidateValue->value;
        }

        if(isset($checkPastValue->value)){
            $pastValue = $checkPastValue->value;
        }

        if(isset($checkQtd->qtd)){
            $qtd = $checkQtd->qtd;
            if($qtd > 0){
                $avgValue = $openValue / $qtd;
            }
        }
        if(isset($checkAvgTerm[0]->value)){
            $avgTerm = $checkAvgTerm[0]->value;
        }

        if(isset($checkLiquidationValue->value)){
            $liquidationValue = $checkLiquidationValue->value;
            if($pastValue > 0){
                $percentageLiquidation = ($liquidationValue/$pastValue) * 100;
            } else {
                $percentageLiquidation = 100;
            }
        }


        return response()->json( [
            "success"               => "",
            "qtd"                   => $qtd,
            "avgValue"              => $avgValue,
            "avgTerm"               => $avgTerm,
            "openValue"             => $openValue,
            "toLiquidateValue"      => $toLiquidateValue,
            "pastValue"             => $pastValue,
            "liquidity"             => $percentageLiquidation,
            "monthValue"            => $monthValuePosition,
            "liquidationMonthValue" => $monthLiquidationPosition
        ] );
    }

    protected function resume(Request $request)
    {
        $simple_charge                  = new SimpleCharge();
        $simple_charge->created_at      = $request->date;
        return response()->json($simple_charge->resumeSimpleCharge()[0]);
    }

    protected function dowByApi(Request $request)
    {
        $apiConfig                           = new ApiConfig();
        $apiConfig->master_id                = $request->header('masterId');
        $apiConfig->api_id                   = 1;
        $apiConfig->onlyActive               = 1;
        $apiData                             = $apiConfig->getApiConfig()[0];
        $apiRendimento                       = new ApiBancoRendimento();
        $apiRendimento->id_cliente           = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso         = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao         = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api         = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia              = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente       = Crypt::decryptString($apiData->api_account);
        $apiRendimento->tit_nosso_numero     = $request->our_number;
        $apiRendimento->tit_data_referencia  = '';
        $apiRendimento->tit_texto            = 'Baixa API';
        $apiRendimento->tit_tipo_referencia  = '0';
        $apiRendimento->tit_valor_referencia = '0';
        $apiRendimento->tit_codigo_instrucao = 51;
        $apiRendimento->tit_codigo_produto   = 2;
        $instruction = $apiRendimento->tituloIncluirInstrucao();
        return response()->json($instruction);
    }

    protected function getBillet(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [96, 224, 290];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge = new SimpleCharge();
        $simpleCharge->master_id = $checkAccount->master_id;
        $simpleCharge->account_id = $checkAccount->account_id;
        $simpleCharge->status_id = 4; // Apenas se boleto em aberto
        $simpleCharge->id = $request->id;

        if( ! $simpleChargeData = $simpleCharge->getBilletData() ) {
            return response()->json(array("error" => "Boleto não localizado, ou pago/baixado"));
        }

        $chargeConfig = ChargeConfig::where('account_id', '=', $checkAccount->account_id)->first();

        $billetClass = new BilletClass();
        $billetClass->bar_code = $simpleChargeData->bar_code;
        $billetClass->digitable_line = $simpleChargeData->digitable_line;
        $billetClass->bank_code = $simpleChargeData->bank_code;
        $billetClass->pix_emv = $simpleChargeData->pix_emv;
        $billetClass->fine = $simpleChargeData->fine;
        $billetClass->interest = $simpleChargeData->interest;
        $billetClass->due_date = $simpleChargeData->due_date;
        $billetClass->value = $simpleChargeData->value;
        $billetClass->document = $simpleChargeData->document;
        $billetClass->our_number = $simpleChargeData->our_number;
        $billetClass->payer_name = $simpleChargeData->payer_name;
        $billetClass->payer_cpf_cnpj = $simpleChargeData->payer_cpf_cnpj;
        $billetClass->beneficiary_name = $simpleChargeData->beneficiary_name;
        $billetClass->beneficiary_cpf_cnpj = $simpleChargeData->beneficiary_cpf_cnpj;
        $billetClass->beneficiary_address_zip_code = $simpleChargeData->beneficiary_address_zip_code;
        $billetClass->observation = $simpleChargeData->observation;
        $billetClass->owner_name = $simpleChargeData->owner_name;
        $billetClass->owner_cpf_cnpj = $simpleChargeData->owner_cpf_cnpj;
        $billetClass->issue_date = $simpleChargeData->issue_date;
        $billetClass->wallet_number = $simpleChargeData->wallet_number;
        $billetClass->message1 = $simpleChargeData->message1;
        $billetClass->message2 = $simpleChargeData->message2;
        $billetClass->payer_address_description = $simpleChargeData->payer_address_description;
        $billetClass->payer_address_city = $simpleChargeData->payer_address_city;
        $billetClass->payer_address_state_short_description = $simpleChargeData->payer_address_state_short_description;
        $billetClass->payer_address_zip_code = $simpleChargeData->payer_address_zip_code;

        $linkLogo = $this->imageLink($chargeConfig);
        $billetClass->logoBase64 = $linkLogo;

        $billet = $billetClass->createBillet();

        if(is_string($billet)) {
            return response()->json(["error" => $billet]);
        }

        return response()->json(array("success" => "true", "file_name" => $simpleChargeData->our_number.'.pdf', "mime_type" => "application/pdf", "base64" => $billet->base64));
    }

    private function imageLink($config): string
    {
        $document = DocumentType::where('description', '=', 'Logo - PJ')->first();

        $path = !empty($document) ? $document->s3_path : '';

        $base_url = rtrim(config('services.s3_public.url'), '/');
        $clean_path = rtrim($path, '/');
        $logo_file_name = '000-default.png';

        if (isset($config->logo_s3_filename)) {
            if ($config->logo_s3_filename != null) {
                $logo_file_name = $config->logo_s3_filename;
            }
        }

        return $base_url . '/' . $clean_path . '/' . $logo_file_name;
    }

    protected function sendBilletMail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [98, 227, 293];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        $simpleCharge = SimpleCharge::where('id','=',$request->id)->where('our_number','=',$request->our_number)->where('master_id','=',$checkAccount->master_id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);});
        $chargeConfig = ChargeConfig::where('account_id', '=', $checkAccount->account_id)->first();

        if( $simpleCharge->count() > 0 ){
            $simpleCharge = $simpleCharge->first();
            if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                return response()->json(array("error" => "Não é possível gerar boleto para título liquidado ou baixado"));
            }

            $billet = $this->createBilletWithoutEmail($chargeConfig, $simpleCharge);

            if(is_string($billet)) {
                return response()->json(["error" => $billet]);
            }


            if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                return response()->json(array("error" => "Não é possível enviar e-mail com boleto para título liquidado ou baixado"));
            }

            $facilities = new Facilites();
            $user = User::where('id','=',$request->header('userId'))->first();

            $apiSendGrind = new ApiSendgrid();
            $apiSendGrind->subject = 'Boleto '.$simpleCharge->document.' de '.$billet->billetData->beneficiary_name;
            $apiSendGrind->content = "
                <html>
                    <body>
                        <p>
                            Olá, <br>
                            Segue em anexo o boleto emitido por <b> "
                            .$billet->billetData->beneficiary_name.
                            " - "
                            .$facilities->mask_cpf_cnpj($billet->billetData->beneficiary_cpf_cnpj).
                            "</b>, para <b>"
                            .$billet->billetData->payer_name.
                            " - "
                            .$facilities->mask_cpf_cnpj($billet->billetData->payer_cpf_cnpj).
                            "</b>
                            <br><br>
                            <b>Documento:</b> $simpleCharge->document <br>
                            <b>Valor:</b> " .number_format($simpleCharge->value,2,',','.'). "<br>
                            <b>Vencimento:</b> " .$billet->billetData->due_date. "<br>
                            <b>Nosso Número:</b> $simpleCharge->our_number <br>
                            <b>Linha Digitável:</b> $simpleCharge->digitable_line <br><br><br>
                            Quer emitir boletos, realizar transferências e pagamentos com muita facilidade e segurança? Acesse https://ip4y.com.br e abra sua conta.
                        </p>
                    </body>
                </html>
            ";


            $apiSendGrind->to_email     = $request->email;
            $apiSendGrind->to_name      = $billet->billetData->payer_name;
            $apiSendGrind->to_cc_mail   = $user->email;
            $apiSendGrind->to_cc_name   = $user->name;
            $apiSendGrind->to_cco_mail  = 'ragazzi@dinari.com.br';
            $apiSendGrind->to_cco_name  = 'Ragazzi';

            $apiSendGrind->attachment_files = [
                "content" => $billet->base64,
                "filename" => $billet->billetData->our_number.'.pdf',
                "type" => "application/pdf",
                "disposition" => "attachment"
            ];


            if( $apiSendGrind->sendMail() ) {
                return response()->json(array("success" => "E-mail enviado com sucesso"));
            }

            return response()->json(array("error" => "Ocorreu uma falha ao enviar o e-mail, por favor tente novamente"));

        }

    }

    protected function sendBatchBilletMail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [102, 227, 293];
        $checkAccount = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'array'],
            'id.*'=> ['required', 'integer'],
        ],[
            'id.required' => 'Selecione o(s) boleto(s).',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $accountId = null;
        $userMasterId = '';
        $accountPJId = $request->header('accountPJId');
        $accountPFId = $request->header('accountPFId');
        if ($accountPJId != '') {
            $accountId = $accountPJId;
        } else if ($accountPFId != '') {
            $accountId = $accountPFId;
        } else {
            $userMasterId = $request->header('userMasterId');
            if($userMasterId == ''){
                return response()->json(array("error" => "Falha ao verificar conta"));
            }
        }
        if ($userMasterId == '') {
            if (Account::where('id','=',$accountId)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ) {
                return response()->json(array("error" => "Falha de verificação da conta"));
            }
        }
        $arrayError = [];

        $chargeConfig = ChargeConfig::where('account_id', '=', $checkAccount->account_id)->first();

        if ($simpleCharges = SimpleCharge::whereIn('id',$request->id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);})->get()) {

            foreach ($simpleCharges as $simpleCharge) {

                if ( $simpleCharge->count() > 0 ) {

                    if ($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29) {
                        array_push($arrayError, 'Não é possível gerar boleto para título liquidado ou baixado');

                    } else {
                        $billet = $this->createBilletWithoutEmail($chargeConfig, $simpleCharge);

                        if(is_string($billet)) {
                            return response()->json(["error" => $billet]);
                        }


                        if ($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29) {
                            array_push($arrayError, 'Não é possível enviar e-mail com boleto para título liquidado ou baixado');
                        }

                        $facilities = new Facilites();
                        $user = User::where('id','=',$request->header('userId'))->first();

                        $apiSendGrind = new ApiSendgrid();
                        $apiSendGrind->subject = 'Boleto '.$simpleCharge->document.' de '.$billet->billetData->beneficiary_name;
                        $apiSendGrind->content = "
                            <html>
                                <body>
                                    <p>
                                        Olá, <br>
                                        Segue em anexo o boleto emitido por <b> "
                                        .$billet->billetData->beneficiary_name.
                                        " - "
                                        .$facilities->mask_cpf_cnpj($billet->billetData->beneficiary_cpf_cnpj).
                                        "</b>, para <b>"
                                        .$billet->billetData->payer_name.
                                        " - "
                                        .$facilities->mask_cpf_cnpj($billet->billetData->payer_cpf_cnpj).
                                        "</b>
                                        <br><br>
                                        <b>Documento:</b> $simpleCharge->document <br>
                                        <b>Valor:</b> " .number_format($simpleCharge->value,2,',','.'). "<br>
                                        <b>Vencimento:</b> " .$billet->billetData->due_date. "<br>
                                        <b>Nosso Número:</b> $simpleCharge->our_number <br>
                                        <b>Linha Digitável:</b> $simpleCharge->digitable_line <br><br><br>
                                        Quer emitir boletos, realizar transferências e pagamentos com muita facilidade e segurança? Acesse https://ip4y.com.br e abra sua conta.
                                    </p>
                                </body>
                            </html>
                        ";


                        $apiSendGrind->to_email   = $billet->billetData->payer_email;
                        $apiSendGrind->to_name    = $billet->billetData->payer_name;
                        $apiSendGrind->to_cc_mail = $user->email;
                        $apiSendGrind->to_cc_name = $user->name;

                        $apiSendGrind->attachment_files = [
                            "content" => $billet->base64,
                            "filename" => $billet->billetData->our_number.'.pdf',
                            "type" => "application/pdf",
                            "disposition" => "attachment"
                        ];


                        if( ! $apiSendGrind->sendMail() ) {
                            return response()->json(array("error" => "Ocorreu uma falha ao enviar o e-mail, por favor tente novamente"));
                        }

                    }

                } else {
                    array_push($arrayError,'Boleto não localizado');
                }
            }

            return response()->json(['success'=>'E-mail enviado com sucesso','error_list' => $arrayError]);

        } else {
            return response()->json(['error'=> 'Boleto não localizado']);
        }
    }

    protected function getBatchBillet(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [99, 224, 290];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'array'],
            'id.*'=> ['required', 'integer'],
        ],[
            'id.required' => 'Selecione o(s) boleto(s).',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $items = [];
        $arrayError = [];

        $simpleCharges = SimpleCharge::whereIn('id',$request->id)->whereNotIn('status_id', [28, 29])->where('master_id','=',$checkAccount->master_id)->when( $checkAccount->account_id, function($query, $accountId){ return $query->where('account_id','=',$accountId); } );

        if($simpleCharges->count() == 0){
            return response()->json(array("error" => "Não existem títulos na seleção, ou foi selecionado um título liquidado/baixado."));
        }

        foreach($simpleCharges->get() as $simpleCharge){
            if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                array_push($arrayError,' Não é possível gerar boleto para título liquidado ou baixado',' Boleto ID '.$simpleCharge->id);
            }else{
                $billetGenerator                          = new BilletGenerator();
                $billetGenerator->barcode                 = $simpleCharge->bar_code;
                $billetGenerator->digitableLine           = $simpleCharge->digitable_line;
                $billetGenerator->bankNumber              = substr($simpleCharge->bank_code,1,3);
                $facilities                               = new Facilites();
                $getBilletData                            = $simpleCharge;
                $getBilletData->id                        = $simpleCharge->id;
                $billetData                               = $getBilletData->getBilletData();
                $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
                $billetData->owner_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
                $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
                $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
                $billetData->document_type                = "DM";
                $billetData->pix_qr_code = "";
                if($billetData->pix_emv <> null and $billetData->pix_emv <> ''){

                    $qrCode = new QrCodeGenerator();
                    $qrCode->data = $billetData->pix_emv;
                    $qrCode->return_type = 'base64';
                    $qrCode->quiet_zone = true;
                    $qrCode->quiet_zone_size = 1;
                    $QrCode = $qrCode->createQrCode();

                    $billetData->pix_qr_code = '<img height="100" src="data:image/png;base64, '.preg_replace('#^data:image/\w+;base64,#i', '', $QrCode->base64).' /><br>
                    <div class="tableCellData code"><center>Pague com PIX</center></div>';
                }
                switch($billetData->api_id){
                    case 10:
                        $billetData->path_bank_logo = "billet/logorendimento.jpg";
                    break;
                    case 13:
                        $billetData->path_bank_logo = "billet/logobb.jpg";
                    break;
                    case 15:
                        $billetData->path_bank_logo = "billet/logobmp.jpg";
                    break;
                    default:
                        $billetData->path_bank_logo = "billet/logorendimento.jpg";
                    break;
                }
                $billetData->path_qr_code                 = "billet/qrCodeDinariPay.png";
                $billetData->issue_date                   = ((\Carbon\Carbon::parse( $billetData->issue_date ))->format('d/m/Y'));
                $billetData->due_date                     = ((\Carbon\Carbon::parse( $billetData->due_date ))->format('d/m/Y'));
                $billetData->value                        = number_format(($billetData->value),2,',','.');
                $billetData->observation                  = $billetData->observation;
                $billetData->message_fine_interest        = '';
                if($billetData->fine > 0 or $billetData->interest > 0 ){
                    $billetData->message_fine_interest =  'Após vencimento, cobrar multa de '.number_format(($billetData->fine),2,',','.').'% e mora de '.number_format(( ($billetData->interest/30) ),2,',','.').'% ao dia.';
                }
                array_push($items,$billetData);
            }
        }
        $pdf = PDF::loadView('reports/self_batch_billet', compact('items'))->setPaper('a4', 'portrait')->download( 'Boletos'.'.pdf', ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => "Boletos".'.pdf', "mime_type" => "application/pdf","error"=> $arrayError, "base64" => base64_encode($pdf)));
    }

    protected function getBilletZip(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [97, 225, 291];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'array'],
            'id.*'=> ['required', 'integer'],
        ],[
            'id.required' => 'Selecione o(s) boleto(s).',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $accountId = null;
        $userMasterId = '';
        $accountPJId = $request->header('accountPJId');
        $accountPFId = $request->header('accountPFId');
        if($accountPJId != ''){
            $accountId = $accountPJId;
        } else if($accountPFId != ''){
            $accountId = $accountPFId;
        } else {
            $userMasterId = $request->header('userMasterId');
            if($userMasterId == ''){
                return response()->json(array("error" => "Falha ao verificar conta"));
            }
        }
        if($userMasterId == ''){
            if(Account::where('id','=',$accountId)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
                return response()->json(array("error" => "Falha de verificação da conta"));
            }
        }
        $arrayError = [];
        $chargeConfig = ChargeConfig::where('account_id', '=', $checkAccount->account_id)->first();

        if($simpleCharges = SimpleCharge::whereIn('id',$request->id)->when( $accountId, function($query, $accountId){ return $query->where('account_id','=',$accountId); } )->get()){
            $SimpleZip = new SimpleZip();
            $createZipFolder = $SimpleZip->createZipFolder();
            if(!$createZipFolder->success){
                return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
            } else {
                foreach($simpleCharges as $simpleCharge){
                    if( $simpleCharge->count() > 0 ){
                        if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                            array_push($arrayError, 'Não é possível gerar boleto para título liquidado ou baixado, título: '.$simpleCharge->document);
                        }else{
                            $billetGenerator                          = new BilletGenerator();
                            $billetGenerator->barcode                 = $simpleCharge->bar_code;
                            $billetGenerator->digitableLine           = $simpleCharge->digitable_line;
                            $billetGenerator->bankNumber              = substr($simpleCharge->bank_code,1,3);
                            $facilities                               = new Facilites();
                            $getBilletData                            = $simpleCharge;
                            $getBilletData->id                        = $simpleCharge->id;
                            $billetData                               = $getBilletData->getBilletData();
                            $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                            $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                            $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                            $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                            $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                            $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
                            $billetData->owner_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
                            $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
                            $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
                            $billetData->document_type                = "DM";

                            $linkLogo = $this->imageLink($chargeConfig);
                            $billetData->logo = $linkLogo;

                            $billetData->pix_qr_code = "";
                            if($billetData->pix_emv <> null and $billetData->pix_emv <> ''){

                                $qrCode = new QrCodeGenerator();
                                $qrCode->data = $billetData->pix_emv;
                                $qrCode->return_type = 'base64';
                                $qrCode->quiet_zone = true;
                                $qrCode->quiet_zone_size = 1;
                                $QrCode = $qrCode->createQrCode();

                                $billetData->pix_qr_code = '<img height="100" src="data:image/png;base64, '.preg_replace('#^data:image/\w+;base64,#i', '', $QrCode->base64).' /><br>
                                <div class="tableCellData code"><center>Pague com PIX</center></div>';
                            }
                            switch($billetData->api_id){
                                case 10:
                                    $billetData->path_bank_logo = "billet/logorendimento.jpg";
                                break;
                                case 13:
                                    $billetData->path_bank_logo = "billet/logobb.jpg";
                                break;
                                case 15:
                                    $billetData->path_bank_logo = "billet/logobmp.jpg";
                                break;
                                default:
                                    $billetData->path_bank_logo = "billet/logorendimento.jpg";
                                break;
                            }
                            $billetData->path_qr_code                 = "billet/qrCodeDinariPay.png";
                            $billetData->issue_date                   = ((\Carbon\Carbon::parse( $billetData->issue_date ))->format('d/m/Y'));
                            $billetData->due_date                     = ((\Carbon\Carbon::parse( $billetData->due_date ))->format('d/m/Y'));
                            $billetData->value                        = number_format(($billetData->value),2,',','.');
                            $billetData->observation                  = $billetData->observation;
                            $billetData->message_fine_interest        = '';
                            if($billetData->fine > 0 or $billetData->interest > 0 ){
                                $billetData->message_fine_interest =  'Após vencimento, cobrar multa de '.number_format(($billetData->fine),2,',','.').'% e mora de '.number_format(( ($billetData->interest/30) ),2,',','.').'% ao dia.';
                            }
                            $pdfFilePath  = '../storage/app/zip/'.$createZipFolder->folderName.'/';
                            $file_name    = $simpleCharge->our_number.'.pdf';
                            if(!(PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name))){
                                array_push($arrayError,'Não foi possível gerar o boleto: '.$simpleCharge->document);
                            }
                        }
                    } else {
                        array_push($arrayError,'Boleto não localizado título: '.$simpleCharge->document);
                    }
                }

                $SimpleZip->fileData = (object) [
                    "folderName" => $createZipFolder->folderName,
                    "deleteFiles" => true
                ];
                $createZipFile = $SimpleZip->createZipFile();
                if(!$createZipFile->success){
                    return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
                } else {
                    return response()->json(array(
                        "success"       => "true",
                        "file_name"     => "Boletos.zip",
                        "mime_type"     => "application/zip",
                        "base64"        => $createZipFile->zipFile64
                    ));
                }
            }
        }else{
            return response()->json(['error'=> 'Boleto não localizado']);
        }
    }

    protected function getDetailedPDF(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [94, 223, 289];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge                                   = new SimpleCharge();
        $simpleCharge->master_id                        = $checkAccount->master_id;
        $simpleCharge->account_id                       = $checkAccount->account_id;
        $simpleCharge->onlyActive                       = $request->onlyActive;
        $simpleCharge->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $simpleCharge->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $simpleCharge->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

       $items = [];
        foreach($simpleCharge->simpleChargeDetailed() as $movementData){
            array_push($items, (object) [
                'occurrence_date'       =>  $movementData->occurrence_date ? \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y h:m:s') : null,
                'type_description'      =>  $movementData->type_description,
                'status_description'    =>  $movementData->status_description,
                'beneficiary_name'      =>  $movementData->beneficiary_name,
                'account_number'        =>  $movementData->account_number,
                'document'              =>  $movementData->document,
                'our_number'            =>  $movementData->our_number,
                'value'                 =>  $movementData->value,
                'payment_date'          =>  $movementData->payment_date ? \Carbon\Carbon::parse($movementData->payment_date)->format('d/m/Y') : null,
                'payment_value'         =>  $movementData->payment_value,
                'down_date'             =>  $movementData->down_date ? \Carbon\Carbon::parse($movementData->down_date)->format('d/m/Y') : null,
                'down_value'            =>  $movementData->down_value,
                'payer_name'            =>  $movementData->payer_name,
                'payer_cpf_cnpj'        =>  Facilites::mask_cpf_cnpj($movementData->payer_cpf_cnpj),
                'issue_date'            =>  \Carbon\Carbon::parse($movementData->issue_date)->format('d/m/Y'),
                'due_date'              =>  \Carbon\Carbon::parse($movementData->due_date)->format('d/m/Y'),
                'created_at'            =>   \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );
        $file_name = "Movimentacao_Carteira_Simples.pdf";
        $pdf       = PDF::loadView('reports/movement_simple_charge', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    public function deadLineDown()
    {
        $simpleChargeDeadLineDown = new SimpleChargeDeadLineDown();
        $simpleChargeDeadLineDown->deadLineDown();
    }

    public function createBilletWithoutEmail($chargeConfig, $s_charge)
    {
        $simpleCharge = new SimpleCharge();
        $simpleCharge->master_id = $s_charge->master_id;
        $simpleCharge->account_id = $s_charge->account_id;
        $simpleCharge->status_id = 4; // Apenas se boleto em aberto
        $simpleCharge->id = $s_charge->id;
        $simpleChargeData = $simpleCharge->getBilletData();

        $billetClass = new BilletClass();
        $billetClass->bar_code = $simpleChargeData->bar_code;
        $billetClass->digitable_line = $simpleChargeData->digitable_line;
        $billetClass->bank_code = $simpleChargeData->bank_code;
        $billetClass->pix_emv = $simpleChargeData->pix_emv;
        $billetClass->fine = $simpleChargeData->fine;
        $billetClass->interest = $simpleChargeData->interest;
        $billetClass->due_date = $simpleChargeData->due_date;
        $billetClass->value = $simpleChargeData->value;
        $billetClass->document = $simpleChargeData->document;
        $billetClass->our_number = $simpleChargeData->our_number;
        $billetClass->payer_name = $simpleChargeData->payer_name;
        $billetClass->payer_email = $simpleChargeData->payer_email;
        $billetClass->payer_cpf_cnpj = $simpleChargeData->payer_cpf_cnpj;
        $billetClass->beneficiary_name = $simpleChargeData->beneficiary_name;
        $billetClass->beneficiary_cpf_cnpj = $simpleChargeData->beneficiary_cpf_cnpj;
        $billetClass->beneficiary_address_zip_code = $simpleChargeData->beneficiary_address_zip_code;
        $billetClass->observation = $simpleChargeData->observation;
        $billetClass->owner_name = $simpleChargeData->owner_name;
        $billetClass->owner_cpf_cnpj = $simpleChargeData->owner_cpf_cnpj;
        $billetClass->issue_date = $simpleChargeData->issue_date;
        $billetClass->wallet_number = $simpleChargeData->wallet_number;
        $billetClass->message1 = $simpleChargeData->message1;
        $billetClass->message2 = $simpleChargeData->message2;
        $billetClass->payer_address_description = $simpleChargeData->payer_address_description;
        $billetClass->payer_address_city = $simpleChargeData->payer_address_city;
        $billetClass->payer_address_state_short_description = $simpleChargeData->payer_address_state_short_description;
        $billetClass->payer_address_zip_code = $simpleChargeData->payer_address_zip_code;

        $linkLogo = $this->imageLink($chargeConfig);
        $billetClass->logoBase64 = $linkLogo;

        $billet = $billetClass->createBillet();

        return $billet;

    }

    public function createBilletWithEmail($chargeConfig, $s_charge, $dueDateType)
    {

        $simpleCharge = new SimpleCharge();
        $simpleCharge->master_id = $s_charge->master_id;
        $simpleCharge->account_id = $s_charge->account_id;
        $simpleCharge->status_id = 4; // Apenas se boleto em aberto
        $simpleCharge->id = $s_charge->id;
        $simpleChargeData = $simpleCharge->getBilletData();

        $billetClass = new BilletClass();
        $billetClass->bar_code = $simpleChargeData->bar_code;
        $billetClass->digitable_line = $simpleChargeData->digitable_line;
        $billetClass->bank_code = $simpleChargeData->bank_code;
        $billetClass->pix_emv = $simpleChargeData->pix_emv;
        $billetClass->fine = $simpleChargeData->fine;
        $billetClass->interest = $simpleChargeData->interest;
        $billetClass->due_date = $simpleChargeData->due_date;
        $billetClass->value = $simpleChargeData->value;
        $billetClass->document = $simpleChargeData->document;
        $billetClass->our_number = $simpleChargeData->our_number;
        $billetClass->payer_name = $simpleChargeData->payer_name;
        $billetClass->payer_cpf_cnpj = $simpleChargeData->payer_cpf_cnpj;
        $billetClass->beneficiary_name = $simpleChargeData->beneficiary_name;
        $billetClass->beneficiary_cpf_cnpj = $simpleChargeData->beneficiary_cpf_cnpj;
        $billetClass->beneficiary_address_zip_code = $simpleChargeData->beneficiary_address_zip_code;
        $billetClass->observation = $simpleChargeData->observation;
        $billetClass->owner_name = $simpleChargeData->owner_name;
        $billetClass->owner_cpf_cnpj = $simpleChargeData->owner_cpf_cnpj;
        $billetClass->issue_date = $simpleChargeData->issue_date;
        $billetClass->wallet_number = $simpleChargeData->wallet_number;
        $billetClass->message1 = $simpleChargeData->message1;
        $billetClass->message2 = $simpleChargeData->message2;
        $billetClass->payer_address_description = $simpleChargeData->payer_address_description;
        $billetClass->payer_address_city = $simpleChargeData->payer_address_city;
        $billetClass->payer_address_state_short_description = $simpleChargeData->payer_address_state_short_description;
        $billetClass->payer_address_zip_code = $simpleChargeData->payer_address_zip_code;

        $linkLogo = $this->imageLink($chargeConfig);
        $billetClass->logoBase64 = $linkLogo;

        $billet = $billetClass->createBillet();


        $returnMail = $this->sendEmailOnDueDates($chargeConfig, $billet, $simpleChargeData->payer_email, $simpleChargeData, $dueDateType);

        if($chargeConfig->send_email_copy_to) {

            $returnMail = $this->sendEmailOnDueDates($chargeConfig, $billet, $chargeConfig->send_email_copy_to, $simpleChargeData, $dueDateType);

        }

        if( is_string($returnMail) ) {
            return $returnMail;
        }

    }

    public function getBase64FromAmazonS3($chargeConfig)
    {
        $amazons3 = new AmazonS3();
        $amazons3->disk = 's3_public';

        if( isset( $chargeConfig->logo_s3_filename ) ){
            if( $chargeConfig->logo_s3_filename != '' and $chargeConfig->logo_s3_filename != null) {
                $amazons3->fileName = $chargeConfig->logo_s3_filename;
                $document_type = DocumentType::where('id','=',29)->first();
                $amazons3->path     = $document_type->s3_path;
                $downfile           = $amazons3->fileDownAmazon();
                if( ! $downfile->success) {
                    return "Falha ao baixar a logo";
                }
                if(! isset($downfile->file64)){
                    return "Ocorreu uma falha ao gerar a logo";
                }

                return $downfile;
            }

        }

        return null;


    }

    public function sendEmailOnDueDates($chargeConfig, $billet, $email_to, $simpleChargeData, $dueDateType)
    {

        $codes = array(
            'documento'              => $simpleChargeData->document,
            'valor'                  => number_format( $simpleChargeData->value, 2, ',', '.' ),
            'dataVencimento'         => (\Carbon\Carbon::parse( $simpleChargeData->due_date ))->format('d/m/Y'),
            'dataEmissao'            => (\Carbon\Carbon::parse( $simpleChargeData->issue_date ))->format('d/m/Y'),
            'multa'                  => number_format( $simpleChargeData->fine ,3,',','.' ),
            'juros'                  => number_format( $simpleChargeData->interest ,3,',','.' ),
            'nossoNumero'            => $simpleChargeData->our_number,
            'nomePagador'            => $simpleChargeData->payer_name,
            'cpfCnpjPagador'         => Facilites::mask_cpf_cnpj( $simpleChargeData->payer_cpf_cnpj ),
            'telefonePagador'        => Facilites::mask_phone( $simpleChargeData->payer_phone ),
            'emailPagador'           => $simpleChargeData->payer_email,
            'enderecoPagador'        => $simpleChargeData->payer_address_public_place .' '. $simpleChargeData->payer_address .' '. $simpleChargeData->payer_address_number .' '. $simpleChargeData->payer_address_complement .' '. $simpleChargeData->payer_address_district .' '. $simpleChargeData->payer_address_city,
            'linhaDigitavel'         => $simpleChargeData->digitable_line,
            'bancoEmissor'           => $simpleChargeData->bank_code,
            'nomeBeneficiario'       => $simpleChargeData->beneficiary_name,
            'cpfCnpjBeneficiario'    => Facilites::mask_cpf_cnpj( $simpleChargeData->beneficiary_cpf_cnpj ),
            'telefoneBeneficiario'   => Facilites::mask_phone( $simpleChargeData->beneficiary_phone ),
            'emailBeneficiario'      => $simpleChargeData->beneficiary_email
        );

        $pattern = '@%s';

        $map = array();

        foreach($codes as $var => $value) {
            $map[sprintf($pattern, $var)] = $value;
        }

        $mailMessage = '';

        switch($dueDateType) {
            case 'currentDueDate':
                $mailMessage .= strtr($chargeConfig->mail_on_due_date_observation, $map);
                break;
            case 'beforeDueDate':
                $mailMessage .= strtr($chargeConfig->mail_before_due_date_observation, $map);
                break;
            case 'afterDueDate':
                $mailMessage .= strtr($chargeConfig->mail_after_due_date_observation, $map);
                break;
            default:
                return 'Tipo de observação inválida';
                break;
        }

        $apiSendGrind = new ApiSendgrid();
        $apiSendGrind->subject = "Boleto de ".$simpleChargeData->beneficiary_name;
        $apiSendGrind->content = "
            <html>
                <body>
                    <p>
                        $mailMessage
                    </p>
                </body>
            </html>
        ";


        $apiSendGrind->to_email = $email_to;
        $apiSendGrind->to_name  = $simpleChargeData->payer_name;

        $apiSendGrind->attachment_files = [
            "content" => $billet->base64,
            "filename" => $simpleChargeData->our_number.'.pdf',
            "type" => "application/pdf",
            "disposition" => "attachment"
        ];

        $apiSendGrind->sendMail();
    }

    public function sendBilletEmailCurrentDueDate(Request $request)
    {

        /*// ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [247, 307];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        } */
        // -------------- Finish Check Account Verification -------------- //

        $chargeConfigs = ChargeConfig::where('mail_on_due_date', '=', 1)->get();

        foreach($chargeConfigs as $chargeConfig) {
            $simpleCharges = SimpleCharge::where('account_id', '=', $chargeConfig->account_id)->where('status_id', '=', 4)->where('due_date', '=', \Carbon\Carbon::now()->format('Y-m-d'))->get();

            foreach($simpleCharges as $s_charge) {

                $billet = $this->createBilletWithEmail($chargeConfig, $s_charge, 'currentDueDate');

                if(is_string($billet)) {
                    return response()->json(["error" => $billet]);
                }
            }
        }

    }

    public function sendBilletEmailBeforeDueDate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        /*$accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [247, 307];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }*/
        // -------------- Finish Check Account Verification -------------- //

        $chargeConfigs = ChargeConfig::where('mail_before_due_date', '=', 1)->get();

        foreach($chargeConfigs as $chargeConfig) {

            // a vencer (vencimento futuro)
            $dayToSendEmail = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->addDays($chargeConfig->days_before_due_date)->format('Y-m-d');

            $simpleCharges = SimpleCharge::where('account_id', '=', $chargeConfig->account_id)->where('status_id', '=', 4)->where('due_date', '=', $dayToSendEmail)->get();

            foreach($simpleCharges as $s_charge) {

                $billet = $this->createBilletWithEmail($chargeConfig, $s_charge, 'beforeDueDate');

                /*if(is_string($billet)) {
                    return response()->json(["error" => $billet]);
                } */

            }
        }

    }

    public function sendBilletEmailAfterDueDate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        /*$accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [247, 307];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }*/
        // -------------- Finish Check Account Verification -------------- //


        $chargeConfigs = ChargeConfig::where('mail_after_due_date', '=', 1)->get();

        foreach($chargeConfigs as $chargeConfig) {

            // vencido (vencimento passado)
            // validar data base para vencido
            $dayToSendEmail = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->subDays($chargeConfig->days_after_due_date)->format('Y-m-d');

            $simpleCharges = SimpleCharge::where('account_id', '=', $chargeConfig->account_id)->where('status_id', '=', 4)->where('due_date', '=', $dayToSendEmail)->get();

            foreach($simpleCharges as $s_charge) {

                /*$daysAfterDueDate = (int)$chargeConfig->days_after_due_date;

                $dayToSendEmail = \Carbon\Carbon::parse( $s_charge->due_date )->subDays($daysAfterDueDate);

                while( $dayToSendEmail->isWeekend() || Holiday::isHoliday( (\Carbon\Carbon::parse( $dayToSendEmail ))->format('Y-m-d')) ) {
                    $dayToSendEmail->subDays(1);
                }

                if($dayToSendEmail->format('Y-m-d') != \Carbon\Carbon::now()->format('Y-m-d') ) {
                    continue;
                } */

                $billet = $this->createBilletWithEmail($chargeConfig, $s_charge, 'afterDueDate');

                /*if(is_string($billet)) {
                    return response()->json(["error" => $billet]);
                }*/

            }
        }
    }
}