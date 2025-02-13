<?php

namespace App\Http\Controllers;

use App\Models\CardMovement;
use App\Models\ApiConfig;
use App\Models\SendSms;
use App\Models\SystemFunctionMaster;
use App\Libraries\Facilites;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\GuzzleRequests;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Facades\Excel;
use App\Classes\ExcelExportClass;
use PDF;

class CardMovementController extends Controller
{

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [125, 310, 352, 467];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $cardMovement = new CardMovement();

        $cardMovement->account_id          = $checkAccount->account_id;
        $cardMovement->master_id           = $checkAccount->master_id;
        $cardMovement->id                  = $request->id;
        $cardMovement->card_id             = $request->card_id;
        $cardMovement->card_user_detail_id = $request->card_user_detail_id;
        $cardMovement->register_detail_id  = $request->register_detail_id;
        $cardMovement->start_value         = $request->start_value;
        $cardMovement->end_value           = $request->end_value;

        if($request->start_date != ''){
            $cardMovement->start_date = $request->start_date." 00:00:00.000";
        }

        if($request->end_date != ''){
            $cardMovement->end_date = $request->end_date." 23:59:59.998";
        }

        return response()->json($cardMovement->get());
    }

    protected function getAnalitic(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [125, 310, 467];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $cardMovement             = new CardMovement();
        $cardMovement->master_id  = $checkAccount->master_id;
        $cardMovement->account_id = $checkAccount->account_id;

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

        return response()->json(array(
            'period_liquidated'  => $cardMovement->CardMovementsAnalitic()
        ));
    }

    protected function getDetailed(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [125, 310, 467];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $cardMovement                                   = new CardMovement();
        $cardMovement->master_id                        = $checkAccount->master_id;
        $cardMovement->account_id                       = $checkAccount->account_id;
        $cardMovement->onlyActive                       = $request->onlyActive;
        $cardMovement->type_id                          = $request->type_id;
        $cardMovement->manager_id                       = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $cardMovement->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $cardMovement->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $cardMovement->created_at_start             = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $cardMovement->created_at_end               = $request->created_at_end." 23:59:59.998";
        }
        return response()->json( $cardMovement->cardMovementDetailed() );
    }

    protected function getDetailedPDF(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [125, 310, 353, 467];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $cardMovement                                   = new CardMovement();
        $cardMovement->master_id                        = $checkAccount->master_id;
        $cardMovement->account_id                       = $checkAccount->account_id;
        $cardMovement->onlyActive                       = $request->onlyActive;
        // $cardMovement->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $cardMovement->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $cardMovement->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $items = [];
        foreach( $cardMovement->cardMovementDetailed() as $movementData){
            array_push($items, (object) [
                'occurrence_date'               =>  $movementData->occurrence_date ? \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y h:m:s') : null,
                'value'                         =>  $movementData->value,
                'sellerName'                    =>  $movementData->sellerName,
                'account_number'                =>  $movementData->account_number,
                'card_user_description'         =>  $movementData->card_user_description,
                'nsu'                           =>  $movementData->nsu,
                'reversed_at'                   =>  $movementData->reversed_at ? \Carbon\Carbon::parse($movementData->reversed_at)->format('d/m/Y h:m:s') : null
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );
        $file_name = "Movimentacao_Cartoes.pdf";
        $pdf       = PDF::loadView('reports/movement_card', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getDetailedExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [125, 310, 352, 467];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //


        $header = collect([
            [
                'occurrence_date'        => 'Ocorrência',
                'value'                  => 'Valor',
                'sellerName'             => 'Histórico',
                'account_number'         => 'Conta',
                'card_user_description'  => 'Titular',
                'nsu'                    => 'NSU',
                'reversed_at'            => 'Estornado Em'
            ]
        ]);

        $cardMovement                                   = new CardMovement();
        $cardMovement->master_id                        = $checkAccount->master_id;
        $cardMovement->account_id                       = $checkAccount->account_id;
        $cardMovement->onlyActive                       = $request->onlyActive;
        // $cardMovement->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $cardMovement->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $cardMovement->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        $data = [];
        foreach( $cardMovement->cardMovementDetailedExcel() as $movementData){
            array_push($data, (object) [
                'occurrence_date'               =>  $movementData->occurrence_date ? \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y h:m:s') : null,
                'value'                         =>  number_format($movementData->value, 2, ',' ,'.'),
                'sellerName'                    =>  $movementData->sellerName,
                'account_number'                =>  $movementData->account_number,
                'card_user_description'         =>  $movementData->card_user_description,
                'nsu'                           =>  $movementData->nsu,
                'reversed_at'                   =>  $movementData->reversed_at ? \Carbon\Carbon::parse($movementData->reversed_at)->format('d/m/Y h:m:s') : null
            ]);
        }

        $card_movement_export        = new ExcelExportClass();
        $card_movement_export->value = $header->merge($data);

        return response()->json(array("success" => "Planilha gerada com sucesso", "file_name" => "Cadastros vinculados gerente.xlsx", "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "base64"=>base64_encode(Excel::raw($card_movement_export, \Maatwebsite\Excel\Excel::XLSX))));
    }

    public function sendMovementSMS()
    {

        $cardMovement = new CardMovement();
        $movements = $cardMovement->getCardMovementToSMS();
        $type = null;
        $operation = null;
        foreach($movements as $movement){
            if($movement->type == 'withdraw'){
                $type = "aprovado";
                $operation = "Saque";
                $message = 'iP4y: Saque aprovado no cartão final '.$movement->last_digits.', no valor de R$ '.number_format($movement->value, 2, ',','.').' em '.str_replace('.',' ',$movement->description50).', no dia '.(\Carbon\Carbon::parse($movement->created_at))->format('d/m/Y H:i');
            } else if($movement->type == 'reverse') {
                $type = "estornada";
                $operation = "Compra";
                $message = 'iP4y: Compra estornada no cartão final '.$movement->last_digits.', no valor de R$ '.number_format( ($movement->value * -1), 2, ',','.').' | '.str_replace('.',' ',$movement->description50).', no dia '.(\Carbon\Carbon::parse($movement->created_at))->format('d/m/Y H:i');
                continue;
            } else {
                $type = "aprovada";
                $operation = "Compra";
                $message = 'iP4y: Compra aprovada no cartão final '.$movement->last_digits.', no valor de R$ '.number_format($movement->value, 2, ',','.').' em '.str_replace('.',' ',$movement->description50).', no dia '.(\Carbon\Carbon::parse($movement->created_at))->format('d/m/Y H:i');
            }
            $apiConfigSMS                  = new ApiConfig();
            $apiConfigSMS->master_id       = $movement->master_id;
            $apiConfigSMS->api_id          = 3;
            $apiConfigSMS->onlyActive      = 1;
            $apiDataZenvia                 = $apiConfigSMS->getApiConfig()[0];
            $apiZenviaSMS                  = new ApiZenviaSMS();
            $apiZenviaSMS->api_address     = Crypt::decryptString($apiDataZenvia->api_address);
            $apiZenviaSMS->authorization   = Crypt::decryptString($apiDataZenvia->api_authentication);
            $sendSMS = SendSms::create([
                'external_id' => ("12".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$movement->id),
                'to'          => "55".$movement->card_user_cell_phone,
                'message'     => $message,
                'type_id'     => 12,
                'origin_id'   => $movement->id,
                'created_at'  => \Carbon\Carbon::now()
            ]);
            $apiZenviaSMS->id              = $sendSMS->external_id;
            $apiZenviaSMS->aggregateId     = "001";
            $apiZenviaSMS->to              = $sendSMS->to;
            $apiZenviaSMS->msg             = $sendSMS->message;
            $apiZenviaSMS->callbackOption  = "NONE";

            //Check if should send token by whatsapp
            if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',1)->first())->available == 1 ){
                $apiZenviaWhats            = new ApiZenviaWhatsapp();
                $apiZenviaWhats->to_number = $sendSMS->to;
                $apiZenviaWhats->operation = $operation;
                $apiZenviaWhats->type = $type;
                $apiZenviaWhats->card = $movement->last_digits;
                $apiZenviaWhats->value = number_format($movement->value, 2, ',','.');
                $apiZenviaWhats->place = str_replace('.',' ',$movement->description50);
                $apiZenviaWhats->date = (\Carbon\Carbon::parse($movement->created_at))->format('d/m/Y H:i');
                if(isset( $apiZenviaWhats->sendCardMovement()->success ) ){
                    $movementUpdate = CardMovement::where('id','=',$movement->id)->first();
                    $movementUpdate->sms_sended_at = \Carbon\Carbon::now();
                    $movementUpdate->save();
                    continue;
                }
            }

            if(isset( $apiZenviaSMS->sendShortSMS()->success ) ){
                $movementUpdate = CardMovement::where('id','=',$movement->id)->first();
                $movementUpdate->sms_sended_at = \Carbon\Carbon::now();
                $movementUpdate->save();
                continue;
            }
        }
        return true;
    }

    protected function approveChargeback(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$cardMovement = CardMovement::where('id', '=', $request->id)->where('nsu', '=', $request->nsu)->whereNull('reversed_at')->first()) {
            return response()->json(array("error" => "Poxa, lançamento não localizado ou já estornado, por favor verifique os dados e tente novamente."));
        }

        if ($cardMovement->value > 0) {
            return response()->json(array("error" => "Poxa, não é possível realizar estorno de transações não sinalizadas como estorno, em caso de dúvidas entre em contato com o administrador"));
        }

        $accessToken = $this->requestApiData()->data->accessToken;

        if (!$this->requestApiData($cardMovement->nsu, str_replace('-', '', number_format($cardMovement->value, 2, '', '',)), $cardMovement->id, $accessToken)->success) {
            return response()->json(array("error" => "Poxa, não foi possível aprovar o estorno de transação de cartão, por favor, verifique os dados informados e tente mais tarde, em caso de dúvidas entre em contato com o administrador"));
        }

        $cardMovement->reversed_at = \Carbon\Carbon::now();

        if ($cardMovement->save()) {
            return response()->json(array("success" => "Estorno de transação de cartão aprovado com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível aprovar o estorno de transação de cartão, por favor, verifique os dados informados e tente mais tarde, em caso de dúvidas entre em contato com o administrador"));
    }

    protected function requestApiData($transactionNSU = null, $amount = null, $origin_id = null, $accessToken = null)
    {
        $this->header = [];

        if (!$accessToken) {
            $this->endpoint = "https://conta.dinari.com.br:8008/api/auth/login";
            $this->params = array(
                "appId"     => "aquiuser1",
                "appSecret" => "aQc@rd$7xDb"
            );
        }

        if (isset($accessToken)) {
            $this->endpoint = [];
            $this->params   = [];
            $this->endpoint = "https://conta.dinari.com.br:8008/api/card-authorization/agency/cancel";
            $this->header = array(
                "Content-Type"    => "application/json",
                "Authorization"   => "Bearer {$accessToken}",
            );

            $this->params = (object) array(
                "transactionNsu"     => $transactionNSU,
                "origin_id"          => $origin_id,
                "amount"             => $amount,
            );
        }

        $apiRequest             = new GuzzleRequests();
        $apiRequest->uri        = $this->endpoint;
        $apiRequest->headers    = $this->header;
        $apiRequest->parameters = $this->params;

        $request = $apiRequest->jsonPostRequest();

        switch($request->status_code){
            case 200:
                return (object) array("success" => true, "data" => $request->body, 'status_code' => $request->status_code);
            break;
            case 201:
                return (object) array("success" => true, "data" => $request->body, 'status_code' => $request->status_code);
            break;
            default:
                return (object) array("success" => false, "data" => $request->body, 'status_code' => $request->status_code);
            break;
        }
    }

    
}
