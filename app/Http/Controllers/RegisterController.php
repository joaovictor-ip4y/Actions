<?php

namespace App\Http\Controllers;

use App\Models\Register;
use App\Models\RegisterMaster;
use App\Models\RegisterDataPf;
use App\Models\RegisterDataPj;
use App\Models\RegisterDetail;
use App\Models\DocumentRg;
use App\Models\DocumentCnh;
use App\Models\DocumentRne;
use App\Models\RegisterRequest;
use App\Models\Limit;
use App\Models\Tax;
use App\Models\RgstrTxVlItm;
use App\Models\RgstrLmtVlItm;
use App\Models\AccntTxVlItms;
use App\Models\AccntLmtVlItm;
use App\Models\SrvcBsktGrpItm;
use App\Models\LmtGrpItm;
use App\Models\Profile;
use App\Models\Account;
use App\Models\PoliticallyExposedPerson;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\PayrollEmployeeDetail;
use App\Models\PayrollEmployee;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerController = new Register();
        return response()->json($registerController->getRegister());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerData = $this->returnRegister($request->cpf_cnpj, '', $checkAccount->master_id);
        if($registerData->status == 0 ){
            return response()->json(array("error" => $registerData->error));
        } else {
            return response()->json(array("success" => "Cadastro realizado com sucesso", "register_master_id" =>  $registerData->success->id));
        }
    }

    protected function edit(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $errorMessage   = '';
        $registerMaster = RegisterMaster::where('id','=',$request->register_master_id)->first();
        $register       = Register::where('id','=',$registerMaster->register_id)->first();
        $registerDetail = RegisterDetail::where('register_master_id','=',$registerMaster->id)->first();

        if($registerDetail->name <> $request->name){
            $registerDetail->name = $request->name;
        }

        if(!$registerDetail->save()){
            $errorMessage .= ' |Ocorreu um erro ao atualizar os detalhes do cadastro| ';
        }

        if(strlen($register->cpf_cnpj) == 11) {
            $dataPf      = RegisterDataPf::where('register_master_id' , '=', $registerMaster->id)->first();
            $documentRg  = DocumentRg::where('rgstr_data_pf_id'       , '=', $dataPf->id)->first();
            $documentCnh = DocumentCnh::where('rgstr_data_pf_id'      , '=', $dataPf->id)->first();
            $documentRne = DocumentRne::where('rgstr_data_pf_id'      , '=', $dataPf->id)->first();

            if($dataPf->mother_name                         <> $request->mother_name ){
                $dataPf->mother_name                         = $request->mother_name;
            }
            if($dataPf->father_name                         <> $request->father_name ){
                $dataPf->father_name                         = $request->father_name;
            }
            if($dataPf->nationality_id                      <> $request->nationality_id ){
                $dataPf->nationality_id                      = $request->nationality_id;
            }
            if($dataPf->state_birth_id                      <> $request->state_birth_id ){
                $dataPf->state_birth_id                      = $request->state_birth_id;
            }
            if($dataPf->date_birth                          <> $request->date_birth ){
                $dataPf->date_birth                          = $request->date_birth;
            }
            if($dataPf->city_birth                          <> $request->city_birth ){
                $dataPf->city_birth                          = $request->city_birth;
            }
            if($dataPf->gender_id                           <> $request->gender_id ){
                $dataPf->gender_id                           = $request->gender_id;
            }
            if($dataPf->professional_occupation             <> $request->professional_occupation ){
                $dataPf->professional_occupation             = $request->professional_occupation;
            }
            if($dataPf->marital_status_id                   <> $request->marital_status_id ){
                $dataPf->marital_status_id                   = $request->marital_status_id;
            }
            if($dataPf->marriage_scheme_id                  <> $request->marriage_scheme_id ){
                $dataPf->marriage_scheme_id                  = $request->marriage_scheme_id ;

            }            
            if($dataPf->spouse                              <> $request->spouse_name ){
                $dataPf->spouse                              = $request->spouse_name;
            }
            if($dataPf->spouse_cpf                           <> $request->spouse_cpf ){
                $dataPf->spouse_cpf                          = $request->spouse_cpf;
            }           
            if($dataPf->income                               <> $request->income ){
                $dataPf->income                              = $request->income;
            }           
            
          //if($dataPf->spouse_id                         <> $request-> ){}            
          
            if($dataPf->politically_exposed                 <> $request->politically_exposed ){
                $dataPf->politically_exposed                 = $request->politically_exposed;
            }
            if($dataPf->politically_exposed_reason          <> $request->politically_exposed_reason ){
                $dataPf->politically_exposed_reason          = $request->politically_exposed_reason;
            }
            if($dataPf->politically_exposed_relation        <> $request->politically_exposed_relation ){
                $dataPf->politically_exposed_relation        = $request->politically_exposed_relation;
            }
            if($dataPf->politically_exposed_relation_cpf    <> $request->politically_exposed_relation_cpf ){
                $dataPf->politically_exposed_relation_cpf    = $request->politically_exposed_relation_cpf;
            }
            if($dataPf->politically_exposed_relation_name   <> $request->politically_exposed_relation_name ){
                $dataPf->politically_exposed_relation_name   = $request->politically_exposed_relation_name;
            }
            if($dataPf->politically_exposed_relation_reason <> $request->politically_exposed_relation_reason ){
                $dataPf->politically_exposed_relation_reason = $request->politically_exposed_relation_reason;
            }
            if($dataPf->us_person                           <> $request->us_person ){
                $dataPf->us_person                           = $request->us_person;
            }
            if($dataPf->international_residence             <> $request->international_residence ){
                $dataPf->international_residence             = $request->international_residence;
            }            
            if($dataPf->international_residence_countries   <> $request->international_residence_countries ){
                $dataPf->international_residence_countries   = $request->international_residence_countries;
            }            
            if($dataPf->observation                         <> $request->pf_observation ){
                $dataPf->observation                         = $request->pf_observation;
            }

            if($documentRg->expedition_state_id             <> $request->rg_expedition_state_id ){
                $documentRg->expedition_state_id             = $request->rg_expedition_state_id;
            }
            if($documentRg->issuing_agency_id               <> $request->rg_issuing_agency_id ){
                $documentRg->issuing_agency_id               = $request->rg_issuing_agency_id;
            }
            if($documentRg->number                          <> $request->rg_number ){
                $documentRg->number                          = $request->rg_number;
            }
            if($documentRg->expedition_date                 <> $request->rg_expedition_date ){
                $documentRg->expedition_date                 = $request->rg_expedition_date;
            }

            if($documentCnh->expedition_state_id            <> $request->cnh_expedition_state_id ){
                $documentCnh->expedition_state_id            = $request->cnh_expedition_state_id;
            }
            if($documentCnh->category_id                    <> $request->cnh_category_id ){
                $documentCnh->category_id                    = $request->cnh_category_id;
            }
            if($documentCnh->number                         <> $request->cnh_number ){
                $documentCnh->number                         = $request->cnh_number;
            }
            //if($documentCnh->mirror_number                <> $request->cnh_number ){}
            if($documentCnh->expedition_date                <> $request->cnh_expedition_date ){
                $documentCnh->expedition_date                = $request->cnh_expedition_date;
            }
            if($documentCnh->expiration_date                <> $request->cnh_expiration_date ){
                $documentCnh->expiration_date                = $request->cnh_expiration_date;
            }

            if($documentRne->expedition_state_id            <> $request->rne_expedition_state_id ){
                $documentRne->expedition_state_id            = $request->rne_expedition_state_id;
            }
            if($documentRne->number                         <> $request->rne_number ){
                $documentRne->number                         = $request->rne_number;
            }
            if($documentRne->expedition_date                <> $request->rne_expedition_date ){
                $documentRne->expedition_date                = $request->rne_expedition_date;
            }

            if(!$dataPf->save()){
                $errorMessage .= ' |Ocorreu um erro ao atualizar os dados da pessoa física| ';
            }
            if(!$documentRg->save()){
                $errorMessage .= ' |Ocorreu um erro ao atualizar os dados do RG| ';
            }
            if(!$documentCnh->save()){
                $errorMessage .= ' |Ocorreu um erro ao atualizar os dados da CNH| ';
            }
            if(!$documentRne->save()){
                $errorMessage .= ' |Ocorreu um erro ao atualizar os dados do RNE| ';
            }
        } else {
            $dataPj      = RegisterDataPj::where('register_master_id','=',$registerMaster->id)->first();

            if($dataPj->fantasy_name                  <> $request->pj_fantasy_name ){
                $dataPj->fantasy_name                  = $request->pj_fantasy_name;
            }
            if($dataPj->municipal_registration        <> $request->pj_municipal_registration ){
                $dataPj->municipal_registration        = $request->pj_municipal_registration;
            }
            if($dataPj->state_registration            <> $request->pj_state_registration ){
                $dataPj->state_registration            = $request->pj_state_registration;
            }
            if($dataPj->commercial_board_registration <> $request->pj_commercial_board_registration ){
                $dataPj->commercial_board_registration = $request->pj_commercial_board_registration;
            }
            if($dataPj->foundation_date               <> $request->pj_foundation_date ){
                $dataPj->foundation_date               = $request->pj_foundation_date;
            }
            if($dataPj->branch_activity               <> $request->pj_branch_activity ){
                $dataPj->branch_activity               = $request->pj_branch_activity;
            }
            if($dataPj->observation                   <> $request->pj_observation ){
                $dataPj->observation                   = $request->pj_observation;
            }
            /*if($dataPj->economic_group                <> $request->pj_economic_group ){
                $dataPj->economic_group                = $request->pj_economic_group;
            }
            if($dataPj->public_agency                 <> $request->pj_public_agency ){
                $dataPj->public_agency                 = $request->pj_public_agency;
            }
            if($dataPj->micro_enterprise_epp          <> $request->pj_micro_enterprise_epp ){
                $dataPj->micro_enterprise_epp          = $request->pj_micro_enterprise_epp;
            }
            if($dataPj->exemption_iof                 <> $request->pj_exemption_iof ){
                $dataPj->exemption_iof                 = $request->pj_exemption_iof;
            }
            if($dataPj->exemption_tax_retention       <> $request->pj_exemption_tax_retention ){
                $dataPj->exemption_tax_retention       = $request->pj_exemption_tax_retention;
            }
            if($dataPj->retention_iss                 <> $request->pj_retention_iss ){
                $dataPj->retention_iss                 = $request->pj_retention_iss;
            } */

            if(!$dataPj->save()){
                $errorMessage .= ' |Ocorreu um erro ao atualizar os dados da pessoa jurídica| ';
            }
        }

        if($errorMessage == ''){
            return response()->json(array("success" => "Cadastro atualizado com sucesso"));
        } else {
            return response()->json(array("error" => $errorMessage));
        }


    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [5];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster = RegisterMaster::where('id','=',$request->register_master_id)->first();
        $registerMaster->deleted_at = \Carbon\Carbon::now();
        if($registerMaster->save()){
            return response()->json(array("success" => "Cadastro excluido com sucesso", "register_master_id" =>  $registerMaster->id));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao excluir o cadastro"));
        }
    }

    protected function updateRegisterProfile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster = RegisterMaster::where('id','=',$request->register_master_id)->first();
        $registerMaster->profile_id = $request->register_profile_id;
        if($registerMaster->save()){
            $profile = Profile::where('id','=', $request->register_profile_id)->first();

            $serviceBasketTaxes = SrvcBsktGrpItm::where('service_basket_id','=',$profile->service_basket_id)->get();
            foreach($serviceBasketTaxes as $serviceBasketTax){
                if(RgstrTxVlItm::where('rgstr_id','=',$registerMaster->id)->where('taxe_id','=',$serviceBasketTax->tax_id)->count() > 0){
                    $tax = RgstrTxVlItm::where('rgstr_id','=',$registerMaster->id)->where('taxe_id','=',$serviceBasketTax->tax_id)->first();
                    $tax->value      = $serviceBasketTax->default_value;
                    $tax->percentage = $serviceBasketTax->default_percentage;
                    $tax->save();
                }
            }


            if($request->update_accounts == 1){
                $accounts = Account::where('register_master_id','=',$registerMaster->id)->get();
                foreach($accounts as $account){
                    $accnt = Account::where('id','=',$account->id)->first();
                    $accnt->srvc_bskt_id = $profile->service_basket_id;
                    foreach($serviceBasketTaxes as $serviceBasketTax){
                        if(AccntTxVlItms::where('accnt_id','=',$account->id)->where('tax_id','=',$serviceBasketTax->tax_id)->count() > 0){
                            $tax = AccntTxVlItms::where('accnt_id','=',$account->id)->where('tax_id','=',$serviceBasketTax->tax_id)->first();
                            $tax->value      = $serviceBasketTax->default_value;
                            $tax->percentage = $serviceBasketTax->default_percentage;
                            $tax->save();
                        }
                    }
                }
            }

            //---------------

            $lmtItms = LmtGrpItm::where('limit_group_id','=',$profile->limit_group_id)->get();
            foreach($lmtItms as $lmtItm){
                if( RgstrLmtVlItm::where('rgstr_id','=',$registerMaster->id)->where('limit_id','=',$lmtItm->limit_id)->count() > 0 ){
                    $lmt             = RgstrLmtVlItm::where('rgstr_id','=',$registerMaster->id)->where('limit_id','=',$lmtItm->limit_id)->first();
                    $lmt->value      = $lmtItm->default_value;
                    $lmt->percentage = $lmtItm->default_percentage;
                    $lmt->save();
                }
            }


            if($request->update_accounts == 1){
                $accounts = Account::where('register_master_id','=',$registerMaster->id)->get();
                foreach($accounts as $account){
                    $accnt = Account::where('id','=',$account->id)->first();
                    $accnt->lmt_grp_id = $profile->limit_group_id;
                    $accnt->save();
                    foreach($lmtItms as $lmtItm){
                        if(AccntLmtVlItm::where('accnt_id','=',$account->id)->where('limit_id','=',$lmtItm->limit_id)->count() > 0){
                            $lmt = AccntLmtVlItm::where('accnt_id','=',$account->id)->where('limit_id','=',$lmtItm->limit_id)->first();
                            $lmt->value      = $lmtItm->default_value;
                            $lmt->percentage = $lmtItm->default_percentage;
                            $lmt->save();
                        }
                    }
                }
            }

            if($request->update_accounts == 1){
                $accounts = Account::where('register_master_id','=',$registerMaster->id)->get();
                foreach($accounts as $account){
                    $accnt = Account::where('id','=',$account->id)->first();
                    $accnt->profile_id = $request->register_profile_id;
                    $accnt->save();
                }
            }

            return response()->json(array("success" => "Perfil atualizado com sucesso", "data" => ["limit_group_id" => $profile->limit_group_id, "service_basket_id" => $profile->service_basket_id]));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o perfil"));
        }
    }

    protected function updateRegisterLimitGroup(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster = RegisterMaster::where('id','=',$request->register_master_id)->first();
        $registerMaster->limit_group_id = $request->register_limit_group_id;
        if($registerMaster->save()){
            $lmtItms = LmtGrpItm::where('limit_group_id','=',$request->register_limit_group_id)->get();
            foreach($lmtItms as $lmtItm){
                if( RgstrLmtVlItm::where('rgstr_id','=',$registerMaster->id)->where('limit_id','=',$lmtItm->limit_id)->count() > 0 ){
                    $lmt             = RgstrLmtVlItm::where('rgstr_id','=',$registerMaster->id)->where('limit_id','=',$lmtItm->limit_id)->first();
                    $lmt->value      = $lmtItm->default_value;
                    $lmt->percentage = $lmtItm->default_percentage;
                    $lmt->save();
                }
            }
            if($request->update_accounts == 1){
                $accounts = Account::where('register_master_id','=',$registerMaster->id)->get();
                foreach($accounts as $account){
                    $accnt = Account::where('id','=',$account->id)->first();
                    $accnt->lmt_grp_id = $request->register_limit_group_id;
                    $accnt->save();
                    foreach($lmtItms as $lmtItm){
                        if(AccntTxVlItms::where('accnt_id','=',$account->id)->where('limit_id','=',$lmtItm->limit_id)->count() > 0){
                            $lmt = AccntTxVlItms::where('accnt_id','=',$account->id)->where('limit_id','=',$lmtItm->limit_id)->first();
                            $lmt->value      = $lmtItm->default_value;
                            $lmt->percentage = $lmtItm->default_percentage;
                            $lmt->save();
                        }
                    }
                }
            }
            return response()->json(array("success" => "Grupo de limite atualizado com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar o grupo de limite"));
        }
    }

    protected function updateRegisterServiceBasket(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerMaster = RegisterMaster::where('id','=',$request->register_master_id)->first();
        $registerMaster->srvc_bskt_id = $request->register_srvc_bskt_id;
        if($registerMaster->save()){
            $serviceBasketTaxes = SrvcBsktGrpItm::where('service_basket_id','=',$request->register_srvc_bskt_id)->get();
            foreach($serviceBasketTaxes as $serviceBasketTax){
                $tax = RgstrTxVlItm::where('rgstr_id','=',$registerMaster->id)->where('taxe_id','=',$serviceBasketTax->tax_id)->first();
                $tax->value      = $serviceBasketTax->default_value;
                $tax->percentage = $serviceBasketTax->default_percentage;
                $tax->save();
            }
            if($request->update_accounts == 1){
                $accounts = Account::where('register_master_id','=',$registerMaster->id)->get();
                foreach($accounts as $account){
                    $accnt = Account::where('id','=',$account->id)->first();
                    $accnt->srvc_bskt_id = $request->register_srvc_bskt_id;
                    $accnt->save();
                    foreach($serviceBasketTaxes as $serviceBasketTax){
                        $tax = AccntTxVlItms::where('accnt_id','=',$account->id)->where('tax_id','=',$serviceBasketTax->tax_id)->first();
                        $tax->value      = $serviceBasketTax->default_value;
                        $tax->percentage = $serviceBasketTax->default_percentage;
                        $tax->save();
                    }
                }
            }
            return response()->json(array("success" => "Cesta de serviço atualizada com sucesso"));
        } else {
            return response()->json(array("error" => "Ocorreu um erro ao atualizar a cesta de serviço"));
        }
    }

    public static function createRegisterData($register_id, $cpf_cnpj, $master_id)
    {
        RegisterDetail::create([
            'register_master_id' => $register_id,
            'created_at'         => \Carbon\Carbon::now()
        ]);

        $taxes = Tax::whereNull('deleted_at')->where('master_id','=',$master_id)->get();
        foreach($taxes as $tax){
            RgstrTxVlItm::create([
                'rgstr_id'   => $register_id,
                'taxe_id'    => $tax->id,
                'value'      => $tax->default_value,
                'percentage' => $tax->default_percentage,
                'created_at' => \Carbon\Carbon::now()
            ]);
        }

        $limits = Limit::whereNull('deleted_at')->where('master_id','=',$master_id)->get();
        foreach($limits as $limit){
            RgstrLmtVlItm::create([
                'rgstr_id'   => $register_id,
                'limit_id'   => $limit->id,
                'value'      => $limit->default_value,
                'percentage' => $limit->default_percentage,
                'created_at' => \Carbon\Carbon::now()
            ]);
        }

        if(strlen($cpf_cnpj) == 11) {
            $registerDataPf = RegisterDataPf::create([
                'register_master_id' => $register_id,
                'created_at'         => \Carbon\Carbon::now()
            ]);

            DocumentRg::create([
                'rgstr_data_pf_id' => $registerDataPf->id,
                'created_at'       => \Carbon\Carbon::now()
            ]);

            DocumentCnh::create([
                'rgstr_data_pf_id' => $registerDataPf->id,
                'created_at'       => \Carbon\Carbon::now()
            ]);

            DocumentRne::create([
                'rgstr_data_pf_id' => $registerDataPf->id,
                'created_at'       => \Carbon\Carbon::now()
            ]);

        } else {
            RegisterDataPj::create([
                'register_master_id' => $register_id,
                'created_at'         => \Carbon\Carbon::now()
            ]);
        }



    }

    public function validateCPF($cpf) {
        // Extrai somente os números
        $cpf = preg_replace( '/[^0-9]/is', '', $cpf );

        // Verifica se foi informado todos os digitos corretamente
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Faz o calculo para validar o CPF
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    public function validateCNPJ($cnpj) {
        // Deixa o CNPJ com apenas números
        $cnpj = preg_replace( '/[^0-9]/', '', $cnpj );

        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if (preg_match('/(\d)\1{10}/', $cnpj)) {
            return false;
        }

        // Garante que o CNPJ é uma string
        $cnpj = (string)$cnpj;

        // O valor original
        $cnpj_original = $cnpj;

        // Captura os primeiros 12 números do CNPJ
        $primeiros_numeros_cnpj = substr( $cnpj, 0, 12 );

        // Multiplicação do CNPJ

       /* if ( ! function_exists('multiplica_cnpj') ) {
            function multiplica_cnpj( $cnpj, $posicao = 5 ) {
                // Variável para o cálculo
                $calculo = 0;

                // Laço para percorrer os item do cnpj
                for ( $i = 0; $i < strlen( $cnpj ); $i++ ) {
                    // Cálculo mais posição do CNPJ * a posição
                    $calculo = $calculo + ( $cnpj[$i] * $posicao );

                    // Decrementa a posição a cada volta do laço
                    $posicao--;

                    // Se a posição for menor que 2, ela se torna 9
                    if ( $posicao < 2 ) {
                        $posicao = 9;
                    }
                }
                // Retorna o cálculo
                return $calculo;
            }
        } */

        // Faz o primeiro cálculo
        $primeiro_calculo = $this->multiplica_cnpj( $primeiros_numeros_cnpj );

        // Se o resto da divisão entre o primeiro cálculo e 11 for menor que 2, o primeiro
        // Dígito é zero (0), caso contrário é 11 - o resto da divisão entre o cálculo e 11
        $primeiro_digito = ( $primeiro_calculo % 11 ) < 2 ? 0 :  11 - ( $primeiro_calculo % 11 );

        // Concatena o primeiro dígito nos 12 primeiros números do CNPJ
        // Agora temos 13 números aqui
        $primeiros_numeros_cnpj .= $primeiro_digito;

        // O segundo cálculo é a mesma coisa do primeiro, porém, começa na posição 6
        $segundo_calculo = $this->multiplica_cnpj( $primeiros_numeros_cnpj, 6 );
        $segundo_digito = ( $segundo_calculo % 11 ) < 2 ? 0 :  11 - ( $segundo_calculo % 11 );

        // Concatena o segundo dígito ao CNPJ
        $cnpj = $primeiros_numeros_cnpj . $segundo_digito;

        // Verifica se o CNPJ gerado é idêntico ao enviado
        if ( $cnpj === $cnpj_original ) {
            return true;
        } else {
            return false;
        }
    }

    public function multiplica_cnpj( $cnpj, $posicao = 5 ) {
        // Variável para o cálculo
        $calculo = 0;

        // Laço para percorrer os item do cnpj
        for ( $i = 0; $i < strlen( $cnpj ); $i++ ) {
            // Cálculo mais posição do CNPJ * a posição
            $calculo = $calculo + ( $cnpj[$i] * $posicao );

            // Decrementa a posição a cada volta do laço
            $posicao--;

            // Se a posição for menor que 2, ela se torna 9
            if ( $posicao < 2 ) {
                $posicao = 9;
            }
        }
        // Retorna o cálculo
        return $calculo;
    }

    public function returnRegister($reg_cpf_cnpj, $name, $master_id){

        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $reg_cpf_cnpj);

        //Verifica se é cpf e valida
        if(strlen($cpf_cnpj) == 11) {
            if( !  $this->validateCPF($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CPF inválido");
            }
        //Verifica se é cnpj e valida
        } else if(strlen($cpf_cnpj) == 14){
            if( ! $this->validateCNPJ($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CNPJ inválido");
            }
        //Retorna erro se não for cpf ou cnpj
        } else {
            return (object) array("status" => 0, "error" => "CPF ou CNPJ inválido");
        }
        //Verifica se a pessoa é politicamente exposta
        if (PoliticallyExposedPerson::where('cpf', '=', $cpf_cnpj)->count() > 0) {
            return (object) array("status" => 0, "error" =>  "Poxa, não é possível realizar cadastro de Pessoa Exposta Politicamente, em caso de dúvidas entre em contato com seu gerente");
        }
        //Verifica se cpf ou cnpj existe
        if( Register::where('cpf_cnpj', '=', $cpf_cnpj)->count() == 0 ){
            if(
                $register = Register::Create([
                    'cpf_cnpj'   => $cpf_cnpj,
                    'master_id'  => $master_id,
                    'created_at' => \Carbon\Carbon::now()
                ])
            ){
                if($register_master = RegisterMaster::Create([
                    'register_id' => $register->id,
                    'master_id'   => $master_id,
                    'created_at'  => \Carbon\Carbon::now()
                ])){
                    $this->createRegisterData($register_master->id, $cpf_cnpj, $master_id);

                    if($name != ''){
                        $registerDetail = RegisterDetail::where('register_master_id','=',$register_master->id)->first();
                        $registerDetail->name = $name;
                        $registerDetail->save();
                    }
                    return (object) array("status" => 1, "success" => $register_master);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao vincular o novo cadastro");
                }
            } else {
                return (object) array("status" => 0, "error" => "Ocorreu um erro ao realizar o novo cadastro");
            }
        } else {
            $register = Register::where('cpf_cnpj', '=', $cpf_cnpj)->first();
            //Verifica se cpf ou cnpj já está cadastrado com o master
            if( RegisterMaster::where('register_id', '=', $register->id)->where('master_id', '=', $master_id)->count() == 0 ){
                if($register_master = RegisterMaster::Create([
                    'register_id' => $register->id,
                    'master_id'   => $master_id,
                    'status_id'   => 50,
                    'created_at'  => \Carbon\Carbon::now()
                ])){
                    $this->createRegisterData($register_master->id, $cpf_cnpj);
                    if($name != ''){
                        $registerDetail = RegisterDetail::where('register_master_id', '=', $register_master->id)->first();
                        $registerDetail->name = $name;
                        $registerDetail->save();
                    }
                    return (object) array("status" => 1, "success" => $register_master);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao vincular o cadastro");
                }
            } else {
                $register_master = RegisterMaster::where('register_id', '=', $register->id)->where('master_id', '=',  $master_id)->first();
                return (object) array("status" => 1, "success" => $register_master);
            }
        }
    }
}
