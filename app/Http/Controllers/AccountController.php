<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountMovement;
use App\Models\ApiConfig;
use App\Models\RegisterMaster;
use App\Models\Register;
use App\Models\RegisterDetail;
use App\Models\RgstrLmtVlItm;
use App\Models\RgstrTxVlItm;
use App\Models\AccntLmtVlItm;
use App\Models\AccntTxVlItms;
use App\Models\Profile;
use App\Models\SrvcBsktGrpItm;
use App\Models\LmtGrpItm;
use App\Models\UsrRltnshpPrmssn;
use App\Libraries\Facilites;
use App\Libraries\ApiBancoRendimento;
use App\Libraries\ApiMoneyPlus;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Account\MovementTaxService;
use App\Services\Failures\sendFailureAlert;
use App\Classes\Account\AccountClass;
use App\Classes\Account\AccountMovementFutureClass;
use App\Classes\BancoMoneyPlus\BancoMoneyPlusClass;
use App\Models\UserRelationship;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Classes\ExcelExportClass;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class AccountController extends Controller
{
    protected function get(Request $request)
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

        $account = new Account();
        $account->master_id                          = $checkAccount->master_id;
        $account->register_master_id                 = $request->register_master_id;
        $account->account_type_id                    = $request->account_type_id;
        $account->profile_id                         = $request->profile_id;
        $account->srvc_bskt_id                       = $request->service_basket_id;
        $account->lmt_grp_id                         = $request->limit_group_id;
        $account->account_number                     = $request->account_number;
        $account->onlyActive                         = $request->onlyActive;
        $account->onlyDeleted                        = $request->onlyDeleted;
        $account->is_antecipation_charge_liquidation = $request->is_antecipation_charge_liquidation;

        $cpf_cnpj = preg_replace( '/[^0-9]/is', '', $request->cpf_cnpj );
        $account->cpf_cnpj = $cpf_cnpj;

        $account->register_id_equal_to = $request->register_id_equal_to; //cadastro
        $account->account_type_id_equal_to = $request->account_type_id_equal_to; //tipo de conta
        $account->created_at_start = $request->created_at_start; //data de abertura de
        $account->created_at_end = $request->created_at_end; //data de abertura ate
        $account->last_movement_start = $request->last_movement_start; //ultima movimentacao de
        $account->last_movement_end = $request->last_movement_end; //ultima movimentacao ate
        $account->balance_start = $request->balance_start; //saldo ate
        $account->balance_end = $request->balance_end; //saldo ate
        $account->classification_id_equal_to = $request->classification_id_equal_to; //classificacao
        $account->lmt_grp_id_equal_to = $request->lmt_grp_id_equal_to; //grupo de limite
        $account->srvc_bskt_id_equal_to = $request->srvc_bskt_id_equal_to; //cesta de serviço
        $account->profile_id_equal_to = $request->profile_id_equal_to; //perfil
        $account->account_number_equal_to = $request->account_number_equal_to; //numero da conta
        $account->alias_account_number_equal_to = $request->alias_account_number_equal_to; //numero da conta bmp
        $account->celcoin_account_equal_to = $request->celcoin_account_equal_to; //numero da conta
        $account->alias_account_safe_account_equal_to = $request->alias_account_safe_account_equal_to; //numero da conta safe do bmp
        

        $userHasPermissionToSeeBalance = false;
        if(UsrRltnshpPrmssn::whereIn('permission_id',[409])->where('usr_rltnshp_id','=',$checkAccount->user_relationship_id)->whereNull('deleted_at')->count() > 0){
            $userHasPermissionToSeeBalance = true;
        }

        $returnArray = [];
        foreach($account->getAccounts() as $acc){
            $balance = 0;
            if($userHasPermissionToSeeBalance){
                $balance = $acc->balance;
            }
            array_push($returnArray, [
                'id' => $acc->id,
                'master_id' => $acc->master_id,
                'register_master_id' => $acc->register_master_id,
                'dtl_id' => $acc->dtl_id,
                'description' => $acc->description,
                'name' => $acc->name,
                'cpf_cnpj' => $acc->cpf_cnpj,
                'register_description' => $acc->register_description,
                'account_type_id' => $acc->account_type_id,
                'account_type_description' => $acc->account_type_description,
                'profile_id' => $acc->profile_id,
                'profile_description' => $acc->profile_description,
                'srvc_bskt_id' => $acc->srvc_bskt_id,
                'service_basket_description' => $acc->service_basket_description,
                'lmt_grp_id' => $acc->lmt_grp_id,
                'limit_group_description' => $acc->limit_group_description,
                'account_description' => $acc->account_description,
                'account_register_number' => $acc->account_register_number,
                'sub_account_register_number' => $acc->sub_account_register_number,
                'account_checker_number' => $acc->account_checker_number,
                'account_number' => $acc->account_number,
                'available_value' => $acc->available_value,
                'last_check_date' => $acc->last_check_date,
                'account_available' => $acc->account_available,
                'signature_number' => $acc->signature_number,
                'signature_min_value' => $acc->signature_min_value,
                'master_cpf_cnpj' => $acc->master_cpf_cnpj,
                'master_name' => $acc->master_name,
                'balance' => $balance,
                'first_movement' => $acc->first_movement,
                'last_movement' => $acc->last_movement,
                'status_id' => $acc->status_id,
                'status_description' => $acc->status_description,
                'limit_vl' => $acc->limit_vl,
                'tranche_vl' => $acc->tranche_vl,
                'guarantee_vl' => $acc->guarantee_vl,
                'liquidity_vl' => $acc->liquidity_vl,
                'is_alias_account' => $acc->is_alias_account,
                'alias_account_created_at' => $acc->alias_account_created_at,
                'alias_account_deleted_at' => $acc->alias_account_deleted_at,
                'alias_account_number' => $acc->alias_account_number,
                'alias_account_minimum_value' => $acc->alias_account_minimum_value,
                'alias_account_keep_balance' => $acc->alias_account_keep_balance,
                'alias_account_safe_account' => $acc->alias_account_safe_account,
                'charge_registration_api_id' => $acc->charge_registration_api_id,
                'add_money_charge_registration_api_id' => $acc->add_money_charge_registration_api_id,
                'transfer_api_id' => $acc->transfer_api_id,
                'charge_agency' => $acc->charge_agency,
                'charge_account' => $acc->charge_account,
                'charge_covenant' => $acc->charge_covenant,
                'charge_wallet' => $acc->charge_wallet,
                'charge_wallet_variation' => $acc->charge_wallet_variation,
                'charge_our_number_first' => $acc->charge_our_number_first,
                'charge_our_number_last' => $acc->charge_our_number_last,
                'charge_mandatory_validation_cnab_import' => $acc->charge_mandatory_validation_cnab_import,
                'charge_register_with_our_number_of_cnab' => $acc->charge_register_with_our_number_of_cnab,
                'classification_id' => $acc->classification_id,
                'classification_description' => $acc->classification_description,
                'celcoin_account_branch' => $acc->celcoin_account_branch,
                'celcoin_account' => $acc->celcoin_account,
                'celcoin_account_created_at' => $acc->celcoin_account_created_at,
                'celcoin_account_deactivate_at' => $acc->celcoin_account_deactivate_at,
                'celcoin_account_close_at' => $acc->celcoin_account_close_at,
                'celcoin_account_status_description' => $acc->celcoin_account_status_description,
                'can_send_charge_to_anticipation_review' => $acc->can_send_charge_to_anticipation_review,
                'inclusion_limit_transfer_payment_qtt' => $acc->inclusion_limit_transfer_payment_qtt,
                'inclusion_limit_bill_payment_qtt' => $acc->inclusion_limit_bill_payment_qtt,
                'inclusion_limit_payroll_payment_qtt' => $acc->inclusion_limit_payroll_payment_qtt,
                'inclusion_limit_pix_payment_qtt' => $acc->inclusion_limit_pix_payment_qtt,
                'external_employee_limit_qtt' => $acc->external_employee_limit_qtt,
                'billet_registration_fee_on_liquidation' => $acc->billet_registration_fee_on_liquidation,
                'billet_registration_fee_on_liquidation_limit_qtt' => $acc->billet_registration_fee_on_liquidation_limit_qtt,
                'created_at' => $acc->created_at,
                'updated_at' => $acc->updated_at,
                'deleted_at' => $acc->deleted_at
            ]);
        }
        return response()->json($returnArray);
    }

    protected function getForRelationship(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        //somente para master
        $account = new Account();
        $account->master_id = $checkAccount->master_id;
        $account->is_antecipation_charge_liquidation = $request->is_antecipation_charge_liquidation;
        return response()->json($account->getAccountsForRelationship());
    }

    protected function getAccountData(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        //era utilizado para retornar dados das contas ip4y automaticamente na transf entre contas, desabilitado

        $account            = new Account();
        $account->master_id = $checkAccount->master_id;
        $account->cpf_cnpj  = $request->cpf_cnpj;


        return response()->json(array(
            "success"         => "",
            "account_exists"  => 0,
            "cpf_cnpj"        => null,
            "name"            => null,
            "account_number"  => null,
            "account_bank_id" => null,
            "account_agency"  => null,
            "account_type_id" => null
        ));

        //disabled

        $accountData = $account->returnAccountData();

        if($accountData != ''){
            if($accountData->name != ''){
                return response()->json(array(
                    "success"         => "",
                    "account_exists"  => 1,
                    "cpf_cnpj"        => $accountData->cpf_cnpj,
                    "name"            => $accountData->name,
                    "account_number"  => $accountData->account_number,
                    "account_bank_id" => $accountData->account_bank_id,
                    "account_agency"  => $accountData->account_agency,
                    "account_type_id" => $accountData->account_type_id
                ));
            }
        }

        return response()->json(array(
            "success"         => "",
            "account_exists"  => 0,
            "cpf_cnpj"        => null,
            "name"            => null,
            "account_number"  => null,
            "account_bank_id" => null,
            "account_agency"  => null,
            "account_type_id" => null
        ));
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster = RegisterMaster::where('id' , '=', $request->register_master_id)->where('master_id', '=', $checkAccount->master_id)->first();
        $register       = Register::where('id', '=', $registerMaster->register_id)->first();
        $registerDetail = RegisterDetail::where('register_master_id', '=', $registerMaster->register_id)->first();

        $accountType = 1;
        if(strlen($register->cpf_cnpj) == 11){
            $accountType = 2;
        }

        if((Account::where('master_id','=',$registerMaster->master_id)->where('register_master_id','=',$registerMaster->id)->count())  > 0){
            $newSubAccount    = Account::where('master_id','=',$registerMaster->master_id)->where('register_master_id','=',$registerMaster->id)->first();
            $accountNumber    = $newSubAccount->account_register_number;
            $subAccountNumber = Account::getNewSubAccountNumber($registerMaster->master_id, $newSubAccount->account_register_number);
        } else {
            $accountNumber    = Account::getNewAccountNumber($registerMaster->master_id);
            $subAccountNumber = 1;
        }

        $checkerNumber = $this->createCheckerNumber( str_pad($accountNumber, 7, '0', STR_PAD_LEFT).str_pad($subAccountNumber, 2, '0', STR_PAD_LEFT) );

        if($account = Account::Create([
            'master_id'                            => $registerMaster->master_id,
            'register_master_id'                   => $registerMaster->id,
            'account_type_id'                      => $accountType,
            'profile_id'                           => $registerMaster->profile_id,
            'srvc_bskt_id'                         => $registerMaster->srvc_bskt_id,
            'lmt_grp_id'                           => $registerMaster->limit_group_id,
            'description'                          => $request->account_description,
            'available_value'                      => 0,
            'last_check_date'                      => \Carbon\Carbon::now(),
            'account_available'                    => 1,
            'account_register_number'              => $accountNumber,
            'sub_account_register_number'          => $subAccountNumber,
            'account_checker_number'               => $checkerNumber,
            'account_number'                       => str_pad($accountNumber, 7, '0', STR_PAD_LEFT).str_pad($subAccountNumber, 2, '0', STR_PAD_LEFT).$checkerNumber,
            'unique_id'                            => md5($registerMaster->id.date('Ymd').time()),
            'limit_vl'                             => 0,
            'tranche_vl'                           => 0,
            'guarantee_vl'                         => 0,
            'liquidity_vl'                         => 0,
            'inclusion_limit_transfer_payment_qtt' => 30,
            'inclusion_limit_bill_payment_qtt'     => 30,
            'inclusion_limit_payroll_payment_qtt'  => 30,
            'inclusion_limit_pix_payment_qtt'      => 30,
            'created_at'                           => \Carbon\Carbon::now()
        ])){
            $taxes = RgstrTxVlItm::whereNull('deleted_at')->where('rgstr_id','=',$registerMaster->id)->get();
            foreach($taxes as $tax){
                AccntTxVlItms::create([
                    'accnt_id'             => $account->id,
                    'tax_id'               => $tax->taxe_id,
                    'srvc_bskt_grp_itm_id' => $tax->srvc_bskt_grp_itm_id,
                    'value'                => $tax->value,
                    'percentage'           => $tax->percentage,
                    'created_at'           => \Carbon\Carbon::now()
                ]);
            }
            $limits = RgstrLmtVlItm::whereNull('deleted_at')->where('rgstr_id','=',$registerMaster->id)->get();
            foreach($limits as $limit){
                AccntLmtVlItm::create([
                    'accnt_id'       => $account->id,
                    'limit_id'       => $limit->limit_id,
                    'lmt_grp_itm_id' => $limit->lmt_grp_itm_id,
                    'value'          => $limit->value,
                    'percentage'     => $limit->percentage,
                    'created_at'     => \Carbon\Carbon::now()
                ]);
            }

            return response()->json(array(
                "success"            => "Conta cadastrada com sucesso",
                "account_id"         =>  $account->id,
                "register_master_id" =>  $account->register_master_id,
                "account_type_id"    =>  $account->account_type_id,
                "profile_id"         =>  $account->profile_id,
                "srvc_bskt_id"       =>  $account->srvc_bskt_id,
                "lmt_grp_id"         =>  $account->lmt_grp_id,
                "description"        =>  $account->description,
                "account_number"     =>  $account->account_number,
                'register_name'      =>  $registerDetail->name
            ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao cadastrar a conta"));
        }
    }

    protected function updateAccountProfile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [46];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id', '=', $request->account_id)->where('master_id', '=', $checkAccount->master_id)->first();
        $profile = Profile::where('id','=', $request->account_profile_id)->first();
        $account->profile_id = $request->account_profile_id;
        $account->lmt_grp_id = $profile->limit_group_id;
        $account->srvc_bskt_id = $profile->service_basket_id;
        if($account->save()){
            $limitValues = LmtGrpItm::where('limit_group_id','=',$profile->limit_group_id)->get();
            foreach($limitValues as $limitValue){
                $limit = AccntLmtVlItm::where('accnt_id','=',$account->id)->where('limit_id','=',$limitValue->limit_id)->first();
                $limit->value      = $limitValue->default_value;
                $limit->percentage = $limitValue->default_percentage;
                $limit->save();
            }
            $serviceBasketTaxes = SrvcBsktGrpItm::where('service_basket_id','=',$profile->service_basket_id)->get();
            foreach($serviceBasketTaxes as $serviceBasketTax){
                $tax = AccntTxVlItms::where('accnt_id','=',$account->id)->where('tax_id','=',$serviceBasketTax->tax_id)->first();
                $tax->value      = $serviceBasketTax->default_value;
                $tax->percentage = $serviceBasketTax->default_percentage;
                $tax->save();
            }
            return response()->json(array("success" => "Perfil atualizado com sucesso", "data" => ["limit_group_id" => $profile->limit_group_id, "service_basket_id" => $profile->service_basket_id] ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o perfil"));
        }
    }

    protected function updateAccountType(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [46];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id', '=', $request->account_id)->where('master_id', '=', $checkAccount->master_id)->first();
        $account->account_type_id = $request->type_id;
        if($account->save()){
            return response()->json(array("success" => "Tipo de conta atualizado com sucesso" ));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o perfil"));
        }
    }

    protected function updateAccountLimitGroup(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [45];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id','=',$request->account_id)->where('master_id', '=', $checkAccount->master_id)->first();
        $account->lmt_grp_id = $request->account_limit_group_id;
        if($account->save()){
            $limitValues = LmtGrpItm::where('limit_group_id','=',$request->account_limit_group_id)->whereNull('deleted_at')->get();
            foreach($limitValues as $limitValue){
                $limit = AccntLmtVlItm::where('accnt_id','=',$account->id)->where('limit_id','=',$limitValue->limit_id)->whereNull('deleted_at')->first();
                $limit->value      = $limitValue->default_value;
                $limit->percentage = $limitValue->default_percentage;
                $limit->save();

            }
            return response()->json(array("success" => "Grupo de limite atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o grupo de limite"));
        }
    }

    protected function updateAccountServiceBasket(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [48];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id','=',$request->account_id)->where('master_id', '=', $checkAccount->master_id)->first();
        $account->srvc_bskt_id = $request->account_srvc_bskt_id;
        if($account->save()){
            $serviceBasketTaxes = SrvcBsktGrpItm::where('service_basket_id','=',$request->account_srvc_bskt_id)->whereNull('deleted_at')->get();
            foreach($serviceBasketTaxes as $serviceBasketTax){
                $tax = AccntTxVlItms::where('accnt_id','=',$account->id)->where('tax_id','=',$serviceBasketTax->tax_id)->first();
                $tax->value      = $serviceBasketTax->default_value;
                $tax->percentage = $serviceBasketTax->default_percentage;
                $tax->save();
            }
            return response()->json(array("success" => "Cesta de serviço atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a cesta de serviço"));
        }
    }

    protected function updateAccountClassification(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [48];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id','=',$request->account_id)->where('master_id', '=', $checkAccount->master_id)->first();
        $account->classification_id = $request->classification_id;
        if($account->save()){
            return response()->json(array("success" => "Classificação atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a cesta de serviço"));
        }
    }

    public function createCheckerNumber($accountNumber)
    {
        $len = 10; //tentar trabalhar qtde de caracteres
        $arrayAccountNumber = str_split($accountNumber);
        $sum = 0;
        foreach($arrayAccountNumber as $number){
            $sum += ($number * $len);
            $len--;
        }
        $mod11 = $sum % 11;
        if($mod11 > 9){
            return '0';
        } else {
            return $mod11;
        }
    }

    protected function updateStatus(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [8];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id','=',$request->account_id)->where('master_id','=',$checkAccount->master_id)->first();
        $account->status_id = $request->account_status_id;
        if(!$account->save()){
            return response()->json(array("error" => "Erro ao atualizar o status da conta"));
        }else{
            return response()->json(array("success" => "Status da conta atualizado com sucesso"));
        }

    }

    protected function updateOperacionalValues(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [54];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id','=',$request->id)->where('master_id','=',$checkAccount->master_id)->first();
        $account->limit_vl      = $request->limit_vl;
        $account->tranche_vl    = $request->tranche_vl;
        $account->guarantee_vl  = $request->guarantee_vl;
        $account->liquidity_vl  = $request->liquidity_vl;
        if(!$account->save()){
            return response()->json(array("error" => "Erro ao atualizar os valores operacionais"));
        }else{
            return response()->json(array("success" => "Valores operacionais atualizados com sucesso"));
        }
    }

    protected function getUserFromAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = new Account();
        $account->register_master_id = $request->register_master_id;
        $account->onlyActive = $request->onlyActive;
        return response()->json($account->getUserFromAccount());
    }

    protected function getDocumentUserFromAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = new Account();
        $account->register_master_id = $request->register_master_id;
        $account->onlyActive         = $request->onlyActive;
        return response()->json($account->getDocumentUserFromAccount());
    }

    public function createAdministrationAccountMonthFee()
    {
        $account = new Account();
        $accountClass = new AccountClass();
        $movementTax = new MovementTaxService();

        $competence = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('m/Y');

       

        foreach($account->getAdministrationAccountMonthFee() as $accountAdmFee){
            if($accountAdmFee->value > 0){
             
                $feeValue = $accountAdmFee->value;

                // check balance
                $accountClass->account_id = $accountAdmFee->account_id;
                $accountClass->master_id = $accountAdmFee->master_id;

                $checkBalance = $accountClass->getAccountBalance();

                $balance = 0;
                if ($checkBalance->success) {
                    $balance = $checkBalance->data->balance;
                }

                if ($balance >= $feeValue) {

                    if( AccountMovement::where('account_id','=',$accountAdmFee->account_id)->whereNull('deleted_at')->count() > 0 ){

                        $movementTax->movementData = (object) [
                            'account_id'    => $accountAdmFee->account_id,
                            'master_id'     => $accountAdmFee->master_id,
                            'origin_id'     => $accountAdmFee->account_id,
                            'mvmnt_type_id' => 39,
                            'value'         => $accountAdmFee->value,
                            'description'   => $accountAdmFee->description.' | '.$competence
                        ];
                        if(!$movementTax->create()){
                            $sendFailureAlert               = new sendFailureAlert();
                            $sendFailureAlert->title        = 'Tarifa Manutenção Mensal Conta';
                            $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de manutenção de conta na conta: '.$accountAdmFee->account_number.', id conta: '.$accountAdmFee->account_id.', valor da tarifa: '.$accountAdmFee->value;
                            $sendFailureAlert->sendFailures();
                        }

                    }
                } else {
                    if ($balance > 0) {
                        
                        $pendingFee = $feeValue - $balance;

                        if( AccountMovement::where('account_id','=',$accountAdmFee->account_id)->whereNull('deleted_at')->count() > 0 ){

                            $movementTax->movementData = (object) [
                                'account_id'    => $accountAdmFee->account_id,
                                'master_id'     => $accountAdmFee->master_id,
                                'origin_id'     => $accountAdmFee->account_id,
                                'mvmnt_type_id' => 39,
                                'value'         => $balance,
                                'description'   => $accountAdmFee->description.' | '.$competence.' | PAGAMENTO PROPORCIONAL, PENDENTE: R$ '.number_format($pendingFee, 2, ',','.')
                            ];
                            if(!$movementTax->create()){
                                $sendFailureAlert               = new sendFailureAlert();
                                $sendFailureAlert->title        = 'Tarifa Manutenção Mensal Conta';
                                $sendFailureAlert->errorMessage = 'Não foi possível lançar o valor da tarifa de manutenção de conta na conta: '.$accountAdmFee->account_number.', id conta: '.$accountAdmFee->account_id.', valor da tarifa: '.$accountAdmFee->value;
                                $sendFailureAlert->sendFailures();
                            }

                            $accountMovementFuture = new AccountMovementFutureClass();
                            $accountMovementFuture->account_id = $accountAdmFee->account_id;
                            $accountMovementFuture->master_id = $accountAdmFee->master_id;
                            $accountMovementFuture->mvmnt_type_id = 39;
                            $accountMovementFuture->description = $accountAdmFee->description.' | '.$competence.' | COBRANÇA RESIDUAL';
                            $accountMovementFuture->value = $pendingFee;
                            $accountMovementFuture->create();
                        }
                    } else {
                        $accountMovementFuture = new AccountMovementFutureClass();
                        $accountMovementFuture->account_id = $accountAdmFee->account_id;
                        $accountMovementFuture->master_id = $accountAdmFee->master_id;
                        $accountMovementFuture->mvmnt_type_id = 39;
                        $accountMovementFuture->description = $accountAdmFee->description.' | '.$competence;
                        $accountMovementFuture->value = $feeValue;
                        $accountMovementFuture->create();
                    }

                }

            }
        }
    }

    protected function createRendimentoAliasAccount(Request $request){
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->is_alias_account == 1 and $accountData->alias_account_deleted_at == null){
            return response()->json(array("error" => "Conta já possui alias account definida"));
        }

        $apiConfig                      = new ApiConfig();
        $apiConfig->master_id           = $checkAccount->master_id;
        $apiConfig->api_id              = 14;
        $apiConfig->onlyActive          = 1;
        $apiData                        = $apiConfig->getApiConfig()[0];
        $apiRendimento                  = new ApiBancoRendimento();
        $apiRendimento->id_cliente      = Crypt::decryptString($apiData->api_client_id);
        $apiRendimento->chave_acesso    = Crypt::decryptString($apiData->api_key);
        $apiRendimento->autenticacao    = Crypt::decryptString($apiData->api_authentication);
        $apiRendimento->endereco_api    = Crypt::decryptString($apiData->api_address);
        $apiRendimento->agencia         = Crypt::decryptString($apiData->api_agency);
        $apiRendimento->conta_corrente  = Crypt::decryptString($apiData->api_account);


        $apiRendimento->account_number  = $accountData->account_number;
        $apiRendimento->cpf_cnpj        = $accountData->cpf_cnpj;
        $apiRendimento->name            = $accountData->name;

        $account = Account::where('id','=',$request->id)->first();
        $registerDetail = RegisterDetail::where('register_master_id','=',$account->register_master_id)->first();

        if( $account->alias_account_number != null and  $account->alias_account_number != '' and $account->alias_account_deleted_at != null){
            $apiRendimento->lq_conta_numero  = $accountData->alias_account_number;
            $apiRendimento->status           = 1;
            $aliasAccount = $apiRendimento->lqTedAlterarNomeStatus();
            if(isset($aliasAccount->status_code) ){
                if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201 or $aliasAccount->status_code == 204){
                    $account = Account::where('id','=',$request->id)->first();
                    $account->alias_account_deleted_at = null;
                    $account->is_alias_account = 1;
                    if($account->save()){
                        return response()->json(array("success" => "Alias Account Reativada com sucesso", "data" => $account));
                    }
                }
            }
            return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível reativar a alias account, por favor tente novamente mais tarde"));
        }

        $aliasAccount = $apiRendimento->lqTedIncluirContaPagamento();
        if(isset($aliasAccount->status_code) ){
            if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201){
                $account->is_alias_account = 1;
                $account->alias_account_created_at = \Carbon\Carbon::now();
                $account->alias_account_number = $account->account_number;
                $account->alias_account_deleted_at = null;
                if($account->save()){
                    return response()->json(array("success" => "Alias Account criada com sucesso", "data" => $account));
                } else {
                    return response()->json(array("error" => "Não foi possível criar a alias account, por favor tente novamente mais tarde"));
                }
            } else {
                return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível criar a alias account, por favor tente novamente mais tarde"));
            }
            return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível criar a alias account, por favor tente novamente mais tarde"));
        }
    }
    

    protected function deleteRendimentoAliasAccount(Request $request){
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->is_alias_account == 1 and $accountData->alias_account_deleted_at == null){
            
            $apiConfig                      = new ApiConfig();
            $apiConfig->master_id           = $checkAccount->master_id;
            $apiConfig->api_id              = 14;
            $apiConfig->onlyActive          = 1;
            $apiData                        = $apiConfig->getApiConfig()[0];
            $apiRendimento                  = new ApiBancoRendimento();
            $apiRendimento->id_cliente      = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso    = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao    = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api    = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia         = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente  = Crypt::decryptString($apiData->api_account);

            $apiRendimento->lq_conta_numero  = $accountData->alias_account_number;
            $apiRendimento->status           = 3;

            $aliasAccount = $apiRendimento->lqTedAlterarStatus();
            if(isset($aliasAccount->status_code) ){
                if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201 or $aliasAccount->status_code == 204){
                    $account = Account::where('id','=',$request->id)->first();
                    $account->alias_account_deleted_at = \Carbon\Carbon::now();
                    $account->is_alias_account = 0;
                    if($account->save()){
                        return response()->json(array("success" => "Alias Account Excluida com sucesso", "data" => $account));
                    }
                }
            }
            
            return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível excluir a alias account, por favor tente novamente mais tarde"));
        }

        return response()->json(array("error" => "Conta não possui alias account definida"));
    }


    protected function updateRendimentoAliasAccountName(Request $request){
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->is_alias_account == 1 and $accountData->alias_account_deleted_at == null){
            
            $apiConfig                      = new ApiConfig();
            $apiConfig->master_id           = $checkAccount->master_id;
            $apiConfig->api_id              = 14;
            $apiConfig->onlyActive          = 1;
            $apiData                        = $apiConfig->getApiConfig()[0];
            $apiRendimento                  = new ApiBancoRendimento();
            $apiRendimento->id_cliente      = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso    = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao    = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api    = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia         = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente  = Crypt::decryptString($apiData->api_account);

            $account = Account::where('id','=',$request->id)->first();
            $registerDetail = RegisterDetail::where('register_master_id','=',$account->register_master_id)->first();

            $apiRendimento->lq_conta_numero  = $accountData->alias_account_number;
            $apiRendimento->name             = $registerDetail->name;

            $aliasAccount = $apiRendimento->lqTedAlterarNome();
            if(isset($aliasAccount->status_code) ){
                if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201 or $aliasAccount->status_code == 204){
                    return response()->json(array("success" => "Alias Account Alterada com sucesso", "data" => $account));
                }
            }
            
            return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível alterar a alias account, por favor tente novamente mais tarde"));
        }

        return response()->json(array("error" => "Conta não possui alias account definida"));
    }


    protected function getRendimetoAliasAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->is_alias_account == 1 and $accountData->alias_account_deleted_at == null){
            
            $apiConfig                      = new ApiConfig();
            $apiConfig->master_id           = $checkAccount->master_id;
            $apiConfig->api_id              = 14;
            $apiConfig->onlyActive          = 1;
            $apiData                        = $apiConfig->getApiConfig()[0];
            $apiRendimento                  = new ApiBancoRendimento();
            $apiRendimento->id_cliente      = Crypt::decryptString($apiData->api_client_id);
            $apiRendimento->chave_acesso    = Crypt::decryptString($apiData->api_key);
            $apiRendimento->autenticacao    = Crypt::decryptString($apiData->api_authentication);
            $apiRendimento->endereco_api    = Crypt::decryptString($apiData->api_address);
            $apiRendimento->agencia         = Crypt::decryptString($apiData->api_agency);
            $apiRendimento->conta_corrente  = Crypt::decryptString($apiData->api_account);

          //  $apiRendimento->valor_consulta  = "42025280000132";

            $aliasAccount = $apiRendimento->lqTedListarContaPagamento();
            if(isset($aliasAccount->status_code) ){
                if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201 or $aliasAccount->status_code == 204){
                    
                    return response()->json(array("success" => $aliasAccount->body));
                    
                }
            }
            
            return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível excluir a alias account, por favor tente novamente mais tarde"));
        }

        return response()->json(array("error" => "Conta não possui alias account definida"));
    }


    protected function updateChargeRegistrationApi(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->charge_registration_api_id = $request->charge_registration_api_id;

        if ($account->save()) {
            return response()->json(array("success" => "API de registro de cobrança definida com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a API de registro de cobrança, por favor tente mais tarde"));
    }

    protected function updateEffectiveTransferAPI(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->transfer_api_id = $request->transfer_api_id;

        if ($account->save()) {
            return response()->json(array("success" => "API de efetivação de TED definida com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a API de efetivação de TED, por favor tente mais tarde"));
    }
    
    protected function updateAddMoneyChargeRegistrationApi(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->add_money_charge_registration_api_id = $request->add_money_charge_registration_api_id;

        if ($account->save()) {
            return response()->json(array("success" => "API de registro de cobrança definida com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a API de registro de cobrança, por favor tente mais tarde"));
    }

    protected function createBMPAliasAccount(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /
        
        $bmp = new BancoMoneyPlusClass();
        $bmp->master_id = $checkAccount->master_id;
        $bmp->account_id = $request->id;

        $alias = $bmp->createBMPAliasAccount();

        return response()->json($alias);

    }

    protected function deleteBMPAliasAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /
        

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->is_alias_account == 1 and $accountData->alias_account_deleted_at == null){
            
            $apiConfig = new ApiConfig();
            $apiConfig->master_id = $checkAccount->master_id;
            $apiConfig->api_id = 15;
            $apiConfig->onlyActive = 1;
            $apiData = $apiConfig->getApiConfig()[0];
            $apiMoneyPlus = new ApiMoneyPlus();
            $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);
            $apiMoneyPlus->alias_account_agency = $accountData->alias_account_agency;
            $apiMoneyPlus->alias_account_number = $accountData->alias_account_number;

            $moneyPlusBalance = 0;
            //Check balance to close account
            $checkBalance = $apiMoneyPlus->checkBalance();

            if ( isset( $checkBalance->data->vlrSaldo ) ) {
                $moneyPlusBalance = $checkBalance->data->vlrSaldo;
            }
           
            if ( isset( $checkBalance->data->vlrBloqueado ) ) {
                if( $checkBalance->data->vlrBloqueado > 0 ) {
                    return response()->json(array("error" => "Essa conta possuí ".$checkBalance->data->vlrBloqueado." bloqueado no BMP. Aguarde o desbloqueio para encerrar a conta."));
                }
            }

            if( $moneyPlusBalance > 0 ) {
                $apiMoneyPlusTransfer = new ApiMoneyPlus();
                $apiMoneyPlusTransfer->client_id = Crypt::decryptString($apiData->api_client_id);
                $apiMoneyPlusTransfer->api_address = Crypt::decryptString($apiData->api_address);
                $apiMoneyPlusTransfer->alias_account_agency = $accountData->alias_account_agency;
                $apiMoneyPlusTransfer->alias_account_number = $accountData->alias_account_number;

                $apiMoneyPlusTransfer->favored_agency = "00018";
                $apiMoneyPlusTransfer->favored_account = "00790584"; //Conta Recebedora BMP
                $apiMoneyPlusTransfer->favored_account_type = 3;

                $apiMoneyPlusTransfer->value = $moneyPlusBalance;

                $apiMoneyPlusTransfer->id = Str::orderedUuid();

                $transfer = $apiMoneyPlusTransfer->transferBetweenAccounts();

                if( !$transfer->success ){
                    
                    $errorMessage = null;
                    if( isset( $transfer->data->mensagem ) ) {
                        $errorMessage = $transfer->data->mensagem;
                    }

                    $sendFailureAlert               = new sendFailureAlert();
                    $sendFailureAlert->title        = 'Falha Transferencia para conta principal BMP ao encerrar conta';
                    $sendFailureAlert->errorMessage = 'Atenção, ocorreu uma falha ao transferir da conta BMP '.$accountData->alias_account_number.', para conta principal. Realize o processo de transferência e credite manualmente a conta destino. Erro '.$errorMessage ;
                    $sendFailureAlert->sendFailures();

                    return response()->json(array("error" => "Não foi possíve transferir o saldo de para a conta principal do BMP. ".$errorMessage ));
                }
            }

            sleep(11);

            $aliasAccount = $apiMoneyPlus->closeAccount();

            if(isset($aliasAccount->status_code) ){
                if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201 or $aliasAccount->status_code == 204){
                    $account = Account::where('id','=',$request->id)->first();
                    $account->alias_account_deleted_at = \Carbon\Carbon::now();
                    $account->is_alias_account = 0;
                    $account->alias_account_closing_code = $aliasAccount->data->codigo;
                    $account->alias_account_closing_status = $aliasAccount->data->situacao;
                    if($account->save()){
                        return response()->json(array("success" => "Alias Account Excluida com sucesso", "data" => $account));
                    }
                }
            }
            
            return response()->json(array("error" => "Devido a uma intermitência no banco correspondente, não foi possível excluir a alias account, por favor tente novamente mais tarde"));
        }

        return response()->json(array("error" => "Conta não possui alias account definida"));
    }

    public function setBMPAliasAccountPixLimit()
    {
        /*// ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /
        

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->is_alias_account == 1 and $accountData->alias_account_deleted_at == null){ */
            
            $apiConfig = new ApiConfig();
            $apiConfig->master_id = 1;
            $apiConfig->api_id = 15;
            $apiConfig->onlyActive = 1;
            $apiData = $apiConfig->getApiConfig()[0];
            $apiMoneyPlus = new ApiMoneyPlus();
            $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

            $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);
            /*$apiMoneyPlus->alias_account_agency = $accountData->alias_account_agency;
            $apiMoneyPlus->alias_account_number = $accountData->alias_account_number;*/

            $aliasAccount = $apiMoneyPlus->setPixLimit();

           

        //return response()->json(array("error" => "Conta não possui alias account definida"));
    }

    protected function updateBMPAliasAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        if($accountData->address_zip_code == '' or $accountData->address_zip_code == null){
            return response()->json(array("error" => "Endereço principal não definido para o cadastro da conta"));
        }
        
        if($accountData->phone == '' or $accountData->phone == null){
            return response()->json(array("error" => "Telefone principal não definido para o cadastro da conta"));
        }
        
        if($accountData->email == '' or $accountData->email == null){
            return response()->json(array("error" => "E-Mail principal não definido para o cadastro da conta"));
        }

        //Atualiza conta no BMP
        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];
        $apiMoneyPlus = new ApiMoneyPlus();
        $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

        $apiMoneyPlus->alias_account_agency = $accountData->alias_account_agency;
        $apiMoneyPlus->alias_account_number = $accountData->alias_account_number;
        $apiMoneyPlus->cpf_cnpj = $accountData->cpf_cnpj;
        $apiMoneyPlus->name = $accountData->name;
        $apiMoneyPlus->email = $accountData->email;
        $apiMoneyPlus->phone = $accountData->phone;
        $apiMoneyPlus->zip_code = $accountData->address_zip_code;
        $apiMoneyPlus->public_place = $accountData->address_public_place;
        $apiMoneyPlus->address = $accountData->address .', '.$accountData->address_number;
        $apiMoneyPlus->district = $accountData->address_district;
        $apiMoneyPlus->complement = $accountData->address_complement;
        $apiMoneyPlus->city = $accountData->address_city;
        $apiMoneyPlus->state = $accountData->address_state_short_description;
        
        $aliasAccount = $apiMoneyPlus->updateAccountData();
        if(isset($aliasAccount->status_code) ){
            if($aliasAccount->status_code == 200 or $aliasAccount->status_code == 201 or $aliasAccount->status_code == 204){
                return response()->json(array("success" => "Alias Account atualizada com sucesso", "data" => $accountData));
            }
        }
        return response()->json(array("error" => "Não foi possível atualizar a alias account, por favor tente novamente mais tarde"));
    }

    protected function checkBMPAliasAccountBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        /*$accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /
        */

        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        //Saldo conta no BMP
        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];
        $apiMoneyPlus = new ApiMoneyPlus();
        $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

        $apiMoneyPlus->alias_account_agency = $accountData->alias_account_agency;
        $apiMoneyPlus->alias_account_number = $accountData->alias_account_number;

        return response()->json(["success" => $apiMoneyPlus->checkBalance()]);
    }

    protected function checkBMPAliasAccountExtact(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        /*$accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /
        */
        
        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        //Saldo conta no BMP
        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];
        $apiMoneyPlus = new ApiMoneyPlus();
        $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

        $apiMoneyPlus->alias_account_agency = $accountData->alias_account_agency;
        $apiMoneyPlus->alias_account_number = $accountData->alias_account_number;

        return response()->json(["success" => $apiMoneyPlus->checkExtract()]);
    }
    
    protected function checkBMPAliasAccountMovement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        /*$accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /
        */
        
        $account = new Account();
        $account->id = $request->id;
        $accountData = $account->returnAccountData();

        if(!isset($accountData->cpf_cnpj)){
            return response()->json(array("error" => "Conta não localizada"));
        }

        //Saldo conta no BMP
        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];
        $apiMoneyPlus = new ApiMoneyPlus();
        $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

        $apiMoneyPlus->alias_account_agency = $accountData->alias_account_agency;
        $apiMoneyPlus->alias_account_number = $accountData->alias_account_number;

        return response()->json(["success" => $apiMoneyPlus->checkMovement()]);
    }

    protected function setAliasAccountMinimumValue(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        if( ! $account = Account::where('id', '=', $request->id)->where('is_alias_account', '=', 1)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Conta não possui Alias Account"));
        }

        if($request->alias_account_minimum_value < 0){
            return response()->json(array("error" => "Valor mínimo deve ser maior que zero"));
        }

        $account->alias_account_minimum_value = $request->alias_account_minimum_value;

        if($account->save()) {
            return response()->json(array("success" => "Valor mínimo para alias account definido com sucesso"));
        }
        return response()->json(array("success" => "Poxa, no momento não foi possível definir o valor mínimo para alias account, por favor tente mais tarde"));
    }

    protected function updateAliasAccountKeepBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        if( ! $account = Account::where('id', '=', $request->id)->where('is_alias_account', '=', 1)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Conta não possui Alias Account"));
        }

        if ($request->alias_account_keep_balance > 1 || $request->alias_account_keep_balance < 0){
            return response()->json(array("error" => "Opção de manter saldo na conta inválida"));
        }

        $account->alias_account_keep_balance = $request->alias_account_keep_balance;

        if($account->save()) {
            return response()->json(array("success" => "Opção de manter saldo na conta definido com sucesso"));
        }
        return response()->json(array("success" => "Poxa, no momento não foi possível definir a opção de manter saldo na conta, por favor tente mais tarde"));
    }

    protected function updateCanSendTitleToAntecipationReview(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        if (!$account = Account::where('id', '=', $request->account_id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->can_send_charge_to_anticipation_review = $request->can_send_charge_to_anticipation_review;

        if ($account->save()) {
            return response()->json(array("success" => "Opção de envio de títulos para análise de antecipação definida com sucesso"));
        }

        return response()->json(array("error" => "Poxa, no momento não foi possível definir a opção de envio de títulos para análise de antecipação, por favor tente mais tarde"));
    }

    protected function updateAccountChargeRegisterData(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [46];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account = Account::where('id', '=', $request->account_id)->where('master_id', '=', $checkAccount->master_id)->first();

        if($request->charge_register_with_our_number_of_cnab == 1){

            if($account->charge_registration_api_id != 13){
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, a API de emissão de boleto de cobrança deve ser: Banco do Brasil. Realize a alteração para continuar.'));
            }

            if($request->charge_agency != '47708') {
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, a agência deve ser: 47708. Realize a alteração para continuar.'));
            }
            
            if($request->charge_account != '00014293X') {
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, a conta deve ser: 00014293X. Realize a alteração para continuar.'));
            }

            if($request->charge_covenant != '3548018') {
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, o convênio deve ser: 3548018. Realize a alteração para continuar.'));
            }
            
            if($request->charge_wallet != '17') {
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, a carteira deve ser: 17. Realize a alteração para continuar.'));
            }
            
            if($request->charge_wallet_variation != '027') {
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, a variação da carteira deve ser: 027. Realize a alteração para continuar.'));
            }
            
            if($request->charge_mandatory_validation_cnab_import != 1) {
                return response()->json(array('error' => 'Para manter o nosso número da remessa na emissão do boleto, a opção | Validação obrigatória na importação do CNAB | deve estar selecionada. Realize a alteração para continuar.'));
            }
        }

        $account->charge_agency = $request->charge_agency;
        $account->charge_account = $request->charge_account;
        $account->charge_covenant = $request->charge_covenant;
        $account->charge_wallet = $request->charge_wallet;
        $account->charge_wallet_variation = $request->charge_wallet_variation;
        $account->charge_our_number_first = $request->charge_our_number_first;
        $account->charge_our_number_last = $request->charge_our_number_last;
        $account->charge_mandatory_validation_cnab_import = $request->charge_mandatory_validation_cnab_import;
        $account->charge_register_with_our_number_of_cnab =  $request->charge_register_with_our_number_of_cnab;

        if( ! $account->save() ) {
            return response()->json(array('error' => 'Poxa, não foi possíve atualizar os dados de registro de cobrança no momento, por favor tente novamente mais tarde'));
        }

        return response()->json(array('success' => 'Dados de registro de cobrança atualizados com sucesso.'));

    }

    public function createBMPAliasAccountFavored()
    {
        $getMoneyPlusAccounts = Account::where('alias_account_bank_id', '=', 161)->where('id', '=', 356)->get();
        
        foreach($getMoneyPlusAccounts as $aliasAccount){
            
            $apiConfig = new ApiConfig();
            $apiConfig->master_id = $aliasAccount->master_id;
            $apiConfig->api_id = 15;
            $apiConfig->onlyActive = 1;
            $apiData = $apiConfig->getApiConfig()[0];

            $apiMoneyPlusCreateFavoredAccount = new ApiMoneyPlus();
            $apiMoneyPlusCreateFavoredAccount->client_id = Crypt::decryptString($apiData->api_client_id);
            $apiMoneyPlusCreateFavoredAccount->api_address = Crypt::decryptString($apiData->api_address);
            $apiMoneyPlusCreateFavoredAccount->alias_account_agency = $aliasAccount->alias_account_agency;
            $apiMoneyPlusCreateFavoredAccount->alias_account_number = $aliasAccount->alias_account_number;
            $apiMoneyPlusCreateFavoredAccount->favored_cpf_cnpj = "11491029000130";
            $apiMoneyPlusCreateFavoredAccount->favored_name = "IP4Y INSTITUICAO DE PAGAMENTO LTDA";
            $apiMoneyPlusCreateFavoredAccount->favored_bank_number = 274;
            $apiMoneyPlusCreateFavoredAccount->favored_agency = "00018";
            $apiMoneyPlusCreateFavoredAccount->favored_account = "00790584"; //Conta Recebedora BMP
            $apiMoneyPlusCreateFavoredAccount->favored_account_type = 3;
            $apiMoneyPlusCreateFavoredAccount->createAccountFavored();
        }
    }

    public function getOwnershipCertificate(Request $request){

        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }

        
        $now = \Carbon\Carbon::now();
        $account = new Account();
        $account->id = $request->account_id;
        $data = $account->returnAccountData();
        $data->address_zip_code = Facilites::mask_cep($data->address_zip_code);
        $data->cpf_cnpj = Facilites::mask_cpf_cnpj($data->cpf_cnpj);
        $data->alias_account_number = Facilites::mask_alias_account($data->alias_account_number);
        $data->account_number = Facilites::mask_account($data->account_number);
        $data->current_date =  $now->format('d').' de '.Facilites::convertNumberMonthToString($now->format('m')) .' de '.$now->format('Y') ;
        
        //dados mockados enquanto isso | se em algum momento precisar puxar dinamicamente está feito//
        $data->signed_by =  'ENZO DONINI VALSIROLLI';
        $data->signed_by_cpf_cnpj =  '31202208843';
        /*******************************************************************************************/

        $data->digital_sign_date = substr_replace(Carbon::now()->format('Y.m.d H:i:s O'), "'", -2, 0);

        $pdf = PDF::loadView('reports/ownership_certificate', compact('data'))->setPaper('a4', 'portrait')->download('Comprovante_de_titularidade.pdf', ['Content-Type: application/pdf']);

        return response()->json(array("success" => "true", "file_name" => 'Comprovante_de_titularidade.pdf', "mime_type" => "application/pdf", "base64" => base64_encode($pdf) ));
    }

    protected function searchAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [44];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $account                       = new Account();
        $account->master_id            = $checkAccount->master_id;
        $account->register_master_id   = $request->header('registerId');
        $account->search               = $request->search;

        $ret['results'] = $account->searchAccount();

        return response()->json($ret);
    }

    protected function setAliasAccountSafeAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        
        if( ! $account = Account::where('id', '=', $request->id)/*->where('is_alias_account', '=', 1)*/->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Conta não possui Alias Account"));
        }

        $account->alias_account_safe_account = $request->alias_account_safe_account;
        if($account->save()) {
            return response()->json(array("success" => "Conta safe definida com sucesso"));
        }
        return response()->json(array("success" => "Poxa, no momento não foi possível definir a conta safe, por favor tente mais tarde"));
    }

    protected function getAccountAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        $account = new Account();
        $account->master_id = $checkAccount->master_id;
        $account->id = $checkAccount->account_id;

        $accountData = $account->returnAccountData();

        $address = null;
        $address_number = null;
        $address_complement = null;
        $address_district = null;
        $address_city = null;
        $address_state = null;
        $address_zip_code = null;

        if($accountData != ''){
            if($accountData->id != ''){
                $address = $accountData->address_public_place.' '. $accountData->address;
                $address_number = $accountData->address_number;
                $address_complement = $accountData->address_complement;
                $address_district = $accountData->address_district;
                $address_city = $accountData->address_city;
                $address_state = $accountData->address_state_short_description;
                $address_zip_code = $accountData->address_zip_code;
            }
        }

        return response()->json(array(
            "success" => "",
            "address" => $address,
            "address_number" => $address_number,
            "address_complement" => $address_complement,
            "address_district" => $address_district,
            "address_city" => $address_city,
            "address_state" => $address_state,
            "address_zip_code" => $address_zip_code,
        ));
    }

    public function closeDigitalAccount(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [410];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        
        $account = Account::where('id', '=', $request->account_id)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('register_master_id', '=', $request->register_master_id)
        ->whereNull('deleted_at')
        ->first();

        if(empty($account)) {
            return response()->json(array("error" => "Ocorreu um erro ao localizar a conta"));
        }


        //encerra a conta
        $account->status_id = 60;
        $account->deleted_at = \Carbon\Carbon::now();

        $removeUserRelationships = $this->deleteUserRelationships($request);


        $msgErro = '';

        if($account->save() && $removeUserRelationships->success) {
            return response()->json(array("success" => "Conta encerrada e usuários vinculados removidos com sucesso", "account_id" => $account->id, "user_relationship_id" => $removeUserRelationships->data));
        } 
        
        if(!$removeUserRelationships->success) {
            $msgErro .= $removeUserRelationships->message;
        } 
        
        if(!$account->save()) {
            $msgErro != '' ? $msgErro .= ". Ocorreu um erro ao alterar o status da conta para encerrado" : $msgErro .= "Ocorreu um erro ao alterar o status da conta para encerrado";
            
        }

        return response()->json(array("error" => $msgErro));
    }

    protected function deleteUserRelationships(Request $request) 
    {

         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id = [410];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //
         
        $userRelationships = UserRelationship::whereNull('deleted_at')
        ->when($request->account_id, function($query, $accountId){
            return $query->where('account_id', '=', $accountId);
        })
        ->get();

        
        $error = 0;
        $idsErroVinculos = [];
        $idsVinculosExcluidos = [];

        //remove os usuarios vinculados, caso existam
        if(!empty($userRelationships)) {
            foreach($userRelationships as $userRlt) {
                
                $userRlt->deleted_at = \Carbon\Carbon::now();
                
                if(!$userRlt->save()) {
                    $error++;
                    array_push($idsErroVinculos, $userRlt->id);
                } else {
                    array_push($idsVinculosExcluidos, $userRlt->id); 
                }       
    
            }

            if($error > 0) {
                return (object) [
                    "success" => false,
                    "message" => "Ocorreu um erro ao excluir o(s) vínculo(s) de id " . implode(', ', $idsErroVinculos),
                    "data" => []
                ];            
            } 
    
            return (object) [
                "success" => true,
                "message" => "Vínculo(s) excluído(s) com sucesso",
                "data" => [$idsVinculosExcluidos]
            ];

        }        
        
    }

    protected function updateInclusionLimitPixPaymentQtt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'inclusion_limit_pix_payment_qtt'=> ['required', 'numeric', 'min:0'],
        ],[
            'id.required' => 'Informe a conta.',
            'inclusion_limit_pix_payment_qtt.required' => 'Informe o limite de quantidade de inclusão de transferência.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->inclusion_limit_pix_payment_qtt = $request->inclusion_limit_pix_payment_qtt;

        if ($account->save()) {
            return response()->json(array("success" => "Limite de quantidade de inclusão de PIX definido com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite de quantidade de inclusão de PIX, por favor tente mais tarde"));
    }

    protected function updateInclusionLimitTransferPaymentQtt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'inclusion_limit_transfer_payment_qtt'=> ['required', 'numeric', 'min:0'],
        ],[
            'id.required' => 'Informe a conta.',
            'inclusion_limit_transfer_payment_qtt.required' => 'Informe o limite de quantidade de inclusão de transferência.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->inclusion_limit_transfer_payment_qtt = $request->inclusion_limit_transfer_payment_qtt;

        if ($account->save()) {
            return response()->json(array("success" => "Limite de quantidade de inclusão de transferência definido com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite de quantidade de inclusão de transferência, por favor tente mais tarde"));
    }

    protected function updateInclusionLimitBillPaymentQtt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'inclusion_limit_bill_payment_qtt'=> ['required', 'numeric', 'min:0'],
        ],[
            'id.required' => 'Informe a conta.',
            'inclusion_limit_bill_payment_qtt.required' => 'Informe o limite de quantidade de inclusão de pagamento.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->inclusion_limit_bill_payment_qtt = $request->inclusion_limit_bill_payment_qtt;

        if ($account->save()) {
            return response()->json(array("success" => "Limite de quantidade de inclusão de pagamento definido com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite de quantidade de inclusão de pagamento, por favor tente mais tarde"));
    }

    protected function updateInclusionLimitPayrollPaymentQtt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'inclusion_limit_payroll_payment_qtt'=> ['required', 'numeric', 'min:0'],
        ],[
            'id.required' => 'Informe a conta.',
            'inclusion_limit_payroll_payment_qtt.required' => 'Informe o limite de quantidade de inclusão de folha de pagamento.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->inclusion_limit_payroll_payment_qtt = $request->inclusion_limit_payroll_payment_qtt;

        if ($account->save()) {
            return response()->json(array("success" => "Limite de quantidade de inclusão de folha de pagamento definido com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite de quantidade de inclusão de folhe de pagamento, por favor tente mais tarde"));
    }

    protected function updateInclusionLimitExternalEmployeeQtt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'id'=> ['required', 'integer'],
            'external_employee_limit_qtt'=> ['required', 'numeric', 'min:0'],
        ],[
            'id.required' => 'Informe a conta.',
            'external_employee_limit_qtt.required' => 'Informe o limite de quantidade de inclusão de folha de pagamento.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->external_employee_limit_qtt = $request->external_employee_limit_qtt;

        if ($account->save()) {
            return response()->json(array("success" => "Limite de quantidade de funcionários externos definido com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir o limite de quantidade de funcionários externos, por favor tente mais tarde"));
    }

    protected function updateBilletRegistrationFeeOnLiquidation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'account_id'=> ['required', 'integer'],
            'billet_registration_fee_on_liquidation'=> ['required', 'numeric', 'min:0'],
        ],[
            'id.required' => 'Informe a conta.',
            'billet_registration_fee_on_liquidation.required' => 'Informe se a cobrança de emissão do boleto será realizada no registro ou na liquidação.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->account_id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->billet_registration_fee_on_liquidation = $request->billet_registration_fee_on_liquidation;

        if ($account->save()) {
            return response()->json(array("success" => "Forma de cobrança de emissão de boleto definida com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a forma de cobrança de emissão de boleto, por favor tente mais tarde"));
    }

    protected function updateBilletRegistrationFeeOnLiquidationLimitQtt(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [43];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- /

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'account_id'=> ['required', 'integer'],
            'billet_registration_fee_on_liquidation_limit_qtt'=> ['required', 'numeric', 'min:0', 'max:100000'],
        ],[
            'account_id.required' => 'Informe a conta.',
            'billet_registration_fee_on_liquidation_limit_qtt.required' => 'Informe a quantidade máxima permitida de boletos emitidos em caso de cobrança de tarifa de emissão na liquidação.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish Validate received data ----------------- //

        if (!$account = Account::where('id', '=', $request->account_id)->first()) {
            return response()->json(array("error" => "Poxa, a conta informada não foi localizada, por favor tente mais tarde"));
        }

        $account->billet_registration_fee_on_liquidation_limit_qtt = $request->billet_registration_fee_on_liquidation_limit_qtt;

        if ($account->save()) {
            return response()->json(array("success" => "Forma de cobrança de emissão de boleto definida com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível definir a forma de cobrança de emissão de boleto, por favor tente mais tarde"));
    }

    protected function getBalanceOnAccount(Request $request)
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

        $account = new Account();

        if($request->base_date == null) {
            $account->base_date = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        } else {
            $account->base_date = $request->base_date;
        }

        if($request->base_date_type == 'occurrence') {
            return response()->json($account->getBalanceOnAccountsOccurrenceDate());
        }
            
        return response()->json($account->getBalanceOnAccountsAccountingDate());
    }

    protected function getBalanceOnAccountExcel(Request $request)
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

        $items = [];

        array_push($items, (object) [
            'register_name' => "CADASTRO",
            'register_cpf_cnpj' => "CPF/CNPJ",
            'account_number' => "CONTA",
            'balance' => "SALDO",
        ]);


        $account = new Account();

        if($request->base_date == null) {
            $account->base_date = \Carbon\Carbon::parse( \Carbon\Carbon::now() )->format('Y-m-d');
        } else {
            $account->base_date = $request->base_date;
        }

        if($request->base_date_type == 'occurrence') {
            $data = $account->getBalanceOnAccountsOccurrenceDate();
        } else {
            $data = $account->getBalanceOnAccountsAccountingDate();
        }
        
        foreach($data as $info){
            array_push($items, (object) [
                'register_name' => $info->register_name,
                'register_cpf_cnpj' => Facilites::mask_cpf_cnpj($info->register_cpf_cnpj),
                'account_number' => $info->account_number,
                'balance' => $info->balance,
            ]);
        }

        $excel_export = new ExcelExportClass();
        $excel_export->value = collect($items);

        return response()->json(array(
            "success" => "Planilha saldo por conta gerada com sucesso", 
            "file_name" => "Saldo_por_conta_".$account->base_date."_".$request->base_date_type.".xlsx", 
            "mime_type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", 
            "base64" => base64_encode(Excel::raw($excel_export, \Maatwebsite\Excel\Excel::XLSX))
        ));
    }

}