<?php

namespace App\Http\Controllers;

use App\Models\AntecipationCharge;
use App\Models\Account;
use App\Models\AccountMovement;
use App\Models\ApiConfig;
use App\Models\AntecipationChargeHistory;
use App\Models\User;
use App\Models\ChargeInstruction;
use App\Models\Charge;
use App\Models\AntecipationReview;
use App\Models\AntecipationChrgMvmnt;
use App\Models\Master;
use App\Models\SimpleCharge;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\BilletGenerator;
use App\Libraries\sendMail;
use App\Libraries\Facilites;
use App\Libraries\SimpleCNAB;
use App\Libraries\SimpleZip;
use App\Libraries\ApiSendgrid;
use App\Libraries\QrCodeGenerator\QrCodeGenerator;
use App\Services\BilletLiquidation\AntecipationChargeBilletLiquidationService;
use App\Services\BilletInstruction\AntecipationChargeBilletInstructionService;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ZipArchive;
use File;
use PDF;

class AntecipationChargeController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [104, 230];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge                       = new AntecipationCharge();
        $antecipationCharge->master_id            = $checkAccount->master_id;
        $antecipationCharge->account_id           = $checkAccount->account_id;
        $antecipationCharge->onlyActive           = $request->onlyActive;
        $antecipationCharge->due_date_start       = $request->due_date_start;
        $antecipationCharge->due_date_end         = $request->due_date_end;
        $antecipationCharge->payment_date_start   = $request->payment_date_start;
        $antecipationCharge->payment_date_end     = $request->payment_date_end;
        $antecipationCharge->inclusion_date_start = $request->inclusion_date_start;
        $antecipationCharge->inclusion_date_end   = $request->inclusion_date_end;
        $antecipationCharge->value_start          = $request->value_start;
        $antecipationCharge->value_end            = $request->value_end;
        $antecipationCharge->payment_value_start  = $request->payment_value_start;
        $antecipationCharge->payment_value_end    = $request->payment_value_end;
        $antecipationCharge->document             = $request->document;
        $antecipationCharge->our_number           = $request->our_number;
        $antecipationCharge->payer_id_in          = $request->payer_id;
        $antecipationCharge->status_id_in         = $request->status_id;
        $antecipationCharge->api_id               = $request->api_id;
        return response()->json($antecipationCharge->getAntecipationCharge());
    }

    protected function getAnalitic(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [104, 230];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge             = new AntecipationCharge();
        $antecipationCharge->master_id  = $checkAccount->master_id;
        $antecipationCharge->account_id = $checkAccount->account_id;

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

        $period_created                       = $antecipationCharge->antecipationChargeAnalitic();
        $antecipationCharge->created_at_start = null;
        $antecipationCharge->created_at_end   = null;
        //----

        //period liquidated
        if($request->payment_date_start != ''){
            $antecipationCharge->payment_date_start = $request->payment_date_start." 00:00:00.000";
        } else {
            $antecipationCharge->payment_date_start = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 00:00:00.000";
        }

        if($request->payment_date_end != ''){
            $antecipationCharge->payment_date_end = $request->payment_date_end." 23:59:59.998";
        } else {
            $antecipationCharge->payment_date_end = \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')." 23:59:59.998";
        }

        $period_liquidated                      = $antecipationCharge->antecipationChargeAnalitic();
        $antecipationCharge->payment_date_start = null;
        $antecipationCharge->payment_date_end   = null;
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

        $period_down                                 = $antecipationCharge->antecipationChargeAnalitic();
        $antecipationCharge->payment_down_date_start = null;
        $antecipationCharge->payment_down_date_end   = null;
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
        $accountCheckService->permission_id = [104, 230];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge                                   = new AntecipationCharge();
        $antecipationCharge->master_id                        = $checkAccount->master_id;
        $antecipationCharge->account_id                       = $checkAccount->account_id;
        $antecipationCharge->status_id                        = $request->status_id;
        $antecipationCharge->onlyActive                       = $request->onlyActive;
        $antecipationCharge->type_id                          = $request->type_id;
        $antecipationCharge->manager_id                          = $request->manager_id;

        if($request->occurrence_date_start != ''){
            $antecipationCharge->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $antecipationCharge->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        if($request->created_at_start != ''){
            $antecipationCharge->created_at_start             = $request->created_at_start." 00:00:00.000";
        }
        if($request->created_at_end != ''){
            $antecipationCharge->created_at_end               = $request->created_at_end." 23:59:59.998";
        }
        if($request->payment_date_start != ''){
            $antecipationCharge->payment_date_start           = $request->payment_date_start." 00:00:00.000";
        }
        if($request->payment_date_end != ''){
            $antecipationCharge->payment_date_end             = $request->payment_date_end." 23:59:59.998";
        }
        if($request->down_date_start != ''){
            $antecipationCharge->down_date_start              = $request->down_date_start." 00:00:00.000";
        }
        if($request->down_date_end != ''){
            $antecipationCharge->down_date_end                = $request->down_date_end." 23:59:59.998";
        }
        return response()->json( $antecipationCharge->antecipationChargeDetailed() );
    }

    protected function exportAntecipation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [108,235];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge                       = new AntecipationCharge();
        $antecipationCharge->master_id            = $checkAccount->master_id;
        $antecipationCharge->account_id           = $checkAccount->account_id;
        $antecipationCharge->onlyActive           = $request->onlyActive;
        $antecipationCharge->due_date_start       = $request->due_date_start;
        $antecipationCharge->due_date_end         = $request->due_date_end;
        $antecipationCharge->payment_date_start   = $request->payment_date_start;
        $antecipationCharge->payment_date_end     = $request->payment_date_end;
        $antecipationCharge->inclusion_date_start = $request->inclusion_date_start;
        $antecipationCharge->inclusion_date_end   = $request->inclusion_date_end;
        $antecipationCharge->value_start          = $request->value_start;
        $antecipationCharge->value_end            = $request->value_end;
        $antecipationCharge->payment_value_start  = $request->payment_value_start;
        $antecipationCharge->payment_value_end    = $request->payment_value_end;
        $antecipationCharge->document             = $request->document;
        $antecipationCharge->our_number           = $request->our_number;
        $antecipationCharge->payer_id_in          = $request->payer_id;
        $antecipationCharge->status_id_in         = $request->status_id;

        $items = [];

        // return $simpleCharge->getSimpleCharge();
        foreach($antecipationCharge->getAntecipationCharge() as $movementData){
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
                'created_at'                        =>   \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')
            ]);
        }

         $data = (object) array(
            "movement_data"     => $items
        );
       // return response()->json($items);

        $file_name = "Titulos_Carteira_Caucionada.pdf";
        $pdf       = PDF::loadView('reports/antecipation_charge', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name,"mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getPayerList(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [104, 230];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge             = new AntecipationCharge();
        $antecipationCharge->master_id  = $checkAccount->master_id;
        $antecipationCharge->account_id = $checkAccount->account_id;
        return response()->json($antecipationCharge->getPayerList());
    }

    protected function getStatistic(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [104, 230];
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

        $accountStatistic             = new AntecipationCharge();
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


        $checkQtd                 = $accountStatistic->getQtd();
        $checkAvgTerm             = $accountStatistic->averageTerm();
        $checkOpenValue           = $accountStatistic->getOpenValue();
        $checkToLiquidateValue    = $accountStatistic->getToLiquidateValue();
        $checkPastValue           = $accountStatistic->getPastValue();
        $checkLiquidationValue    = $accountStatistic->getLiquidation();
        $monthValuePosition       = $accountStatistic->monthValuePosition();
        $monthLiquidationPosition = $accountStatistic->monthLiquidationPosition();

        $qtd                   = 0;
        $avgValue              = 0;
        $avgTerm               = 0;
        $openValue             = 0;
        $toLiquidateValue      = 0;
        $pastValue             = 0;
        $liquidationValue      = 0;
        $percentageLiquidation = 0;

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
            if($openValue > 0){
                $percentageLiquidation = ($liquidationValue/$openValue) * 100;
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

    protected function getBillet(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [103, 231];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge = AntecipationCharge::where('id','=',$request->id)->where('our_number','=',$request->our_number)->where('master_id','=',$checkAccount->master_id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);});

        if( $antecipationCharge->count() > 0 ){
            $antecipationCharge = $antecipationCharge->first();
            if($antecipationCharge->status_id == 28 or $antecipationCharge->status_id == 29){
                return response()->json(array("error" => "Não é possível gerar boleto para título liquidado ou baixado"));
            }
            $billetGenerator                          = new BilletGenerator();
            $billetGenerator->barcode                 = $antecipationCharge->bar_code;
            $billetGenerator->digitableLine           = $antecipationCharge->digitable_line;
            $billetGenerator->bankNumber              = substr($antecipationCharge->bank_code,1,3);
            $facilities                               = new Facilites();
            $getBilletData                            = new AntecipationCharge();
            $getBilletData->id                        = $antecipationCharge->id;
            $billetData                               = $getBilletData->getBilletData();
            $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
            $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
            $billetData->bank_code_formated           = $billetGenerator->createBankCode();
            $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
            $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
            $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
            $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
            $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
            $billetData->owner_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
            $billetData->document_type                = "DM";
            $billetData->logo                         = null;

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
            $pdf = PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->download( $antecipationCharge->our_number.'.pdf', ['Content-Type: application/pdf']);
            return response()->json(array("success" => "Download do boleto realizado com sucesso", "file_name" => $antecipationCharge->our_number.'.pdf', "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
        } else {
            return response()->json(array("error" => "Boleto não localizado"));
        }
    }

    protected function sendBilletMail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [105, 234];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($antecipationCharges = AntecipationCharge::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);})->get()){
            $arrayError = [];
            foreach($antecipationCharges as $antecipationCharge){
                if( $antecipationCharge->count() > 0 ){
                    if($antecipationCharge->status_id == 28 or $antecipationCharge->status_id == 29){
                        array_push($arrayError, 'Não é possível gerar boleto para título liquidado ou baixado');
                    }else{
                        $billetGenerator                          = new BilletGenerator();
                        $billetGenerator->barcode                 = $antecipationCharge->bar_code;
                        $billetGenerator->digitableLine           = $antecipationCharge->digitable_line;
                        $billetGenerator->bankNumber              = substr($antecipationCharge->bank_code,1,3);
                        $facilities                               = new Facilites();
                        $getBilletData                            = new AntecipationCharge();
                        $getBilletData->id                        = $antecipationCharge->id;
                        $billetData                               = $getBilletData->getBilletData();
                        $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                        $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                        $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                        $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                        $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                        $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
                        $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
                        $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
                        $billetData->owner_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
                        $billetData->document_type                = "DM";
                        $billetData->logo                         = null;
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
                        $pdfFilePath  = '../storage/app/billet_download/';
                        $file_name    = $antecipationCharge->our_number.'.pdf';
                        if($antecipationCharge->status_id == 28 or $antecipationCharge->status_id == 29){
                            array_push($arrayError, 'Não é possível enviar e-mail com boleto para título liquidado ou baixado');
                        }
                        if(PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name)){
                            $user = User::where('id','=',$request->header('userId'))->first();
                            $facilities = new Facilites();
                            $message = "Olá, <br>
                            Segue em anexo o boleto emitido por <b>$billetData->beneficiary_name - ".$facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj)."</b>, para <b>$billetData->payer_name - ".$facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj)."</b><br><br>
                            <b>Documento:</b> $antecipationCharge->document<br>
                            <b>Valor:</b> ".number_format($antecipationCharge->value,2,',','.')."<br>
                            <b>Vencimento:</b> $billetData->due_date<br>
                            <b>Nosso Número:</b> $antecipationCharge->our_number<br>
                            <b>Linha Digitável:</b> $antecipationCharge->digitable_line<br><br><br>
                            Quer emitir boletos, realizar transferências e pagamentos com muita facilidade e segurança? Acesse https://ip4y.com.br e abra sua conta";

                            if (!$file_encode = base64_encode(Storage::disk('billet_download')->get($file_name))) {
                                array_push($arrayError, 'Ocorreu uma falha ao converter o documento, por favor tente novamente');
                            }

                            $apiSendGrind = new ApiSendgrid();
                            $apiSendGrind->to_email                 = $request->email;
                            $apiSendGrind->to_name                  = $billetData->payer_name;
                            $apiSendGrind->to_cc_email              = $user->email;
                            $apiSendGrind->to_cc_name               = $user->name;
                            $apiSendGrind->subject                  = 'Boleto '.$antecipationCharge->document.' de '.$billetData->beneficiary_name;
                            $apiSendGrind->content                  = $message;
                            $apiSendGrind->attachment_content       = $file_encode;
                            $apiSendGrind->attachment_file_name     = $antecipationCharge->our_number.'.pdf';
                            $apiSendGrind->attachment_mime_type     = 'application/pdf';

                            if($billetData->payer_email != ''){
                                if($apiSendGrind->sendEmailWithAttachment()){
                                    File::delete($pdfFilePath.$file_name);
                                } else {
                                    array_push($arrayError, 'Ocorreu uma falha ao enviar o e-mail, por favor tente novamente');
                                }
                            } else {
                                array_push($arrayError, 'Ocorreu uma falha ao enviar o e-mail, reveja os endereços de e-mails e tente novamente');
                            }
                        }
                    }
                } else {
                    array_push($arrayError,'Boleto não localizado');
                }
            }
            return response()->json(['success'=>'E-Mail enviado com sucesso','error_list' => $arrayError]);
        }else{
            return response()->json(['error'=> 'Boleto não localizado']);
        }
    }

    protected function sendInstruction(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [106, 233];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $userMasterId = $request->header('userMasterId');
        if($userMasterId == ''){
            return response()->json(array("error" => "Instrução não permitida para carteira antecipada"));
        }

        $accountId = (AntecipationCharge::where('id','=',($request->id))->first())->account_id;

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
        $titleSendInstruction = new AntecipationChargeBilletInstructionService();
        $titleSendInstruction->titleData = $titleDataObj;
        $instruction = $titleSendInstruction->sendInstruction();

        if($instruction->success){
            return response()->json(array("success" => $instruction->message));
        } else {
            return response()->json(array("error" => $instruction->message));
        }




        //if( AntecipationCharge::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$accountId)->count() > 0 ){
        //    $antecipationCharge = AntecipationCharge::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->where('account_id','=',$accountId)->first();
        // 
        //    if($antecipationCharge->status_id == 28 or $antecipationCharge->status_id == 29){
        //        return response()->json(array("error" => "Não é possível solicitar instrução para título liquidado ou baixado"));
        //    }
        //    $tax = 0;
        //    $taxId = 9;
        //    if($request->instruction == 1){
        //        $taxId = 8;
        //    }
        //    $getTax = Account::getTax($accountId, $taxId, $checkAccount->master_id);
        //    if($getTax->value > 0){
        //        $tax = $getTax->value;
        //    } else if($getTax->percentage > 0){
        //        if($antecipationCharge->value > 0){
        //            $tax = round(( ($getTax->percentage/100) * $antecipationCharge->value),2);
        //        }
        //    }
        //    $accountMovement             = new AccountMovement();
        //    $accountMovement->account_id = $antecipationCharge->account_id;
        //    $accountMovement->master_id  = $checkAccount->master_id;
        //    $accountMovement->start_date = \Carbon\Carbon::now();
        //    $accountBalance              = 0;
        //    $accountMasterBalance        = 0;
        //    if(isset( $accountMovement->getAccountBalance()->balance )){
        //        $accountBalance = $accountMovement->getAccountBalance()->balance;
        //    }
        //    if(isset( $accountMovement->getMasterAccountBalance()->master_balance )){
        //        $accountMasterBalance = $accountMovement->getMasterAccountBalance()->master_balance;
        //    }
        //    /*if($accountBalance < $tax){
        //        return response()->json(array("error" => "Saldo insuficiente para solicitar instrução" ));
        //    } */
        //    $apiConfig                           = new ApiConfig();
        //    $apiConfig->master_id                = $checkAccount->master_id;
        //    $apiConfig->api_id                   = 1;
        //    $apiConfig->onlyActive               = 1;
        //    $apiData                             = $apiConfig->getApiConfig()[0];
        //    $apiRendimento                       = new ApiBancoRendimento();
        //    $apiRendimento->id_cliente           = Crypt::decryptString($apiData->api_client_id);
        //    $apiRendimento->chave_acesso         = Crypt::decryptString($apiData->api_key);
        //    $apiRendimento->autenticacao         = Crypt::decryptString($apiData->api_authentication);
        //    $apiRendimento->endereco_api         = Crypt::decryptString($apiData->api_address);
        //    $apiRendimento->agencia              = Crypt::decryptString($apiData->api_agency);
        //    $apiRendimento->conta_corrente       = Crypt::decryptString($apiData->api_account);
        //    $apiRendimento->tit_nosso_numero     = $antecipationCharge->our_number;
        //    $apiRendimento->tit_data_referencia  = '';
        //    $apiRendimento->tit_texto            = $request->description.' | id: '.$antecipationCharge->id;
        //    $apiRendimento->tit_tipo_referencia  = '0';
        //    $apiRendimento->tit_valor_referencia = '0';
        //    $chargeInstruction = ChargeInstruction::where('id','=',$request->instruction)->first();
        //    $apiRendimento->tit_codigo_instrucao = $chargeInstruction->code;
        //    $apiRendimento->tit_codigo_produto   = 2;
        //    switch($chargeInstruction->code){
        //        case 145:
        //            $apiRendimento->tit_tipo_referencia  = 1;
        //            $apiRendimento->tit_data_referencia  = $request->new_due_date;
        //        break;
        //        case 148:
        //            $apiRendimento->tit_tipo_referencia  = 2;
        //            $apiRendimento->tit_valor_referencia = $request->discount_value;
        //        break;
        //    }
        //    $instruction = $apiRendimento->tituloIncluirInstrucao();
        //    $review = AntecipationReview::where('id','=',$antecipationCharge->review_id)->first();
        //    if(isset($instruction->body->value)){
        //        if( $tax > 0 ){ //cobrar tarifa de instrução em carteira antecipada
        //          /*  AccountMovement::create([
        //               'account_id'       => $antecipationCharge->account_id,
        //                'master_id'        => $checkAccount->master_id,
        //                'origin_id'        => $antecipationCharge->id,
        //                'mvmnt_type_id'    => 17,
        //                'date'             => \Carbon\Carbon::now(),
        //                'value'            => ($tax * -1),
        //                'balance'          => ($accountBalance - $tax),
        //                'master_balance'   => ($accountMasterBalance - $tax),
        //                'description'      => 'Tarifa de Instrução | Carteira Caucionada | '.$chargeInstruction->description.' | '.$antecipationCharge->document,
        //                'created_at'       => \Carbon\Carbon::now(),
        //            ]);
        //            $master = Master::where('id','=',$antecipationCharge->master_id)->first();
        //            if($master->margin_accnt_id != ''){
        //                $masterAccountMovement             = new AccountMovement();
        //                $masterAccountMovement->account_id = $master->margin_accnt_id;
        //                $masterAccountMovement->master_id  = $antecipationCharge->master_id;
        //                $masterAccountMovement->start_date = \Carbon\Carbon::now();
        //                $masterAccountBalance              = 0;
        //                $masterAccountMasterBalance        = 0;
        //                if(isset( $masterAccountMovement->getAccountBalance()->balance )){
        //                    $masterAccountBalance = $masterAccountMovement->getAccountBalance()->balance;
        //                }
        //                if(isset( $masterAccountMovement->getMasterAccountBalance()->master_balance )){
        //                    $masterAccountMasterBalance = $masterAccountMovement->getMasterAccountBalance()->master_balance;
        //               }
        //                AccountMovement::create([
        //                    'account_id'       => $master->margin_accnt_id,
        //                    'accnt_origin_id'  => $antecipationCharge->account_id,
        //                    'master_id'        => $antecipationCharge->master_id,
        //                    'origin_id'        => $antecipationCharge->id,
        //                    'mvmnt_type_id'    => 17,
        //                    'date'             => \Carbon\Carbon::now(),
        //                    'value'            => $tax,
        //                    'balance'          => $masterAccountBalance  + $tax,
        //                    'master_balance'   => $masterAccountMasterBalance + $tax,
        //                    'description'      => 'Tarifa de Compensação de Boleto de Cobrança | Carteira Caucionada | '.$accntAddMoney->document,
        //                    'created_at'       => \Carbon\Carbon::now(),
        //                ]);
        //            }
        // */
        //        }
        //        switch($chargeInstruction->code){
        //            case 145:
        //                AntecipationChargeHistory::create([
        //                    'antcptn_chrg_id'      => $antecipationCharge->id,
        //                    'instruction_id'       => $chargeInstruction->id,
        //                    'user_id'              => $request->header('userId'),
        //                    'master_id'            => $antecipationCharge->master_id,
        //                    'register_master_id'   => $antecipationCharge->register_master_id,
        //                    'account_id'           => $antecipationCharge->account_id,
        //                    'action_id'            => 9,
        //                    'description'          => $request->description,
        //                    'tax_value'            => $tax,
        //                    'original_due_date'    => $review->due_date,
        //                   'old_due_date'         => $antecipationCharge->due_date,
        //                    'new_due_date'         => $request->new_due_date,
        //                    'instruction_protocol' => $instruction->body->value->protocoloId,
        //                    'created_at'           => \Carbon\Carbon::now(),
        //                ]);
        //                $antecipationCharge->due_date = $request->new_due_date;
        //            break;
        //            case 148:
        //                AntecipationChargeHistory::create([
        //                    'antcptn_chrg_id'      => $antecipationCharge->id,
        //                    'instruction_id'       => $chargeInstruction->id,
        //                    'user_id'              => $request->header('userId'),
        //                    'master_id'            => $antecipationCharge->master_id,
        //                    'register_master_id'   => $antecipationCharge->register_master_id,
        //                    'account_id'           => $antecipationCharge->account_id,
        //                    'action_id'            => 9,
        //                    'description'          => $request->description,
        //                    'tax_value'            => $tax,
        //                    'original_value'       => $review->value,
        //                    'old_value'            => $antecipationCharge->value,
        //                    'new_value'            => $antecipationCharge->value - $request->discount_value,
        //                    'instruction_protocol' => $instruction->body->value->protocoloId,
        //                    'created_at'           => \Carbon\Carbon::now(),
        //                ]);
        //                $antecipationCharge->value =  $antecipationCharge->value - $request->discount_value;
        //            break;
        //            case 51:
        //                AntecipationChargeHistory::create([
        //                    'antcptn_chrg_id'      => $antecipationCharge->id,
        //                    'instruction_id'       => $chargeInstruction->id,
        //                    'user_id'              => $request->header('userId'),
        //                    'master_id'            => $antecipationCharge->master_id,
        //                    'register_master_id'   => $antecipationCharge->register_master_id,
        //                    'account_id'           => $antecipationCharge->account_id,
        //                    'action_id'            => 8,
        //                    'description'          => $request->description,
        //                    'tax_value'            => $tax,
        //                    'instruction_protocol' => $instruction->body->value->protocoloId,
        //                    'created_at'           => \Carbon\Carbon::now(),
        //                ]);
        //                $antecipationCharge->status_id =  28;
        //                $antecipationCharge->down_date = \Carbon\Carbon::now();
        //            break;
        //        }
        //        if($antecipationCharge->save()){
        //            return response()->json(array("success" => "Instrução aplicada com sucesso"));
        //         } else {
        //            return response()->json(array("error" => "Instrução aplicada com sucesso, porém ocorreu um erro ao atualizar o título no sistema, entre em contato com o suporte"));
        //        }
        //    } else {
        //        return response()->json(array("error" => "Ocorreu uma falha ao aplicar a instrução, por favor tente novamente"));
        //    }
        //} else {
        //    return response()->json(array("error" => "Não foi possível localizar o título"));
        //}
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

        $titles = AntecipationCharge::whereNotNull('our_number')->whereNull('payment_date')->whereNull('down_date')->get();
        foreach($titles as $title){
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

            $titleData = $apiRendimento->tituloConsultar();
            if(isset($titleData->body->value[0]->codigoSituacao)){
                if($titleData->body->value[0]->codigoSituacao == 'Pago'){

                    $titleDataObj = (object) [
                        'our_number'            => $titleData->body->value[0]->nossoNumero,
                        'wallet_number'         => $titleData->body->value[0]->numeroCarteira,
                        'bank_code'             => '0633',
                        'payment_date'          => $paymentDate,
                        'payment_value'         => $titleData->body->value[0]->valorTitulo,
                        'api_id'                => $title->api_id,
                        'concliliation_success' => false
                    ];

                    $billetConciliation = new AntecipationChargeBilletLiquidationService();
                    $billetConciliation->our_number = $titleData->body->value[0]->nossoNumero;
                    $billetConciliation->master_id = $title->master_id;
                    $billetConciliation->api_id = $title->api_id;
                    $concliliation = $billetConciliation-> billetConciliation();

                    if($concliliation->success){

                        $titleDataObj = null;

                        $titleDataObj = (object) [
                            'our_number'            => $titleData->body->value[0]->nossoNumero,
                            'wallet_number'         => $titleData->body->value[0]->numeroCarteira,
                            'bank_code'             => $title->bank_code,
                            'payment_date'          => $concliliation->data->datapagtobaixa,
                            'payment_value'         => $concliliation->data->valorpgto,
                            'api_id'                => $title->api_id,
                            'concliliation_success' => true
                        ];
                    }


                    $antecipationChargeLiquidation = new AntecipationChargeBilletLiquidationService();
                    $antecipationChargeLiquidation->paymentData = $titleDataObj;
                    $liquidationData = $antecipationChargeLiquidation->billetLiquidation();
                    if(!$liquidationData->success){
                        array_push($errors, $liquidationData->message);
                    }


                   /* if( AntecipationCharge::where('our_number','=',$titleData->body->value[0]->nossoNumero)->where('wallet_number','=',$titleData->body->value[0]->numeroCarteira)->whereNull('payment_date')->count() > 0  ){
                        $antecipationCharge = AntecipationCharge::where('our_number','=',$titleData->body->value[0]->nossoNumero)->where('wallet_number','=',$titleData->body->value[0]->numeroCarteira)->whereNull('payment_date')->first();
                        if($antecipationCharge->payment_value == null or $antecipationCharge->payment_value == '' or $antecipationCharge->payment_value == 0){
                            $payedValue = 0;
                            $paymentValue = $antecipationCharge->value;
                        } else {
                            $payedValue = $antecipationCharge->payment_value;
                            $paymentValue = $payedValue +  ($titleData->body->value[0]->valorTitulo - $payedValue);
                        }
                        $antecipationCharge->payment_date  = (\Carbon\Carbon::parse( \Carbon\Carbon::now()))->format('Y-m-d');
                        $antecipationCharge->payment_value = $paymentValue;
                        $antecipationCharge->status_id     = 29;
                        $antecipationCharge->save();
                        $tax = 0;
                        $getTax = Account::getTax($antecipationCharge->account_id, 7, $antecipationCharge->master_id);
                        if($getTax->value > 0){
                            $tax = $getTax->value;
                        } else if($getTax->percentage > 0){
                            if($paymentValue > 0){
                                $tax = round(( ($getTax->percentage/100) * $paymentValue),2);
                            }
                        }
                        AntecipationChargeHistory::create([
                            'antcptn_chrg_id'    => $antecipationCharge->id,
                            'master_id'           => $antecipationCharge->master_id,
                            'register_master_id'  => $antecipationCharge->register_master_id,
                            'account_id'          => $antecipationCharge->account_id,
                            'action_id'           => 7,
                            'description'         => 'Pago via compensação bancária',
                            'tax_value'           => $tax,
                            'payment_value'       => $paymentValue,
                            'total_payment_value' => $paymentValue,
                            'created_at'          => \Carbon\Carbon::now(),
                        ]);

                        $chargeMovement              = new AntecipationChrgMvmnt();
                        $chargeMovement->account_id  = $antecipationCharge->account_id;
                        $chargeMovement->master_id   = $antecipationCharge->master_id;
                        $chargeMovement->start_date  = \Carbon\Carbon::now();
                        $chargeAccountBalance              = 0;
                        $chargeAccountMasterBalance        = 0;

                        if(isset( $chargeMovement->getAccountBalance()->balance )){
                            $chargeAccountBalance = $chargeMovement->getAccountBalance()->balance;
                        }
                        if(isset( $chargeMovement->getMasterAccountBalance()->master_balance )){
                            $chargeAccountMasterBalance = $chargeMovement->getMasterAccountBalance()->master_balance;
                        }

                        AntecipationChrgMvmnt::create([
                            'account_id'     => $antecipationCharge->account_id,
                            'master_id'      => $antecipationCharge->master_id,
                            'charge_id'      => $antecipationCharge->id,
                            'chrg_mvnt_id'   => 5,
                            'document'       => $antecipationCharge->document,
                            'date'           => \Carbon\Carbon::now(),
                            'value'          => ($paymentValue * -1),
                            'balance'        => $chargeAccountBalance + ($paymentValue * -1),
                            'master_balance' => $chargeAccountMasterBalance + ($paymentValue * -1),
                            'created_at'     => \Carbon\Carbon::now()
                        ]);

                        $accountMovement             = new AccountMovement();
                        $accountMovement->account_id = 67; // 67 - Dinari Caucionada | TODO: Criar opção para deixar dinamico
                        $accountMovement->master_id  = $antecipationCharge->master_id;
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
                            'account_id'       => 67, // 67 - Dinari Caucionada | TODO: Criar opção para deixar dinamico
                            'master_id'        => $antecipationCharge->master_id,
                            'origin_id'        => $antecipationCharge->id,
                            'mvmnt_type_id'    => 11,
                            'date'             => \Carbon\Carbon::now(),
                            'value'            => $paymentValue,
                            'balance'          => $accountBalance + $paymentValue,
                            'master_balance'   => $accountMasterBalance + $paymentValue,
                            'description'      => 'Compensação de Boleto de Cobrança | Carteira Caucionada | '.$antecipationCharge->document,
                            'created_at'       => \Carbon\Carbon::now(),
                        ]);
                        // sem cobrança de tarifas em liquidação da carteira caucionada
                        /*if( $tax > 0 ){
                            AccountMovement::create([
                                'account_id'       => $antecipationCharge->account_id,
                                'master_id'        => $antecipationCharge->master_id,
                                'origin_id'        => $antecipationCharge->id,
                                'mvmnt_type_id'    => 19,
                                'date'             => \Carbon\Carbon::now(),
                                'value'            => ($tax * -1),
                                'balance'          => $accountBalance + $paymentValue - $tax,
                                'master_balance'   => $accountMasterBalance + $paymentValue - $tax,
                                'description'      => 'Tarifa de Compensação de Boleto de Cobrança | Carteira Caucionada | '.$antecipationCharge->document,
                                'created_at'       => \Carbon\Carbon::now(),
                            ]);
                            $master = Master::where('id','=',$antecipationCharge->master_id)->first();
                            if($master->margin_accnt_id != ''){
                                $masterAccountMovement             = new AccountMovement();
                                $masterAccountMovement->account_id = $master->margin_accnt_id;
                                $masterAccountMovement->master_id  = $antecipationCharge->master_id;
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
                                    'accnt_origin_id'  => $antecipationCharge->account_id,
                                    'master_id'        => $antecipationCharge->master_id,
                                    'origin_id'        => $antecipationCharge->id,
                                    'mvmnt_type_id'    => 19,
                                    'date'             => \Carbon\Carbon::now(),
                                    'value'            => $tax,
                                    'balance'          => $masterAccountBalance  + $tax,
                                    'master_balance'   => $masterAccountMasterBalance + $tax,
                                    'description'      => 'Tarifa de Compensação de Boleto de Cobrança | Carteira Caucionada | '.$accntAddMoney->document,
                                    'created_at'       => \Carbon\Carbon::now(),
                                ]);
                            }
                        }*/
                 //   }

                }
                $searchTitles[$i] = $titleData;
                $i++;
            }
        }
        return response()->json($searchTitles);
    }

    protected function storageBillet($our_number, $masterId, $remittance)
    {
        $apiConfig                       = new ApiConfig();
        $apiConfig->master_id            = $masterId;
        $apiConfig->api_id               = 1;
        $apiConfig->onlyActive           = 1;
        $apiData                         = $apiConfig->getApiConfig()[0];
        $apiRendimento                   = new ApiBancoRendimento();
        $apiRendimento->id_cliente       = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso     = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao     = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api     = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia          = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente   = Crypt::decryptString($apiData->api_account);
        $apiRendimento->tit_nosso_numero = $our_number;
        $dadosBoleto                     = $apiRendimento->tituloConsultarBoleto();
        if(isset($dadosBoleto->body->value->boletoPdfBase64)){
            if(Storage::disk('billet_download')->put('/'.$remittance.'/'.$our_number.'.pdf', base64_decode($dadosBoleto->body->value->boletoPdfBase64))){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    protected function getSelfBillet(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [103, 231];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge = AntecipationCharge::where('id','=',$request->id)->where('our_number','=',$request->our_number)->where('master_id','=',$checkAccount->master_id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);});

        if( $simpleCharge->count() > 0 ){
            $simpleCharge = $simpleCharge->first();
            if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                return response()->json(array("error" => "Não é possível gerar boleto para título liquidado ou baixado"));
            }

            //to draw bar code and format digitable line
            $billetGenerator                = new BilletGenerator();
            $billetGenerator->barcode       = $simpleCharge->bar_code;
            $billetGenerator->digitableLine = $simpleCharge->digitable_line;
            $billetGenerator->bankNumber    = substr($simpleCharge->bank_code,1,3);

            $facilities = new Facilites();

            $getBilletData                      = new AntecipationCharge();
            $getBilletData->id                  = $simpleCharge->id;
            $billetData                         = $getBilletData->getBilletData();
            $billetData->draw_digitable_line    = $billetGenerator->drawDigitableLine();
            $billetData->draw_bar_code          = $billetGenerator->drawBarCode();
            $billetData->bank_code_formated     = $billetGenerator->createBankCode();
            $billetData->master_cpf_cnpj        = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
            $billetData->beneficiary_cpf_cnpj   = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
            $billetData->payer_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
            $billetData->document_type          = "DM";
            $billetData->logo                   = null;
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
            $billetData->issue_date             = ((\Carbon\Carbon::parse( $billetData->issue_date ))->format('d/m/Y'));
            $billetData->due_date               = ((\Carbon\Carbon::parse( $billetData->due_date ))->format('d/m/Y'));
            $billetData->value                  = number_format(($billetData->value),2,',','.');


            $pdf = PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->download( $simpleCharge->our_number.'.pdf', ['Content-Type: application/pdf']);
            return response()->json(array("success" => "true", "file_name" => $simpleCharge->our_number.'.pdf', "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
        } else {
            return response()->json(array("error" => "Boleto não localizado"));
        }
    }

    protected function sendSelfBilletMail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [103, 231];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $simpleCharge = AntecipationCharge::where('id','=',$request->id)->where('our_number','=',$request->our_number)->where('master_id','=',$checkAccount->master_id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);});


        if( $simpleCharge->count() > 0 ){
            $simpleCharge = $simpleCharge->first();
            if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                return response()->json(array("error" => "Não é possível gerar boleto para título liquidado ou baixado"));
            }

            $billetGenerator                = new BilletGenerator();
            $billetGenerator->barcode       = $simpleCharge->bar_code;
            $billetGenerator->digitableLine = $simpleCharge->digitable_line;
            $billetGenerator->bankNumber    = substr($simpleCharge->bank_code,1,3);

            $facilities = new Facilites();

            $getBilletData                      = new AntecipationCharge();
            $getBilletData->id                  = $simpleCharge->id;
            $billetData                         = $getBilletData->getBilletData();
            $billetData->draw_digitable_line    = $billetGenerator->drawDigitableLine();
            $billetData->draw_bar_code          = $billetGenerator->drawBarCode();
            $billetData->bank_code_formated     = $billetGenerator->createBankCode();
            $billetData->master_cpf_cnpj        = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
            $billetData->beneficiary_cpf_cnpj   = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
            $billetData->payer_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
            $billetData->document_type          = "DM";
            $billetData->logo                   = null;
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
            $billetData->issue_date             = ((\Carbon\Carbon::parse( $billetData->issue_date ))->format('d/m/Y'));
            $billetData->due_date               = ((\Carbon\Carbon::parse( $billetData->due_date ))->format('d/m/Y'));
            $billetData->value                  = number_format(($billetData->value),2,',','.');

            $pdfFilePath  = '../storage/app/billet_download/';
            $file_name    = $simpleCharge->our_number.'.pdf';

            if($simpleCharge->status_id == 28 or $simpleCharge->status_id == 29){
                return response()->json(array("error" => "Não é possível enviar e-mail com boleto para título liquidado ou baixado"));
            }
            if(PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name)){
                $user = User::where('id','=',$request->header('userId'))->first();
                $facilities = new Facilites();
                $message = "Olá, <br>
                Segue em anexo o boleto emitido por <b>$billetData->beneficiary_name - ".$facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj)."</b>, para <b>$billetData->payer_name - ".$facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj)."</b><br><br>
                <b>Documento:</b> $simpleCharge->document<br>
                <b>Valor:</b> ".number_format($simpleCharge->value,2,',','.')."<br>
                <b>Vencimento:</b> ".\Carbon\Carbon::parse($billetData->issue_date)->format('d/m/Y')."<br>
                <b>Nosso Número:</b>$simpleCharge->our_number<br>
                <b>Linha Digitável:</b> $simpleCharge->digitable_line<br><br><br>
                Quer emitir boletos, realizar transferências e pagamentos com muita facilidade e segurança? Acesse https://ip4y.com.br e abra sua conta";
                $sendMail = new sendMail();
                $sendMail->to_mail      = $billetData->payer_email;
                $sendMail->to_name      = $billetData->payer_name;
                $sendMail->send_cc      = 1;
                $sendMail->to_cc_mail   = $user->email;
                $sendMail->to_cc_name   = $user->name;
                $sendMail->send_cco     = 1;
                $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
                $sendMail->to_cco_name  = 'Ragazzi';
                $sendMail->attach_pdf   = 1;
                $sendMail->attach_path  = '../storage/app/billet_download/'.$simpleCharge->our_number.'.pdf';
                $sendMail->attach_file  = $simpleCharge->our_number.'.pdf';
                $sendMail->subject      = 'Boleto '.$simpleCharge->document.' de '.$billetData->beneficiary_name;
                $sendMail->email_layout = 'emails/confirmEmailAccount';
                $sendMail->bodyMessage  = $message;
                if($sendMail->send()){
                    File::delete($pdfFilePath.$file_name);
                    return response()->json(array("success" => "E-Mail enviado com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao enviar o e-mail, por favor tente novamente"));
                }
            }
        } else {
            return response()->json(array("error" => "Boleto não localizado"));
        }
    }

    protected function sendBatchBilletMail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [110, 234];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $arrayError = [];

        if($antecipationCharges = AntecipationCharge::whereIn('id',$request->id)->when($checkAccount->account_id, function($query, $account_id){ return $query->where('account_id','=',$account_id);})->get()){
            foreach($antecipationCharges as $antecipationCharge){
                if( $antecipationCharge->count() > 0 ){
                    if($antecipationCharge->status_id == 28 or $antecipationCharge->status_id == 29){
                        array_push($arrayError, 'Não é possível gerar boleto para título liquidado ou baixado');
                    }else{
                        $billetGenerator                          = new BilletGenerator();
                        $billetGenerator->barcode                 = $antecipationCharge->bar_code;
                        $billetGenerator->digitableLine           = $antecipationCharge->digitable_line;
                        $billetGenerator->bankNumber              = substr($antecipationCharge->bank_code,1,3);
                        $facilities                               = new Facilites();
                        $getBilletData                            = new AntecipationCharge();
                        $getBilletData->id                        = $antecipationCharge->id;
                        $billetData                               = $getBilletData->getBilletData();
                        $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                        $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                        $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                        $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                        $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                        $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
                        $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
                        $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
                        $billetData->owner_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
                        $billetData->document_type                = "DM";
                        $billetData->logo                         = null;
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
                        $pdfFilePath  = '../storage/app/billet_download/';
                        $file_name    = $antecipationCharge->our_number.'.pdf';
                        if($antecipationCharge->status_id == 28 or $antecipationCharge->status_id == 29){
                            array_push($arrayError, 'Não é possível enviar e-mail com boleto para título liquidado ou baixado');
                        }
                        if(PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name)){
                            $user = User::where('id','=',$request->header('userId'))->first();
                            $facilities = new Facilites();
                            $message = "Olá, <br>
                            Segue em anexo o boleto emitido por <b>$billetData->beneficiary_name - ".$facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj)."</b>, para <b>$billetData->payer_name - ".$facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj)."</b><br><br>
                            <b>Documento:</b> $antecipationCharge->document<br>
                            <b>Valor:</b> ".number_format($antecipationCharge->value,2,',','.')."<br>
                            <b>Vencimento:</b> $billetData->due_date<br>
                            <b>Nosso Número:</b> $antecipationCharge->our_number<br>
                            <b>Linha Digitável:</b> $antecipationCharge->digitable_line<br><br><br>
                            Quer emitir boletos, realizar transferências e pagamentos com muita facilidade e segurança? Acesse https://ip4y.com.br e abra sua conta";

                            if (!$file_encode = base64_encode(Storage::disk('billet_download')->get($file_name))) {
                                array_push($arrayError, 'Ocorreu uma falha ao converter o documento, por favor tente novamente');
                            }

                            $apiSendGrind = new ApiSendgrid();
                            $apiSendGrind->to_email                 = $billetData->payer_email;
                            $apiSendGrind->to_name                  = $billetData->payer_name;
                            $apiSendGrind->to_cc_email              = $user->email;
                            $apiSendGrind->to_cc_name               = $user->name;
                            $apiSendGrind->subject                  = 'Boleto '.$antecipationCharge->document.' de '.$billetData->beneficiary_name;
                            $apiSendGrind->content                  = $message;
                            $apiSendGrind->attachment_content       = $file_encode;
                            $apiSendGrind->attachment_file_name     = $antecipationCharge->our_number.'.pdf';
                            $apiSendGrind->attachment_mime_type     = 'application/pdf';

                            if($billetData->payer_email != ''){
                                if($apiSendGrind->sendEmailWithAttachment()){
                                    File::delete($pdfFilePath.$file_name);
                                } else {
                                    array_push($arrayError, 'Ocorreu uma falha ao enviar o e-mail, por favor tente novamente');
                                }
                            } else {
                                array_push($arrayError, 'Ocorreu uma falha ao enviar o e-mail, reveja os endereços de e-mails e tente novamente');
                            }
                        }
                    }
                } else {
                    array_push($arrayError,'Boleto não localizado');
                }
            }
            return response()->json(['success'=>'E-Mail enviado com sucesso','error_list' => $arrayError]);
        }else{
            return response()->json(['error'=> 'Boleto não localizado']);
        }
    }

    protected function getBatchBillet(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [107, 231];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $items = [];
        $arrayError = [];

        $AntecipationCharges = AntecipationCharge::whereIn('id',$request->id)->whereNotIn('status_id', [28, 29])->when( $checkAccount->account_id, function($query, $accountId){ return $query->where('account_id','=',$accountId); } );

        if($AntecipationCharges->count() == 0){
            return response()->json(array("error" => "Não existem títulos na seleção, ou foi selecionado um título liquidado/baixado."));
        }

        foreach($AntecipationCharges->get() as $AntecipationCharge){
            if($AntecipationCharge->status_id == 28 or $AntecipationCharge->status_id == 29){
                array_push($arrayError,' Não é possível gerar boleto para título liquidado ou baixado',' Boleto ID '.$AntecipationCharge->id);
            }else{
                $billetGenerator                          = new BilletGenerator();
                $billetGenerator->barcode                 = $AntecipationCharge->bar_code;
                $billetGenerator->digitableLine           = $AntecipationCharge->digitable_line;
                $billetGenerator->bankNumber              = substr($AntecipationCharge->bank_code,1,3);
                $facilities                               = new Facilites();
                $getBilletData                            = $AntecipationCharge;
                $getBilletData->id                        = $AntecipationCharge->id;
                $billetData                               = $getBilletData->getBilletData();
                $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
                $billetData->beneficiary_address_zip_code = $facilities->mask_cep($billetData->beneficiary_address_zip_code);
                $billetData->payer_address_zip_code       = $facilities->mask_cep($billetData->payer_address_zip_code);
                $billetData->owner_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->owner_cpf_cnpj);
                $billetData->document_type                = "DM";
                $billetData->logo                         = null;
                $billetData->pix_qr_code                  = "";
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

    protected function getChargeReturn(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [103, 231];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $chargeReturn                   = new Charge();
        $chargeReturn->master_id        = $checkAccount->master_id;
        $chargeReturn->account_id       = $checkAccount->account_id;
        if($request->occurrence_date_start != ''){
            $chargeReturn->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $chargeReturn->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $remittanceTitles = $chargeReturn->getRemittance();


        $cnabTitles = [];
        foreach($remittanceTitles as $title){
            array_push($cnabTitles, [
                'agenciaDebito'                       => $title->agency_number,
                'contaCorrente'                       => $title->account_number,
                'agenciaDebitoCorrespondente'         => mb_substr($title->bank_agency,1,4),
                'contaCorrenteCorrespondente'         => $title->bank_account,
                'contaCobranca'                       => mb_substr($title->control_number, 8, 7),
                'codigoBancoCorrespondente'           => mb_substr($title->bank_code,1,3),
                'nomeEmpresa'                         => $title->beneficiary_name,
                'numeroInscricaoEmpresa'              => $title->master_cpf_cnpj,
                'numeroControleParticipante'          => $title->participant_control_number,
                'identificacaoTituloBanco'            => $title->our_number,
                'carteira'                            => $title->wallet_number,
                'identificacaoOcorrencia'             => $title->occurrence_id,
                'dataCredito'                         => (\Carbon\Carbon::parse( $title->payment_date ))->format('Y-m-d'),
                'numeroDocumento'                     => $title->document,
                'dataVencimento'                      => (\Carbon\Carbon::parse( $title->due_date ))->format('Y-m-d'),
                'valor'                               => $title->value,
                'bancoPagamento'                      => $title->bank_payment,
                'agenciaPagamento'                    => $title->agency_payment,
                'especieTitulo'                       => '01',
                'valorDespesa'                        => $title->tax_value,
                'outrasDespesasCustaCartorio'         => 0,
                'jurosOperacaoAtraso'                 => 0,
                'valorIof'                            => 0,
                'valorAbatimentoConcedidoOuCancelado' => 0,
                'valorDesconto'                       => 0,
                'valorPago'                           => $title->payment_value,
                'jurosMora'                           => 0,
                'outrosCreditos'                      => 0,
                'dataOcorrencia'                      => (\Carbon\Carbon::parse( $title->occurrence_date ))->format('Y-m-d'),
                'codigoBarras'                        => $title->bar_code,
                'linhaDigitavel'                      => $title->digitable_line,
            ]);
        }

        if($cnabTitles != []){
            $cnab              = new SimpleCNAB();
            $cnab->cnabData    = json_encode($cnabTitles);
            $cnab->pathFile    = Storage::disk('remittance')->path('/');
            $rendimentoReturn  = $cnab->writeReturnRendimento400();
            if($rendimentoReturn->success){
                $base64File = base64_encode(Storage::disk('remittance')->get($rendimentoReturn->file_name));
                File::delete($cnab->pathFile.$rendimentoReturn->file_name);
                return response()->json(array(
                    "success"    => "Arquivo de retorno da carteira caucionada gerado com sucesso",
                    "file_name"  => $rendimentoReturn->file_name,
                    "mime_type"  => "text/plain",
                    "base64"     => $base64File
                ));

            } else {
                return response()->json(array("error" => $rendimentoReturn->error));
            }
        } else {
            return response()->json(array("error" => "Sem títulos para retorno"));
        }
    }

    protected function getAgencyAntecipationChargeReturn(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [113];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $chargeReturn                       = new Charge();
        $chargeReturn->master_id            = $checkAccount->master_id;
        $chargeReturn->account_id           = $checkAccount->account_id;
        $chargeReturn->remittance_charge_id = $request->remittance_charge_id;
        $chargeReturn->wallet_id            = 2;
        if($request->occurrence_date_start != ''){
            $chargeReturn->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $chargeReturn->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }

        $remittanceTitles = $chargeReturn->getRemittance();


        $cnabTitles = [];
        foreach($remittanceTitles as $title){
            array_push($cnabTitles, [
                'agenciaDebito'                       => $title->agency_number,
                'contaCorrente'                       => $title->account_number,
                'agenciaDebitoCorrespondente'         => mb_substr($title->bank_agency,1,4),
                'contaCorrenteCorrespondente'         => $title->bank_account,
                'contaCobranca'                       => mb_substr($title->control_number, 8, 7),
                'codigoBancoCorrespondente'           => mb_substr($title->bank_code,1,3),
                'nomeEmpresa'                         => $title->master_name,
                'numeroInscricaoEmpresa'              => $title->master_cpf_cnpj,
                'numeroControleParticipante'          => $title->participant_control_number,
                'identificacaoTituloBanco'            => $title->our_number,
                'carteira'                            => $title->wallet_number,
                'identificacaoOcorrencia'             => $title->occurrence_id,
                'dataCredito'                         => (\Carbon\Carbon::parse( $title->payment_date ))->format('Y-m-d'),
                'numeroDocumento'                     => $title->document,
                'dataVencimento'                      => (\Carbon\Carbon::parse( $title->due_date ))->format('Y-m-d'),
                'valor'                               => $title->value,
                'bancoPagamento'                      => $title->bank_payment,
                'agenciaPagamento'                    => $title->agency_payment,
                'especieTitulo'                       => '01',
                'valorDespesa'                        => $title->tax_value,
                'outrasDespesasCustaCartorio'         => 0,
                'jurosOperacaoAtraso'                 => 0,
                'valorIof'                            => 0,
                'valorAbatimentoConcedidoOuCancelado' => 0,
                'valorDesconto'                       => 0,
                'valorPago'                           => $title->payment_value,
                'jurosMora'                           => 0,
                'outrosCreditos'                      => 0,
                'dataOcorrencia'                      => (\Carbon\Carbon::parse( $title->occurrence_date ))->format('Y-m-d'),
                'codigoBarras'                        => $title->bar_code,
                'linhaDigitavel'                      => $title->digitable_line,
            ]);
        }

        if($cnabTitles != []){
            $cnab              = new SimpleCNAB();
            $cnab->cnabData    = json_encode($cnabTitles);
            $cnab->pathFile    = Storage::disk('remittance')->path('/');
            $cnab->returnType  = 'agency';
            $rendimentoReturn  = $cnab->writeReturnRendimento400();
            if($rendimentoReturn->success){
                $base64File = base64_encode(Storage::disk('remittance')->get($rendimentoReturn->file_name));
                \File::delete($cnab->pathFile.$rendimentoReturn->file_name);
                return response()->json(array(
                    "success"    => "Arquivo de retorno gerado com sucesso",
                    "file_name"  => $rendimentoReturn->file_name,
                    "mime_type"  => "text/plain",
                    "base64"     => $base64File
                ));
            } else {
                return response()->json(array("error" => $rendimentoReturn->error));
            }
        } else {
            return response()->json(array("error" => "Sem títulos para retorno"));
        }
    }

    protected function getAgencyAntecipationChargeReturnTitles(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [113];
        $checkAccount = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //
        $chargeReturn                         = new Charge();
        $chargeReturn->master_id              = $checkAccount->master_id;
        $chargeReturn->account_id             = $checkAccount->account_id;
        $chargeReturn->liquidation_account_id = $request->liquidation_account_id;
        $chargeReturn->wallet_id        = 2;
        if($request->occurrence_date_start != ''){
            $chargeReturn->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $chargeReturn->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
        return response()->json($chargeReturn->getRemittance());
    }

    protected function getDetailedPDF(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [103, 231];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $antecipationCharge                                   = new AntecipationCharge();
        $antecipationCharge->master_id                        = $checkAccount->master_id;
        $antecipationCharge->account_id                       = $checkAccount->account_id;
        $antecipationCharge->onlyActive                       = $request->onlyActive;
        $antecipationCharge->type_id                          = $request->type_id;

        if($request->occurrence_date_start != ''){
            $antecipationCharge->occurrence_date_start        = $request->occurrence_date_start." 00:00:00.000";
        }
        if($request->occurrence_date_end != ''){
            $antecipationCharge->occurrence_date_end          = $request->occurrence_date_end." 23:59:59.998";
        }
       // return response()->json($simpleCharge->simpleChargeDetailed() );

       $items = [];
        foreach($antecipationCharge->antecipationChargeDetailed() as $movementData){
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
                'created_at'            =>  \Carbon\Carbon::parse($movementData->created_at)->format('d/m/Y')
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );

        //return response()->json($data);

        $file_name = "Movimentacao_Cob_Caucionada.pdf";
        $pdf       = PDF::loadView('reports/movement_antecipation_charge', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getBilletZip(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [103, 231];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $arrayError = [];
        if($antecipation_chages = AntecipationCharge::whereIn('id',$request->id)->when( $checkAccount->account_id, function($query, $accountId){ return $query->where('account_id','=',$accountId); } )->get()){
            $SimpleZip = new SimpleZip();
            $createZipFolder = $SimpleZip->createZipFolder();
            if(!$createZipFolder->success){
                return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
            } else {
                foreach($antecipation_chages as $antecipation_chage){
                    if( $antecipation_chage->count() > 0 ){
                        if($antecipation_chage->status_id == 28 or $antecipation_chage->status_id == 29){
                            array_push($arrayError, 'Não é possível gerar boleto para título liquidado ou baixado, título: '.$antecipation_chage->document);
                        }else{
                            $billetGenerator                          = new BilletGenerator();
                            $billetGenerator->barcode                 = $antecipation_chage->bar_code;
                            $billetGenerator->digitableLine           = $antecipation_chage->digitable_line;
                            $billetGenerator->bankNumber              = substr($antecipation_chage->bank_code,1,3);
                            $facilities                               = new Facilites();
                            $getBilletData                            = $antecipation_chage;
                            $getBilletData->id                        = $antecipation_chage->id;
                            $billetData                               = $getBilletData->getBilletData();
                            $billetData->draw_digitable_line          = $billetGenerator->drawDigitableLine();
                            $billetData->draw_bar_code                = $billetGenerator->drawBarCode();
                            $billetData->bank_code_formated           = $billetGenerator->createBankCode();
                            $billetData->master_cpf_cnpj              = $facilities->mask_cpf_cnpj($billetData->master_cpf_cnpj);
                            $billetData->beneficiary_cpf_cnpj         = $facilities->mask_cpf_cnpj($billetData->beneficiary_cpf_cnpj);
                            $billetData->payer_cpf_cnpj               = $facilities->mask_cpf_cnpj($billetData->payer_cpf_cnpj);
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
                            $pdfFilePath  = '../storage/app/zip/'.$createZipFolder->folderName.'/';
                            $file_name    = $antecipation_chage->our_number.'.pdf';
                            if(!(PDF::loadView('reports/self_billet', compact('billetData'))->setPaper('a4', 'portrait')->save($pdfFilePath.$file_name))){
                                array_push($arrayError,'Não foi possível gerar o boleto: '.$antecipation_chage->document);
                            }
                        }
                    } else {
                        array_push($arrayError,'Boleto não localizado título: '.$antecipation_chage->document);
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
}
