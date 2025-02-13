<?php

namespace App\Http\Controllers;

use App\Models\BillPayment;
use App\Models\Account;
use App\Models\SendSms;
use App\Models\ApiConfig;
use App\Models\AuthorizationToken;
use App\Models\AccountMovement;
use App\Models\PaymentCovenant;
use App\Models\Master;
use App\Models\Holiday;
use App\Models\User;
use App\Models\SystemFunctionMaster;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\ApiCelCoin;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Services\BillPayment\BillSchedulePayService;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Account\MovementService;
use App\Services\Failures\sendFailureAlert;
use App\Classes\Failure\TransactionFailureClass;
use App\Classes\Failure\MovimentationFailureClass;
use App\Classes\Banking\BillClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use PDF;
use File;

class BillPaymentController extends Controller
{

    public function checkServiceAvailable(Request $request)
    {
        if( Holiday::isHoliday( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ) ){
            return response()->json(array("error" => "Informamos que devido ao feriado, não é possível realizar pagamentos, sendo possível realizar um agendamento para o próximo dia útil"));
        }

        if( ((date("w", strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ))) == 0) or ((date("w", strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ))) == 6) ){
            return response()->json(array("error" => "Informamos que devido ao final de semana, não é possível realizar pagamentos, sendo possível realizar um agendamento para o próximo dia útil"));
        }

        if( (\Carbon\Carbon::now())->toTimeString() > '22:59:54' ){
            return response()->json(array("error" => "Informamos que devido ao horário, não é possível realizar pagamentos, sendo possível realizar um agendamento para o próximo dia útil"));
        }

        if( (\Carbon\Carbon::now())->toTimeString() < '08:00:01' ){
            return response()->json(array("error" => "Informamos que devido ao horário, não é possível realizar pagamentos, a efetivação de pagamentos estará disponível após as 08:00"));
        }

        if( (SystemFunctionMaster::where('system_function_id','=',5)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível realizar pagamentos."));
        } else {
            return response()->json(array("success" => ""));
        }
    }

    protected function get(Request $request)
    {        

        //check permission for effective payments
        if(isset( $request->onlyActive) and isset($request->onlyEffective)){
            $checkAccount = json_decode($request->headers->get('check-account'));
        } else {
            // ----------------- Check Account Verification ----------------- //
            $accountCheckService           = new AccountRelationshipCheckService();
            $accountCheckService->request  = $request;
            $checkAccount                  = $accountCheckService->checkAccount();
            if(!$checkAccount->success){
                return response()->json(array("error" => $checkAccount->message));
            }
            // -------------- Finish Check Account Verification -------------- //
        }

        $billPayment                         = new BillPayment();
        $billPayment->account_id             = $checkAccount->account_id;
        $billPayment->master_id              = $checkAccount->master_id;
        $billPayment->onlyPending            = $request->onlyPending;
        $billPayment->onlyActive             = $request->onlyActive;
        $billPayment->onlyEffective          = $request->onlyEffective;
        $billPayment->onlyConfirmed          = $request->onlyConfirmed;
        $billPayment->onlyApprovedOrSchedule = $request->onlyApprovedOrSchedule;
        $billPayment->onlyNotApproved        = $request->onlyNotApproved;
        if($request->onlyNotApproved != 1){
            if($request->payment_date_start != ''){
                $billPayment->payment_date_start = $request->payment_date_start." 00:00:00.000";
            }
            if($request->payment_date_end != ''){
                $billPayment->payment_date_end = $request->payment_date_end." 23:59:59.998";
            }
            if($request->created_at_start != ''){
                $billPayment->created_at_start = $request->created_at_start." 00:00:00.000";
            }
            if($request->created_at_end != ''){
                $billPayment->created_at_end = $request->created_at_end." 23:59:59.998";
            }
        } else {
            if($request->created_at_start != ''){
                $billPayment->deleted_at_start = $request->created_at_start." 00:00:00.000";
            }
            if($request->created_at_end != ''){
                $billPayment->deleted_at_end = $request->created_at_end." 23:59:59.998";
            }
            if($request->schedule_at_start != ''){
                $billPayment->schedule_at_start = $request->schedule_at_start." 00:00:00.000";
            }
            if($request->schedule_at_end != ''){
                $billPayment->schedule_at_end = $request->schedule_at_end." 23:59:59.998";
            }
        }
        return response()->json( $billPayment->getBillPayment() );
    }

    protected function getAnalitic(Request $request)
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

        $billPayment             = new BillPayment();
        $billPayment->master_id  = $checkAccount->master_id;
        $billPayment->account_id = $checkAccount->account_id;

        //period liquidated
        $billPayment->status_id = 37;

        if($request->payment_date_start != ''){
            $billPayment->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $billPayment->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $billPayment->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $billPayment->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $period_liquidated                = $billPayment->BillPaymentAnalitic();
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

        $period_schedule                          = $billPayment->BillPaymentAnalitic();
        $billPayment->payment_schedule_date_start = null;
        $billPayment->payment_schedule_date_end   = null;
        $billPayment->status_id                   = null;
        //-----

        //schedule
        $billPayment->status_id = 7;

        $schedule                                 = $billPayment->BillPaymentAnalitic();
        $billPayment->status_id                   = null;
        //-----

        return response()->json(array(
            'period_liquidated'  => $period_liquidated,
            'period_schedule'    => $period_schedule,
            'schedule'           => $schedule
        ));
    }

    protected function getDetailed(Request $request)
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

        $billPayment                                   = new BillPayment();
        $billPayment->master_id                        = $checkAccount->master_id;
        $billPayment->account_id                       = $checkAccount->account_id;
        $billPayment->status_id                        = $request->status_id;
        $billPayment->onlyActive                       = $request->onlyActive;
        $billPayment->type_id                          = $request->type_id;
        $billPayment->manager_id                       = $request->manager_id;
        if($request->occurrence_date_start != ''){
            $billPayment->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $billPayment->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $billPayment->created_at_start             = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $billPayment->created_at_end               = $request->created_at_end." 23:59:59.998";
        }
        if($request->payment_date_start != ''){
            $billPayment->payment_date_start           = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $billPayment->payment_date_end             = $request->payment_date_end." 23:59:59.998";
        }
        if($request->payment_schedule_date_start != ''){
            $billPayment->payment_schedule_date_start  = $request->payment_schedule_date_start." 00:00:00.000";
        }
        if($request->payment_schedule_date_end != ''){
            $billPayment->payment_schedule_date_end    = $request->payment_schedule_date_end." 23:59:59.998";
        }

        return response()->json($billPayment->billPaymentDetailed());
    }

    protected function exportBillPayment(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $billPayment                         = new BillPayment();
        $billPayment->account_id             = $checkAccount->account_id;
        $billPayment->master_id              = $checkAccount->master_id;
        $billPayment->onlyPending            = $request->onlyPending;
        $billPayment->onlyActive             = $request->onlyActive;
        $billPayment->onlyEffective          = $request->onlyEffective;
        $billPayment->onlyConfirmed          = $request->onlyConfirmed;
        $billPayment->onlyApprovedOrSchedule = $request->onlyApprovedOrSchedule;
        $billPayment->onlyNotApproved        = $request->onlyNotApproved;

        $items = [];
        // return $simpleCharge->getSimpleCharge();
        foreach($billPayment->getBillPayment() as $movementData){
            array_push($items, (object) [
            'favored_name'                          =>      $movementData->favored_name,
            'favored_cpf_cnpj'                      =>      Facilites::mask_cpf_cnpj($movementData->favored_cpf_cnpj),
            'value'                                 =>      $movementData->value,
            'interest'                             =>      $movementData->interest,
            'fines'                                 =>      $movementData->fines,
            'discount'                              =>      $movementData->discount,
            'payment_value'                         =>      $movementData->payment_value,
            'due_date'                              =>      \Carbon\Carbon::parse($movementData->due_date)->format('d/m/Y'),
            'schedule_date'                         =>      \Carbon\Carbon::parse($movementData->schedule_date)->format('d/m/Y'),
            'description'                           =>      $movementData->description,
            'digitable_line'                        =>      $movementData->digitable_line,
            'bill_status_description'               =>      $movementData->bill_status_description,
            'created_at'                            =>      \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')

            ]);
        }

        $data = (object) array(
            "movement_data"     => $items
        );

        $file_name = "Aprovar_Pagamento.pdf";
        $pdf       = PDF::loadView('reports/bill_payment', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function exportReceiptBillPayment(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $billPayment                         = new BillPayment();
        $billPayment->account_id             = $checkAccount->account_id;
        $billPayment->master_id              = $checkAccount->master_id;
        $billPayment->onlyPending            = $request->onlyPending;
        $billPayment->onlyActive             = $request->onlyActive;
        $billPayment->onlyEffective          = $request->onlyEffective;
        $billPayment->onlyConfirmed          = $request->onlyConfirmed;
        $billPayment->onlyApprovedOrSchedule = $request->onlyApprovedOrSchedule;
        $billPayment->onlyNotApproved        = $request->onlyNotApproved;
        $items = [];
        foreach($billPayment->getBillPayment() as $movementData){
            array_push($items, (object) [
                'payment_date'            => \Carbon\Carbon::parse($movementData->payment_date)->format('d/m/Y'),
                'favored_name'            => $movementData->favored_name,
                'digitable_line'          => $movementData->digitable_line,
                'bill_status_description' => $movementData->bill_status_description,
                'value'                   => $movementData->value,
                'payment_value'           => $movementData->payment_value,
                'description'             => $movementData->description,
            ]);
        }

        $data = (object) array(
            "movement_data" => $items
        );

        $file_name = "Pagamentos_Efetuados.pdf";
        $pdf       = PDF::loadView('reports/bill_payment_voucher', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getReceipt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [197, 278]; 
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $billPaymentReceipt                  = new BillPayment();
        $billPaymentReceipt->account_id      = $checkAccount->account_id;
        $billPaymentReceipt->onlyActive      = $request->onlyActive;
        $billPaymentReceipt->onlyEffective   = $request->onlyEffective;
        if($request->payment_date_start != ''){
            $billPaymentReceipt->payment_date_start = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $billPaymentReceipt->payment_date_end = $request->payment_date_end." 23:59:59.998";
        }
        return response()->json( $billPaymentReceipt->getBillPayment() );
    }

    protected function getScheduledValue(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $billPayment             = new BillPayment();
        $billPayment->master_id  = $checkAccount->master_id;
        $billPayment->account_id = $checkAccount->account_id;
        $scheduleValue = 0;
        if(isset($billPayment->getBillPaymentScheduledValue()->scheduleValue)){
            $scheduleValue = $billPayment->getBillPaymentScheduledValue()->scheduleValue;
        }
        return response()->json(array("success" => "", "scheduledValue" => $scheduleValue));
    }

    protected function getMasterScheduledValue(Request $request)
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

        $billPayment             = new BillPayment();
        $billPayment->master_id  = $checkAccount->master_id;
        $scheduleValue = 0;
        if(isset($billPayment->getBillPaymentScheduledValue()->scheduleValue)){
            $scheduleValue = $billPayment->getBillPaymentScheduledValue()->scheduleValue;
        }
        return response()->json(array("success" => "", "scheduledValue" => $scheduleValue));
    }

    protected function new(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'digitable_line_or_bar_code'=> ['required', 'string'],
        ],[
            'digitable_line_or_bar_code.required' => 'Informe a linha digitável ou código de barras.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if(   (SystemFunctionMaster::where('system_function_id','=',5)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if(Account::where('id','=',$checkAccount->account_id)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
            return response()->json(array("error" => "Falha de verificação da conta"));
        }

        $getAccountInclusionLimitQtt = Account::where('id','=',$checkAccount->account_id)->whereNull('deleted_at')->first()->inclusion_limit_bill_payment_qtt;
        if($getAccountInclusionLimitQtt == null) {
            $getAccountInclusionLimitQtt = 0;
        }

        $paymentWaitingApprovingQtt = BillPayment::where('account_id', '=', $checkAccount->account_id)->whereNull('payment_date')->whereNull('deleted_at')->where('value', '>', 0)->whereIn('status_id', [5])->count();

        if ($paymentWaitingApprovingQtt >= $getAccountInclusionLimitQtt ) {
            return response()->json(array("error" => "Sua conta possuí $paymentWaitingApprovingQtt pagamentos aguardando aprovação, por favor realize a aprovação para continuar"));
        }

        $digitableLineOrBarCode = null;
        $digitableLineOrBarCode = preg_replace( '/[^0-9]/is', '',$request->digitable_line_or_bar_code);
   
        $digitableLine = null;
        $barCode       = null;
        $billType      = null;
        $covenantType  = null;

        if( strlen($digitableLineOrBarCode) == 47 ){
            $digitableLine = $digitableLineOrBarCode;
            $billType = 1; // Boleto
        } else if(strlen($digitableLineOrBarCode) == 44){
            $barCode       = $digitableLineOrBarCode;
            $digitableLine = $digitableLineOrBarCode;
            if(substr($barCode, 0, 1) == '8'){
                $billType = 2; // Conta de Consumo
                $covenantType   = (int) substr($digitableLineOrBarCode, 1, 1);
                $covenantNumber = (int) substr($digitableLineOrBarCode, 15, 4);
                
                //Removido para validar convenio/tributo na celcoin
                /*$covenantData = PaymentCovenant::getcovenantData($covenantNumber, $covenantType);
                if($covenantData == ''){
                    return response()->json(array("error" => "Convênio para pagamento não cadastrado, entre em contato com o administrador do sistema | Convênio: ".$covenantNumber));
                }*/
                
                
                if($covenantType == 2 or $covenantType == 3 or $covenantType == 4 or $covenantType == 7){
                    $billType = 2; // Conta de Consumo
                    $covenantName = "Conta Consumo";
                } else {
                    $billType = 3; // Tributo
                    $covenantName = "Tributo";
                }
                //$covenantName = $covenantData->name;
            } else {
                $billType = 1; // Boleto
            }
        } else if(strlen($digitableLineOrBarCode) == 48){
            $digitableLine = $digitableLineOrBarCode;
            $barCode       = substr($digitableLineOrBarCode,  0, 11);
            $barCode      .= substr($digitableLineOrBarCode, 12, 11);
            $barCode      .= substr($digitableLineOrBarCode, 24, 11);
            $barCode      .= substr($digitableLineOrBarCode, 36, 11);
            if(substr($barCode, 0, 1) == '8'){
                $covenantType   = (int) substr($digitableLineOrBarCode, 1, 1);
                $covenantNumber = (int) substr($digitableLineOrBarCode, 16, 4);
                $convenatValue = (float) (substr($digitableLineOrBarCode, 4, 7).substr($digitableLineOrBarCode, 12, 4))/100;
                
                //Removido para validar convenio/tributo na celcoin
                /*$covenantData = PaymentCovenant::getcovenantData($covenantNumber, $covenantType);
                if($covenantData == ''){
                    return response()->json(array("error" => "Convênio para pagamento não cadastrado, entre em contato com o administrador do sistema | Convênio: ".$covenantNumber));
                } */

                if($covenantType == 2 or $covenantType == 3 or $covenantType == 4 or $covenantType == 7){
                    $billType = 2; // Conta de Consumo
                    $covenantName = "Conta Consumo";
                } else {
                    $billType = 3; // Tributo
                    $covenantName = "Tributo";
                }
                //$covenantName = $covenantData->name;
            } else {
                $billType = 1; // Boleto
            }
        } else {
            return response()->json(array("error" => "Por favor verifique a linha digitável informada e tente novamente mais tarde"));
        }

        /*
        //------------------------------------------------ teste
        $billPaymentCreate = BillPayment::create([
            'digitable_line_or_bar_code'       => $digitableLineOrBarCode,
            'account_id'                       => $checkAccount->account_id,
            'bill_type_id'                     => $billType,
            'status_id'                        => 38,
            'digitable_line'                   => $digitableLine,
            'bar_code'                         => $barCode,
            'uuid'                             => Str::orderedUuid(),
            'created_at'                       => \Carbon\Carbon::now(),
            'deleted_at'                       => \Carbon\Carbon::now(),
            'api_id'                           => 8,
            'included_by_user_id'              => $checkAccount->user_id,
            'included_by_user_relationship_id' => $checkAccount->user_relationship_id
        ]); 

        $billPaymentInclude                             = BillPayment::where('id','=',$billPaymentCreate->id)->first();               
        $billPaymentInclude->schedule_date              = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
        $billPaymentInclude->digitable_line             = preg_replace( '/[^0-9]/is', '', $digitableLineOrBarCode);               
        
        $billPaymentInclude->validate_duplicity         = 0;
        $billPaymentInclude->enable_mp                  = 0;
        $billPaymentInclude->diverse_payment_type       = null;
        $billPaymentInclude->auth_rcvmnt_divergent      = 'NaoAceitarDivergente';
        $billPaymentInclude->reason                     = null;
        $billPaymentInclude->deleted_at                 = null;
        $billPaymentInclude->authorize_id               = 123;

        $billPaymentInclude->favored_cpf_cnpj       = '32032097000100';
        $billPaymentInclude->favored_name           = 'Teste Favorecido';
        $billPaymentInclude->payer_cpf_cnpj         = '12312312334';
        $billPaymentInclude->payer_name             = 'Teste Pagador';
        $billPaymentInclude->due_date               = '2024-07-20';//(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
        $billPaymentInclude->payment_deadline       = '2100-05-10';
        $billPaymentInclude->value                  = 10;
        $billPaymentInclude->total_value            = 10;
        $billPaymentInclude->payment_value          = 10;
        $billPaymentInclude->interest               = 0;
        $billPaymentInclude->fines                  = 0;
        $billPaymentInclude->max_value              = 0;
        $billPaymentInclude->min_value              = 0;
        $billPaymentInclude->discount               = 0;
        $billPaymentInclude->allow_update_value     = 0;
        $billPaymentInclude->allow_partial_payment  = 0;                    

        $billPaymentInclude->save();

        $billPayment = BillPayment::where('id','=',$billPaymentInclude->id)->first();
        return response()->json(array("success" => $billPayment));

        //------------------------------------------------ fim teste
        */
        
        

        // Checa se pagamento já incluído
        if( $checkBillPayment = BillPayment::where('digitable_line_or_bar_code', '=', $digitableLineOrBarCode)->where('account_id', '=', $checkAccount->account_id)->where('status_id', '=', 5)->whereNull('payment_date')->first() ) {
            
            if($billType != 1) {
                return response()->json(array(
                    "error" => "Pagamento para linha digitável/código de barras ".$digitableLineOrBarCode.", já incluído em ".\Carbon\Carbon::parse($checkBillPayment->created_at)->format('d/m/Y H:i:s').", acesse o menu aprovar pagamento para aprova-lo."
                ));
            } else {
                
                if( ((int) substr($digitableLineOrBarCode, -10)) != 0 ) {
                    return response()->json(array(
                        "error" => "Pagamento para linha digitável/código de barras ".$digitableLineOrBarCode.", já incluído em ".\Carbon\Carbon::parse($checkBillPayment->created_at)->format('d/m/Y H:i:s').", acesse o menu aprovar pagamento para aprova-lo."
                    ));
                }
            }
        }


        // Checa se pagamento já pago
        if( $checkBillPayment = BillPayment::where('digitable_line_or_bar_code', '=', $digitableLineOrBarCode)->where('account_id', '=', $checkAccount->account_id)->where('status_id', '=', 37)->whereNotNull('payment_date')->first() ) {
            
            if($billType != 1) {
                return response()->json(array(
                    "error" => "Pagamento para linha digitável/código de barras ".$digitableLineOrBarCode.", já realizado em ".\Carbon\Carbon::parse($checkBillPayment->payment_date)->format('d/m/Y H:i:s')."."
                ));
            } else {
                
                if( ((int) substr($digitableLineOrBarCode, -10)) != 0 ) {
                    return response()->json(array(
                        "error" => "Pagamento para linha digitável/código de barras ".$digitableLineOrBarCode.", já realizado em ".\Carbon\Carbon::parse($checkBillPayment->payment_date)->format('d/m/Y H:i:s')."."
                    ));
                }
            }  
        }



        switch($billType){
            case 3: //Tributo
                
                $apiConfig                          = new ApiConfig();
                $apiConfig->master_id               = $checkAccount->master_id;
                $apiConfig->api_id                  = 8;
                $apiConfig->onlyActive              = 1;
                $apiData                            = $apiConfig->getApiConfig()[0];

                $billPaymentCreate = BillPayment::create([
                    'digitable_line_or_bar_code'       => $digitableLineOrBarCode,
                    'account_id'                       => $checkAccount->account_id,
                    'bill_type_id'                     => $billType,
                    'status_id'                        => 38,
                    'digitable_line'                   => $digitableLine,
                    'bar_code'                         => $barCode,
                    'uuid'                             => Str::orderedUuid(),
                    'created_at'                       => \Carbon\Carbon::now(),
                    'deleted_at'                       => \Carbon\Carbon::now(),
                    'api_id'                           => $apiConfig->api_id,
                    'included_by_user_id'              => $checkAccount->user_id,
                    'included_by_user_relationship_id' => $checkAccount->user_relationship_id
                ]);
                

                $apiCelCoin                         = new ApiCelCoin();
                $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                $apiCelCoin->payer_id               = '11491029000130';
                $apiCelCoin->type                   = 1;
                $apiCelCoin->digitable              = '';
                $apiCelCoin->barCode                = $billPaymentCreate->bar_code;
                $apiCelCoin->externalNSU            = $billPaymentCreate->id;
                $apiCelCoin->externalTerminal       = $checkAccount->account_id;

                $checkBill = null;
                $checkBill = $apiCelCoin->billPaymentBilletData();

                if( ! $checkBill->success ){
                    if( $covenantNumber != 179 and $covenantNumber != 180 and  $covenantNumber != 239 ) {
                        if(isset($checkBill->data->message)){
                            return response()->json(array("error" => $checkBill->data->message));
                        }
                        return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));

                    } else {
                        //Tributos Governamentais - Tentativa de checagem no rendimento em caso de falha na celcoin ( Especialmente para FGTS )
                        $apiConfig = new ApiConfig();
                        $apiConfig->master_id  = $checkAccount->master_id;
                        $apiConfig->api_id = 10;
                        $apiConfig->onlyActive = 1;
                        $apiData = $apiConfig->getApiConfig()[0];
                
                        unset($apiRendimento);
                        $apiRendimento = new ApiBancoRendimento();
                        $apiRendimento->unsetVariables();
                        $apiRendimento->id_cliente = Crypt::decryptString($apiData->api_client_id);
                        $apiRendimento->chave_acesso = Crypt::decryptString($apiData->api_key);
                        $apiRendimento->autenticacao = Crypt::decryptString($apiData->api_authentication);
                        $apiRendimento->endereco_api = Crypt::decryptString($apiData->api_address);
                        $apiRendimento->agencia = Crypt::decryptString($apiData->api_agency);
                        $apiRendimento->conta_corrente = Crypt::decryptString($apiData->api_account);
                
                
                        $apiRendimento->bill_type = $billType;
                        $apiRendimento->con_linha_digitavel = $digitableLine;
                        $apiRendimento->con_codigo_barras = $barCode;
                        $apiRendimento->tit_seu_numero = null;
                        $apiRendimento->tit_data_vencimento = null;
                        $checkBillRendimento = null;
                        $checkBillRendimento = $apiRendimento->consultarDadosPagamentoFgtsV5();

                        // Trata falha de retorno do rendimento
                        if( ! isset($checkBillRendimento->body->value) ){ 


                            if(isset($checkBillRendimento->body->isSuccess) ){
                                if( ! $checkBillRendimento->body->isSuccess ) {
                                    if(isset($checkBillRendimento->body->erroMessage->errors[0]) ){
                                        return response()->json(array("error" => $checkBillRendimento->body->erroMessage->errors[0]->message));
                                    }
                                }
                            }

                            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                        }

                        // Trata erro retornado do rendimento
                        if(isset($checkBillRendimento->body->isSuccess) ){
                            if( ! $checkBillRendimento->body->isSuccess ) {
                                if(isset($checkBillRendimento->body->erroMessage->errors[0]) ){
                                    return response()->json(array("error" => $checkBillRendimento->body->erroMessage->errors[0]->message));
                                }
                            }
                        }

                        if( $checkBillRendimento->body->value->valorTotal == 0 or $checkBillRendimento->body->value->valorTotal == null ) {
                            if( isset( $checkBillRendimento->body->value->motivo  ) ) {

                               

                                if( $checkBillRendimento->body->value->motivo != '' and $checkBillRendimento->body->value->motivo != null ){
                                    return response()->json(array("error" => $checkBillRendimento->body->value->motivo));
                                }
                            }
                        }
    

                        // define o objeto check bill
                        $checkBill = (object) [
                            "data" => (object) [
                                "transactionId" => $billPaymentCreate->id,
                                "value" => $checkBillRendimento->body->value->valorTotal,
                                "assignor" => $checkBillRendimento->body->value->nomeBeneficiario,
                                "digitable" => $checkBillRendimento->body->value->linhaDigitavel,
                                "message" => $checkBillRendimento->body->value->motivo,
                            ]
                        ];
                    }
                }

                if( ! isset( $checkBill->data->transactionId ) ){
                    return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde! (err 1)"));
                }


                if( ! isset( $checkBill->data->value ) ){
                    return response()->json(array("error" => "Poxa, não foi possível recuperar o valor do documento, por favor entre em contato com o suporte e informe essa situação."));
                }

                if($checkBill->data->value <= 0 or $checkBill->data->value == null){
                    return response()->json(array("error" => "Poxa, não foi possível recuperar o valor do documento, por favor entre em contato com o suporte e informe essa situação."));
                }

                if( $checkBill->data->value > 250000 ) {
                    return response()->json(array("error" => "Não é possível realizar pagamento de tributo acima de R$ 250.000,00."));
                }

                $billPaymentInclude                             = BillPayment::where('id','=',$billPaymentCreate->id)->first();
                $billPaymentInclude->favored_cpf_cnpj           = $covenantNumber;
                $billPaymentInclude->favored_name               = isset($checkBill->data->assignor) ? $checkBill->data->assignor : $covenantName;
                $billPaymentInclude->due_date                   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->schedule_date              = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->value                      = $checkBill->data->value;
                $billPaymentInclude->total_value                = $checkBill->data->value;
                $billPaymentInclude->payment_value              = $checkBill->data->value;
                $billPaymentInclude->digitable_line             = str_replace(' ', '', $checkBill->data->digitable);
                $billPaymentInclude->interest                   = 0;
                $billPaymentInclude->fines                      = 0;
                $billPaymentInclude->max_value                  = $checkBill->data->value;
                $billPaymentInclude->min_value                  = 0;
                $billPaymentInclude->payment_deadline           = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->discount                   = 0;
                $billPaymentInclude->allow_update_value         = 0;
                $billPaymentInclude->allow_partial_payment      = 0;
                $billPaymentInclude->validate_duplicity         = 0;
                $billPaymentInclude->enable_mp                  = 0;
                $billPaymentInclude->diverse_payment_type       = isset($checkBill->data->assignor) ? $checkBill->data->assignor : null;
                $billPaymentInclude->auth_rcvmnt_divergent      = 'NaoAceitarDivergente';
                $billPaymentInclude->reason                     = isset($checkBill->data->message) ? $checkBill->data->message : null;
                $billPaymentInclude->deleted_at                 = null;
                $billPaymentInclude->authorize_id               = $checkBill->data->transactionId;
                $billPaymentInclude->api_id                     = $apiConfig->api_id;
                $billPaymentInclude->save();

                $billPayment = BillPayment::where('id','=',$billPaymentInclude->id)->first();
    
                return response()->json(array("success" => $billPayment));

            break;
            case 2: //Concessionária - Consumo
                
                
                $apiConfig                          = new ApiConfig();
                $apiConfig->master_id               = $checkAccount->master_id;
                $apiConfig->api_id                  = 8;
                $apiConfig->onlyActive              = 1;
                $apiData                            = $apiConfig->getApiConfig()[0];

                $billPaymentCreate = BillPayment::create([
                    'digitable_line_or_bar_code'       => $digitableLineOrBarCode,
                    'account_id'                       => $checkAccount->account_id,
                    'bill_type_id'                     => $billType,
                    'status_id'                        => 38,
                    'digitable_line'                   => $digitableLine,
                    'bar_code'                         => $barCode,
                    'uuid'                             => Str::orderedUuid(),
                    'created_at'                       => \Carbon\Carbon::now(),
                    'deleted_at'                       => \Carbon\Carbon::now(),
                    'api_id'                           => $apiConfig->api_id,
                    'included_by_user_id'              => $checkAccount->user_id,
                    'included_by_user_relationship_id' => $checkAccount->user_relationship_id
                ]);

                $apiCelCoin                         = new ApiCelCoin();
                $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                $apiCelCoin->type                   = 1;
                $apiCelCoin->digitable              = '';
                $apiCelCoin->barCode                = $billPaymentCreate->bar_code;
                $apiCelCoin->externalNSU            = $billPaymentCreate->id;
                $apiCelCoin->externalTerminal       = $checkAccount->account_id;

                $checkBill = null;
                $checkBill = $apiCelCoin->billPaymentBilletData();

                if( ! $checkBill->success ){
                    if(isset($checkBill->data->message)){
                        return response()->json(array("error" => $checkBill->data->message));

                    }

                    return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                }

                if( ! isset( $checkBill->data->transactionId ) ){
                    return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                }


                if( ! isset( $checkBill->data->value ) ){
                    return response()->json(array("error" => "Poxa, não foi possível recuperar o valor do documento, por favor entre em contato com o suporte e informe essa situação."));
                }

                if($checkBill->data->value <= 0 or $checkBill->data->value == null){
                    return response()->json(array("error" => "Poxa, não foi possível recuperar o valor do documento, por favor entre em contato com o suporte e informe essa situação."));
                }

                if( $checkBill->data->value > 250000 ) {
                    return response()->json(array("error" => "Não é possível realizar pagamento de conta de consumo acima de R$ 250.000,00."));
                }

                $billPaymentInclude                             = BillPayment::where('id','=',$billPaymentCreate->id)->first();
                $billPaymentInclude->favored_cpf_cnpj           = $covenantNumber;
                $billPaymentInclude->favored_name               = isset($checkBill->data->assignor) ? $checkBill->data->assignor : $covenantName;
                $billPaymentInclude->due_date                   = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->schedule_date              = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->value                      = $checkBill->data->value;
                $billPaymentInclude->total_value                = $checkBill->data->value;
                $billPaymentInclude->payment_value              = $checkBill->data->value;
                $billPaymentInclude->digitable_line             = $checkBill->data->digitable;
                $billPaymentInclude->interest                   = 0;
                $billPaymentInclude->fines                      = 0;
                $billPaymentInclude->max_value                  = $checkBill->data->value;
                $billPaymentInclude->min_value                  = 0;
                $billPaymentInclude->payment_deadline           = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->discount                   = 0;
                $billPaymentInclude->allow_update_value         = 0;
                $billPaymentInclude->allow_partial_payment      = 0;
                $billPaymentInclude->validate_duplicity         = 0;
                $billPaymentInclude->enable_mp                  = 0;
                $billPaymentInclude->diverse_payment_type       = isset($checkBill->data->assignor) ? $checkBill->data->assignor : null;
                $billPaymentInclude->auth_rcvmnt_divergent      = 'NaoAceitarDivergente';
                $billPaymentInclude->reason                     = isset($checkBill->data->message) ? $checkBill->data->message : null;
                $billPaymentInclude->deleted_at                 = null;
                $billPaymentInclude->authorize_id               = $checkBill->data->transactionId;
                $billPaymentInclude->save();

                $billPayment = BillPayment::where('id','=',$billPaymentInclude->id)->first();
    
                return response()->json(array("success" => $billPayment));
            break;
            case 1: //Boleto
               

                


                $sendTo = 'celcoin';


                if( $sendTo == 'celcoin' ) {
                    //Para Celcoin
                    $apiConfig                          = new ApiConfig();
                    $apiConfig->master_id               = $checkAccount->master_id;
                    $apiConfig->api_id                  = 8;
                    $apiConfig->onlyActive              = 1;
                    $apiData                            = $apiConfig->getApiConfig()[0];

                    $billPaymentCreate = BillPayment::create([
                        'digitable_line_or_bar_code'       => $digitableLineOrBarCode,
                        'account_id'                       => $checkAccount->account_id,
                        'bill_type_id'                     => $billType,
                        'status_id'                        => 38,
                        'digitable_line'                   => $digitableLine,
                        'bar_code'                         => $barCode,
                        'uuid'                             => Str::orderedUuid(),
                        'created_at'                       => \Carbon\Carbon::now(),
                        'deleted_at'                       => \Carbon\Carbon::now(),
                        'api_id'                           => $apiConfig->api_id,
                        'included_by_user_id'              => $checkAccount->user_id,
                        'included_by_user_relationship_id' => $checkAccount->user_relationship_id
                    ]);

                    $apiCelCoin                         = new ApiCelCoin();
                    $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                    $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                    $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                    $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                    $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                    $apiCelCoin->type                   = 2;
                    $apiCelCoin->digitable              = $digitableLine;
                    $apiCelCoin->barCode                = '';
                    $apiCelCoin->externalNSU            = $billPaymentCreate->id;
                    $apiCelCoin->externalTerminal       = $checkAccount->account_id;

                    $checkBill = null;
                    $checkBill = $apiCelCoin->billPaymentBilletData();

                    if( ! $checkBill->success ){
                        if(isset($checkBill->data->message)){
                            return response()->json(array("error" => $checkBill->data->message));

                        }

                        return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                    }

                    if( ! isset( $checkBill->data->transactionId ) ){
                        return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                    }


                    if( ! isset( $checkBill->data->registerData ) ){
                        return response()->json(array("error" => "Poxa, não foi possível recuperar o valor do documento, por favor entre em contato com o suporte e informe essa situação."));
                    }



                }

                if( $sendTo == 'rendimento' ) {
                    $apiConfig = new ApiConfig();
                    $apiConfig->master_id  = $checkAccount->master_id;
                    $apiConfig->api_id = 10;
                    $apiConfig->onlyActive = 1;
                    $apiData = $apiConfig->getApiConfig()[0];
            
                    unset($apiRendimento);
                    $apiRendimento = new ApiBancoRendimento();
                    $apiRendimento->unsetVariables();
                    $apiRendimento->id_cliente = Crypt::decryptString($apiData->api_client_id);
                    $apiRendimento->chave_acesso = Crypt::decryptString($apiData->api_key);
                    $apiRendimento->autenticacao = Crypt::decryptString($apiData->api_authentication);
                    $apiRendimento->endereco_api = Crypt::decryptString($apiData->api_address);
                    $apiRendimento->agencia = Crypt::decryptString($apiData->api_agency);
                    $apiRendimento->conta_corrente = Crypt::decryptString($apiData->api_account);

                    $billPaymentCreate = BillPayment::create([
                        'digitable_line_or_bar_code'       => $digitableLineOrBarCode,
                        'account_id'                       => $checkAccount->account_id,
                        'bill_type_id'                     => $billType,
                        'status_id'                        => 38,
                        'digitable_line'                   => $digitableLine,
                        'bar_code'                         => $barCode,
                        'uuid'                             => Str::orderedUuid(),
                        'created_at'                       => \Carbon\Carbon::now(),
                        'deleted_at'                       => \Carbon\Carbon::now(),
                        'api_id'                           => $apiConfig->api_id,
                        'included_by_user_id'              => $checkAccount->user_id,
                        'included_by_user_relationship_id' => $checkAccount->user_relationship_id
                    ]);
            
            
                    $apiRendimento->bill_type = $billType;
                    $apiRendimento->con_linha_digitavel = $digitableLine;
                    $apiRendimento->con_codigo_barras =  (($digitableLine == '' or $digitableLine == null) ? $con_codigo_barras : null);//$barCode;
                    $checkBillRendimento = null;
                    $checkBillRendimento = $apiRendimento->consultarDadosPagamentoV5();

                    // Trata falha de retorno do rendimento
                    if( ! isset($checkBillRendimento->body->data) ){ 
                        return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                    }

                    // Trata erro retornado do rendimento
                    if(isset($checkBillRendimento->body->success) ){
                        if( ! $checkBillRendimento->body->success ) {
                            if(isset($checkBillRendimento->body->notifications[0]) ){
                                return response()->json(array("error" => $checkBillRendimento->body->notifications[0]->message));
                            }

                            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
                        }
                    }

                   
                    if ( $checkBillRendimento->body->data->motivo != null and $checkBillRendimento->body->data->motivo != '' ) {
                        return response()->json(array("error" => $checkBillRendimento->body->data->motivo));
                    }
                    

                    // define o objeto check bill
                    $checkBill = (object) [
                        "data" => (object) [
                            "transactionId" => $billPaymentCreate->id,
                            "value" => $checkBillRendimento->body->data->valorTotal,
                            "assignor" => $checkBillRendimento->body->data->tipoPagamentoDiverso,
                            "digitable" => $checkBillRendimento->body->data->linhaDigitavel,
                            "message" => $checkBillRendimento->body->data->motivo,
                            "registerData" => (object) [
                                "documentRecipient" => $checkBillRendimento->body->data->cnpjcpfBeneficiario,
                                "recipient" => $checkBillRendimento->body->data->nomeBeneficiario,
                                "documentPayer" => $checkBillRendimento->body->data->cnpjcpfPagador,
                                "payer" => $checkBillRendimento->body->data->nomePagador,
                                "dueDateRegister" => $checkBillRendimento->body->data->dataVencimento,
                                "payDueDate" => $checkBillRendimento->body->data->dataLimitePagamento,
                                "originalValue" => $checkBillRendimento->body->data->valorNominal,
                                "totalUpdated" => $checkBillRendimento->body->data->valorTotal,
                                "interestValueCalculated" => $checkBillRendimento->body->data->juros,
                                "fineValueCalculated" => $checkBillRendimento->body->data->multa,
                                "maxValue" => $checkBillRendimento->body->data->valorMaximo,
                                "minValue" => $checkBillRendimento->body->data->valorMinimo,
                                "discountValue" => $checkBillRendimento->body->data->desconto,
                                "allowChangeValue" => $checkBillRendimento->body->value->permitePagamentoParcial,
                            ]
                        ]
                    ];
                }
                
                $billPaymentInclude                             = BillPayment::where('id','=',$billPaymentCreate->id)->first();               
                $billPaymentInclude->schedule_date              = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');
                $billPaymentInclude->digitable_line             = preg_replace( '/[^0-9]/is', '', $checkBill->data->digitable);               
                
                $billPaymentInclude->validate_duplicity         = 0;
                $billPaymentInclude->enable_mp                  = 0;
                $billPaymentInclude->diverse_payment_type       = isset($checkBill->data->assignor) ? $checkBill->data->assignor : null;
                $billPaymentInclude->auth_rcvmnt_divergent      = 'NaoAceitarDivergente';
                $billPaymentInclude->reason                     = isset($checkBill->data->message) ? $checkBill->data->message : null;
                $billPaymentInclude->deleted_at                 = null;
                $billPaymentInclude->authorize_id               = $checkBill->data->transactionId;

                if(isset($checkBill->data->registerData)) {
                    $billPaymentInclude->favored_cpf_cnpj       = preg_replace( '/[^0-9]/is', '', $checkBill->data->registerData->documentRecipient);
                    $billPaymentInclude->favored_name           = $checkBill->data->registerData->recipient;
                    $billPaymentInclude->payer_cpf_cnpj         = preg_replace( '/[^0-9]/is', '', $checkBill->data->registerData->documentPayer);
                    $billPaymentInclude->payer_name             = $checkBill->data->registerData->payer;
                    $billPaymentInclude->due_date               = (\Carbon\Carbon::parse(  $checkBill->data->registerData->dueDateRegister ))->format('Y-m-d');
                    $billPaymentInclude->payment_deadline       = (\Carbon\Carbon::parse(  $checkBill->data->registerData->payDueDate ))->format('Y-m-d');
                    $billPaymentInclude->value                  = $checkBill->data->registerData->originalValue;
                    $billPaymentInclude->total_value            = ($checkBill->data->registerData->totalUpdated > 0) ? $checkBill->data->registerData->totalUpdated : $checkBill->data->registerData->originalValue;
                    $billPaymentInclude->payment_value          = ($checkBill->data->registerData->totalUpdated > 0) ? $checkBill->data->registerData->totalUpdated : $checkBill->data->registerData->originalValue;
                    $billPaymentInclude->interest               = $checkBill->data->registerData->interestValueCalculated;
                    $billPaymentInclude->fines                  = $checkBill->data->registerData->fineValueCalculated;
                    $billPaymentInclude->max_value              = $checkBill->data->registerData->maxValue;
                    $billPaymentInclude->min_value              = $checkBill->data->registerData->minValue;
                    $billPaymentInclude->discount               = $checkBill->data->registerData->discountValue;
                    $billPaymentInclude->allow_update_value     = $checkBill->data->registerData->allowChangeValue ? 1 : 0;
                    $billPaymentInclude->allow_partial_payment  = 0;                    
                }
                $billPaymentInclude->save();

                if(  $billPaymentInclude->payment_value > 250000 ) {
                    return response()->json(array("error" => "Não é possível realizar pagamento de boleto acima de R$ 250.000,00."));
                }
                
                $billPayment = BillPayment::where('id','=',$billPaymentInclude->id)->first();
                return response()->json(array("success" => $billPayment));
            break;
        }
    }

    protected function edit(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'digitable_line_or_bar_code'=> ['required', 'string'],
            'id'=> ['required', 'integer'],
            'payment_value'=> ['required', 'numeric'],
            'schedule_date'=> ['required', 'date'],
        ],[
            'digitable_line_or_bar_code.required' => 'Informe a linha digitável ou código de barras.',
            'id.required' => 'Informe o id de pagamento.',
            'payment_value.required' => 'Informe o valor do pagamento.',
            'schedule_date.required' => 'Informe a data de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $billPayment = BillPayment::where('account_id','=',$checkAccount->account_id)->where('id','=',$request->id)->where('digitable_line_or_bar_code','=',$request->digitable_line_or_bar_code)->whereNull('deleted_at')->first();

        if($billPayment->status_id == 9){
            return response()->json(array("error" => "Não é possível alterar pagamento aprovado"));
        }

        if($billPayment->status_id == 7){
            return response()->json(array("error" => "Não é possível alterar pagamento agendado, cancele o agendamento para alterar"));
        }

        if($request->payment_value <= 0 ) {

            $sendFailureAlert = new MovimentationFailureClass();
            $sendFailureAlert->title = 'TENTATIVA DE GOLPE DE TRANSAÇÃO';
            $sendFailureAlert->errorMessage = 'Atenção, a conta ID: '.$checkAccount->account_id.'<br/><br/>
            Tentou realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($request->payment_value, 2, ',','.').'
            A transação NÃO FOI EFETIVADA e o usuário foi bloqueado.<br/><br/>
            ID de transação '.$request->id.'<br/><br/>';

            if($user = Auth::user()) {
                $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                E-Mail: '.$user->email.'<br/>
                Celular: '.$user->phone;
            }

            $sendFailureAlert->sendFailures();

            return response()->json(array("error" => "Valor inválido"));
        }

        $billPayment->payment_value = $request->payment_value;
        $billPayment->schedule_date = $request->schedule_date;
        $billPayment->description   = $request->description;

        if($billPayment->save()){
            return response()->json(array("success" => "Pagamento alterado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao alterar o pagamento, por favor tente mais tarde"));
        }
    }

    protected function delete(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'digitable_line_or_bar_code'=> ['required', 'string'],
            'id'=> ['required', 'integer'],
        ],[
            'digitable_line_or_bar_code.required' => 'Informe a linha digitável ou código de barras.',
            'id.required' => 'Informe o id de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if($billPayment = BillPayment::where('account_id','=',$checkAccount->account_id)->where('id','=',$request->id)->where('digitable_line_or_bar_code','=',$request->digitable_line_or_bar_code)->whereNull('payment_date')->whereNull('deleted_at')->first()){
            $billPayment->deleted_at  = \Carbon\Carbon::now();
            if($request->description != ''){
                $billPayment->description = $request->description;
            }
            $billPayment->status_id = 10;
            $billPayment->save();
        }

        return response()->json(array("success" => "Pagamento não aprovado com sucesso"));
    }

    protected function sendToken(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'digitable_line_or_bar_code'=> ['required', 'string'],
            'id'=> ['required', 'integer'],
            'password'=> ['required', 'string'],
        ],[
            'digitable_line_or_bar_code.required' => 'Informe a linha digitável ou código de barras.',
            'id.required' => 'Informe o id de pagamento.',
            'password.required' => 'Informe a senha.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();
            if( Hash::check(base64_decode($request->password), $usr->password) ){
                if( ! $billPayment = BillPayment::where('id', '=', $request->id)
                ->where('digitable_line_or_bar_code', '=', $request->digitable_line_or_bar_code)
                ->where('account_id', '=', $checkAccount->account_id)
                ->whereNull('payment_date')
                ->whereNull('deleted_at')
                ->first() ) {
                    return response()->json(array("error" => "Pagamento não localizado ou já realizado, por favor verifique ou tente novamente."));
                }

                $accountMovement             = new AccountMovement();
                $accountMovement->account_id = $billPayment->account_id;
                $accountMovement->master_id  = $checkAccount->master_id;
                $accountMovement->start_date = \Carbon\Carbon::now();
                $accountBalance = 0;
                if(isset( $accountMovement->getAccountBalance()->balance )){
                    $accountBalance = $accountMovement->getAccountBalance()->balance;
                }

                if( $accountBalance < ($billPayment->payment_value + $billPayment->tax_value) ){
                    return response()->json(array("error" => "Saldo insuficiente para realizar o pagamento <br>
                    Saldo disponível: <strong>R$ ".number_format($accountBalance, 2, ',','.')."</strong> <br>
                    Valor do Pagamento: <strong>R$ ".number_format($billPayment->payment_value, 2, ',','.')."</strong> <br>
                    Valor da Tarifa : <strong>R$ ".number_format($billPayment->tax_value, 2, ',','.')."</strong>") );
                }

                if($billPayment != ''){
                    $token = new Facilites();
                    $authorizationToken = AuthorizationToken::create([
                        'token_phone'       => $token->createApprovalToken(),
                        'token_email'       => $token->createApprovalToken(),
                        'type_id'           => 1,
                        'origin_id'         => $billPayment->id,
                        'token_expiration'  => \Carbon\Carbon::now()->addMinutes(5),
                        'token_expired'     => 0,
                        'created_at'        => \Carbon\Carbon::now()
                    ]);
                    $billPayment->approval_token            = $authorizationToken->token_phone;
                    $billPayment->approval_token_expiration = $authorizationToken->token_expiration;
                    $billPayment->token_attempt             = 0;
                    $billPayment->token_send_to_user_id     = $user->id;
                    $billPayment->description               = $request->description;
                    if($billPayment->save()){
                        $sendSMS = SendSms::create([
                            'external_id' => ("1".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$billPayment->id),
                            'to'          => "55".$usr->phone,
                            'message'     => "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para aprovar o pagamento no valor de R$ ".number_format($billPayment->value, 2, ',','.').", para ".mb_substr(str_replace('.',' ',$billPayment->favored_name),0,20),
                            'type_id'     => 1,
                            'origin_id'   => $billPayment->id,
                            'created_at'  => \Carbon\Carbon::now()
                        ]);
                        $apiConfig                     = new ApiConfig();
                        $apiConfig->master_id          = $checkAccount->master_id;
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

                        //Check if should send token by whatsapp
                        if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$request->header('masterId'))->first())->available == 1 ){
                            $apiZenviaWhats            = new ApiZenviaWhatsapp();
                            $apiZenviaWhats->to_number = $sendSMS->to;
                            $apiZenviaWhats->token     = "*".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4)."*";                     
                            if(isset( $apiZenviaWhats->sendToken()->success ) ){
                                return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove o pagamento novamente para gerar outro token"));
                            }
                        }

                        if(isset( $apiZenviaSMS->sendShortSMS()->success ) ){
                            return response()->json(array("success" => "Token enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove o pagamento novamente para gerar outro token"));
                        } else {
                            return response()->json(array("error" => "Não foi possível enviar o token de aprovação, por favor tente novamente"));
                        }
                    } else {
                        return response()->json(array("error" => "Não foi possível gerar o token de aprovação, por favor tente novamente"));
                    }
                } else {
                    return response()->json(array("error" => "Ocorreu um erro, pagamento já realizado ou não localizado"));
                }
            } else {
                return response()->json(array("error" => "Senha Inválida"));
            }
        } else {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
        }
    }

    protected function sendTokenBatch(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'array'],
            'id.*'=> ['required', 'integer'],
            'uuid'=> ['required', 'array'],
            'uuid.*'=> ['required', 'string'],
            'password'=> ['required', 'string'],
        ],[
            'id.required' => 'Informe o id de pagamento.',
            'uuid.required' => 'Informe o uuid de pagamento.',
            'password.required' => 'Informe a senha.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();
            if( Hash::check(base64_decode($request->password), $usr->password) ){
                $getBillPayment              = new BillPayment();
                $getBillPayment->idIn        = $request->id;
                $getBillPayment->uuidIn      = $request->uuid;
                $getBillPayment->account_id  = $checkAccount->account_id;
                $getBillPayment->onlyPending = 1;
                $billPaymentsData            = $getBillPayment->getBillPayment();
                $token = new Facilites();
                $tokenPhone = $token->createApprovalToken();
                $tokenEmail = $token->createApprovalToken();
                $tokeExpiration = \Carbon\Carbon::now()->addMinutes(5);
                $batch_id = Str::orderedUuid();
                $qtd      = 0;
                $value    = 0;
                $taxValue = 0;
                $valueNotSchedule = 0;
                $taxValueNotSchedule = 0;
                foreach($billPaymentsData as $billPaymentData){
                    $authorizationToken = AuthorizationToken::create([
                        'token_phone'       => $tokenPhone,
                        'token_email'       => $tokenEmail,
                        'type_id'           => 1,
                        'origin_id'         => $billPaymentData->id,
                        'token_expiration'  => $tokeExpiration,
                        'token_expired'     => 0,
                        'created_at'        => \Carbon\Carbon::now()
                    ]);

                    $billPayment = BillPayment::where('id', '=', $billPaymentData->id)
                    ->where('uuid', '=', $billPaymentData->uuid)
                    ->where('account_id', '=', $checkAccount->account_id)
                    ->whereNull('payment_date')
                    ->whereNull('deleted_at')
                    ->first();
                    $billPayment->approval_token            = $authorizationToken->token_phone;
                    $billPayment->approval_token_expiration = $authorizationToken->token_expiration;
                    $billPayment->token_attempt             = 0;
                    $billPayment->token_send_to_user_id     = $user->id;
                    $billPayment->batch_id                  = $batch_id;
                    $billPayment->save();
                    $qtd++;
                    $value    += $billPaymentData->payment_value;
                    $taxValue += $billPaymentData->tax_value;

                    // ------------ Add Value Not Schedule Transfer ------------ //
                    if( (\Carbon\Carbon::parse( $billPayment->schedule_date))->format('Y-m-d')  ==  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ){
                        $valueNotSchedule    += $billPayment->payment_value;
                        $taxValueNotSchedule += $billPayment->tax_value;
                    }
                    // --------- Finish Add Value Not Schedule Transfer --------- //

                }

                if( $qtd == 0 ) {
                    return response()->json(array("error" => "Ocorreu um erro, pagamento(s) já realizado(s) ou não localizado(s), por favor verifique."));
                } 

                $accountMovement             = new AccountMovement();
                $accountMovement->account_id = $billPaymentData->account_id;
                $accountMovement->master_id  = $checkAccount->master_id;
                $accountMovement->start_date = \Carbon\Carbon::now();
                $accountBalance = 0;
                if(isset( $accountMovement->getAccountBalance()->balance )){
                    $accountBalance = $accountMovement->getAccountBalance()->balance;
                }

                if( $accountBalance < ($valueNotSchedule + $taxValueNotSchedule) ){
                    return response()->json(array("error" => "Saldo insuficiente para realizar os pagamentos do dia <br>
                    Saldo disponível: <strong>R$ ".number_format($accountBalance, 2, ',','.')."</strong> <br>
                    Valor dos Pagamentos: <strong>R$ ".number_format($valueNotSchedule, 2, ',','.')."</strong> <br>
                    Valor da Tarifa : <strong>R$ ".number_format($taxValueNotSchedule, 2, ',','.')."</strong>") );
                }

                if($qtd > 0){

                    if($qtd > 1){
                        $message = "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para aprovar em lote $qtd pagamento(s) no valor de R$ ".number_format($value, 2, ',','.');
                    } else {
                        $message = "Token ".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4).". Gerado para aprovar o pagamento no valor de R$ ".number_format($value, 2, ',','.').", para ".mb_substr(str_replace('.',' ',$billPaymentData->favored_name),0,20);
                    }

                    $sendSMS = SendSms::create([
                        'external_id' => ("2".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$billPayment->id),
                        'to'          => "55".$usr->phone,
                        'message'     => $message,
                        'type_id'     => 1,
                        'origin_id'   => $billPayment->id,
                        'created_at'  => \Carbon\Carbon::now()
                    ]);
                    $apiConfig                     = new ApiConfig();
                    $apiConfig->master_id          = $checkAccount->master_id;
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

                    //Check if should send token by whatsapp
                    if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$request->header('masterId'))->first())->available == 1 ){
                        $apiZenviaWhats            = new ApiZenviaWhatsapp();
                        $apiZenviaWhats->to_number = $sendSMS->to;
                        $apiZenviaWhats->token     = "*".substr($authorizationToken->token_phone,0,4)."-".substr($authorizationToken->token_phone,4,4)."*";                     
                        if(isset( $apiZenviaWhats->sendToken()->success ) ){
                            return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove o lote de pagamentos novamente para gerar outro token", "batch_id" => $batch_id ));
                        }
                    }

                    if(isset( $apiZenviaSMS->sendShortSMS()->success ) ){
                        return response()->json(array("success" => "Token enviado por SMS, a partir de agora você tem 5 minutos para utilizá-lo, se necessário aprove o lote de pagamentos novamente para gerar outro token", "batch_id" => $batch_id ));
                    } else {
                        return response()->json(array("error" => "Não foi possível enviar o token de aprovação, por favor tente novamente"));
                    }
                } else {
                    return response()->json(array("error" => "Ocorreu um erro, pagamento já realizada ou não localizado"));
                }
            } else {
                return response()->json(array("error" => "Senha Inválida"));
            }
        } else {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login novamente"));
        }
    }

    protected function schedule(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'digitable_line_or_bar_code'=> ['required', 'string'],
            'id'=> ['required', 'integer'],
            'date'=> ['required', 'date'],
        ],[
            'digitable_line_or_bar_code.required' => 'Informe a linha digitável ou código de barras.',
            'id.required' => 'Informe o id de pagamento.',
            'date.required' => 'Informe a data de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if(Account::where('id','=',$checkAccount->account_id)->where('unique_id','=',$request->header('accountUniqueId'))->count() == 0 ){
            return response()->json(array("error" => "Falha de verificação da conta"));
        }


        $getAccountInclusionLimitQtt = Account::where('id','=',$checkAccount->account_id)->whereNull('deleted_at')->first()->inclusion_limit_bill_payment_qtt;
        if($getAccountInclusionLimitQtt == null) {
            $getAccountInclusionLimitQtt = 0;
        }

        $paymentWaitingApprovingQtt = BillPayment::where('account_id', '=', $checkAccount->account_id)->whereNull('payment_date')->whereNull('deleted_at')->where('value', '>', 0)->whereIn('status_id', [5])->count();

        if ($paymentWaitingApprovingQtt >= $getAccountInclusionLimitQtt ) {
            return response()->json(array("error" => "Sua conta possuí $paymentWaitingApprovingQtt pagamentos aguardando aprovação, por favor realize a aprovação para continuar"));
        }

        if( BillPayment::where('id','=',$request->id)->where('digitable_line_or_bar_code','=',$request->digitable_line_or_bar_code)->where('account_id','=',$checkAccount->account_id)->whereNull('deleted_at')->whereNull('payment_date')->count() > 0 ){
            $billPayment = BillPayment::where('id','=',$request->id)->where('digitable_line_or_bar_code','=',$request->digitable_line_or_bar_code)->where('account_id','=',$checkAccount->account_id)->whereNull('deleted_at')->whereNull('payment_date')->first();
                      

            if( (\Carbon\Carbon::parse($request->date))->format('Y-m-d') <  (\Carbon\Carbon::parse( $billPayment->schedule_date))->format('Y-m-d') ){
                return response()->json(array("error" => "Não é possível agendar para uma data menor"));
            }

            if( Holiday::isHoliday( (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d')  ) ){
                return response()->json(array("error" => "Não é possível agendar pagamento para feriados, por favor realize um agendamento para o próximo dia útil"));
            }

            if( ((date("w", strtotime(  (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d') ))) == 0) or ((date("w", strtotime(  (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d')  ))) == 6) ){
                return response()->json(array("error" => "Não é possível agendar pagamento para finais de semana, por favor realize um agendamento para o próximo dia útil"));
            }

            // data de vencimento menor que data atual
            if( (\Carbon\Carbon::parse( $billPayment->due_date))->format('Y-m-d') < (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ){
                
                if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') !=  (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d')) {
                    return response()->json(array("error" => "Utilize a data de hoje para agendar pagamento com vencimento em finais de semana, feriados ou pagamentos vencidos."));
                }

            }

            if ( 
                // data de agendamento maior que hoje
                (   (  (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d') > (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d') )  
                    and 
                    (  (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d') >= (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  )
                )       
            ){
                if(  
                    (  date("w", strtotime(  (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d'))) != 0  )
                    and 
                    (  date("w", strtotime(  (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d'))) != 6  )
                    and
                    ( ! Holiday::isHoliday( (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d') )  )

                ) {
                    return response()->json(array("error" => "Não é possível realizar agendamento com data superior a do vencimento."));
                }
            }

            // limita agendamento para o próximo dia útil caso o vencimento caia em um dia não útil
            if(
                (  date("w", strtotime(  (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d'))) != 0  )
                or 
                (  date("w", strtotime(  (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d'))) != 6  )
                or
                ( ! Holiday::isHoliday( (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d') )  )
            ) {
                $nextBusinessDay = Facilites::getNextBusinessDays(  (\Carbon\Carbon::parse( $billPayment->due_date ))->format('Y-m-d')  );

                if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  > $nextBusinessDay ) {

                    if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') !=  (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d')) {
                        return response()->json(array("error" => "Utilize a data de hoje para agendar pagamento com vencimento em finais de semana, feriados ou pagamentos vencidos."));
                    }

                } else {

                    if( (\Carbon\Carbon::parse( $request->date ))->format('Y-m-d') > $nextBusinessDay ) {
                        return response()->json( array("error" => "Data limite permitida para agendamento deste pagamento que possui vencimento em final de semana ou feriado: ".(\Carbon\Carbon::parse( $nextBusinessDay ))->format('d/m/Y')) );
                    }

                }

            }

            $billPayment->schedule_date = $request->date;
            $billPayment->description   = $request->description;
            $billPayment->status_id     = 5;
            if($billPayment->save()){
                return response()->json(array("success" => "Pagamento agendado com sucesso, realize a aprovação para efetivá-lo"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao agendar o pagamento, por favor tente novamente"));
            }
            
        } else {
            return response()->json(array("error" => "Ocorreu uma falha ao realizar o agendamento, por favor tente novamente mais tarde"));
        }
    }

    protected function pay(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'digitable_line_or_bar_code'=> ['required', 'string'],
            'id'=> ['required', 'integer'],
            'token'=> ['required', 'string', 'size:8'],
        ],[
            'digitable_line_or_bar_code.required' => 'Informe a linha digitável ou código de barras.',
            'id.required' => 'Informe o id de pagamento.',
            'token.required' => 'Informe o token.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if (\Carbon\Carbon::now()->format('Y-m-d') == '2024-12-24') {
            $specialReferenceHour = \Carbon\Carbon::createFromTime(11, 0, 0);
            $specialNowHour = \Carbon\Carbon::now();
            if ($specialNowHour->greaterThanOrEqualTo($specialReferenceHour)) {
                return response()->json(array("error" => "Horário não permite pagamento, por favor realize um agendamento para o próximo dia útil"));
            }
        }

        if( ( \Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') == '2024-12-31' ) {
            return response()->json(array("error" => "Não é possível realizar pagamentos no último dia útil do ano, por favor realize um agendamento para o próximo dia útil"));
        }

        if( Holiday::isHoliday( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ) ){
            return response()->json(array("error" => "Não é possível realizar pagamento em feriados, por favor realize um agendamento para o próximo dia útil"));
        }

        if( ((date("w", strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ))) == 0) or ((date("w", strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ))) == 6) ){
            return response()->json(array("error" => "Não é possível realizar pagamento nos finais de semana, por favor realize um agendamento para o próximo dia útil"));
        }

        if( (\Carbon\Carbon::now())->toTimeString() > '22:59:54' ){
            return response()->json(array("error" => "Horário não permite pagamento, por favor realize um agendamento para o próximo dia útil"));
        }

        if( (\Carbon\Carbon::now())->toTimeString() < '08:00:01' ){
            return response()->json(array("error" => "Horário não permite pagamento, a efetivação de pagamento estará disponível após as 08:00"));
        }

        $billPayment                                            = new BillPayment();
        $billPayment->id                                        = $request->id;
        $billPayment->digitable_line_or_bar_code                = $request->digitable_line_or_bar_code;
        $billPayment->onlyPending                               = 1;
        $billPayment->onlyActive                                = 1;
        $billPayment->account_id                                = $checkAccount->account_id;
        $payData                                                = $billPayment->getBillPayment();

        if( ! isset( $payData[0]  )) {
            return response()->json(array("error" => "Pagamento não localizado ou já realizado, por favor verifique ou tente novamente."));
        }

        $pay = $payData[0];

        if($pay != ''){

            if ($pay->bill_type_id != 1) {
                if( (\Carbon\Carbon::now())->toTimeString() > '19:59:54' ){
                    return response()->json(array("error" => "Horário não permite pagamento, por favor realize um agendamento para o próximo dia útil"));
                }
            }

            $token = BillPayment::where('id','=',$pay->id)->first();

            if($pay->payment_value <= 0) {

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'TENTATIVA DE GOLPE DE TRANSAÇÃO - '.$pay->payment_from_name;
                $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                Tentou realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'. Com tarifa de: '.number_format($pay->tax_value, 2, ',','.').'.<br/><br/>
                A transação NÃO FOI EFETIVADA e o usuário foi bloqueado.<br/><br/>
                ID de transação '.$pay->id.'<br/><br/>';
    
                if($user = Auth::user()) {
                    $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                    E-Mail: '.$user->email.'<br/>
                    Celular: '.$user->phone;
                }
    
                $sendFailureAlert->sendFailures();
                

                return response()->json(array("error" => "Valor do pagamento deve ser maior que 0."));
            }

            if($token->token_attempt >= 3){
                return response()->json(array("error" => "Token informado incorretamente por mais de 3 vezes, por favor reinicie o processo de aprovação."));
            }

            if($token->approval_token == null or $token->approval_token == ''){
                $token->token_attempt += 1;
                $token->save();
    
                if($token->token_attempt >= 3) {
                    $sendFailureAlert = new MovimentationFailureClass();
                    $sendFailureAlert->title = 'Token para pagamento inválido - '.$pay->payment_from_name;
                    $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                    Informou incorretamente o token para confirmar o pagamento de boleto por mais de 3 vezes.<br/><br/>
                    Por esse motivo, não conseguiu realizar o pagamento no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                    ID de transação '.$pay->id.'<br/><br/>';
                    if($user = Auth::user()) {
                        $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                        E-Mail: '.$user->email.'<br/>
                        Celular: '.$user->phone;
                    }
                    $sendFailureAlert->sendFailures();
                }

                return response()->json(array("error" => "Token inválido"));
            }

            if($request->token != $token->approval_token){
                $token->token_attempt += 1;
                $token->save();
    
                if($token->token_attempt >= 3) {
                    $sendFailureAlert = new MovimentationFailureClass();
                    $sendFailureAlert->title = 'Token para pagamento inválido - '.$pay->payment_from_name;
                    $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                    Informou incorretamente o token para confirmar o pagamento de boleto por mais de 3 vezes.<br/><br/>
                    Por esse motivo, não conseguiu realizar o pagamento no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                    ID de transação '.$pay->id.'<br/><br/>';
                    if($user = Auth::user()) {
                        $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                        E-Mail: '.$user->email.'<br/>
                        Celular: '.$user->phone;
                    }
                    $sendFailureAlert->sendFailures();
                }

                return response()->json(array("error" => "Token inválido"));
            }
            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse( $token->approval_token_expiration )->format('Y-m-d H:i:s')) ){
                $authorizationToken = AuthorizationToken::where('origin_id','=',$token->id)->where('type_id','=',1)->where('token_phone','=',$token->approval_token)->first();
                $authorizationToken->token_expired = 1;
                $authorizationToken->save();
                return response()->json(array("error" => "Token inválido, token gerado a mais de 5 minutos, cancele e faça novamente o processo de aprovação do pagamento"));
            }

            if((\Carbon\Carbon::parse($pay->schedule_date))->format('Y-m-d') > (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ){
                $billSchedule                                   = BillPayment::where('id','=',$pay->id)->first();
                $billSchedule->approved_by_user_id              = $checkAccount->user_id;
                $billSchedule->approved_by_user_relationship_id = $checkAccount->user_relationship_id;
                $billSchedule->status_id                        = 7;
                if($billSchedule->save()){
                    return response()->json(array("success" => "Pagamento agendado com sucesso, certifique-se de ter saldo na conta para cobrir o valor agendado na data do agendamento", "payment_id" => $pay->id, "file_name" => null, "bill_data" => null));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao aprovar o agendamento, por favor tente novamente"));
                }
            }

            //check function available
            if(   (SystemFunctionMaster::where('system_function_id','=',5)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
                return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
            }


            //check all out month limit
            $outMovement   = ( (AccountMovement::getMonthOutputOperationsValue($pay->account_id, $checkAccount->master_id))->value * -1);
            $limitMovement = (Account::getLimit($pay->account_id, 13, $checkAccount->master_id))->value;
            $limitAvailable = $limitMovement - $outMovement;
            if($limitAvailable <  $pay->payment_value ){

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'Limite de Movimentação Mensal - '.$pay->payment_from_name;
                $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                Possui <strong>limite de movimentação mensal</strong> definido em: '.number_format($limitMovement, 2, ',','.').'<br/>
                Já utilizou: '.number_format($outMovement, 2, ',','.').'<br/>
                Possuí disponível: '.number_format($limitAvailable, 2, ',','.').'<br/><br/>
                Por esse motivo, não conseguiu realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                ID de transação '.$pay->id.'<br/><br/>';

                if($user = Auth::user()) {
                    $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                    E-Mail: '.$user->email.'<br/>
                    Celular: '.$user->phone;
                }

                $sendFailureAlert->sendFailures();

                return response()->json(array("error" => "Limite mensal insuficiente para realizar o pagamento <br>
                Movimentação no mês: <strong>R$ ".number_format($outMovement, 2, ',','.')."</strong> <br>
                Limite disponível: <strong>R$ ".number_format($limitAvailable, 2, ',','.')."</strong> <br>
                Valor do Pagamento: <strong>R$ ".number_format($pay->payment_value, 2, ',','.')."</strong>") );
            }



            //check all daily month limit
            $outMovement   = ( (AccountMovement::getDailyOutputOperationsValue($pay->account_id, $checkAccount->master_id))->value * -1);
            $limitMovement = (Account::getLimit($pay->account_id, 30, $checkAccount->master_id))->value;
            $limitAvailable = $limitMovement - $outMovement;
            if($limitAvailable <  $pay->payment_value ){

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'Limite de Movimentação Diário - '.$pay->payment_from_name;
                $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                Possui <strong>limite de movimentação diário</strong> definido em: '.number_format($limitMovement, 2, ',','.').'<br/>
                Já utilizou: '.number_format($outMovement, 2, ',','.').'<br/>
                Possuí disponível: '.number_format($limitAvailable, 2, ',','.').'<br/><br/>
                Por esse motivo, não conseguiu realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                ID de transação '.$pay->id.'<br/><br/>';

                if($user = Auth::user()) {
                    $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                    E-Mail: '.$user->email.'<br/>
                    Celular: '.$user->phone;
                }

                $sendFailureAlert->sendFailures();

                return response()->json(array("error" => "Limite diário insuficiente para realizar o pagamento <br>
                Movimentação no dia: <strong>R$ ".number_format($outMovement, 2, ',','.')."</strong> <br>
                Limite disponível: <strong>R$ ".number_format($limitAvailable, 2, ',','.')."</strong> <br>
                Valor do Pagamento: <strong>R$ ".number_format($pay->payment_value, 2, ',','.')."</strong>") );
            }

            $accountMovement             = new AccountMovement();
            $accountMovement->account_id = $pay->account_id;
            $accountMovement->master_id  = $checkAccount->master_id;
            $accountMovement->start_date = \Carbon\Carbon::now();
            $accountBalance              = 0;
            $accountMasterBalance        = 0;
            if(isset( $accountMovement->getAccountBalance()->balance )){
                $accountBalance = $accountMovement->getAccountBalance()->balance;
            }
            if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
                $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
            }

            if( $accountBalance < ($pay->payment_value + $pay->tax_value) ){

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'Saldo - '.$pay->payment_from_name;
                $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                Possui <strong>saldo</strong> de: '.number_format( $accountBalance, 2, ',','.').'<br/>
                Por esse motivo, não conseguiu realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'. Com tarifa de: '.number_format($pay->tax_value, 2, ',','.').'.<br/><br/>
                ID de transação '.$pay->id.'<br/><br/>';

                if($user = Auth::user()) {
                    $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                    E-Mail: '.$user->email.'<br/>
                    Celular: '.$user->phone;
                }

                $sendFailureAlert->sendFailures();


                return response()->json(array("error" => "Saldo insuficiente para realizar o pagamento <br>
                Saldo disponível: <strong>R$ ".number_format($accountBalance, 2, ',','.')."</strong> <br>
                Valor do Pagamento: <strong>R$ ".number_format($pay->payment_value, 2, ',','.')."</strong> <br>
                Valor da Tarifa : <strong>R$ ".number_format($pay->tax_value, 2, ',','.')."</strong>") );
            }

            $movementDescription    = "Pagamento de";
            $movementTaxDescription = "Tarifa de pagamento de";

            switch($pay->bill_type_id){
                case 1:
                    $movementDescription    .= " boleto";
                    $movementTaxDescription .= " boleto";
                break;
                case 2:
                    $movementDescription    .= " conta de consumo";
                    $movementTaxDescription .= " conta de consumo";
                break;
                case 3:
                    $movementDescription    .= " tributo";
                    $movementTaxDescription .= " tributo";
                break;
            }

            $movementDescription    .= " | ".$pay->favored_name;
            $movementTaxDescription .= " | ".$pay->favored_name;

            if($pay->description != ''){
                $movementDescription    .= " | ".$pay->description;
                $movementTaxDescription .= " | ".$pay->description;
            }

            //create account movement
            $movement = new MovementService();
            $movement->movementData = (object) [
                'account_id'           => $pay->account_id,
                'master_id'            => $checkAccount->master_id,
                'origin_id'            => $pay->id,
                'mvmnt_type_id'        => 1,
                'value'                => ($pay->payment_value * -1),
                'description'          =>  mb_substr($movementDescription, 0, 255),
                'user_id'              => $checkAccount->user_id,
                'user_relationship_id' => $checkAccount->user_relationship_id
            ];

            if(!$movement->create()){
                $sendFailureAlert               = new TransactionFailureClass();
                $sendFailureAlert->title        = 'Pagamento de Boleto - Conta de Consumo - Tributo';
                $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor do pagamento na conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.', id transferência: '.$pay->id.', valor: '.$pay->payment_value;
                $sendFailureAlert->sendFailures();
                return response()->json(array("error" => "Poxa, tivemos uma instabilidade no sistema e não foi possível concluir sua solicitação, por favor tente de novo mais tarde."));
            }

            /*if($insertMovement = AccountMovement::create([
                'account_id'       => $pay->account_id,
                'master_id'        => $checkAccount->master_id,
                'origin_id'        => $pay->id,
                'mvmnt_type_id'    => 1,
                'date'             => \Carbon\Carbon::now(),
                'value'            => ($pay->payment_value * -1),
                'balance'          => $accountBalance - $pay->payment_value,
                'master_balance'   => $accountMasterBalance - $pay->payment_value,
                'description'      => mb_substr($movementDescription, 0, 255),
                'created_at'       => \Carbon\Carbon::now(),
            ])){ */
            
            if( $pay->tax_value > 0 ){
                $insertMovementTax = AccountMovement::create([
                    'account_id'       => $pay->account_id,
                    'master_id'        => $checkAccount->master_id,
                    'origin_id'        => $pay->id,
                    'mvmnt_type_id'    => 2,
                    'date'             => \Carbon\Carbon::now(),
                    'value'            => ($pay->tax_value * -1),
                    'balance'          => $accountBalance - ($pay->payment_value + $pay->tax_value),
                    'master_balance'   => $accountMasterBalance - ($pay->payment_value + $pay->tax_value),
                    'description'      => mb_substr($movementTaxDescription, 0, 255),
                    'created_at'       => \Carbon\Carbon::now(),
                ]);

                $master = Master::where('id','=',$checkAccount->master_id)->first();
                if($master->margin_accnt_id != ''){
                    $masterAccountMovement             = new AccountMovement();
                    $masterAccountMovement->account_id = $master->margin_accnt_id;
                    $masterAccountMovement->master_id  = $checkAccount->master_id;
                    $masterAccountMovement->start_date = \Carbon\Carbon::now();
                    $masterAccountBalance              = 0;
                    $masterAccountMasterBalance        = 0;
                    if(isset( $masterAccountMovement->getAccountBalance()->balance )){
                        $masterAccountBalance = $masterAccountMovement->getAccountBalance()->balance;
                    }
                    if(isset( $masterAccountMovement->getMasterAccountBalance()->master_balance )){
                        $masterAccountMasterBalance = $masterAccountMovement->getMasterAccountBalance()->master_balance;
                    }

                    AccountMovement::create([
                        'account_id'       => $master->margin_accnt_id,
                        'accnt_origin_id'  => $pay->account_id,
                        'master_id'        => $checkAccount->master_id,
                        'origin_id'        => $pay->id,
                        'mvmnt_type_id'    => 2,
                        'date'             => \Carbon\Carbon::now(),
                        'value'            => $pay->tax_value,
                        'balance'          => $masterAccountBalance  + $pay->tax_value,
                        'master_balance'   => $masterAccountMasterBalance + $pay->tax_value,
                        'description'      => mb_substr($movementTaxDescription, 0, 255),
                        'created_at'       => \Carbon\Carbon::now(),
                    ]);
                }

            }

            $billPay = BillPayment::where('id','=', $pay->id)->first();
            $billPay->transaction_id                       = Str::orderedUuid();
            $billPay->status_id                            = 37;
            $billPay->bnk_trnsctn_stt                      = 38;
            $billPay->payment_date                         = \Carbon\Carbon::now();
            $billPay->approved_by_user_id                  = $checkAccount->user_id;
            $billPay->approved_by_user_relationship_id     = $checkAccount->user_relationship_id;
            $billPay->save();
            $file_name = 'Pagamento_'.$billPay->digitable_line_or_bar_code.'_'.$billPay->id.'.pdf';

            $payReturn = null;

            switch($pay->bill_type_id){
                case 3:
                    if( $pay->api_id == 10 ) {
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
                        $apiRendimento->pag_valor_pagamento                 = number_format($pay->payment_value, 2, '.','');
                        $apiRendimento->pag_valor_titulo                    = number_format($pay->value, 2, '.','');
                        $apiRendimento->pag_descricao_extrato               = "P".$pay->id;
                        
                        $apiRendimento->pag_codigo_barra_ou_linha_digitavel = $pay->digitable_line_or_bar_code;
                        
                        // Convenio FGTS
                        if( $pay->favored_cpf_cnpj == 179 or $pay->favored_cpf_cnpj == 180 or $pay->favored_cpf_cnpj == 239 ) {
                            $rendimentoBillPayment = $apiRendimento->pagarFGTS();

                            if( isset( $rendimentoBillPayment->body->success ) ){
                                if( $rendimentoBillPayment->body->success ) {
                                    if( isset($rendimentoBillPayment->body->data->transacaoId) ) {
                                        $payReturn = (object) ["body" => (object) ["value" => $rendimentoBillPayment->body->data->transacaoId]];
                                    }
                                }
                            }                                
                        }


                    } else {

                        $apiConfig                          = new ApiConfig();
                        $apiConfig->master_id               = $checkAccount->master_id;
                        $apiConfig->api_id                  = 8;
                        $apiConfig->onlyActive              = 1;
                        $apiData                            = $apiConfig->getApiConfig()[0];
        
                        $apiCelCoin                         = new ApiCelCoin();
                        $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                        $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                        $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                        $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                        $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                        $apiCelCoin->payer_id               = $pay->payment_from_cpf_cnpj;
                        $apiCelCoin->type                   = 1;
                        $apiCelCoin->digitable              = $pay->digitable_line;
                        $apiCelCoin->barCode                = '';
                        $apiCelCoin->externalNSU            = $pay->id;
                        $apiCelCoin->externalTerminal       = $pay->account_id;
                        $apiCelCoin->value                  = $pay->total_value;
                        $apiCelCoin->originalValue          = 0;
                        $apiCelCoin->valueWithDiscount      = 0;
                        $apiCelCoin->valueWithAdditional    = 0;
                        $apiCelCoin->dueDate                = $pay->due_date;
                        $apiCelCoin->transactionIdAuthorize = $pay->authorize_id;

                        $celcoinBillPaymnent = $apiCelCoin->billPayment();

                        if( $celcoinBillPaymnent->success ){
                            $billPayment = BillPayment::where('id', '=', $pay->id)->first();
                            $billPayment->bank_transaction_id = $celcoinBillPaymnent->data->transactionId;
                            $billPayment->save();

                            $apiCelCoin->transactionId = $billPayment->bank_transaction_id;

                            sleep( mt_rand(1, 6) ); // important wait

                            $celcoinConfirmBillPaymnent = $apiCelCoin->billPaymentConfirm();

                            if( $celcoinConfirmBillPaymnent->success ){
                                $payReturn = (object) ["body" => (object) ["value" => $billPayment->bank_transaction_id]];
                            }
                        }
                    }

                break;
                case 2:
                    $apiConfig                          = new ApiConfig();
                    $apiConfig->master_id               = $checkAccount->master_id;
                    $apiConfig->api_id                  = 8;
                    $apiConfig->onlyActive              = 1;
                    $apiData                            = $apiConfig->getApiConfig()[0];
    
                    $apiCelCoin                         = new ApiCelCoin();
                    $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                    $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                    $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                    $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                    $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                    $apiCelCoin->payer_id               = $pay->payment_from_cpf_cnpj;
                    $apiCelCoin->type                   = 1;
                    $apiCelCoin->digitable              = $pay->digitable_line;
                    $apiCelCoin->barCode                = '';
                    $apiCelCoin->externalNSU            = $pay->id;
                    $apiCelCoin->externalTerminal       = $pay->account_id;
                    $apiCelCoin->value                  = $pay->total_value;
                    $apiCelCoin->originalValue          = 0;
                    $apiCelCoin->valueWithDiscount      = 0;
                    $apiCelCoin->valueWithAdditional    = 0;
                    $apiCelCoin->dueDate                = $pay->due_date;
                    $apiCelCoin->transactionIdAuthorize = $pay->authorize_id;

                    $celcoinBillPaymnent = $apiCelCoin->billPayment();

                    if( $celcoinBillPaymnent->success ){
                        $billPayment = BillPayment::where('id', '=', $pay->id)->first();
                        $billPayment->bank_transaction_id = $celcoinBillPaymnent->data->transactionId;
                        $billPayment->save();

                        $apiCelCoin->transactionId = $billPayment->bank_transaction_id;

                        sleep( mt_rand(1, 6) ); // important wait

                        $celcoinConfirmBillPaymnent = $apiCelCoin->billPaymentConfirm();

                        if( $celcoinConfirmBillPaymnent->success ){
                            $payReturn = (object) ["body" => (object) ["value" => $billPayment->bank_transaction_id]];
                        }
                    }
                    

                break;
                case 1:
                    
                    $sendTo = 'celcoin';

                    if( $sendTo == 'celcoin' ) {
                        //Para Celcoin
                        $apiConfig                          = new ApiConfig();
                        $apiConfig->master_id               = $checkAccount->master_id;
                        $apiConfig->api_id                  = 8;
                        $apiConfig->onlyActive              = 1;
                        $apiData                            = $apiConfig->getApiConfig()[0];
                        
                        if( \Carbon\Carbon::parse($pay->created_at)->format('Y-m-d') < (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ) {
                            // Consultar boleto novamente
                            $apiCelCoin                         = new ApiCelCoin();
                            $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                            $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                            $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                            $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                            $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                            $apiCelCoin->type                   = 2;
                            $apiCelCoin->digitable              = $pay->digitable_line;
                            $apiCelCoin->barCode                = '';
                            $apiCelCoin->externalNSU            = $pay->id;
                            $apiCelCoin->externalTerminal       = $pay->account_id;

                            $checkBill = null;
                            $checkBill = $apiCelCoin->billPaymentBilletData();

                            if( $checkBill->success ){
                                if(  isset( $checkBill->data->transactionId ) /*and isset( $checkBill->data->registerData )*/ ){
                                    $updateBillPayment = BillPayment::where('id', '=', $pay->id)->first();
                                    $updateBillPayment->authorize_id = $checkBill->data->transactionId;
                                    $pay->authorize_id = $checkBill->data->transactionId;
                                    $updateBillPayment->save();
                                }
                            }
                        }

                        $apiCelCoin                         = new ApiCelCoin();
                        $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                        $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                        $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                        $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                        $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                        $apiCelCoin->payer_id               = $pay->payment_from_cpf_cnpj;
                        $apiCelCoin->type                   = 2;
                        $apiCelCoin->digitable              = $pay->digitable_line;
                        $apiCelCoin->barCode                = '';
                        $apiCelCoin->externalNSU            = $pay->id;
                        $apiCelCoin->externalTerminal       = $pay->account_id;
                        $apiCelCoin->value                  = $pay->total_value;
                        $apiCelCoin->originalValue          = $pay->value;
                        $apiCelCoin->valueWithDiscount      = $pay->discount;
                        $apiCelCoin->valueWithAdditional    = $pay->interest + $pay->fines;
                        $apiCelCoin->dueDate                = $pay->due_date;
                        $apiCelCoin->transactionIdAuthorize = $pay->authorize_id;

                        $celcoinBillPaymnent = $apiCelCoin->billPayment();

                        if( $celcoinBillPaymnent->success ){
                            $billPayment = BillPayment::where('id', '=', $pay->id)->first();
                            $billPayment->bank_transaction_id = $celcoinBillPaymnent->data->transactionId;
                            $billPayment->save();

                            $apiCelCoin->transactionId = $billPayment->bank_transaction_id;

                            sleep( mt_rand(1, 6) ); // important wait

                            $celcoinConfirmBillPaymnent = $apiCelCoin->billPaymentConfirm();

                            if( $celcoinConfirmBillPaymnent->success ){
                                $payReturn = (object) ["body" => (object) ["value" => $billPayment->bank_transaction_id]];
                            }
                        }
                    }

                    if ( $sendTo == 'rendimento' ) {
                        //Para Banco Rendimento
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
                        $apiRendimento->pag_valor_pagamento                 = number_format($pay->payment_value, 2, '.','');
                        $apiRendimento->pag_valor_titulo                    = number_format($pay->value, 2, '.','');
                        $apiRendimento->pag_descricao_extrato               = "P".$pay->id;
                        $apiRendimento->pag_codigo_controle                 = $pay->account_id.'-'.$pay->id;
                        $apiRendimento->pag_codigo_barra_ou_linha_digitavel = $pay->digitable_line_or_bar_code;
                        $apiRendimento->razao_social_pagador                = $pay->payment_from_name;
                        $apiRendimento->cpf_cnpj_pagador                    = $pay->payment_from_cpf_cnpj;

                        $payReturn = null;
                        
                        $rendimentoBillPayment = $apiRendimento->pagarTitulo();

                        if( isset( $rendimentoBillPayment->body->success ) ){
                            if( $rendimentoBillPayment->body->success ) {
                                if( isset($rendimentoBillPayment->body->data->transacaoId) ) {
                                    $payReturn = (object) ["body" => (object) ["value" => $rendimentoBillPayment->body->data->transacaoId]];
                                }
                            }
                        }
                    }
                        
                break;
                default:
                    $payReturn = null;
                break;

            }

            if(isset($payReturn->body->value) ){
                $billPay = BillPayment::where('id','=', $pay->id)->first();
                $billPay->transaction_id                       = $payReturn->body->value;
                $billPay->bank_transaction_id                  = $payReturn->body->value;
                $billPay->status_id                            = 37;
                $billPay->bnk_trnsctn_stt                      = null;
                $billPay->payment_date                         = \Carbon\Carbon::now();
                $billPay->approved_by_user_id                  = $checkAccount->user_id;
                $billPay->approved_by_user_relationship_id     = $checkAccount->user_relationship_id;
                $billPay->save();
                $file_name = 'Pagamento_'.$billPay->digitable_line_or_bar_code.'_'.$billPay->id.'.pdf';
            } else {
                // send mail on payment failure
                $sendFailureAlert               = new TransactionFailureClass();
                $sendFailureAlert->title        = 'PAGAMENTO NÃO EFETIVADO';
                $sendFailureAlert->errorMessage = 'Atenção, acompanhe o extrato, pode ser que o banco não efetivou o pagamento de boleto, conta de consumo ou tributo solicitado pela conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.', id pagamento: '.$pay->id.', valor: '.$pay->value.'.';
                $sendFailureAlert->sendFailures();
            }

            return response()->json(array("success" => "Pagamento realizado com sucesso", "payment_id" => $billPay->id, "file_name" => $file_name, "bill_data" => $billPayment->getTransferPaymentReceipt()));

            
            //} else {
            //    return response()->json(array("error" => "Ocorreu uma falha ao registrar o pagamento, por favor tente novamente mais tarde."));
            //}
        } else {
            return response()->json(array("error" => "Ocorreu uma falha ao realizar o pagamento, por favor tente novamente mais tarde."));
        }
    }

    protected function payBatch(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'batch_id'=> ['required', 'string'],
            'id'=> ['required', 'array'],
            'id.*'=> ['required', 'integer'],
            'uuid'=> ['required', 'array'],
            'uuid.*'=> ['required', 'string'],
            'token'=> ['required', 'string', 'size:8'],
        ],[
            'batch_id.required' => 'Informe o lote de pagamento.',
            'id.required' => 'Informe o id de pagamento.',
            'uuid.required' => 'Informe o uuid de pagamento.',
            'token.required' => 'Informe o token.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if( count($request->id) > 1 ) {
            return response()->json(array("error" => "Não é possível enviar mais de um pagamento na mesma requisição"));
        }

        $billPayment                             = new BillPayment();
        $billPayment->idIn                       = $request->id;
        $billPayment->uuidIn                     = $request->uuid;
        $billPayment->token                      = $request->token;
        $billPayment->batch_id                   = $request->batch_id;
        $billPayment->account_id                 = $checkAccount->account_id;
        $billPayment->onlyPending                = 1;
        $billPayment->onlyActive                 = 1;
        $payments                                = $billPayment->getBillPayment();
        $tokenVerification                       = false;

        foreach($payments as $pay){

            //--- Check Token ---
            if($tokenVerification == false){
                $token = BillPayment::where('id','=',$pay->id)->where('uuid', '=', $pay->uuid)->where('batch_id', '=', $request->batch_id)->first();

                if($token->token_attempt >= 3) {
                    return response()->json(array("error" => "Token informado incorretamente por mais de 3 vezes, por favor reinicie o processo de aprovação.", "data" => ["invalid_token" => true]));
                }

                if($token->approval_token == null or $token->approval_token == ''){

                    BillPayment::where('account_id','=',$checkAccount->account_id)
                    //->whereIn('id', $request->id)
                    ->where('batch_id', '=', $request->batch_id)
                    ->whereNull('payment_date')
                    ->whereIn('status_id',[5,6,38])
                    ->update(['token_attempt' => ($token->token_attempt + 1)]);

                    $token->token_attempt += 1;
                    
                    if($token->token_attempt >= 3) {
                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Token para pagamento inválido - '.$pay->payment_from_name;
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                        Informou incorretamente o token para confirmar o pagamento de boleto por mais de 3 vezes.<br/><br/>
                        Por esse motivo, não conseguiu realizar o pagamento no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                        ID de transação '.$pay->id.'<br/><br/>';
                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }
                        $sendFailureAlert->sendFailures();
                    }
    
                    return response()->json(array("error" => "Token inválido", "data" => ["invalid_token" => true], "bill_data" => $pay));
                }

                if($request->token != $token->approval_token){

                    BillPayment::where('account_id','=',$checkAccount->account_id)
                    //->whereIn('id', $request->id)
                    ->where('batch_id', '=', $request->batch_id)
                    ->whereNull('payment_date')
                    ->whereIn('status_id',[5,6,38])
                    ->update(['token_attempt' => ($token->token_attempt + 1)]);

                    $token->token_attempt += 1;
        
                    if($token->token_attempt >= 3) {
                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Token para pagamento inválido - '.$pay->payment_from_name;
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                        Informou incorretamente o token para confirmar o pagamento de boleto por mais de 3 vezes.<br/><br/>
                        Por esse motivo, não conseguiu realizar o pagamento no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                        ID de transação '.$pay->id.'<br/><br/>';
                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }
                        $sendFailureAlert->sendFailures();
                    }
    
                    return response()->json(array("error" => "Token inválido", "data" => ["invalid_token" => true], "bill_data" => $pay));
                }

                if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse( $token->approval_token_expiration )->format('Y-m-d H:i:s')) ){
                    if( $authorizationToken = AuthorizationToken::where('origin_id','=',$token->id)->where('type_id','=',1)->where('token_phone','=',$token->approval_token)->first() ) {
                        $authorizationToken->token_expired = 1;
                        $authorizationToken->save();
                    }
                    return response()->json(array("error" => "Token inválido, token gerado a mais de 5 minutos, cancele e faça novamente o processo de aprovação do pagamento", "data" => ["invalid_token" => true], "bill_data" => $pay));
                }

                $tokenVerification = true;
            }

            //--- Finish Check Token ---

            if($pay->payment_value <= 0) {

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'TENTATIVA DE GOLPE DE TRANSAÇÃO - '.$pay->payment_from_name;
                $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                Tentou realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'. Com tarifa de: '.number_format($pay->tax_value, 2, ',','.').'.<br/><br/>
                A transação NÃO FOI EFETIVADA e o usuário foi bloqueado.<br/><br/>
                ID de transação '.$pay->id.'<br/><br/>';
    
                if($user = Auth::user()) {
                    $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                    E-Mail: '.$user->email.'<br/>
                    Celular: '.$user->phone;
                }
    
                $sendFailureAlert->sendFailures();

                return response()->json(array("error" => "Ocorreu uma falha ao aprovar o pagamento para ".$pay->favored_name.", no valor de ".number_format($pay->payment_value, 2, '.','').", o valor do pagamento deve ser maior que 0.", "data" => ["invalid_token" => false], "bill_data" => $pay));
            }

            //Schedule Payments or Pay
            if((\Carbon\Carbon::parse($pay->schedule_date))->format('Y-m-d') > (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ){
                $billSchedule                                   = BillPayment::where('id','=',$pay->id)->first();
                $billSchedule->status_id                        = 7;
                $billSchedule->approved_by_user_id              = $checkAccount->user_id;
                $billSchedule->approved_by_user_relationship_id = $checkAccount->user_relationship_id;

                if($billSchedule->save()){
                    $billPayment = new BillPayment;
                    $billPayment->id = $pay->id;
                    $billPayment->uuid = $pay->uuid;
                    return response()->json(array("success" => "O agendamento para $pay->favored_name, no valor de ".number_format($pay->value, 2, '.','').", foi realizado com sucesso", "bill_data" => $billPayment->getTransferPaymentReceipt()));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao aprovar o agendamento para $pay->favored_name, no valor de ".number_format($pay->value, 2, '.','').", por favor tente novamente mais tarde", "data" => ["invalid_token" => false], "bill_data" => $pay));
                }
            } else {

                if (\Carbon\Carbon::now()->format('Y-m-d') == '2024-12-24') {
                    $specialReferenceHour = \Carbon\Carbon::createFromTime(11, 0, 0);
                    $specialNowHour = \Carbon\Carbon::now();
                    if ($specialNowHour->greaterThanOrEqualTo($specialReferenceHour)) {
                        return response()->json(array("error" => "Horário não permite pagamento, por favor realize um agendamento para o próximo dia útil", "data" => ["invalid_token" => false], "bill_data" => $pay));
                    }
                }

                if( ( \Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') == '2024-12-31' ) {
                    return response()->json(array("error" => "Não é possível realizar pagamento no último dia útil do ano, por favor realize um agendamento para o próximo dia útil", "data" => ["invalid_token" => false], "bill_data" => $pay));
                }
        
                if( Holiday::isHoliday( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ) ){
                    return response()->json(array("error" => "Não é possível realizar pagamento em feriados, por favor realize um agendamento para o próximo dia útil", "data" => ["invalid_token" => false], "bill_data" => $pay));
                }
        
                if( ((date("w", strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ))) == 0) or ((date("w", strtotime(  (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d')  ))) == 6) ){
                    return response()->json(array("error" => "Não é possível realizar pagamento nos finais de semana, por favor realize um agendamento para o próximo dia útil", "data" => ["invalid_token" => false], "bill_data" => $pay));
                }
        
                if( (\Carbon\Carbon::now())->toTimeString() > '22:59:54' ){
                    return response()->json(array("error" => "Horário não permite pagamento, por favor realize um agendamento para o próximo dia útil", "data" => ["invalid_token" => false], "bill_data" => $pay));
                }
        
                if( (\Carbon\Carbon::now())->toTimeString() < '08:00:01' ){
                    return response()->json(array("error" => "Horário não permite pagamento, a efetivação de pagamento estará disponível após as 08:00", "data" => ["invalid_token" => false], "bill_data" => $pay));
                }
                
                if( (SystemFunctionMaster::where('system_function_id','=',5)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
                    return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, não foi possível realizar o pagamento para $pay->favored_name, no valor de ".number_format($pay->payment_value, 2, '.','').", por favor tente novamente mais tarde", "data" => ["invalid_token" => false], "bill_data" => $pay));
                } else {

                    if ($pay->bill_type_id != 1) {
                        if( (\Carbon\Carbon::now())->toTimeString() > '19:59:54' ){
                            return response()->json(array("error" => "Não foi possível realizar o pagamento para $pay->favored_name, no valor de ".number_format($pay->payment_value, 2, '.','').", horário não permite pagamento, por favor realize um agendamento para o próximo dia útil", "data" => ["invalid_token" => false], "bill_data" => $pay));
                        }
                    }

                    sleep( mt_rand(1, 3) );

                    //Check all out month limit
                    $outMovement   = ( (AccountMovement::getMonthOutputOperationsValue($pay->account_id,$checkAccount->master_id))->value * -1);
                    $limitMovement = (Account::getLimit($pay->account_id, 13, $checkAccount->master_id))->value;
                    $limitAvailable = $limitMovement - $outMovement;
                    if($limitAvailable < $pay->payment_value ){

                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Limite de Movimentação Mensal - '.$pay->payment_from_name;
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                        Possui <strong>limite de movimentação mensal</strong> definido em: '.number_format($limitMovement, 2, ',','.').'<br/>
                        Já utilizou: '.number_format($outMovement, 2, ',','.').'<br/>
                        Possuí disponível: '.number_format($limitAvailable, 2, ',','.').'<br/><br/>
                        Por esse motivo, não conseguiu realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                        ID de transação '.$pay->id.'<br/><br/>';

                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }

                        $sendFailureAlert->sendFailures();

                        return response()->json(array("error" => "Limite mensal insuficiente para realizar o pagamento <br>
                        Movimentação no mês: <strong>R$ ".number_format($outMovement, 2, ',','.')."</strong> <br>
                        Limite disponível: <strong>R$ ".number_format($limitAvailable, 2, ',','.')."</strong> <br>
                        Valor do Pagamento Atual: <strong>R$ ".number_format($pay->payment_value, 2, ',','.')."</strong>", "data" => ["invalid_token" => false], "bill_data" => $pay) );
                    }


                    //Check all out daily limit
                    $outMovement   = ( (AccountMovement::getDailyOutputOperationsValue($pay->account_id,$checkAccount->master_id))->value * -1);
                    $limitMovement = (Account::getLimit($pay->account_id, 30, $checkAccount->master_id))->value;
                    $limitAvailable = $limitMovement - $outMovement;
                    if($limitAvailable < $pay->payment_value ){

                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Limite de Movimentação Diário - '.$pay->payment_from_name;
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                        Possui <strong>limite de movimentação diário</strong> definido em: '.number_format($limitMovement, 2, ',','.').'<br/>
                        Já utilizou: '.number_format($outMovement, 2, ',','.').'<br/>
                        Possuí disponível: '.number_format($limitAvailable, 2, ',','.').'<br/><br/>
                        Por esse motivo, não conseguiu realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'<br/><br/>
                        ID de transação '.$pay->id.'<br/><br/>';

                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }

                        $sendFailureAlert->sendFailures();

                        return response()->json(array("error" => "Limite diário insuficiente para realizar o pagamento <br>
                        Movimentação no dia: <strong>R$ ".number_format($outMovement, 2, ',','.')."</strong> <br>
                        Limite disponível: <strong>R$ ".number_format($limitAvailable, 2, ',','.')."</strong> <br>
                        Valor do Pagamento Atual: <strong>R$ ".number_format($pay->payment_value, 2, ',','.')."</strong>", "data" => ["invalid_token" => false], "bill_data" => $pay) );
                    }

                    $accountMovement             = new AccountMovement();
                    $accountMovement->account_id = $pay->account_id;
                    $accountMovement->master_id  = $checkAccount->master_id;
                    $accountMovement->start_date = \Carbon\Carbon::now();
                    $accountBalance              = 0;
                    $accountMasterBalance        = 0;
                    if(isset( $accountMovement->getAccountBalance()->balance )){
                        $accountBalance = $accountMovement->getAccountBalance()->balance;
                    }
                    if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
                        $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
                    }
                    if( $accountBalance < ($pay->payment_value + $pay->tax_value) ){

                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Saldo - '.$pay->payment_from_name;
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.'<br/><br/>
                        Possui <strong>saldo</strong> de: '.number_format( $accountBalance, 2, ',','.').'<br/>
                        Por esse motivo, não conseguiu realizar o pagamento do boleto / conta de consumo / tributo no valor de: '.number_format($pay->payment_value, 2, ',','.').'. Com tarifa de: '.number_format($pay->tax_value, 2, ',','.').'.<br/><br/>
                        ID de transação '.$pay->id.'<br/><br/>';

                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }

                        $sendFailureAlert->sendFailures();

                        return response()->json(array("error" => "Saldo insuficiente para realizar o pagamento <br>
                        Saldo disponível: <strong>R$ ".number_format($accountBalance, 2, ',','.')."</strong> <br>
                        Valor do Pagamento Atual: <strong>R$ ".number_format($pay->payment_value, 2, ',','.')."</strong> <br>
                        Valor da Tarifa : <strong>R$ ".number_format($pay->tax_value, 2, ',','.')."</strong>", "data" => ["invalid_token" => false], "bill_data" => $pay) );
                    }

                    //check function available
                    if(   (SystemFunctionMaster::where('system_function_id','=',5)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
                        return response()->json(array("error" => "Poxa, temos uma instabilidade com a rede de Bancos Correspondentes, e não foi possível realizar o pagamento para $pay->favored_name, no valor de ".number_format($pay->payment_value, 2, '.','').", por favor tente novamente mais tarde", "data" => ["invalid_token" => false], "bill_data" => $pay));
                    }

                    $movementDescription    = "Pagamento de";
                    $movementTaxDescription = "Tarifa de pagamento de";
                    switch($pay->bill_type_id){
                        case 1:
                            $movementDescription    .= " boleto";
                            $movementTaxDescription .= " boleto";
                        break;
                        case 2:
                            $movementDescription    .= " conta de consumo";
                            $movementTaxDescription .= " conta de consumo";
                        break;
                        case 3:
                            $movementDescription    .= " tributo";
                            $movementTaxDescription .= " tributo";
                        break;
                    }

                    $movementDescription    .= " | ".$pay->favored_name;
                    $movementTaxDescription .= " | ".$pay->favored_name;

                    if($pay->description != ''){
                        $movementDescription    .= " | ".$pay->description;
                        $movementTaxDescription .= " | ".$pay->description;
                    }

                    //create account movement
                    $movement = new MovementService();
                    $movement->movementData = (object) [
                        'account_id'           => $pay->account_id,
                        'master_id'            => $checkAccount->master_id,
                        'origin_id'            => $pay->id,
                        'mvmnt_type_id'        => 1,
                        'value'                => ($pay->payment_value * -1),
                        'description'          =>  mb_substr($movementDescription, 0, 255),
                        'user_id'              => $checkAccount->user_id,
                        'user_relationship_id' => $checkAccount->user_relationship_id
                    ];

                    if(!$movement->create()){
                        $sendFailureAlert               = new TransactionFailureClass();
                        $sendFailureAlert->title        = 'Pagamento de Boleto - Conta de Consumo - Tributo';
                        $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor do pagamento na conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.', id transferência: '.$pay->id.', valor: '.$pay->payment_value;
                        $sendFailureAlert->sendFailures();
                        return response()->json(array("error" => "Poxa, tivemos uma instabilidade no sistema e não foi possível concluir sua solicitação, por favor tente de novo mais tarde.", "data" => ["invalid_token" => false], "bill_data" => $pay));
                    }

                    if( $pay->tax_value > 0 ){
                        $insertMovementTax = AccountMovement::create([
                            'account_id'       => $pay->account_id,
                            'master_id'        => $checkAccount->master_id,
                            'origin_id'        => $pay->id,
                            'mvmnt_type_id'    => 2,
                            'date'             => \Carbon\Carbon::now(),
                            'value'            => ($pay->tax_value * -1),
                            'balance'          => $accountBalance - ($pay->payment_value + $pay->tax_value),
                            'master_balance'   => $accountMasterBalance - ($pay->payment_value + $pay->tax_value),
                            'description'      => mb_substr($movementTaxDescription, 0, 255),
                            'created_at'       => \Carbon\Carbon::now(),
                        ]);

                        $master = Master::where('id','=',$checkAccount->master_id)->first();
                        if($master->margin_accnt_id != ''){
                            $masterAccountMovement             = new AccountMovement();
                            $masterAccountMovement->account_id = $master->margin_accnt_id;
                            $masterAccountMovement->master_id  = $checkAccount->master_id;
                            $masterAccountMovement->start_date = \Carbon\Carbon::now();
                            $masterAccountBalance              = 0;
                            $masterAccountMasterBalance        = 0;
                            if(isset( $masterAccountMovement->getAccountBalance()->balance )){
                                $masterAccountBalance = $masterAccountMovement->getAccountBalance()->balance;
                            }
                            if(isset( $masterAccountMovement->getMasterAccountBalance()->master_balance )){
                                $masterAccountMasterBalance = $masterAccountMovement->getMasterAccountBalance()->master_balance;
                            }

                            AccountMovement::create([
                                'account_id'       => $master->margin_accnt_id,
                                'accnt_origin_id'  => $pay->account_id,
                                'master_id'        => $checkAccount->master_id,
                                'origin_id'        => $pay->id,
                                'mvmnt_type_id'    => 2,
                                'date'             => \Carbon\Carbon::now(),
                                'value'            => $pay->tax_value,
                                'balance'          => $masterAccountBalance  + $pay->tax_value,
                                'master_balance'   => $masterAccountMasterBalance + $pay->tax_value,
                                'description'      => mb_substr($movementTaxDescription, 0, 255),
                                'created_at'       => \Carbon\Carbon::now(),
                            ]);
                        }

                    }

                    $billPay = BillPayment::where('id','=', $pay->id)->first();
                    $billPay->transaction_id                    = Str::orderedUuid();
                    $billPay->status_id                         = 37;
                    $billPay->bnk_trnsctn_stt                   = 38;
                    $billPay->payment_date                      = \Carbon\Carbon::now();
                    $billPay->approved_by_user_id               = $checkAccount->user_id;
                    $billPay->approved_by_user_relationship_id  = $checkAccount->user_relationship_id;
                    $billPay->save();

                    $payReturn = null;

                    switch($pay->bill_type_id){
                        case 3:

                            if( $pay->api_id == 10 ) {
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
                                $apiRendimento->pag_valor_pagamento                 = number_format($pay->payment_value, 2, '.','');
                                $apiRendimento->pag_valor_titulo                    = number_format($pay->value, 2, '.','');
                                $apiRendimento->pag_descricao_extrato               = "P".$pay->id;
                                
                                $apiRendimento->pag_codigo_barra_ou_linha_digitavel = $pay->digitable_line_or_bar_code;
                                
                                // Convenio FGTS
                                if( $pay->favored_cpf_cnpj == 179 or $pay->favored_cpf_cnpj == 180 or $pay->favored_cpf_cnpj == 239  ) {
                                    $rendimentoBillPayment = $apiRendimento->pagarFGTS();

                                    if( isset( $rendimentoBillPayment->body->success ) ){
                                        if( $rendimentoBillPayment->body->success ) {
                                            if( isset($rendimentoBillPayment->body->data->transacaoId) ) {
                                                $payReturn = (object) ["body" => (object) ["value" => $rendimentoBillPayment->body->data->transacaoId]];
                                            }
                                        }
                                    }                                
                                }
    
    
                            } else {

                                $apiConfig                          = new ApiConfig();
                                $apiConfig->master_id               = $checkAccount->master_id;
                                $apiConfig->api_id                  = 8;
                                $apiConfig->onlyActive              = 1;
                                $apiData                            = $apiConfig->getApiConfig()[0];
                
                                $apiCelCoin                         = new ApiCelCoin();
                                $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                                $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                                $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                                $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                                $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                                $apiCelCoin->payer_id               = $pay->payment_from_cpf_cnpj;
                                $apiCelCoin->type                   = 1;
                                $apiCelCoin->digitable              = $pay->digitable_line;
                                $apiCelCoin->barCode                = '';
                                $apiCelCoin->externalNSU            = $pay->id;
                                $apiCelCoin->externalTerminal       = $pay->account_id;
                                $apiCelCoin->value                  = $pay->total_value;
                                $apiCelCoin->originalValue          = 0;
                                $apiCelCoin->valueWithDiscount      = 0;
                                $apiCelCoin->valueWithAdditional    = 0;
                                $apiCelCoin->dueDate                = $pay->due_date;
                                $apiCelCoin->transactionIdAuthorize = $pay->authorize_id;
        
                                $celcoinBillPaymnent = $apiCelCoin->billPayment();
        
                                if( $celcoinBillPaymnent->success ){
                                    $billPayment = BillPayment::where('id', '=', $pay->id)->first();
                                    $billPayment->bank_transaction_id = $celcoinBillPaymnent->data->transactionId;
                                    $billPayment->save();
            
                                    $apiCelCoin->transactionId = $billPayment->bank_transaction_id;

                                    sleep( mt_rand(1, 6) ); // important wait
            
                                    $celcoinConfirmBillPaymnent = $apiCelCoin->billPaymentConfirm();

                                    if( $celcoinConfirmBillPaymnent->success ){
                                        $payReturn = (object) ["body" => (object) ["value" => $billPayment->bank_transaction_id]];
                                    }                                       
                                }
                            }

                        break;
                        case 2:
                            $apiConfig                          = new ApiConfig();
                            $apiConfig->master_id               = $checkAccount->master_id;
                            $apiConfig->api_id                  = 8;
                            $apiConfig->onlyActive              = 1;
                            $apiData                            = $apiConfig->getApiConfig()[0];
            
                            $apiCelCoin                         = new ApiCelCoin();
                            $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                            $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                            $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                            $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                            $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                            $apiCelCoin->payer_id               = $pay->payment_from_cpf_cnpj;
                            $apiCelCoin->type                   = 1;
                            $apiCelCoin->digitable              = $pay->digitable_line;
                            $apiCelCoin->barCode                = '';
                            $apiCelCoin->externalNSU            = $pay->id;
                            $apiCelCoin->externalTerminal       = $pay->account_id;
                            $apiCelCoin->value                  = $pay->total_value;
                            $apiCelCoin->originalValue          = 0;
                            $apiCelCoin->valueWithDiscount      = 0;
                            $apiCelCoin->valueWithAdditional    = 0;
                            $apiCelCoin->dueDate                = $pay->due_date;
                            $apiCelCoin->transactionIdAuthorize = $pay->authorize_id;
    
                            $celcoinBillPaymnent = $apiCelCoin->billPayment();
    
                            if( $celcoinBillPaymnent->success ){
                                $billPayment = BillPayment::where('id', '=', $pay->id)->first();
                                $billPayment->bank_transaction_id = $celcoinBillPaymnent->data->transactionId;
                                $billPayment->save();
        
                                $apiCelCoin->transactionId = $billPayment->bank_transaction_id;
        
                                sleep( mt_rand(1, 6) ); // important wait

                                $celcoinConfirmBillPaymnent = $apiCelCoin->billPaymentConfirm();

                                if( $celcoinConfirmBillPaymnent->success ){
                                    $payReturn = (object) ["body" => (object) ["value" => $billPayment->bank_transaction_id]];  
                                }
                            }
    
    
                            //Para Banco Rendimento
                            /*$apiConfig                                          = new ApiConfig();
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
                            $apiRendimento->pag_valor_pagamento                 = number_format($pay->payment_value, 2, '.','');
                            $apiRendimento->pag_valor_titulo                    = number_format($pay->value, 2, '.','');
                            $apiRendimento->pag_descricao_extrato               = "p".$pay->id;
                            $payReturn = null;
                            $apiRendimento->pag_codigo_barra_ou_linha_digitavel = $pay->bar_code;
                            $payReturn = $apiRendimento->contaConsumoPagar();*/
                        break;
                        case 1:
                            
                            $sendTo = 'celcoin';

                            if( $sendTo == 'celcoin' ) {
                                //Para Celcoin
                                $apiConfig                          = new ApiConfig();
                                $apiConfig->master_id               = $checkAccount->master_id;
                                $apiConfig->api_id                  = 8;
                                $apiConfig->onlyActive              = 1;
                                $apiData                            = $apiConfig->getApiConfig()[0];
                
                                if( \Carbon\Carbon::parse($pay->created_at)->format('Y-m-d') < (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d') ) {
                                    // Consultar boleto novamente
                                    $apiCelCoin                         = new ApiCelCoin();
                                    $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                                    $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                                    $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                                    $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                                    $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                                    $apiCelCoin->type                   = 2;
                                    $apiCelCoin->digitable              = $pay->digitable_line;
                                    $apiCelCoin->barCode                = '';
                                    $apiCelCoin->externalNSU            = $pay->id;
                                    $apiCelCoin->externalTerminal       = $pay->account_id;
    
                                    $checkBill = null;
                                    $checkBill = $apiCelCoin->billPaymentBilletData();
    
                                    if( $checkBill->success ){

                                        if( isset( $checkBill->data->transactionId ) ) {
                                            $updateBillPayment = BillPayment::where('id', '=', $pay->id)->first();
                                            $updateBillPayment->authorize_id = $checkBill->data->transactionId;
                                            $pay->authorize_id = $checkBill->data->transactionId;
                                            $updateBillPayment->save();
                                        }
                                    }      
                                }

                                $apiCelCoin                         = new ApiCelCoin();
                                $apiCelCoin->api_address_request    = Crypt::decryptString($apiData->api_address);
                                $apiCelCoin->api_address            = Crypt::decryptString($apiData->api_address);
                                $apiCelCoin->client_id              = Crypt::decryptString($apiData->api_client_id);
                                $apiCelCoin->grant_type             = Crypt::decryptString($apiData->api_key);
                                $apiCelCoin->client_secret          = Crypt::decryptString($apiData->api_authentication);
                                $apiCelCoin->payer_id               = $pay->payment_from_cpf_cnpj;
                                $apiCelCoin->type                   = 2;
                                $apiCelCoin->digitable              = $pay->digitable_line;
                                $apiCelCoin->barCode                = '';
                                $apiCelCoin->externalNSU            = $pay->id;
                                $apiCelCoin->externalTerminal       = $pay->account_id;
                                $apiCelCoin->value                  = $pay->total_value;
                                $apiCelCoin->originalValue          = $pay->value;
                                $apiCelCoin->valueWithDiscount      = $pay->discount;
                                $apiCelCoin->valueWithAdditional    = $pay->interest + $pay->fines;
                                $apiCelCoin->dueDate                = $pay->due_date;
                                $apiCelCoin->transactionIdAuthorize = $pay->authorize_id;
        
                                $celcoinBillPaymnent = $apiCelCoin->billPayment();
        
                                if( $celcoinBillPaymnent->success ){
                                    $billPayment = BillPayment::where('id', '=', $pay->id)->first();
                                    $billPayment->bank_transaction_id = $celcoinBillPaymnent->data->transactionId;
                                    $billPayment->save();
            
                                    $apiCelCoin->transactionId = $billPayment->bank_transaction_id;

                                    sleep( mt_rand(1, 6) ); // important wait
            
                                    $celcoinConfirmBillPaymnent = $apiCelCoin->billPaymentConfirm();

                                    if( $celcoinConfirmBillPaymnent->success ){
                                        $payReturn = (object) ["body" => (object) ["value" => $billPayment->bank_transaction_id]];
                                    }
                                }
                                
                            }

                            if ( $sendTo == 'rendimento' ) {

                                //Para Banco Rendimento
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
                                $apiRendimento->pag_valor_pagamento                 = number_format($pay->payment_value, 2, '.','');
                                $apiRendimento->pag_valor_titulo                    = number_format($pay->value, 2, '.','');
                                $apiRendimento->pag_descricao_extrato               = "P".$pay->id;
                                $apiRendimento->pag_codigo_controle                 = $pay->account_id.'-'.$pay->id;
                                $apiRendimento->pag_codigo_barra_ou_linha_digitavel = $pay->digitable_line_or_bar_code;
                                $apiRendimento->razao_social_pagador                = $pay->payment_from_name;
                                $apiRendimento->cpf_cnpj_pagador                    = $pay->payment_from_cpf_cnpj;

                                $payReturn = null;

                                $rendimentoBillPayment = $apiRendimento->pagarTitulo();

                                if( isset( $rendimentoBillPayment->body->success ) ){
                                    if( $rendimentoBillPayment->body->success ) {
                                        if( isset($rendimentoBillPayment->body->data->transacaoId) ) {
                                            $payReturn = (object) ["body" => (object) ["value" => $rendimentoBillPayment->body->data->transacaoId]];
                                        }
                                    }
                                }
                            }                                
                        break;
                        default:
                            $payReturn = null;
                        break;
                    }

                    if(isset($payReturn->body->value) ){
                        $billPay = BillPayment::where('id','=', $pay->id)->first();
                        $billPay->transaction_id                    = $payReturn->body->value;
                        $billPay->bank_transaction_id               = $payReturn->body->value;
                        $billPay->status_id                         = 37;
                        $billPay->bnk_trnsctn_stt                   = null;
                        $billPay->payment_date                      = \Carbon\Carbon::now();
                        $billPay->approved_by_user_id               = $checkAccount->user_id;
                        $billPay->approved_by_user_relationship_id  = $checkAccount->user_relationship_id;
                        $billPay->save();
                    } else {
                        // send mail on payment failure
                        $sendFailureAlert               = new TransactionFailureClass();
                        $sendFailureAlert->title        = 'PAGAMENTO NÃO EFETIVADO';
                        $sendFailureAlert->errorMessage = 'Atenção, acompanhe o extrato, pode ser que o banco não efetivou o pagamento de boleto, conta de consumo ou tributo solicitado pela conta: '.$pay->from_account_number.' - '.$pay->payment_from_name.', id pagamento: '.$pay->id.', valor: '.$pay->value.'.';
                        $sendFailureAlert->sendFailures();
                    } 
                }
            }
            sleep(1); // important wait

            $billPayment = new BillPayment;
            $billPayment->id = $pay->id;
            $billPayment->uuid = $pay->uuid;
            return response()->json(array("success" => "Pagamento para $pay->favored_name, no valor de ".number_format($pay->payment_value, 2, '.','')." realizado com sucesso.", "bill_data" => $billPayment->getTransferPaymentReceipt()));
        }
    }

    protected function receiptDownload(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'transfer_id'=> ['required', 'integer'],
        ],[
            'transfer_id.required' => 'Informe o id de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $billPaymentReceipt              = new BillPayment();
        $billPaymentReceipt->id          = $request->transfer_id;
        $billPaymentReceipt->account_id  = $checkAccount->account_id;
        $receiptData                     = $billPaymentReceipt->getTransferPaymentReceipt();

        $facilities = new Facilites();

        $receipt = (object) array(
            "billPay_day"                         => \Carbon\Carbon::parse($receiptData->payment_date)->format('d'),
            "billPay_month"                       => $facilities->convertNumberMonthToString(\Carbon\Carbon::parse($receiptData->payment_date)->format('m')),
            "billPay_year"                        => \Carbon\Carbon::parse($receiptData->payment_date)->format('Y'),
            "billPay_date"                        => \Carbon\Carbon::parse($receiptData->payment_date)->format('d/m/Y H:i:s'),
            "billPay_from_name"                   => $receiptData->payment_from_name,
            "billPay_from_cpf_cnpj"               => $facilities->mask_cpf_cnpj($receiptData->payment_from_cpf_cnpj),
            "billPay_from_agency"                 => $receiptData->payment_from_agency,
            "billPay_from_account"                => $facilities->mask_account($receiptData->payment_from_account),
            "billPay_to_name"                     => $receiptData->payment_to_name,
            "billPay_to_cpf_cnpj"                 => $facilities->mask_cpf_cnpj($receiptData->payment_to_cpf_cnpj),
            "billPay_digitable_line_or_bar_code"  => $receiptData->digitable_line_or_bar_code,
            "billPay_value"                       => number_format($receiptData->value,2,',','.'),
            "billPay_payment_value"               => number_format($receiptData->payment_value,2,',','.'),
            "billPay_type"                        => $receiptData->payment_type,
            "billPay_description"                 => $receiptData->payment_description,
            "master_name"                         => $receiptData->payment_master_name,
            "master_cpf_cnpj"                     => $receiptData->payment_master_cpf_cnpj,
            "billPay_transaction_id"              => $receiptData->payment_transaction_id,
            "id"                                  => $receiptData->payment_id
        );
        $file_name = 'Pagamento_'.$receiptData->payment_to_cpf_cnpj.'_'.$receiptData->payment_id.'.pdf';
        $pdf       = PDF::loadView('reports/receipt_bill_payment', compact('receipt'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "base64" => base64_encode($pdf) ));
    }

    protected function receiptEmail(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'email'=> ['required', 'email'],
        ],[
            'id.required' => 'Informe o id de pagamento.',
            'email.required' => 'Informe um e-mail válido.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $billPaymentReceipt              = new BillPayment();
        $billPaymentReceipt->id          = $request->id;
        $billPaymentReceipt->account_id  = $checkAccount->account_id;
        $receiptData                     = $billPaymentReceipt->getTransferPaymentReceipt();

        $facilities = new Facilites();

        $receipt = (object) array(
            "billPay_day"                         => \Carbon\Carbon::parse($receiptData->payment_date)->format('d'),
            "billPay_month"                       => $facilities->convertNumberMonthToString(\Carbon\Carbon::parse($receiptData->payment_date)->format('m')),
            "billPay_year"                        => \Carbon\Carbon::parse($receiptData->payment_date)->format('Y'),
            "billPay_date"                        => \Carbon\Carbon::parse($receiptData->payment_date)->format('d/m/Y H:i:s'),
            "billPay_from_name"                   => $receiptData->payment_from_name,
            "billPay_from_cpf_cnpj"               => $facilities->mask_cpf_cnpj($receiptData->payment_from_cpf_cnpj),
            "billPay_from_agency"                 => $receiptData->payment_from_agency,
            "billPay_from_account"                => $facilities->mask_account($receiptData->payment_from_account),
            "billPay_to_name"                     => $receiptData->payment_to_name,
            "billPay_to_cpf_cnpj"                 => $facilities->mask_cpf_cnpj($receiptData->payment_to_cpf_cnpj),
            "billPay_digitable_line_or_bar_code"  => $receiptData->digitable_line_or_bar_code,
            "billPay_value"                       => number_format($receiptData->value,2,',','.'),
            "billPay_payment_value"               => number_format($receiptData->payment_value,2,',','.'),
            "billPay_type"                        => $receiptData->payment_type,
            "billPay_description"                 => $receiptData->payment_description,
            "master_name"                         => $receiptData->payment_master_name,
            "master_cpf_cnpj"                     => $receiptData->payment_master_cpf_cnpj,
            "billPay_transaction_id"              => $receiptData->payment_transaction_id,
            "id"                                  => $receiptData->payment_id
        );
        $pdfFilePath  = '../storage/app/email_receipt/';
        $file_name    = 'Pagamento_'.$receiptData->payment_to_cpf_cnpj.'_'.$receiptData->payment_id.'.pdf';

        if ( PDF::loadView('reports/receipt_bill_payment', compact('receipt'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name) ){
            $message = "Olá, <br>
            Segue em anexo o comprovante do pagamento realizado por <b>$receipt->billPay_from_name - ".$facilities->mask_cpf_cnpj($receipt->billPay_from_cpf_cnpj)."</b>, para <b>$receipt->billPay_to_name - ".$facilities->mask_cpf_cnpj($receipt->billPay_to_cpf_cnpj)."</b><br><br>
            <b>Tipo:</b> $receipt->billPay_type<br>
            <b>Beneficiário:</b> $receipt->billPay_to_name<br>
            <b>Valor Pago:</b> ".$receipt->billPay_payment_value."<br>
            <b>Pago Em:</b> ".$receipt->billPay_date."<br>
            <b>Linha Digitável / Código de Barras:</b> $receipt->billPay_digitable_line_or_bar_code";
            $user = User::where('id','=',$request->header('userId'))->first();
            $sendMail = new sendMail();
            $sendMail->to_mail      = $request->email;
            $sendMail->to_name      = $request->email;
            $sendMail->send_cc      = 1;
            $sendMail->to_cc_mail   = $user->email;
            $sendMail->to_cc_name   = $user->name;
            $sendMail->send_cco     = 0;
            $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
            $sendMail->to_cco_name  = 'Ragazzi';
            $sendMail->attach_pdf   = 1;
            $sendMail->attach_path  = $pdfFilePath.$file_name;
            $sendMail->attach_file  = $file_name;
            $sendMail->subject      = 'Comprovante do Pagamento Realizado por '.$receipt->billPay_from_name;
            $sendMail->email_layout = 'emails/confirmEmailAccount';
            $sendMail->bodyMessage  = $message;
            if($sendMail->send()){
                File::delete($pdfFilePath.$file_name);
                return response()->json(array("success" => "E-Mail enviado com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao enviar o e-mail, por favor tente novamente"));
            }
        } else {
            return response()->json(array('error' => 'Ocorreu uma falha ao gerar o anexo em PDF, por favor tente novamente'));
        }

    }

    /*protected function reversePayment($id, $account_id, $master_id, $document, $value)
    {
        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $account_id;
        $accountMovement->master_id  = $master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();
        $accountBalance              = 0;
        $accountMasterBalance        = 0;
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $accountBalance = $accountMovement->getAccountBalance()->balance;
        }
        if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
            $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
        }
        AccountMovement::create([
            'account_id'       => $account_id,
            'master_id'        => $master_id,
            'origin_id'        => $id,
            'mvmnt_type_id'    => 13,
            'date'             => \Carbon\Carbon::now(),
            'value'            => $value,
            'balance'          => ($accountBalance + $value),
            'master_balance'   => ($accountMasterBalance + $value),
            'description'      => mb_substr('Estorno de Pagamento | '.$document, 0, 255),
            'created_at'       => \Carbon\Carbon::now(),
        ]);
    }

    protected function reverseTax($id, $account_id, $master_id, $document, $tax)
    {
        $accountMovement             = new AccountMovement();
        $accountMovement->account_id = $account_id;
        $accountMovement->master_id  = $master_id;
        $accountMovement->start_date = \Carbon\Carbon::now();
        $accountBalance              = 0;
        $accountMasterBalance        = 0;
        if(isset( $accountMovement->getAccountBalance()->balance )){
            $accountBalance = $accountMovement->getAccountBalance()->balance;
        }
        if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
            $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
        }
        AccountMovement::create([
            'account_id'       => $account_id,
            'master_id'        => $master_id,
            'origin_id'        => $id,
            'mvmnt_type_id'    => 12,
            'date'             => \Carbon\Carbon::now(),
            'value'            => $tax,
            'balance'          => ($accountBalance + $tax),
            'master_balance'   => ($accountMasterBalance + $tax),
            'description'      => mb_substr('Estorno de Tarifa de Pagamento | '.$document, 0, 255),
            'created_at'       => \Carbon\Carbon::now(),
        ]);

        $master = Master::where('id','=',$master_id)->first();
        if($master->margin_accnt_id != ''){
            $masterAccountMovement             = new AccountMovement();
            $masterAccountMovement->account_id = $master->margin_accnt_id;
            $masterAccountMovement->master_id  = $master_id;
            $masterAccountMovement->start_date = \Carbon\Carbon::now();
            $masterAccountBalance              = 0;
            $masterAccountMasterBalance        = 0;
            if(isset( $masterAccountMovement->getAccountBalance()->balance )){
                $masterAccountBalance = $masterAccountMovement->getAccountBalance()->balance;
            }
            if(isset( $masterAccountMovement->getMasterAccountBalance()->master_balance )){
                $masterAccountMasterBalance = $masterAccountMovement->getMasterAccountBalance()->master_balance;
            }

            AccountMovement::create([
                'account_id'       => $master->margin_accnt_id,
                'accnt_origin_id'  => $account_id,
                'master_id'        => $master_id,
                'origin_id'        => $id,
                'mvmnt_type_id'    => 12,
                'date'             => \Carbon\Carbon::now(),
                'value'            => ($tax * -1),
                'balance'          => $masterAccountBalance  - $tax,
                'master_balance'   => $masterAccountMasterBalance - $tax,
                'description'      => mb_substr('Estorno de Tarifa de Pagamento | '.$document, 0, 255),
                'created_at'       => \Carbon\Carbon::now(),
            ]);
        }
    }*/

    protected function resume(Request $request)
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

        $billPayment                = new BillPayment();
        $billPayment->payment_date  = $request->date;
        return response()->json( $billPayment->resumeBillPayment()[0]);
    }

    protected function cancelSchedule(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer']
        ],[
            'id.required' => 'Informe o id de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if($bill_payment = BillPayment::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->where('status_id ','=',7)->first()){
           $bill_payment->status_id = 8;
           $bill_payment->save();
           return response()->json(array("success" => "Agendamento cancelado com sucesso"));
        }else{
            return response()->json(array("error" => "Ocorreu uma falha ao cancelar o agendamento"));
        }
    }

    protected function receiptDownloadList(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'array'],
            'id.*'=> ['required', 'integer'],
        ],[
            'id.required' => 'Informe o id de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $billPaymentReceipt              = new BillPayment();
        $billPaymentReceipt->idIn        = $request->id;
        $billPaymentReceipt->account_id  = $checkAccount->account_id;

        $facilities = new Facilites();
        $items = [];

        foreach($billPaymentReceipt->getTransferPaymentReceiptList() as $movementData){
            array_push($items, (object) [
                "billPay_day"                         => \Carbon\Carbon::parse($movementData->payment_date)->format('d'),
                "billPay_month"                       => $facilities->convertNumberMonthToString(\Carbon\Carbon::parse($movementData->payment_date)->format('m')),
                "billPay_year"                        => \Carbon\Carbon::parse($movementData->payment_date)->format('Y'),
                "billPay_date"                        => \Carbon\Carbon::parse($movementData->payment_date)->format('d/m/Y H:i:s'),
                "billPay_from_name"                   => $movementData->payment_from_name,
                "billPay_from_cpf_cnpj"               => $facilities->mask_cpf_cnpj($movementData->payment_from_cpf_cnpj),
                "billPay_from_agency"                 => $movementData->payment_from_agency,
                "billPay_from_account"                => $facilities->mask_account($movementData->payment_from_account),
                "billPay_to_name"                     => $movementData->payment_to_name,
                "billPay_to_cpf_cnpj"                 => $facilities->mask_cpf_cnpj($movementData->payment_to_cpf_cnpj),
                "billPay_digitable_line_or_bar_code"  => $movementData->digitable_line_or_bar_code,
                "billPay_value"                       => number_format($movementData->value,2,',','.'),
                "billPay_payment_value"               => number_format($movementData->payment_value,2,',','.'),
                "billPay_type"                        => $movementData->payment_type,
                "billPay_description"                 => $movementData->payment_description,
                "master_name"                         => $movementData->payment_master_name,
                "master_cpf_cnpj"                     => $movementData->payment_master_cpf_cnpj,
                "billPay_transaction_id"              => $movementData->payment_transaction_id,
                "id"                                  => $movementData->payment_id
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );
        $file_name = 'Comprovantes_de_Pagamentos.pdf';
        $pdf       = PDF::loadView('reports/receipt_bill_payment_list', compact('data'))->setPaper('a4', 'portrait')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "base64" => base64_encode($pdf) ));
    }

    public function paySchedule()
    {
        $bills = BillPayment::where('schedule_date','=',(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d'))->where('status_id','=',7)->whereNull('payment_date')->whereNull('deleted_at')->get();
        foreach($bills as $bill){
            $account            = Account::where('id','=',$bill->account_id)->first();
            $billPaymentService = new BillSchedulePayService();
            $billPaymentService->billData = (object) [
                'bill_id'     => $bill->id,
                'account_id'  => $bill->account_id,
                'master_id'   => $account->master_id
            ];
            $billPayment = $billPaymentService->pay();
            if(!$billPayment->success){
                $sendFailureAlert               = new sendFailureAlert();
                $sendFailureAlert->title        = 'Falha em pagamento agendado';
                $sendFailureAlert->errorMessage = $billPayment->message.' | Pagamento ID '.$bill->id;
                $sendFailureAlert->sendFailures();
            }
            sleep(1); // important wait
        }
    }

    protected function getDetailedPDF(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $billPayment                                   = new BillPayment();
        $billPayment->master_id                        = $checkAccount->master_id;
        $billPayment->account_id                       = $checkAccount->account_id;
        $billPayment->onlyActive                       = $request->onlyActive;
        $billPayment->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $billPayment->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $billPayment->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $items = [];
        foreach($billPayment->billPaymentDetailed() as $movementData){
            array_push($items, (object) [
                'occurrence_date'               =>  $movementData->occurrence_date ? \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y h:m:s') : null,
                'schedule_date'                 =>  $movementData->schedule_date ? \Carbon\Carbon::parse($movementData->schedule_date)->format('d/m/Y h:m:s') : null,
                'type_description'              =>  $movementData->type_description,
                'total_value'                   =>  $movementData->total_value,
                'bill_type_description'         =>  $movementData->bill_type_description,
                'status_description'            =>  $movementData->status_description,
                'register_name'                 =>  $movementData->register_name,
                'account_number'                =>  $movementData->account_number,
                'tax_value_charged'             =>  $movementData->tax_value_charged,
                'payment_value'                 =>  $movementData->payment_value,
                'payment_date'                  =>  $movementData->payment_date,
                'favored_cpf_cnpj'              =>  Facilites::mask_cpf_cnpj($movementData->favored_cpf_cnpj),
                'favored_name'                  =>  $movementData->favored_name,
                'payer_name'                    =>  $movementData->payer_name,
                'payer_cpf_cnpj'                =>  $movementData->payer_cpf_cnpj,
                'discount'                       =>  $movementData->discount,
                'fines'                         =>  $movementData->fines,
                'interest'                      =>  $movementData->interest,
                'digitable_line'                =>  $movementData->digitable_line,
                'description'                   =>  $movementData->description,
                'transaction_id'                =>  $movementData->transaction_id,
                'bank_transaction_id'           =>  $movementData->bank_transaction_id,
                'payment_deadline'              =>  $movementData->payment_deadline, //pagamento até
                'created_at'                    =>  \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );
        $file_name = "Movimentacao_Pagamentos.pdf";
        $pdf       = PDF::loadView('reports/movement_bill_payment', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function resendUniqueTokenByWhatsApp(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $billPayment = new BillClass();
        $billPayment->data = $request;

        $sendUniqueTokenByWhatsApp = $billPayment->resendUniqueTokenByWhatsApp();

        if(!$sendUniqueTokenByWhatsApp->success){
            return response()->json(array("error" => $sendUniqueTokenByWhatsApp->message_pt_br, "data" => $sendUniqueTokenByWhatsApp));
        }

        return response()->json(array("success" => $sendUniqueTokenByWhatsApp->message_pt_br, "data" => $sendUniqueTokenByWhatsApp));
    }

    protected function resendBatchTokenByWhatsApp(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'array'],
            'id.*' => ['required', 'integer'],
        ],[
            'id.required' => 'Informe o id de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $billPayment = new BillClass();
        $billPayment->data = $request;

        $sendBatchTokenByWhatsApp = $billPayment->resendBatchTokenByWhatsApp();

        if(!$sendBatchTokenByWhatsApp->success){
            return response()->json(array("error" => $sendBatchTokenByWhatsApp->message_pt_br, "data" => $sendBatchTokenByWhatsApp));
        }

        return response()->json(array("success" => $sendBatchTokenByWhatsApp->message_pt_br, "data" => $sendBatchTokenByWhatsApp, "batch_id" => $sendBatchTokenByWhatsApp->data->batch_id));
    }

    public function removeNotApprovedPayments()
    {
        $bills = BillPayment::where('due_date', '<', (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d'))->where('status_id', '=', 5)->whereNull('payment_date')->whereNull('deleted_at')->get();
        foreach( $bills as $bill ){
            $billPayment = BillPayment::where('id', '=', $bill->id)->first();
            $billPayment->status_id = 10;
            $billPayment->save();
        }
    }
}
