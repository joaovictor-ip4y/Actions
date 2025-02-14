<?php

namespace App\Http\Controllers;

use App\Classes\ExcelExportClass;
use App\Classes\ManagerExportClass;
use App\Models\ManagersRegister;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ManagersRegisterController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [80];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managers_registers                     = new ManagersRegister();
        $managers_registers->manager_detail_id  = $request->manager_detail_id;
        $managers_registers->manager_unique_id  = $request->manager_unique_id;
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->onlyActive         = $request->onlyActive;
        return response()->json($managers_registers->getManagersRegister());
    }

    protected function getManagerRegisterComissionGroupedByManager(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start." 00:00:00.000";
        $managers_registers->date_end           = $request->date_end." 23:59:59.998";
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_id;

        return response()->json( $managers_registers->getManagerRegisterComissionGroupedByManager());
    }

    protected function getManagerRegisterComissionGroupedByManagerExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $header = collect([
            [
                'manager_detail_id'         => 'ID Gerente',
                'manager_name'              => 'Gerente',
                'fee_value'                 => 'Tarifa Arrecada',
                'bank_fee_value'            => 'Tarifa Cobrada Pelo Banco',
                'fee_net_value_before_tax'  => 'Valor Liquido Antes do Imposto',
                'tax_value'                 => 'Valor Imposto',
                'fee_net_value_after_tax'   => 'Valor Liquido Após Imposto',
                'manager_comission_value'   => 'Comissão do Gerente',
                'net_fee_value'             => 'Valor Liquido'
            ]
        ]);

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start." 00:00:00.000";
        $managers_registers->date_end           = $request->date_end." 23:59:59.998";
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_id;

        $manager_export        = new ExcelExportClass();
        $manager_export->value = $header->merge($managers_registers->getManagerRegisterComissionGroupedByManager());

        return response()->json(array("success" => "Planilha gerada com sucesso", "file_name" => "Comissão por gerente.xlsx", "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "base64"=>base64_encode(Excel::raw($manager_export, \Maatwebsite\Excel\Excel::XLSX))));
    }

    protected function getManagerRegisterComissionGroupedByRegister(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start." 00:00:00.000";
        $managers_registers->date_end           = $request->date_end." 23:59:59.998";
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_detail_id;

        return response()->json( $managers_registers->getManagerRegisterComissionGroupedByRegister());
    }

    protected function getManagerRegisterComissionGroupedByRegisterExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $header = collect([
            [
                //'manager_detail_id'                 => 'ID Gerente',
                'manager_name'                      => 'Nome Gerente',
                //'manager_register_comission'        => 'Comissão Gerente',
                //'register_master_id'                => 'ID Cadastro',
                'register_description'              => 'Cadastro',
                //'account_first_movement'            => 'Primeiro movimento da conta',
                //'max_days_between_first_movement'   => 'Dias entre primeiro e último movimento',
                //'fee_value'                         => 'Tarifa arrecadada',
                //'bank_fee_value'                    => 'Tarifa cobrada pelo banco',
                //'fee_net_value_before_tax'          => 'Valor da tarifa antes do imposto',
                //'tax_value'                         => 'Imposto',
                'fee_net_value_after_tax'           => 'Valor da tarifa após imposto',
                'alias_account_value'               => 'Valor Alias Account',
                'manager_comission_value'           => 'Valor comissão gerente',
                //'net_fee_value'                     => 'Valor liquido tarifa'
            ]
        ]);

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start." 00:00:00.000";
        $managers_registers->date_end           = $request->date_end." 23:59:59.998";
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_detail_id;

        $manager_export         = new ExcelExportClass();
        $manager_export->value = $header->merge($managers_registers->getManagerRegisterComissionGroupedByRegisterToExcel());

        return response()->json(array("success" => "Planilha gerada com sucesso", "file_name" => "Comissão gerente agrupado por cliente.xlsx", "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "base64"=>base64_encode(Excel::raw($manager_export, \Maatwebsite\Excel\Excel::XLSX))));

    }

    protected function getManagerRegisterComissionDetailed(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [84];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

         $managers_registers                     = new ManagersRegister();
         $managers_registers->master_id          = $checkAccount->master_id;
         $managers_registers->date_start         = $request->date_start." 00:00:00.000";
         $managers_registers->date_end           = $request->date_end." 23:59:59.998";
         $managers_registers->register_master_id = $request->register_master_id;
         $managers_registers->manager_detail_id  = $request->manager_detail_id;

         return response()->json( $managers_registers->getManagerRegisterComissionDetailed());
    }

    protected function getManagerRegisterComissionDetailedExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $header = collect([
            [
                'master_id'                     => 'master_id',
                'master_name'                   => 'master_name',
                'master_cpf_cnpj'               => 'master_cpf_cnpj',
                'manager_detail_id'             => 'manager_detail_id',
                'manager_name'                  => 'manager_name',
                'register_master_id'            => 'register_master_id',
                'register_cpf_cnpj'             => 'register_cpf_cnpj',
                'register_name'                 => 'register_name',
                'register_description'          => 'register_description',
                'account_id'                    => 'account_id',
                'account_number'                => 'account_number',
                'account_created_at'            => 'account_created_at',
                'account_first_movement'        => 'account_first_movement',
                'occurrence_date'               => 'occurrence_date',
                'origin_id'                     => 'origin_id',
                'description'                   => 'description',
                'movement_type_id'              => 'movement_type_id',
                'movement_type'                 => 'movement_type',
                'bank_fee_description'          => 'bank_fee_description',
                'bank_fee_id'                   => 'bank_fee_id',
                'value'                         => 'value',
                'bank_fee_value'                => 'bank_fee_value',
                'net_fee_value_before_tax'      => 'net_fee_value_before_tax',
                'tax_percentage'                => 'tax_percentage',
                'tax_value'                     => 'tax_value',
                'net_fee_value_after_tax'       => 'net_fee_value_after_tax',
                'manager_commission_percentage' => 'manager_commission_percentage',
                'days_between_first_movement'   => 'days_between_first_movement',
                'manager_commission_value'      => 'manager_commission_value',
                'net_fee_value'                 => 'net_fee_value'
            ]
        ]);

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start." 00:00:00.000";
        $managers_registers->date_end           = $request->date_end." 23:59:59.998";
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_detail_id;

        $manager_export                     = new ExcelExportClass();
        $manager_export->value = $header->merge($managers_registers->getManagerRegisterComissionDetailed());

        return response()->json(array("success" => "Planilha gerada com sucesso", "file_name" => "Comissão por gerente detalhado.xlsx", "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "base64"=>base64_encode(Excel::raw($manager_export, \Maatwebsite\Excel\Excel::XLSX))));
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [79];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(ManagersRegister::where('manager_detail_id', $request->manager_detail_id)->where('register_master_id', $request->register_master_id)->count() == 0){
            $managers_registers                         = new ManagersRegister();
            $managers_registers->manager_detail_id      = $request->manager_detail_id;
            $managers_registers->register_master_id     = $request->register_master_id;
            $managers_registers->commission             = $request->commission;
            $managers_registers->first_commission       = $request->first_commission;
            $managers_registers->first_commission_days  = $request->first_commission_days;
            $managers_registers->save();
            return response()->json(['success' => 'Cadastro vinculado ao gerente com sucesso']);
        }else{
            $managerRegister = ManagersRegister::where('manager_detail_id', $request->manager_detail_id)->where('register_master_id', $request->register_master_id)->first();
            $managerRegister->deleted_at = null;
            if($managerRegister->save()){
                return response()->json(['success' => 'Vínculo do cadastro com o gerente realizado com sucesso']);
            }else{
                return response()->json(['error' => 'Ocorreu uma falha ao vincular o cadastro ao gerente, por favor tente novamente mais tarde']);
            }
        }
    }

    protected function edit(Request $request)
    {
       // ----------------- Check Account Verification ----------------- //
       $accountCheckService           = new AccountRelationshipCheckService();
       $accountCheckService->request  = $request;
       $accountCheckService->permission_id = [82];
       $checkAccount                  = $accountCheckService->checkAccount();
       if(!$checkAccount->success){
           return response()->json(array("error" => $checkAccount->message));
       }
       // -------------- Finish Check Account Verification -------------- //

        if( $managers_registers                         = ManagersRegister::where('id','=',$request->id)->first()){
            $managers_registers->commission             = $request->commission;
            $managers_registers->first_commission       = $request->first_commission;
            $managers_registers->first_commission_days  = $request->first_commission_days;
            if($managers_registers->save()){
                return response()->json(array("success" => "Comissão do gerente com o cadastro atualizada com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu um erro ao atualizar a comissão do gerente com o cadastro"));
            }
        }else{
            return response()->json(array("error" => "Vínculo do cadastro com o gerente não localizado"));
        }
    }

    protected function delete(Request $request)
    {
        /// ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [81];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if( $managers_registers = ManagersRegister::where('id','=',$request->id)->first()){
            $managers_registers->deleted_at = \Carbon\Carbon::now();
            if($managers_registers->save()){
                return response()->json(array("success" => "Vínculo do cadastro com o gerente excluído com sucesso"));
            }else{
                return response()->json(array("error" => "Ocorreu um erro ao excluir o vínculo do cadastro com o gerente, por favor tente novamente mais tarde"));
            }
        }else{
            return response()->json(array("error" => "Vínculo do cadastro com o gerente não localizado"));
        }
    }

    protected function getManagerRegisterAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [80];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managersRegister = new ManagersRegister;
        $managersRegister->register_master_id = $request->register_master_id;
        $managersRegister->manager_detail_id = $request->manager_detail_id;
        return response()->json($managersRegister->getManagerRegisterAccount());
    }
    
    protected function getManagerRegisterAccountPdf(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [80];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $managersRegister = new ManagersRegister;
        $managersRegister->register_master_id = $request->register_master_id;
        $managersRegister->manager_detail_id = $request->manager_detail_id;

        $items = [];

        foreach($managersRegister->getManagerRegisterAccount() as $movementData){
            array_push($items, (object) [
                'manager_name' => Str::limit($movementData->manager_name, 15),
                'register_name' => Str::limit($movementData->register_name, 15),
                'first_commission' => number_format($movementData->first_commission, 3, ',', '.'),
                'first_commission_days' => $movementData->first_commission_days,
                'commission' => number_format($movementData->commission, 3, ',', '.'),
                'account_number' => $movementData->account_number,
                'manager_at' => \Carbon\Carbon::parse($movementData->manager_at)->format('d/m/Y'),
                'account_created_at' => \Carbon\Carbon::parse($movementData->account_created_at)->format('d/m/Y'),
                'account_balance' => number_format($movementData->account_balance, 2, ',', '.'),
                'account_first_movement_at' => \Carbon\Carbon::parse($movementData->account_first_movement_at)->format('d/m/Y'),
                'account_last_movement_at' => \Carbon\Carbon::parse($movementData->account_last_movement_at)->format('d/m/Y'),
            ]);
        }

        $data = (object) array(
            "movement_data" => $items
        );

        $file_name = "ContasPorGerente.pdf";
        $pdf = PDF::loadView('reports/manager_register_account', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));

    }

    protected function getManagerRegisterComissionGroupedByManagerPdf(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start;
        $managers_registers->date_end           = $request->date_end;
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_detail_id;
        $items = [];

        foreach($managers_registers->getManagerRegisterComissionGroupedByManager() as $movementData){
            array_push($items, (object) [
                'manager_detail_id'         =>  $movementData->manager_detail_id,
                'manager_name'              =>  $movementData->manager_name,
                'fee_value'                 =>  $movementData->fee_value,
                'bank_fee_value'            =>  $movementData->bank_fee_value,
                'fee_net_value_before_tax'  =>  $movementData->fee_net_value_before_tax,
                'tax_value'                 =>  $movementData->tax_value,
                'fee_net_value_after_tax'   =>  $movementData->fee_net_value_after_tax,
                'manager_comission_value'   =>  $movementData->manager_comission_value,
                'net_fee_value'             =>  $movementData->net_fee_value,
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );

        $file_name = "Apurar_Comissao.pdf";
        $pdf       = PDF::loadView('reports/comission_register_manager', compact('data'))->setPaper('a4', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getManagerRegisterComissionGroupedByRegisterPdf(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start;
        $managers_registers->date_end           = $request->date_end;
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_detail_id;
        $items = [];

        foreach($managers_registers->getManagerRegisterComissionGroupedByRegister() as $movementData){
            array_push($items, (object) [
                'bank_fee_value'                    =>  $movementData->bank_fee_value,
                'fee_net_value_after_tax'           =>  $movementData->fee_net_value_after_tax,
                'fee_net_value_before_tax'          =>  $movementData->fee_net_value_before_tax,
                'fee_value'                         =>  $movementData->fee_value,
                'manager_comission_value'           =>  $movementData->manager_comission_value,
                'manager_detail_id'                 =>  $movementData->manager_detail_id,
                'manager_name'                      =>  $movementData->manager_name,
                'net_fee_value'                     =>  $movementData->net_fee_value,
                'register_description'              =>  $movementData->register_description,
                'register_master_id'                =>  $movementData->register_master_id,
                'tax_value'                         =>  $movementData->tax_value,
                'alias_account_value'               =>  $movementData->alias_account_value
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items,
            "date_start"        => $request->date_start,
            "date_end"          => $request->date_end
        );

        //return response()->json($data);

        $file_name = "Comissão_Por_Cliente.pdf";
        $pdf       = PDF::loadView('reports/comission_client_manager', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getManagerRegisterComissionDetailedPdf(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [84];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $managers_registers                     = new ManagersRegister();
        $managers_registers->master_id          = $checkAccount->master_id;
        $managers_registers->date_start         = $request->date_start;
        $managers_registers->date_end           = $request->date_end;
        $managers_registers->register_master_id = $request->register_master_id;
        $managers_registers->manager_detail_id  = $request->manager_detail_id;
        $items = [];

        foreach($managers_registers->getManagerRegisterComissionDetailed() as $movementData){
            array_push($items, (object) [
                'account_created_at'            =>  $movementData->account_created_at,
                'account_first_movement'        =>  \Carbon\Carbon::parse($movementData->account_first_movement)->format('d/m/Y'),
                'account_id'                    =>  $movementData->account_id,
                'account_number'                =>  $movementData->account_number,
                'bank_fee_id'                   =>  $movementData->bank_fee_id,
                'bank_fee_value'                =>  $movementData->bank_fee_value,
                'days_between_first_movement'   =>  $movementData->days_between_first_movement,
                'description'                   =>  $movementData->description,
                'manager_commission_percentage' =>  $movementData->manager_commission_percentage,
                'manager_commission_value'      =>  $movementData->manager_commission_value,
                'master_cpf_cnpj'               =>  $movementData->master_cpf_cnpj,
                'master_id'                     =>  $movementData->master_id,
                'master_name'                   =>  $movementData->master_name,
                'movement_type'                 =>  $movementData->movement_type,
                'movement_type_id'              =>  $movementData->movement_type_id,
                'net_fee_value'                 =>  $movementData->net_fee_value,
                'net_fee_value_after_tax'       =>  $movementData->net_fee_value_after_tax,
                'net_fee_value_before_tax'      =>  $movementData->net_fee_value_before_tax,
                'occurrence_date'               =>  \Carbon\Carbon::parse($movementData->occurrence_date)->format('d/m/Y'),
                'origin_id'                     =>  $movementData->origin_id,
                'register_cpf_cnpj'             =>  $movementData->register_cpf_cnpj,
                'register_description'          =>  $movementData->register_description,
                'register_master_id'            =>  $movementData->register_master_id,
                'register_name'                 =>  $movementData->register_name,
                'tax_percentage'                =>  $movementData->tax_percentage,
                'tax_value'                     =>  $movementData->tax_value,
                'value'                         =>  $movementData->value,
                'manager_name'                  =>  $movementData->manager_name,
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );
        $file_name = "Comissão_Detalhada.pdf";
        $pdf       = PDF::loadView('reports/comission_manager_detail', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getManagerRegistersPdf(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [80];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $managers_registers                     = new ManagersRegister();
        $managers_registers->manager_detail_id  = $request->manager_detail_id;
        $managers_registers->manager_unique_id  = $request->manager_unique_id;
        // $managers_registers->register_master_id = $checkAccount->register_master_id;
        $managers_registers->onlyActive         = $request->onlyActive;
        $items = [];


        foreach($managers_registers->getManagersRegister() as $managerData){
            array_push($items, (object) [
                'register_cpf_cnpj'       =>  $managerData->register_cpf_cnpj,
                'register_name'           =>  $managerData->register_name,
                'first_commission'        =>  $managerData->first_commission,
                'first_commission_days'   =>  $managerData->first_commission_days,
                'mngr_rgstr_commission'   =>  $managerData->mngr_rgstr_commission,
                'mngr_rgstr_created_at'   =>  $managerData->mngr_rgstr_created_at ? \Carbon\Carbon::parse($managerData->mngr_rgstr_created_at)->format('d/m/Y') : null
            ]);
        }
        $data = (object) array(
            "movement_data"     => $items
        );

        $file_name = "Cadastros_Vinculados_Gerente.pdf";
        $pdf       = PDF::loadView('reports/manager_detail', compact('data'))->setPaper('a3', 'landscape')->download($file_name, ['Content-Type: application/pdf']);
        return response()->json(array("success" => "true", "file_name" => $file_name, "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function getManagerRegistersExcel(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [80];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $header = collect([
            [
                'register_cpf_cnpj'      => 'CPF/CNPJ',
                'register_name'          => 'Nome/Razão Social',
                'first_commission'       => 'Comissão Inicial',
                'first_commission_days'  => 'Vigência Comissão Inicial (Dias)',
                'mngr_rgstr_commission'  => 'Comissão',
                'mngr_rgstr_created_at'  => 'Vinculado Em'
            ]
        ]);


        $managers_registers                     = new ManagersRegister();
        $managers_registers->manager_detail_id  = $request->manager_detail_id;
        $managers_registers->manager_unique_id  = $request->manager_unique_id;
        $managers_registers->onlyActive         = $request->onlyActive;

        $data = [];

        $facilites = new Facilites();

        foreach ($managers_registers->getManagersRegisterExcel() as $item) {

            array_push($data, (object) [
                'register_cpf_cnpj'             => $facilites->mask_cpf_cnpj($item->register_cpf_cnpj),
                'register_name'                 => $item->register_name,
                'first_commission'              => number_format($item->first_commission ,3,',','.'),
                'first_commission_days'         => $item->first_commission_days,
                'mngr_rgstr_commission'         => number_format($item->mngr_rgstr_commission ,3,',','.'),
                'mngr_rgstr_created_at'         => $item->mngr_rgstr_created_at ? \Carbon\Carbon::parse($item->mngr_rgstr_created_at)->format('d/m/Y') : null
            ]);
        }

        $manager_export        = new ExcelExportClass();
        $manager_export->value = $header->merge($data);

        return response()->json(array("success" => "Planilha gerada com sucesso", "file_name" => "Cadastros vinculados gerente.xlsx", "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "base64"=>base64_encode(Excel::raw($manager_export, \Maatwebsite\Excel\Excel::XLSX))));
    }
}
