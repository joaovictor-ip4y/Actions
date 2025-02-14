<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bank;
use App\Models\PayrollEmployeeDetail;
use App\Models\PayrollEmployee;
use App\Models\PayrollRelease;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use File;
use Illuminate\Support\Facades\Validator;
use App\Libraries\SimpleCNAB;
use App\Models\SystemFunctionMaster;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\PayrollRelease\PayrollReleaseService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PayrollEmployeeDetailController extends Controller
{
    protected $cnab_type;
    protected $payroll_identification;
    protected $payrollService;
    protected $payroll_sequence;

    public function __construct(PayrollReleaseService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    protected function setCnabType($type) {
        switch($type) {
            case 'employee':
                $this->cnab_type = '1';
                break;
            case 'release':
                $this->cnab_type = '2';
                break;
            default:
                $this->cnab_type = null;
                break;
        }
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [199];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

       
        $payroll_employee_detail                        = new PayrollEmployeeDetail();
        $payroll_employee_detail->id                    = $request->id;
        $payroll_employee_detail->id_in                 = $request->id_in;
        $payroll_employee_detail->employee_id           = $request->employee_id;
        $payroll_employee_detail->account_id            = $checkAccount->account_id;
        $payroll_employee_detail->master_id             = $checkAccount->master_id;
        $payroll_employee_detail->emply_accnt_id        = $request->emply_accnt_id;
        $payroll_employee_detail->status_id             = $request->status_id;
        $payroll_employee_detail->created_at            = $request->created_at;
        $payroll_employee_detail->onlyActive            = $request->onlyActive;
        $payroll_employee_detail->onlyDeleted           = $request->onlyDeleted;
        $payroll_employee_detail->value_start           = $request->value_start;
        $payroll_employee_detail->value_end             = $request->value_end;
        $payroll_employee_detail->salary_start          = $request->salary_start;
        $payroll_employee_detail->salary_end            = $request->salary_end;
        $payroll_employee_detail->bank_id               = $request->bank_id;
        $payroll_employee_detail->bank_agency           = $request->bank_agency;
        $payroll_employee_detail->bank_account          = $request->bank_account;
        $payroll_employee_detail->config_id             = $request->config_id;
        $payroll_employee_detail->email                 = $request->email;
        return response()->json($payroll_employee_detail->viewall());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [200];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'cpf_cnpj'=> ['required', 'string'],
            'name'=> ['required', 'string'],
            'salary'=> ['required', 'numeric'],
            'bank_id'=> ['required', 'integer'],
            'bank_agency'=> ['string'],
            'bank_account'=> ['string'],
            'bank_account_type'=> ['required', 'string'],
        ],[
            'cpf_cnpj.required' => 'Informe o CPF/CNPJ.',
            'name.required' => 'Informe o nome.',
            'salary.required' => 'Informe o salário.',
            'bank_id.required' => 'Informe o id do banco.',
            'bank_agency.required' => 'Informe a agência.',
            'bank_account.required' => 'Informe a conta.',
            'bank_account_type.required' => 'Informe o tipo de conta.',
        ]);

        $validator->sometimes(['bank_agency', 'bank_account'], 'required', function ($input) {
            return $input->bank_id != 1;
        });

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;
        //Verifica se é cpf e valida
        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return response()->json( array("error" => "CPF inválido") );
            }
        //Verifica se é cnpj e valida
        } else if(strlen($cpf_cnpj) == 14){
            if( $request->bank_account_type == 7){
                return response()->json( array("error" => "No momento não estamos realizando abertura de conta PJ para esta modalidade.") );
            }

            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json( array("error" => "CNPJ inválido") );
            }
        //Retorna erro se não for cpf ou cnpj
        } else {
            return response()->json( array("error" => "CPF ou CNPJ inválido") );
        }

        if($request->salary <= 0) {
            return response()->json( array("error" => "Informe um salário maior que 0.") );
        }

        if($request->bank_account_type != 7 && $request->bank_id != 1){
            if(PayrollEmployeeDetail::where('bank_account', $request->bank_account)->where('bank_agency', $request->bank_agency)->where('bank_id', $request->bank_id)->where('account_id', $checkAccount->account_id)->whereNull('deleted_at')->first()){
                return response()->json( array("error" => "Já existe um funcionário cadastrado com as informações bancárias fornecidas.") );
            }
        } else {
            $payroll_employee = PayrollEmployee::where('cpf_cnpj',$cpf_cnpj)->first();
            if($payroll_employee){
                $details = PayrollEmployeeDetail::where('employee_id', $payroll_employee['id'])->where('accnt_tp_id', $request->bank_account_type)->where('account_id', $checkAccount->account_id)->first();
                
                if(empty($details->deleted_at)){
                    return response()->json( array("error" => "Já existe um funcionário com as informações fornecidas.") );
                } else {
                    return response()->json( array("error" => "Este usuário já está em sua lista de funcionários desligados, reative-o ou entre em contato com nossa equipe de suporte.") );
                }
            }
        } 

        

       $emplAccntId = null;
       $bank = Bank::where('id','=',$request->bank_id)->first();

        $limitExternalEmployee = $this->checkLimitExternalEmployee($bank->number, $checkAccount->account_id);
        if(!$limitExternalEmployee->success){
            return response()->json(["error" => $limitExternalEmployee->message]);
        }

        if( DB::table('payroll_employees')->where('cpf_cnpj', '=', $cpf_cnpj)->count() == 0){
            
            $payroll_employees = new PayrollEmployee();
            $payroll_employees->cpf_cnpj = $cpf_cnpj;
            $payroll_employees->save();
            
            if(PayrollEmployeeDetail::create([
                'employee_id'       => $payroll_employees->id,
                'account_id'        => $checkAccount->account_id,
                'master_id'         => $checkAccount->master_id,
                'emply_accnt_id'    => $emplAccntId,
                'status_id'         => 1,
                'name'              => $request->name,
                'default_pay_value' => $request->salary > 0 ? $request->salary : 0,
                'config_id'         => $request->config_id,
                'bank_id'           => $request->bank_id,
                'bank_number'       => $bank->number,
                'bank_agency'       => $request->bank_agency,
                'bank_account'      => $request->bank_account,
                'accnt_tp_id'       => $request->bank_account_type,
                'email'             => $request->email,
                'salary'            => $request->salary > 0 ? $request->salary : 0
            ])){
                return response()->json(['success' => 'Funcionário cadastrado com sucesso']);
            } else {
                return response()->json(['error' => 'Ocorreu uma falha ao cadastrar o funcionário, por favor tente mais tarde']);
            }
        }else{
            $payroll_employee = PayrollEmployee::where('cpf_cnpj',$cpf_cnpj)->first();

            if(PayrollEmployeeDetail::create([
                'employee_id'       => $payroll_employee->id,
                'account_id'        => $checkAccount->account_id,
                'master_id'         => $checkAccount->master_id,
                'emply_accnt_id'    => $emplAccntId,
                'status_id'         => 1,
                'name'              => $request->name,
                'default_pay_value' => $request->salary > 0 ? $request->salary : 0,
                'config_id'         => $request->config_id,
                'bank_id'           => $request->bank_id,
                'bank_number'       => $bank->number,
                'bank_agency'       => $request->bank_agency,
                'bank_account'      => $request->bank_account,
                'accnt_tp_id'       => $request->bank_account_type,
                'email'             => $request->email,
                'salary'            => $request->salary > 0 ? $request->salary : 0
            ])){
                return response()->json(['success' => 'Funcionário cadastrado com sucesso']);
            }else {
                return response()->json(['error' => 'Ocorreu uma falha ao cadastrar o funcionário, por favor tente mais tarde']);
            }
        }
    }

    protected function checkLimitExternalEmployee($bank_number, $account_id): object
    {
        $account = Account::where('id', $account_id)->first();
        $limit_qtt = $account->external_employee_limit_qtt;

        //external bank
        if($bank_number != "000") {
            $payrollDetails = PayrollEmployeeDetail::where('account_id', '=', $account_id)->where('bank_number', '!=', "000")->whereNull('deleted_at')->count();          
            if( ($payrollDetails + 1) > $limit_qtt) {
                return (object) [
                    "success" => false,
                    "message" => "Limite para criação de funcionários externos atingido"
                ];
            }
        }

        return (object) [
            "success" => true,
            "message" => "Limite disponível"
        ];
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [202];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
        ],[
            'id.required' => 'Informe o id do funcionário.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if($payroll_employee_detail = PayrollEmployeeDetail::where('id','=',$request->id)->where('account_id', '=', $checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $payroll_employee_detail->deleted_at = \Carbon\Carbon::now();
            if($payroll_employee_detail->save()){
                return response()->json(array("success" => "Funcionário excluído com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao excluir Funcionário"));
            }
        }else{
            return response()->json(array("error" => "Nenhum Funcionário foi localizado"));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [201];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'name'=> ['required', 'string'],
            'bank_id'=> ['required', 'integer'],
            'bank_agency'=> ['required', 'string'],
            'bank_account'=> ['required', 'string'],
            'bank_account_type'=> ['required', 'string'],
        ],[
            'id.required' => 'Informe o id do funcionário.',
            'name.required' => 'Informe o nome.',
            'bank_id.required' => 'Informe o id do banco.',
            'bank_agency.required' => 'Informe a agência.',
            'bank_account.required' => 'Informe a conta.',
            'bank_account_type.required' => 'Informe o tipo de conta.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if(isset($request->value)){
            if( $request->value > 500000 ){
                return response()->json(array("error" => "Por motivo de segurança, não é permitido realizar transferências acima de R$ 500.000,00 em uma única transação, portanto será necessário definir um novo valor."));
            }

            if( $request->value <= 0 ){
                return response()->json(array("error" => "Informe um valor maior que 0"));
            }
        }

        if(isset($request->default_pay_value)){
            if( $request->default_pay_value > 500000 ){
                return response()->json(array("error" => "Por motivo de segurança, não é permitido realizar transferências acima de R$ 500.000,00 em uma única transação, portanto será necessário definir um novo valor."));
            }

            if( $request->default_pay_value <= 0 ){
                return response()->json(array("error" => "Informe um valor maior que 0"));
            }
        }

        if(isset($request->salary)){
            if( $request->salary > 500000 ){
                return response()->json(array("error" => "Por motivo de segurança, não é permitido realizar transferências acima de R$ 500.000,00 em uma única transação, portanto será necessário definir um novo valor."));
            }

            if( $request->salary <= 0 ){
                return response()->json(array("error" => "Informe um salário maior que 0"));
            }
        }       


        if( $payroll_employee_detail = PayrollEmployeeDetail::where('id','=',$request->id)->where('account_id ','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->first()){
            if($payroll_employee_detail->bank_id != 1 && $payroll_employee_detail->accnt_tp_id != 7){
                $payroll_employee_detail->bank_id           = $request->bank_id;
                $payroll_employee_detail->accnt_tp_id       = $request->bank_account_type;
            }
            
            $bank = Bank::where('id','=',$request->bank_id)->first();
            $payroll_employee_detail->name              = $request->name;
            $payroll_employee_detail->config_id         = $request->config_id;
            $payroll_employee_detail->bank_agency       = $request->bank_agency;
            $payroll_employee_detail->bank_account      = $request->bank_account;
            $payroll_employee_detail->bank_number       = $bank->number;
            $payroll_employee_detail->email             = $request->email;
            $payroll_employee_detail->deleted_at        = null;
            if(isset($request->value)){
                $payroll_employee_detail->default_pay_value = $request->value > 0 ? $request->value : 0;
            }
            if(isset($request->default_pay_value)){
                $payroll_employee_detail->default_pay_value = $request->default_pay_value > 0 ? $request->default_pay_value : 0;
            }
            if(isset($request->salary)){
                $payroll_employee_detail->salary = $request->salary > 0 ? $request->salary : 0;
            }
            if($payroll_employee_detail->save()){
                return response()->json(array("success" => "Funcionário atualizado com sucesso"));
            }
            
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a folha"));
        }

        return response()->json(array("error" => "Nenhum funcionário localizado"));
    }

    protected function receiptDownload(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [209];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
        ],[
            'id.required' => 'Informe o id do funcionário.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //
        
        $PayrollEmployeeDetail = new PayrollEmployeeDetail();
        $PayrollEmployeeDetail->id = $request->id;
        $PayrollEmployeeDetail->id_in = $request->id_in;
        $PayrollEmployeeDetail->account_id = $checkAccount->account_id;
        $PayrollEmployeeDetail->master_id = $checkAccount->master_id;
        $items = [];
        $total_pay = 0;

        foreach($PayrollEmployeeDetail->viewall() as $movementData){
            array_push($items, (object)[
                "pyrll_emply_dtl_name"                    => $movementData->pyrll_emply_dtl_name,
                "pyrll_emply_cpf_cnpj"                    => Facilites::mask_cpf_cnpj($movementData->pyrll_emply_cpf_cnpj),
                "bank_name"                               => $movementData->bank_name,
                "pyrll_emply_dtl_bank_agency"             => $movementData->pyrll_emply_dtl_bank_agency,
                "pyrll_emply_dtl_bank_account"            => Facilites::mask_account($movementData->pyrll_emply_dtl_bank_account),
                "pyrll_emply_dtl_default_pay_value"       => $movementData->pyrll_emply_dtl_default_pay_value,
                "config_description"                      => $movementData->config_description,
                "pyrll_emply_dtl_last_release_date"       => $movementData->pyrll_emply_dtl_last_release_date ? \Carbon\Carbon::parse($movementData->pyrll_emply_dtl_last_release_date)->format('d/m/Y') : null,
            ]);
        }

        $data = (object) array(
            "movement_data" => $items
        );

        $file_name = 'Folha_de_Pagamento_Funcionario.pdf';
        $pdf = PDF::loadView('reports/receipt_payroll_employees',compact('data'))->setPaper('a4', 'landscape')->download($file_name,['Content-Type: application/pdf']);
        return response()->json(array(
            "success"   => "true",
            "file_name" => $file_name,
            "mime_type" => "application/pdf",
            "base64"    => base64_encode($pdf)
        ));
    }

    protected function updateValue(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [203];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'default_pay_value'=> ['required', 'numeric'],
        ],[
            'id.required' => 'Informe o id do funcionário.',
            'default_pay_value.required' => 'Informe o valor de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //


        if($request->default_pay_value <= 0){
            return response()->json(array("error" => "Valor de pagamento deve ser maior que zero"));
        }

        if( $payroll_employee_detail = PayrollEmployeeDetail::where('id','=',$request->id)->where('account_id ','=',$checkAccount->account_id)->where('master_id','=',$checkAccount->master_id)->first()){
            $payroll_employee_detail->default_pay_value = $request->default_pay_value;
            if($payroll_employee_detail->save()){
                return response()->json(array("success" => "Valor atualizado com sucesso"));
            } else {
                return response()->json(array("error" => "Ocorreu um erro ao atualizar o valor do funcionário"));
            }
        }else{
            return response()->json(array("error" => "Funcionário não localizado"));
        }
    }

    protected function searchPayrollEmployee(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $payrollEmployeeDetail                     = new PayrollEmployeeDetail();
        $payrollEmployeeDetail->master_id          = $checkAccount->master_id;
        // $payrollEmployeeDetail->register_master_id = $request->header('registerId');
        $payrollEmployeeDetail->search             = $request->search;
        $payrollEmployeeDetail->onlyActive         = $request->only_active;

        $ret['results'] = $payrollEmployeeDetail->searchEmployee();

        return response()->json($ret);
    }

    protected function importCsv(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [200];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json([
                "error" => $checkAccount->message, 
                "has_permission" => $checkAccount->has_permission
            ]);
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|string', 
        ], [
            'csv_file.required' => 'Informe o arquivo Excel.',
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        try {
            $base64String = $request->csv_file;

            if (strpos($base64String, ';base64') !== false) {
                $base64String = explode(',', $base64String)[1];
            }

            $decodedFile = base64_decode($base64String);
            if ($decodedFile === false) {
                return response()->json(["error" => "Falha ao decodificar o arquivo Base64."]);
            }

            $tempFilePath = tempnam(sys_get_temp_dir(), 'excel_') . '.xlsx';
            file_put_contents($tempFilePath, $decodedFile);

            $spreadsheet = IOFactory::load($tempFilePath);
            unlink($tempFilePath); 

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $header = array_shift($rows); 
            $data = [];

            foreach ($rows as $row) {
                $data[] = array_combine($header, $row); 
            }

            if (empty($data)) {
                return response()->json(["error" => "Erro ao importar o Excel"]);
            }

            $validate = new Facilites();
            $arrayError = [];

            foreach ($data as $row) {
                unset($cpf_cnpj);
                unset($validate->cpf_cnpj);

                $cpf_cnpj = preg_replace('/[^0-9]/', '', $row['CPF']);
                $validate->cpf_cnpj = $cpf_cnpj;

                if (empty($row['NOME']) || empty($row['SALARIO']) || empty($row['CPF'])){
                    return response()->json(["error" => "Faltam informações para a criação de alguns funcionários, certifique-se de todos os dados estarem preenchidos." ]);
                }

                if (!$validate->validateCPF($cpf_cnpj)) {
                    return response()->json(["error" => "Cpf do funcionário '". $row['NOME']. "' é inválido." ]);
                }

                if ($row['SALARIO'] <= 0) {
                    return response()->json(["error" => "Informe um salário maior que 0."]);
                }

                $emplAccntId = null;

                if (DB::table('payroll_employees')->where('cpf_cnpj', '=', $cpf_cnpj)->count() == 0) {
                    $payroll_employee = new PayrollEmployee();
                    $payroll_employee->cpf_cnpj = $cpf_cnpj;
                    $payroll_employee->save();
                } else {
                    $payroll_employee = PayrollEmployee::where('cpf_cnpj', $cpf_cnpj)->first();
                }

                if (empty($payroll_employee)) {
                    array_push($arrayError, "Falha ao cadastrar o funcionário " . $row['NOME'] . " de cpf $cpf_cnpj");
                }

                if (DB::table('payroll_employee_details')
                    ->where('employee_id', $payroll_employee->id)
                    ->where('account_id', $checkAccount->account_id)
                    ->count() == 0) {
                    if (!PayrollEmployeeDetail::create([
                        'employee_id'       => $payroll_employee->id,
                        'account_id'        => $checkAccount->account_id,
                        'master_id'         => $checkAccount->master_id,
                        'emply_accnt_id'    => $emplAccntId,
                        'status_id'         => 1,
                        'name'              => $row['NOME'],
                        'default_pay_value' => $row['SALARIO'] > 0 ? $row['SALARIO'] : 0,
                        'config_id'         => null,
                        'bank_id'           => 1,
                        'bank_number'       => '000',
                        'bank_agency'       => '0001',
                        'bank_account'      => null,
                        'accnt_tp_id'       => 7,
                        'email'             => null,
                        'salary'            => $row['SALARIO'] > 0 ? $row['SALARIO'] : 0,
                    ])) {
                        array_push($arrayError, "Falha ao cadastrar o funcionário " . $row['NOME'] . " de cpf $cpf_cnpj");
                    }
                } else {
                    $payroll = PayrollEmployeeDetail::where('employee_id', '=', $payroll_employee->id)
                    ->where('account_id', $checkAccount->account_id)
                    ->first();

                    if ($payroll->account_id == $checkAccount->account_id) {
                        $payroll->default_pay_value = $row['SALARIO'] > 0 ? $row['SALARIO'] : null;
                        $payroll->salary = $row['SALARIO'] > 0 ? $row['SALARIO'] : null;
                        $payroll->save();
                    }
                }
            }

            if (sizeof($arrayError) == 0) {
                return response()->json(["success" => "Excel importado e funcionário(s) criado(s) com sucesso"]);
            } else {
                return response()->json([
                    "error" => "Ocorreu um erro ao importar o Excel e criar " . sizeof($arrayError) . " funcionário(s), por favor tente novamente mais tarde",
                    "error_list" => $arrayError,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(["error" => "Erro ao processar o arquivo: " . $e->getMessage()]);
        }
    }

    protected function importCnabPayrollEmployee(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        if( (SystemFunctionMaster::where('system_function_id','=',6)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if($request->fileName != ''){            

            $fileName =  strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName);
            if(Storage::disk('charge_upload')->put( $fileName, base64_decode($request->file64))){
                $path =  Storage::disk('charge_upload')->path($fileName);
                switch(\File::extension($path)){
                    case 'rem':
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }
                        
                        $this->setCnabType('employee');
                        return $this->newCNAB($path, $fileName,$checkAccount->master_id, (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id, $checkAccount->account_id);
                    break;
                    case 'txt':
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }
                        
                        $this->setCnabType('employee');
                        return $this->newCNAB($path, $fileName,$checkAccount->master_id, (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id, $checkAccount->account_id);
                    break;
                    default:
                        return response()->json(array("error" => "Formato inválido para o arquivo ".$request->fileName ));
                    break;
                }
            } else {
                return response()->json(array("error" => "Não foi possível fazer o upload do arquivo ".$request->fileName));
            }
        } else {
            return response()->json(array("error" => "Não foi possível fazer o upload do arquivo"));
        }
    }

    protected function importCnabPayrollRelease(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        if( (SystemFunctionMaster::where('system_function_id','=',6)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Poxa, não foi possível efetuar essa transação agora! Temos uma instabilidade com a rede de Bancos Correspondentes. Por favor tente de novo mais tarde!"));
        }

        if($request->fileName != ''){            

            $fileName =  strtolower((\Carbon\Carbon::now())->format('YmdHis').'_'.rand().'_'.$request->fileName);
            if(Storage::disk('charge_upload')->put( $fileName, base64_decode($request->file64))){
                $path =  Storage::disk('charge_upload')->path($fileName);
                switch(\File::extension($path)){
                    case 'rem':
                        //check permission
                        $checkAccount = $accountCheckService->checkAccount();
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }

                        $this->setCnabType('release');
                        return $this->newCNAB($path, $fileName,$checkAccount->master_id, (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id, $checkAccount->account_id);
                    break;
                    case 'txt':
                        //check permission
                        $checkAccount = $accountCheckService->checkAccount();
                        if(!$checkAccount->success){
                            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
                        }
                        
                        $this->setCnabType('release');
                        return $this->newCNAB($path, $fileName,$checkAccount->master_id, (Account::where('id', '=', $checkAccount->account_id)->first())->register_master_id, $checkAccount->account_id);
                    break;
                    default:
                        return response()->json(array("error" => "Formato inválido para o arquivo ".$request->fileName ));
                        //delete file

                    break;
                }
            }
            return response()->json(array("error" => "Não foi possível fazer o upload do arquivo ".$request->fileName));
        } 
        return response()->json(array("error" => "Não foi possível fazer o upload do arquivo"));
    }

    protected function newCNAB($path, $fileName, $masterId, $registerMasterId, $accountId)
    {
        $errors = [];
        $cpfErrors = [];
        $statusPayroll = [];
        $cpfs = [];

        // get CNAB data
        $simpleCNAB = new SimpleCNAB();
        $simpleCNAB->pathFile = $path;
        $simpleCNAB->optionType = 'readPayroll';
        $cnabData = (object) json_decode($simpleCNAB->getPayrollCNABFile());

        if( ! $cnabData->success ){
            return response()->json(array("error" => $cnabData->error));
        }

        $this->payroll_identification = $this->gerarHashFolhaPagamento();
        $this->payroll_sequence = $this->payrollService->getNextPayrollSequence($accountId);
        
        foreach($cnabData->data->registers as $register) {
            $payrollData = (object) array(
                'nomeFavorecido'     => $register->nomeFavorecido,
                'valorPgto'          => $register->valorPgto,
                'numeroInscricao'    => $register->numeroInscricao,
            );        

            if($this->cnab_type == '1') {
                $responsePayroll = $this->newPayrollEmployee($payrollData, $masterId, $accountId);
            }

            if($this->cnab_type == '2') {
                $responsePayroll = $this->checkListPayrollRelease($payrollData, $masterId, $accountId);
                if($responsePayroll->success) {
                    array_push($cpfs, $responsePayroll->cpf);
                }
            }
                        
            if( !$responsePayroll->success ){
                array_push($errors, $responsePayroll);
            }

        }

        if( sizeof($errors) > 0 || sizeof($cpfErrors) > 0 ){
            return response()->json(array("error" => $errors, "cpf_errors" => !empty($cpfErrors) ? $cpfErrors : null, "status_payroll" => !empty($statusPayroll) ? $statusPayroll : null));
        }

        $employeeIds = [];
        if( $this->cnab_type == '2' ){    
            foreach($cpfs as $cpf) {
                $employeeId = PayrollEmployee::where('cpf_cnpj', '=', $cpf)->first();
                array_push($employeeIds, $employeeId->id);
            }

            $payrollDetail = new PayrollEmployeeDetail();
            $payrollDetail->employee_id_in = $employeeIds;
            $payrollDetail->account_id = $accountId;
            $data = $payrollDetail->viewall();
        } 

        return response()->json(array("success" => "Registros do arquivo importados com sucesso.", "data" => isset($data) ? $data : null));                                

    }

    public function newPayrollEmployee($payrollData, $masterId, $accountId)
    {        
        $validate = new Facilites();

        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $payrollData->numeroInscricao);
        $cpf = substr($cpf_cnpj, 0, 11);        
        $validate->cpf_cnpj = $cpf;

        if( !$validate->validateCPF($cpf) ){
            return (object)[
                'success' => false, 
                'message' => "CPF inválido.", 
                "cpf" => $cpf,
                "name" => $payrollData->nomeFavorecido,
                "valor" => $payrollData->valorPgto,
            ];
        }            
        
        if( DB::table('payroll_employees')->where('cpf_cnpj', $cpf)->count() == 0){
            $payroll_employee = PayrollEmployee::create([
                'cpf_cnpj' => $cpf,
                'created_at'        => \Carbon\Carbon::now()
            ]);                                    
        } else {
            $payroll_employee = PayrollEmployee::where('cpf_cnpj',$cpf)->first();
        }

        if(empty($payroll_employee)) {
            return (object)['success' => false, 'message' => "Ocorreu uma falha ao cadastrar o funcionário na folha de pagamento, por favor tente mais tarde.", "cpf" => $cpf];
        }

        if( DB::table('payroll_employee_details')->where('employee_id', $payroll_employee->id)->where('account_id', $accountId)->count() == 0){
            if(!PayrollEmployeeDetail::create([
                'employee_id'       => $payroll_employee->id,
                'account_id'        => $accountId,
                'master_id'         => $masterId,
                'emply_accnt_id'    => null,
                'status_id'         => 1,
                'name'              => $payrollData->nomeFavorecido,
                'default_pay_value' => $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0,
                'salary'            => $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0,
                'accnt_tp_id'       => 7, 
                'bank_id'           => 1,
                'created_at'        => \Carbon\Carbon::now()
            ])){
                return (object)['success' => false, 'message' => "Ocorreu uma falha ao cadastrar o funcionário na folha de pagamento, por favor tente mais tarde.", "cpf" => $cpf];
            }
        } else {
            $payroll = PayrollEmployeeDetail::where('employee_id','=',$payroll_employee->id)->where('account_id', $accountId)->first();
            
            if($payroll->account_id == $accountId) {
                $payroll->default_pay_value = $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0;
                $payroll->salary = $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0;
                $payroll->save();
            }
        }

        return (object)['success' => true];
        
    }

    public function newPayrollRelease($payrollData, $employeeDetailId) 
    {   
        $payroll_employee_detail = PayrollEmployeeDetail::where('id', '=', $employeeDetailId)->whereNull('deleted_at')->first(); 

        PayrollRelease::create([
            'uuid'                   => Str::orderedUuid(),
            'emply_dtl_id'           => $payroll_employee_detail->id,
            'account_id'             => $payroll_employee_detail->account_id,
            'emply_accnt_id'         => $payroll_employee_detail->emply_accnt_id,
            'master_id'              => $payroll_employee_detail->master_id,
            'payment_value'          => $payroll_employee_detail->default_pay_value > 0 ? $payroll_employee_detail->default_pay_value : 0,
            'status_id'              => 5,
            'payroll_identification' => $this->payroll_identification,
            'created_at'             => \Carbon\Carbon::now(),
            'payroll_sequence'       => $this->payroll_sequence
        ]);        
    }

    public function gerarHashFolhaPagamento()
    {
        $mesAno = now()->format('my'); 
        $uuid = Str::orderedUuid();
        $hash = $mesAno . '-' . $uuid;
        return $hash;
    }

    public function checkListPayrollRelease($payrollData, $masterId, $accountId)
    {        
        $validate = new Facilites();

        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $payrollData->numeroInscricao);
        $cpf = substr($cpf_cnpj, 0, 11);        
        $validate->cpf_cnpj = $cpf;

        if( !$validate->validateCPF($cpf) ){
            return (object)[
                'success' => false, 
                'message' => "CPF inválido.", 
                "cpf" => $cpf,
                "name" => $payrollData->nomeFavorecido,
                "valor" => $payrollData->valorPgto,
                "status_payroll" => "error"
            ];
        }                    

        if( DB::table('payroll_employees')->where('cpf_cnpj', $cpf)->count() == 0){
            $payroll_employee = PayrollEmployee::create([
                'cpf_cnpj' => $cpf,
                'created_at'        => \Carbon\Carbon::now()
            ]);    

            $payroll_employee_detail = PayrollEmployeeDetail::create([
                'employee_id'       => $payroll_employee->id,
                'account_id'        => $accountId,
                'master_id'         => $masterId,
                'emply_accnt_id'    => null,
                'status_id'         => 1,
                'name'              => $payrollData->nomeFavorecido,
                'default_pay_value' => $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0,
                'salary'            => $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0,
                'bank_number'       => '000',
                'bank_agency'       => '0001',
                'bank_account'      => null,
                'accnt_tp_id'       => 7, 
                'bank_id'           => 1,
                'created_at'        => \Carbon\Carbon::now()
            ]);
            
            if(empty($payroll_employee) || empty($payroll_employee_detail)) {
                return (object)['success' => false, 'message' => "Ocorreu uma falha ao cadastrar o funcionário, por favor tente mais tarde." , "cpf" => $cpf, "status_payroll" => "error"];
            }

            return (object)['success' => true, "cpf" => $cpf];
        } 

        $payroll_employee = PayrollEmployee::where('cpf_cnpj', $cpf)->first();
        
        if( DB::table('payroll_employee_details')->where('employee_id', '=', $payroll_employee->id)->where('account_id', '=', $accountId)->whereNull('deleted_at')->count() == 0) {
            if(!PayrollEmployeeDetail::create([
                'employee_id'       => $payroll_employee->id,
                'account_id'        => $accountId,
                'master_id'         => $masterId,
                'emply_accnt_id'    => null,
                'status_id'         => 1,
                'name'              => $payrollData->nomeFavorecido,
                'default_pay_value' => $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0,
                'salary'            => $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0,
                'bank_number'       => '000',
                'bank_agency'       => '0001',
                'bank_account'      => null,
                'accnt_tp_id'       => 7, 
                'bank_id'           => 1,
                'created_at'        => \Carbon\Carbon::now()
            ])) {
                return (object)['success' => false, 'message' => "Ocorreu uma falha ao cadastrar o funcionário, por favor tente mais tarde." , "cpf" => $cpf, "status_payroll" => "error"];
            }
        } 

        $payrollDetail = PayrollEmployeeDetail::where('employee_id', '=', $payroll_employee->id)->where('account_id', '=', $accountId)->whereNull('deleted_at')->first();
        $payrollDetail->default_pay_value = $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0;
        $payrollDetail->salary = $payrollData->valorPgto > 0 ? $payrollData->valorPgto : 0;
        $payrollDetail->save();

        return (object)['success' => true, "cpf" => $cpf];
    }

    protected function reactivateEmployee(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'string'],
        ],[
            'id.required' => 'Informe o identificador do funcionário.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "error" => $validator->errors()->first()
            ]);
        }

        $employee = PayrollEmployeeDetail::where('id', $request->id)->first();          
        $result = $this->checkLimitExternalEmployee($employee->bank_number, $checkAccount->account_id);
        if(!$result->success){
            return response()->json(
                ["error" => $result->message]
            );
        }

        $result = $this->payrollService->reactivateEmployee(
            $request->id
        );

        return response()->json($result);
    }

    public function checkEmployee(Request $request){
        $validate = Validator::make($request->all(), [
            'cpf' => 'required|string|max:11|exists:payroll_employees,cpf_cnpj',
        ], [
            'cpf.required' => 'O campo cpf é obrigatório.',
            'cpf.string'   => 'O campo cpf deve ser uma string.',
            'cpf.max'      => 'cpf inválido.',
            'cpf.exists'   => 'Não estamos realizando abertura de contas PF neste momento.',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'error' => $validate->errors()->first(),
            ], 200);
        }

        $payroll_employee = PayrollEmployee::where('cpf_cnpj', $request['cpf'])->first();
        $payroll_employee_detail = PayrollEmployeeDetail::where('employee_id' , $payroll_employee['id'])->where('accnt_tp_id', 7)->first();

        if(!$payroll_employee_detail){
            return response()->json(array("error" => "Não estamos realizando abertura de contas PF neste momento."), 200);
        }

        return response()->json(array("success" => "Usuário encontrado com sucesso"), 200);
    }
    
}
