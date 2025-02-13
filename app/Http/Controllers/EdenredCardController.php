<?php

namespace App\Http\Controllers;

use App\Models\EdenredCard;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use App\Models\Account;
use App\Models\RegisterDetail;
use App\Models\ChangeLimit;
use App\Models\DocumentType;
use App\Models\Document;
use App\Classes\Edenred\EdenredClass;
use App\Classes\Account\ChangeLimitClass;
use Illuminate\Support\Facades\Validator;
use App\Services\Register\RegisterService;
use App\Libraries\Facilites;
use App\Libraries\AmazonS3;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


class EdenredCardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [122];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'integer'],
            'card_type_id' => ['required', 'integer'],
        ],[
            'account_id.required' => 'É obrigatório informar a conta',
            'account_id.card_type_id' => 'É obrigatório informar o tipo do cartão',
        ]);
        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        if($request->card_type_id != 1){
            if($request->register_master_id == ''){
                return response()->json(array("error" => "Informe o cadastro do titular do cartão"));
            }
        }

        // get account data
        $accountData = Account::where('id', '=', $request->account_id)->first();
                
        // get account register data
        $accountRegisterDetail = new RegisterDetail;
        $accountRegisterDetail->register_master_id = $accountData->register_master_id;
        $accountRegisterData = $accountRegisterDetail->getRegister();

        // get owner data
        $ownerRegisterDetail = new RegisterDetail;
        $ownerRegisterDetail->register_master_id = $request->card_type_id != 1 ? $request->register_master_id : $accountData->register_master_id;
        $ownerRegisterData = $ownerRegisterDetail->getRegister();


        // validate data to request card
        if($request->card_type_id == 1) {
            if( strlen($ownerRegisterData->cpf_cnpj) != 11 ) {
                return response()->json(array("error" => "O titular do cartão deve ser uma pessoa física"));
            }


            if($accountRegisterData->rg_number == '') {
                return response()->json(array("error" => "Número do RG não definido para o cadastro"));
            }

            if($accountRegisterData->date_birth == '') {
                return response()->json(array("error" => "Data de nascimento não definida para o cadastro"));
            }
 

            if( \Carbon\Carbon::parse($accountRegisterData->date_birth)->diffInYears(\Carbon\Carbon::now()) < 18 ) {
                return response()->json(array("error" => "Não é possível solicitar cartão para menor de idade"));
            }
        }

        if($accountRegisterData->address == '') {
            return response()->json(array("error" => "Endereço do cadastro não definido"));
        }

        if($accountRegisterData->address_number == '') {
            return response()->json(array("error" => "Número do endereço do cadastro não definido"));
        }

        if($accountRegisterData->address_district == '') {
            return response()->json(array("error" => "Bairro do endereço do cadastro não definido"));
        }

        if($accountRegisterData->address_city == '') {
            return response()->json(array("error" => "Cidade do endereço do cadastro não definida"));
        }

        if($accountRegisterData->address_state_short_description == '') {
            return response()->json(array("error" => "Estado do endereço do cadastro não definido"));
        }

        if($accountRegisterData->email == '') {
            return response()->json(array("error" => "E-Mail do cadastro não definido"));
        }

        if($accountRegisterData->phone == '') {
            return response()->json(array("error" => "Telefone/Celular do cadastro não definido"));
        }


        if($ownerRegisterData->address == '') {
            return response()->json(array("error" => "Endereço do titular não definido"));
        }

        if($ownerRegisterData->address_number == '') {
            return response()->json(array("error" => "Número do endereço do titular não definido"));
        }

        if($ownerRegisterData->address_district == '') {
            return response()->json(array("error" => "Bairro do endereço do titular não definido"));
        }

        if($ownerRegisterData->address_city == '') {
            return response()->json(array("error" => "Cidade do endereço do titular não definida"));
        }

        if($ownerRegisterData->address_state_short_description == '') {
            return response()->json(array("error" => "Estado do endereço do titular não definido"));
        }

        if($ownerRegisterData->email == ''){
            return response()->json(array("error" => "E-mail do titular não definido"));
        }

        if($ownerRegisterData->phone == '') {
            return response()->json(array("error" => "Celular do titular não definido"));
        }

        if(strlen($ownerRegisterData->phone) != 11) {
            return response()->json(array("error" => "Celular do titular inválido"));
        }

        if($ownerRegisterData->date_birth == '') {
            return response()->json(array("error" => "Data de nascimento do titular não definida"));
        }

        if( \Carbon\Carbon::parse($ownerRegisterData->date_birth)->diffInYears(\Carbon\Carbon::now()) < 18 ) {
            return response()->json(array("error" => "Não é possível solicitar cartão para titular menor de idade"));
        }


        $edenred = new EdenredClass;

        $edenred->cpfCnpj = $accountRegisterData->cpf_cnpj;
        $edenred->name = $accountRegisterData->name;
        $edenred->birthDate = $accountRegisterData->date_birth;
        $edenred->unitName = $accountRegisterData->name;
        $edenred->departamentName = $accountRegisterData->name;

        $edenred->phoneDdd = substr($accountRegisterData->phone, 0, 2);
        $edenred->phone = substr($accountRegisterData->phone, 2, 9);
        $edenred->email = $accountRegisterData->email;
        $edenred->documentRgNumber = $accountRegisterData->rg_number;
        $edenred->accountId = $accountData->id;
        $edenred->registerMasterId = $accountData->register_master_id;

        $edenred->address = trim($accountRegisterData->address_public_place.' '.$accountRegisterData->address);
        $edenred->addressNumber = $accountRegisterData->address_number;
        $edenred->addressComplement = $accountRegisterData->address_complement;
        $edenred->addressDistrict = $accountRegisterData->address_district;
        $edenred->addressZipCode = $accountRegisterData->address_zip_code;
        $edenred->addressCity = $accountRegisterData->address_city;
        $edenred->addressState = $accountRegisterData->address_state_short_description;
       
        $edenred->ownerCpf = $ownerRegisterData->cpf_cnpj;
        $edenred->ownerName = $ownerRegisterData->name;
        $edenred->ownerPhoneDdd = substr($ownerRegisterData->phone, 0, 2);
        $edenred->ownerPhone = substr($ownerRegisterData->phone, 2, 9);
        $edenred->ownerEmail = $ownerRegisterData->email;
        $edenred->ownerBirthDate = $ownerRegisterData->date_birth;
        
        $edenred->ownerAddress = trim($ownerRegisterData->address_public_place.' '.$ownerRegisterData->address);
        $edenred->ownerAddressNumber = $ownerRegisterData->address_number;
        $edenred->ownerAddressComplement = $ownerRegisterData->address_complement;
        $edenred->ownerAddressDistrict = $ownerRegisterData->address_district;
        $edenred->ownerAddressZipCode = $ownerRegisterData->address_zip_code;
        $edenred->ownerAddressCity = $ownerRegisterData->address_city;
        $edenred->ownerAddressState = $ownerRegisterData->address_state_short_description;

        if($request->update_delivery_address == 1) {
            $edenred->address = $request->address;
            $edenred->addressNumber = $request->address_number;
            $edenred->addressComplement = $request->address_complement;
            $edenred->addressDistrict = $request->address_district;
            $edenred->addressZipCode = $request->address_zip_code;
            $edenred->addressCity = $request->address_city;
            $edenred->addressState = $request->address_state;
        }

        $createIssueCard = $edenred->createIssueCard();

        return response()->json(array("success" => $createIssueCard->message, "data" => $createIssueCard->data));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\EdenredCard  $edenredCard
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));


        $edenredCards = EdenredCard::when($checkAccount->account_id, function($query, $accountId) {
            return $query->where('account_id', '=', $accountId);
        })
        //->whereNull('deleted_at')
        //->where('status_id', '=', 4)
        ->when($request->onlyActive, function($query, $onlyActive){
            return $query->where('card_status_id', '=', 4);
        })
        ->when($request->toManage, function($query, $toManage){
            return $query->whereIn('card_status_id', [2,3,4,6]);
        })
        ->when($request->toCancel, function($query, $toActivate){
            return $query->whereNotIn('card_status_id', [5]);
        })
        ->get();

        $card = [];

        foreach($edenredCards as $edenredCard) {
            $edenredCardStatusDescription = null;

            switch($edenredCard->card_status_id){
                case 1: 
                    $edenredCardStatusDescription = 'Aguardando Emissão';
                break;
                case 2: 
                    $edenredCardStatusDescription = 'Emissão Solicitada';
                break;
                case 3: 
                    $edenredCardStatusDescription = 'Bloqueado';
                break;
                case 4: 
                    $edenredCardStatusDescription = 'Ativado';
                break;
                case 5: 
                    $edenredCardStatusDescription = 'Cancelado';
                break;
                case 6: 
                    $edenredCardStatusDescription = 'Aguardando Desbloqueio';
                break;
                case 7: 
                    $edenredCardStatusDescription = 'Emissão Não Aprovada';
                break;
            }

            $accountData = Account::where('id', '=', $edenredCard->account_id)->first();
            $registerDetail = new RegisterDetail;
            $registerDetail->register_master_id = $accountData->register_master_id;
            $registerData = $registerDetail->getRegister();

            array_push($card, [
                'id' => $edenredCard->id,
                'uuid' => $edenredCard->uuid,
                'card_status_id' => $edenredCard->card_status_id,
                'card_status_description' => $edenredCardStatusDescription,
                'account_number' => $accountData->account_number,
                'created_at' => $edenredCard->created_at,
                'name' => $registerData->name,
                'account_id' => $edenredCard->account_id,
                'register_master_id' => $edenredCard->register_master_id,
                'bearer_name' => $edenredCard->owner_name,
                'card_check_limit' => $edenredCard->card_check_limit,
                'card_check_limit_description' => $edenredCard->card_check_limit == 1 ? 'Sim' : 'Não',
                'card_monthly_limit' => $edenredCard->card_monthly_limit,
                'month_value' => EdenredCard::getSumMonthCardTransaction($edenredCard->id),
                'card_daily_limit'  => $edenredCard->card_daily_limit,
                'internal_code' => $edenredCard->order_code,
                'pan_card' => $edenredCard->card_status_id != 6 ? substr($edenredCard->card_number, 0, 4).' XXXX XXXX '.substr($edenredCard->card_number, 12, 4) : substr($edenredCard->card_number, 0, 4).' XXXX XXXX XXX'.substr($edenredCard->card_number, 15, 1),
            ]);

        }

        return response()->json($card);

    }

    public function getCardsToActivate(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));


        $edenredCards = EdenredCard::where('account_id', '=', $checkAccount->account_id)
        ->whereNull('deleted_at')
        ->whereIn('card_status_id', [3, 6])
        ->get();
        

        $card = [];

        foreach($edenredCards as $edenredCard) {
            if($edenredCard->card_number != null) {

                $edenredCardStatusDescription = null;

                switch($edenredCard->card_status_id){
                    case 1: 
                        $edenredCardStatusDescription = 'Aguardando Emissão';
                    break;
                    case 2: 
                        $edenredCardStatusDescription = 'Emissão Solicitada';
                    break;
                    case 3: 
                        $edenredCardStatusDescription = 'Bloqueado';
                    break;
                    case 4: 
                        $edenredCardStatusDescription = 'Ativado';
                    break;
                    case 5: 
                        $edenredCardStatusDescription = 'Cancelado';
                    break;
                    case 6: 
                        $edenredCardStatusDescription = 'Aguardando Desbloqueio';
                    break;
                    case 7: 
                        $edenredCardStatusDescription = 'Emissão Não Aprovada';
                    break;
                }

                $accountData = Account::where('id', '=', $edenredCard->account_id)->first();
                $registerDetail = new RegisterDetail;
                $registerDetail->register_master_id = $accountData->register_master_id;
                $registerData = $registerDetail->getRegister();

                array_push($card, [
                    'id' => $edenredCard->id,
                    'uuid' => $edenredCard->uuid,
                    'card_status_id' => $edenredCard->card_status_id,
                    'name' => $registerData->name,
                    'account_id' => $edenredCard->account_id,
                    'account_number' => $accountData->account_number,
                    'register_master_id' => $edenredCard->register_master_id,
                    'bearer_name' => $edenredCard->owner_name,
                    'internal_code' => $edenredCard->order_code,
                    'pan_card' => substr($edenredCard->card_number, 0, 4).' XXXX XXXX XXX'.substr($edenredCard->card_number, 15, 1),
                    
                ]);
            }

        }

        return response()->json($card);
    }

    public function getCardsRequest(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));


        $edenredCards = EdenredCard::whereIn('card_status_id', [1, 2, 6, 7])
        ->when($checkAccount->account_id, function($query, $accountId) {
            return $query->where('account_id', '=', $accountId);
        })
        ->when($request->onlyActive, function($query, $onlyActive){
            return $query->whereNull('deleted_at');
        })
        ->get();

        $card = [];

        foreach($edenredCards as $edenredCard) {
            $edenredCardStatusDescription = null;

            switch($edenredCard->card_status_id){
                case 1: 
                    $edenredCardStatusDescription = 'Aguardando Emissão';
                break;
                case 2: 
                    $edenredCardStatusDescription = 'Emissão Solicitada';
                break;
                case 3: 
                    $edenredCardStatusDescription = 'Bloqueado';
                break;
                case 4: 
                    $edenredCardStatusDescription = 'Ativado';
                break;
                case 5: 
                    $edenredCardStatusDescription = 'Cancelado';
                break;
                case 6: 
                    $edenredCardStatusDescription = 'Aguardando Desbloqueio';
                break;
                case 7: 
                    $edenredCardStatusDescription = 'Emissão Não Aprovada';
                break;
            }

            $accountData = Account::where('id', '=', $edenredCard->account_id)->first();
            $registerDetail = new RegisterDetail;
            $registerDetail->register_master_id = $accountData->register_master_id;
            $registerData = $registerDetail->getRegister();

            array_push($card, [
                'id' => $edenredCard->id,
                'uuid' => $edenredCard->uuid,
                'card_status_id' => $edenredCard->card_status_id,
                'card_status_description' => $edenredCardStatusDescription,
                'name' => $registerData->name,
                'account_id' => $edenredCard->account_id,
                'account_number' => $accountData->account_number,
                'register_master_id' => $edenredCard->register_master_id,
                'bearer_name' => $edenredCard->owner_name,
                'internal_code' => $edenredCard->order_code,
                'invoice_due_date' => $edenredCard->invoice_due_date,
                'pan_card' => $edenredCard->card_number != null ? substr($edenredCard->card_number, 0, 4).' XXXX XXXX XXX'.substr($edenredCard->card_number, 15, 1) : null,
                'address' => $edenredCard->address,
                'address_number' => $edenredCard->address_number,
                'address_complement' => $edenredCard->address_complement,
                'address_district' => $edenredCard->address_district,
                'address_zip_code' => $edenredCard->address_zip_code,
                'address_city' => $edenredCard->address_city,
                'address_state' => $edenredCard->address_state
            ]);

        }

        return response()->json($card);
    }

    public function requestPFCard(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        if($request->term_accepted != 1) {
            return response()->json(array("error" => "É necessário concordar com o termo de uso do cartão para continuar."));
        }

        if( EdenredCard::whereIn('card_status_id', [1, 2, 6])->where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->count() != 0 ) {
           return response()->json(array("error" => "Sua conta possuí solicitação de cartão ainda não concluída, em caso de dúvidas entre em contato com seu gerente de relacionamento."));
        }

        // bloquear nova solicitação se cartão ativo

        // get account data
        $accountData = Account::where('id', '=', $checkAccount->account_id)->first();
            
        // get account register data
        $accountRegisterDetail = new RegisterDetail;
        $accountRegisterDetail->register_master_id = $accountData->register_master_id;
        $accountRegisterData = $accountRegisterDetail->getRegister();

        // get owner data
        $ownerRegisterDetail = new RegisterDetail;
        $ownerRegisterDetail->register_master_id = $accountData->register_master_id;
        $ownerRegisterData = $ownerRegisterDetail->getRegister();

        if( strlen($accountRegisterData->cpf_cnpj) != 11 ) {
            return response()->json(array("error" => "Utilize a solicitação de cartão PJ para solicitar um cartão para uma empresa."));
        }

        if($accountRegisterData->rg_number == '') {
            return response()->json(array("error" => "Número do RG não definido para o cadastro"));
        }

        if($accountRegisterData->date_birth == '') {
            return response()->json(array("error" => "Data de nascimento não definida para o cadastro"));
        }

        if( \Carbon\Carbon::parse($accountRegisterData->date_birth)->diffInYears(\Carbon\Carbon::now()) < 18 ) {
            return response()->json(array("error" => "Não é possível solicitar cartão para menor de idade"));
        }

        if($request->address == '') {
            return response()->json(array("error" => "Endereço de entrega não definido"));
        }

        if($request->address_number == '') {
            return response()->json(array("error" => "Número do endereço de entrega não definido"));
        }

        if($request->address_district == '') {
            return response()->json(array("error" => "Bairro do endereço de entrega não definido"));
        }

        if($request->address_city == '') {
            return response()->json(array("error" => "Cidade do endereço de entrega não definida"));
        }

        if($request->address_state == '') {
            return response()->json(array("error" => "Estado do endereço do cadastro não definido"));
        }
        
        if($request->address_zip_code == '') {
            return response()->json(array("error" => "CEP do endereço do cadastro não definido"));
        }

        if($accountRegisterData->email == '') {
            return response()->json(array("error" => "E-Mail do cadastro não definido"));
        }

        if($accountRegisterData->phone == '') {
            return response()->json(array("error" => "Telefone/Celular do cadastro não definido"));
        }

        $edenred = new EdenredClass;

        $edenred->cpfCnpj = $accountRegisterData->cpf_cnpj;
        $edenred->name = $accountRegisterData->name;
        $edenred->birthDate = $accountRegisterData->date_birth;
        $edenred->unitName = $accountRegisterData->name;
        $edenred->departamentName = $accountRegisterData->name;

        $edenred->phoneDdd = substr($accountRegisterData->phone, 0, 2);
        $edenred->phone = substr($accountRegisterData->phone, 2, 9);
        $edenred->email = $accountRegisterData->email;
        $edenred->documentRgNumber = $accountRegisterData->rg_number;
        $edenred->accountId = $accountData->id;
        $edenred->registerMasterId = $accountData->register_master_id;

        $edenred->address = $request->address;
        $edenred->addressNumber = $request->address_number;
        $edenred->addressComplement = substr($request->address_complement,0,20);
        $edenred->addressDistrict = $request->address_district;
        $edenred->addressZipCode = $request->address_zip_code;
        $edenred->addressCity = $request->address_city;
        $edenred->addressState = $request->address_state;
       
        $edenred->ownerCpf = $ownerRegisterData->cpf_cnpj;
        $edenred->ownerName = $ownerRegisterData->name;
        $edenred->ownerPhoneDdd = substr($ownerRegisterData->phone, 0, 2);
        $edenred->ownerPhone = substr($ownerRegisterData->phone, 2, 9);
        $edenred->ownerEmail = $ownerRegisterData->email;
        $edenred->ownerBirthDate = $ownerRegisterData->date_birth;
        
        $edenred->ownerAddress = trim($ownerRegisterData->address_public_place.' '.$ownerRegisterData->address);
        $edenred->ownerAddressNumber = $ownerRegisterData->address_number;
        $edenred->ownerAddressComplement = substr($ownerRegisterData->address_complement,0,20);
        $edenred->ownerAddressDistrict = $ownerRegisterData->address_district;
        $edenred->ownerAddressZipCode = $ownerRegisterData->address_zip_code;
        $edenred->ownerAddressCity = $ownerRegisterData->address_city;
        $edenred->ownerAddressState = $ownerRegisterData->address_state_short_description;

        $createIssueCard = $edenred->createIssueCard();

        return response()->json(array("success" => $createIssueCard->message, "data" => $createIssueCard->data));
    }

    public function requestPJCard(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [122, 309, 351];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($request->term_accepted != 1) {
            return response()->json(array("error" => "É necessário concordar com o termo de uso do cartão para continuar."));
        }

        if( EdenredCard::whereIn('card_status_id', [1, 2, 6])->where('account_id', '=', $checkAccount->account_id)->where('owner_cpf', '=', $request->cpf_cnpj)->whereNull('deleted_at')->count() != 0 ) {
            return response()->json(array("error" => "Sua conta possuí solicitação de cartão ainda não concluída para o titular informado, em caso de dúvidas entre em contato com seu gerente de relacionamento."));
        }

        // get account data
        $accountData = Account::where('id', '=', $checkAccount->account_id)->first();
            
        // get account register data
        $accountRegisterDetail = new RegisterDetail;
        $accountRegisterDetail->register_master_id = $accountData->register_master_id;
        $accountRegisterData = $accountRegisterDetail->getRegister();

        if( strlen($accountRegisterData->cpf_cnpj) != 14 ) {
            return response()->json(array("error" => "Utilize a solicitação de cartão PF para solicitar um cartão para uma Pessoa Física."));
        }

        if(! Facilites::validateCpfCnpj($request->cpf_cnpj)) {
            return response()->json(array("error" => "CPF do titular inválido"));
        }

        if($request->name == '') {
            return response()->json(array("error" => "Nome do titular não definido"));
        }

        if($request->document_number == '') {
            return response()->json(array("error" => "Número do RG do titular não definido"));
        }

        if($request->birth_date == '') {
            return response()->json(array("error" => "Data de nascimento do titular não definida"));
        }

        if( \Carbon\Carbon::parse($request->birth_date)->diffInYears(\Carbon\Carbon::now()) < 18 ) {
            return response()->json(array("error" => "Não é possível solicitar cartão para titular menor de idade"));
        }

        if($request->address == '') {
            return response()->json(array("error" => "Endereço de entrega não definido"));
        }

        if($request->address_number == '') {
            return response()->json(array("error" => "Número do endereço de entrega não definido"));
        }

        if($request->address_district == '') {
            return response()->json(array("error" => "Bairro do endereço de entrega não definido"));
        }

        if($request->address_city == '') {
            return response()->json(array("error" => "Cidade do endereço de entrega não definida"));
        }

        if($request->address_state == '') {
            return response()->json(array("error" => "Estado do endereço do cadastro não definido"));
        }
        
        if($request->address_zip_code == '') {
            return response()->json(array("error" => "CEP do endereço do cadastro não definido"));
        }

        if($request->email == '') {
            return response()->json(array("error" => "E-Mail do titular não definido"));
        }

        if($request->phone == '') {
            return response()->json(array("error" => "Celular do titular não definido"));
        }
        
        if($request->document_front == '') {
            return response()->json(array("error" => "Importe a frente do documento do titular"));
        }
       
        if($request->document_back == '') {
            return response()->json(array("error" => "Importe o verso do documento do titular"));
        }


        $documentFrontFileName = null;
        if($request->document_front != '') {
            $ext = strtolower(pathinfo($request->document_front_file_name, PATHINFO_EXTENSION));
            
            if( ! in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf']) ){
                return reponse()->json(array("error" => "Formato de arquivo $ext não permitido para frente do documento, formatos permitidos: jpg, jpeg, png e pdf."));
            }

            $documentFrontFileName = Str::orderedUuid().'.'.$ext;

            $amazons3 = new AmazonS3();
            $amazons3->fileName = $documentFrontFileName;
            $amazons3->file64 = base64_encode(file_get_contents($request->document_front));;
            $amazons3->path = (DocumentType::where('id', '=', 1)->first())->s3_path;

            $upfile = $amazons3->fileUpAmazon();
        }

        $documentVerseFileName = null;
        if($request->document_back != '') {
            $ext = strtolower(pathinfo($request->document_back_file_name, PATHINFO_EXTENSION));
            
            if( ! in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf']) ){
                return reponse()->json(array("error" => "Formato de arquivo $ext não permitido para verso do documento, formatos permitidos: jpg, jpeg, png e pdf."));
            }

            $documentVerseFileName = Str::orderedUuid().'.'.$ext;

            $amazons3 = new AmazonS3();
            $amazons3->fileName = $documentVerseFileName;
            $amazons3->file64 = base64_encode(file_get_contents($request->document_back));;
            $amazons3->path = (DocumentType::where('id', '=', 2)->first())->s3_path;

            $upfile = $amazons3->fileUpAmazon();
        }

          

        $edenred = new EdenredClass;

        $edenred->cpfCnpj = $accountRegisterData->cpf_cnpj;
        $edenred->name = $accountRegisterData->name;
        $edenred->birthDate = $accountRegisterData->date_birth;
        $edenred->unitName = $accountRegisterData->name;
        $edenred->departamentName = $accountRegisterData->name;

        $edenred->phoneDdd = substr($accountRegisterData->phone, 0, 2);
        $edenred->phone = substr($accountRegisterData->phone, 2, 9);
        $edenred->email = $accountRegisterData->email;
        
        $edenred->accountId = $accountData->id;
        $edenred->registerMasterId = $accountData->register_master_id;

        $edenred->address = $request->address;
        $edenred->addressNumber = $request->address_number;
        $edenred->addressComplement = substr($request->address_complement,0,20);
        $edenred->addressDistrict = $request->address_district;
        $edenred->addressZipCode = $request->address_zip_code;
        $edenred->addressCity = $request->address_city;
        $edenred->addressState = $request->address_state;
       
        $edenred->ownerCpf = $request->cpf_cnpj;
        $edenred->ownerName = $request->name;
        $edenred->ownerPhoneDdd = substr($request->phone, 0, 2);
        $edenred->ownerPhone = substr($request->phone, 2, 9);
        $edenred->ownerEmail = $request->email;
        $edenred->ownerBirthDate = $request->birth_date;
        
        $edenred->ownerAddress = $request->address;
        $edenred->ownerAddressNumber = $request->address_number;
        $edenred->ownerAddressComplement = substr($request->address_complement,0,20);
        $edenred->ownerAddressDistrict = $request->address_district;
        $edenred->ownerAddressZipCode = $request->address_zip_code;
        $edenred->ownerAddressCity = $request->address_city;
        $edenred->ownerAddressState = $request->address_state;

        $edenred->ownerDocumentFrontS3 = $documentFrontFileName;
        $edenred->ownerDocumentVerseS3 = $documentVerseFileName;

        $edenred->documentRgNumber = $request->document_number;

        $createIssueCard = $edenred->createIssueCard();

        return response()->json(array("success" => $createIssueCard->message, "data" => $createIssueCard->data));
    }

    public function activateCard(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->where('account_id', '=', $checkAccount->account_id)->whereIn('card_status_id', [3,6])->first() ) {
            return response()->json(array("error" => "Cartão não localizado, ou já desbloqueado"));
        }

        if( $card->owner_cpf != $request->cpf ) {
            return response()->json(array("error" => "CPF informado não corresponde ao CPF do titular do cartão."));
        }

        if((\Carbon\Carbon::parse($card->owner_birth_date))->format('Y-m-d') != $request->birth_date){
            return response()->json(array('error' => 'Data de nascimento informada não corresponde a data de nascimento do titular do cartão.'));
        }

        if( substr($card->card_number, 12, 4) != $request->last_digits ) {
            return response()->json(array("error" => "Últimos 4 dígitos do cartão informados não correspondem aos últimos 4 dígitos do cartão a ser desbloqueado."));
        }

        $edenred = new EdenredClass;
        $edenred->id = $card->id;
        $edenred->uuid = $card->uuid;
        $activateCard = $edenred->activateCard();

        if( !$activateCard->success ){
            return response()->json(array("error" => $activateCard->message));
        }

        return response()->json(array("success" => "Cartão desbloqueado com sucesso"));
    }

    protected function updateCardPassword(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));


        if( strlen(base64_decode($request->oldpass)) != 4 ){
            return response()->json(array("error" => "A senha atual deve conter 4 caracteres"));
        }
        
        if( strlen(base64_decode($request->cpass)) != 4 ){
            return response()->json(array("error" => "A nova senha deve conter 4 caracteres"));
        }

        if($request->cpass != $request->confirm_cpass) {
            return response()->json(array("error" => "A confirmação da nova senha não coincide com a nova senha informada"));
        }

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->where('account_id', '=', $checkAccount->account_id)->whereIn('card_status_id', [4])->first() ) {
            return response()->json(array("error" => "Cartão não localizado, para alteração de senha"));
        }

        $edenred = new EdenredClass;
        $edenred->id = $card->id;
        $edenred->uuid = $card->uuid;
        $edenred->birthDate = $card->owner_birth_date;
        $edenred->newPassword = base64_decode($request->cpass);
        $edenred->password = base64_decode($request->oldpass);

        $updateCardPassword = $edenred->updateCardPassword();

        if( ! $updateCardPassword->success ){
            return response()->json(array("error" => $updateCardPassword->message));
        }

        return response()->json(array("success" => "Senha do cartão alterada com sucesso"));

    }

    protected function blockCard(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->where('account_id', '=', $checkAccount->account_id)->whereIn('card_status_id', [4])->first() ) {
            return response()->json(array("error" => "Cartão não localizado para bloqueio, ou já bloqueado"));
        }

        $edenred = new EdenredClass;
        $edenred->id = $card->id;
        $edenred->uuid = $card->uuid;
        
        $blockCard = $edenred->blockCard();

        if( ! $blockCard->success ){
            return response()->json(array("error" => $blockCard->message));
        }

        return response()->json(array("success" => "Cartão bloqueado com sucesso"));

    }

    protected function cancelCard(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->when($checkAccount->account_id, function($query, $accountId) { return $query->where('account_id', '=', $accountId);})->whereIn('card_status_id', [3,4,6])->first() ) {
            return response()->json(array("error" => "Cartão não localizado para cancelamento, ou já cancelado"));
        }

        $edenred = new EdenredClass;
        $edenred->id = $card->id;
        $edenred->uuid = $card->uuid;
        
        $cancelCard = $edenred->cancelCard();

        if( ! $cancelCard->success ){
            return response()->json(array("error" => $cancelCard->message));
        }

        return response()->json(array("success" => "Cartão cancelado com sucesso"));

    }

    protected function updateDeliveryAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [122];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereIn('card_status_id', [1])->first() ) {
            return response()->json(array("error" => "Cartão não localizado para alteração de endereço de entrega, ou já solicitado"));
        }

        $card->address = $request->address;
        $card->address_number = $request->address_number;
        $card->address_complement = $request->address_complement;
        $card->address_district = $request->address_district;
        $card->address_zip_code = $request->address_zip_code;
        $card->address_city = $request->address_city;
        $card->address_state = $request->address_state;

        if($card->save()) {
            return response()->json(array("success" => "Endereço de entrega do cartão atualizado com sucesso"));
        }

        return response()->json(array("error" => "Poxa, ocorreu uma falha ao atualizar o endereço de entrega do cartão, por favor tente novamente mais tarde"));
    }

    protected function approveRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [123];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereIn('card_status_id', [1])->first() ) {
            return response()->json(array("error" => "Cartão não localizado para solicitação, ou já solicitado"));
        }

        if(strlen($card->cpf_cnpj) == 14) {
            $registerServiceToCardOwner = new RegisterService();
        
            $observationText = 'Aprovado na requisição de cartão corporativo da conta '.$card->name;
            $registerServiceToCardOwner->cpf_cnpj = $card->owner_cpf;
            $registerServiceToCardOwner->name = $card->owner_name;
            $registerServiceToCardOwner->register_birth_date = $card->owner_birth_date;
            $registerServiceToCardOwner->master_id = $checkAccount->master_id;
            $registerServiceToCardOwner->manager_email = '';
            $registerServiceToCardOwner->register_address = $card->owner_address;
            $registerServiceToCardOwner->register_address_state_id = Facilites::convertStateToInt($card->owner_address_state);
            $registerServiceToCardOwner->register_address_public_place = '';
            $registerServiceToCardOwner->register_address_number = $card->owner_address_number;
            $registerServiceToCardOwner->register_address_complement = $card->owner_address_complement;
            $registerServiceToCardOwner->register_address_district = $card->owner_address_district;
            $registerServiceToCardOwner->register_address_city = $card->owner_address_city;  
            $registerServiceToCardOwner->register_address_zip_code = $card->owner_address_zip_code;
            $registerServiceToCardOwner->register_address_observation = $observationText;
            $registerServiceToCardOwner->register_address_main = false;
            $registerServiceToCardOwner->register_email = $card->owner_email;
            $registerServiceToCardOwner->register_email_observation = $observationText;
            $registerServiceToCardOwner->register_email_main = false;
            $registerServiceToCardOwner->register_phone = $card->owner_phone_ddd.$card->owner_phone_number;
            $registerServiceToCardOwner->register_phone_observation = $observationText;
            $registerServiceToCardOwner->register_phone_main = false;
            $registerServiceToCardOwner->register_mother_name = '';//$request->mother_name;
            $registerServiceToCardOwner->register_observation = $observationText;
            $registerServiceToCardOwner->register_rg_number = $card->document_rg_number;

            $createRegister = $registerServiceToCardOwner->returnRegister();

            if($createRegister->success){

                if($card->owner_document_front_s3_file_name != '') {
                    Document::create([
                        'register_master_id' => $createRegister->register_master->id,
                        'master_id' => $checkAccount->master_id,
                        'document_type_id' => 1,
                        's3_file_name' => $card->owner_document_front_s3_file_name,
                        'status_id' => 9,
                        'description' => 'Aprovado na requisição de cartão corporativo da conta '.$card->name,
                        'created_by' => (Auth::user())->id,
                        'created_at' => \Carbon\Carbon::now()
                    ]);
                }

                if($card->owner_document_verse_s3_file_name != '') {
                    Document::create([
                        'register_master_id' => $createRegister->register_master->id,
                        'master_id' => $checkAccount->master_id,
                        'document_type_id' => 2,
                        's3_file_name' => $card->owner_document_verse_s3_file_name,
                        'status_id' => 9,
                        'description' => 'Aprovado na requisição de cartão corporativo da conta '.$card->name,
                        'created_by' => (Auth::user())->id,
                        'created_at' => \Carbon\Carbon::now()
                    ]);
                }
            }
        }

        $edenred = new EdenredClass;
        $edenred->id = $card->id;
        $edenred->uuid = $card->uuid;
        $edenred->cpfCnpj = $card->cpf_cnpj;
        $edenred->invoiceDueDate = $card->invoice_due_date;
        $edenred->name = $card->name;
        $edenred->birthDate = $card->birth_date;
        $edenred->ownerName = $card->owner_name;
        $edenred->address = $card->address;
        $edenred->addressNumber = $card->address_number;
        $edenred->addressComplement = $card->address_complement;
        $edenred->addressDistrict = $card->address_district;
        $edenred->addressZipCode = $card->address_zip_code;
        $edenred->addressCity = $card->address_city;
        $edenred->addressState = $card->address_state;
        $edenred->phoneDdd = $card->owner_phone_ddd;
        $edenred->phone = $card->owner_phone_number;
        $edenred->email = $card->owner_email;
        $edenred->documentRgNumber = $card->document_rg_number;

        $issueCard = $edenred->apiIssueCard();

        if( $issueCard->status_code == 200 ){
            $card->order_code = $issueCard->body->codigoPedido;
            $card->order_reference = $issueCard->body->esppRef;
            $card->card_status_id = 2;
            $card->save();

            return response()->json(array("success" => "Cartão solicitado com sucesso"));
        }

        $errorMessage = null;
        if (isset( $issueCard->body->portadores[0]->codigosRetorno ) ){
            $errorMessage = EdenredClass::handleCardOrderError($issueCard->body->portadores[0]->codigosRetorno);
            if( is_array($errorMessage)) {
                $errorMessage = implode(", ", $errorMessage);
            }
        }

        return response()->json(array("error" => "Poxa, ocorreu uma falha ao solicitar o cartão | ".$errorMessage));


    }

    protected function reproveRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [126];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(! $card = EdenredCard::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereIn('card_status_id', [1])->first() ) {
            return response()->json(array("error" => "Cartão não localizado para cancelamento de solicitação"));
        }

        $card->card_status_id = 7;
        $card->save();
        
        return response()->json(array("success" => "Emissão de cartão recusada com sucesso"));

    }

    public function checkCardBearer()
    {
        $edenred = new EdenredClass;
        return $edenred->checkApiCardBearer();
    }

    public function requestUpdateCardLimit(Request $request, ChangeLimitClass $changeLimitClass)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $request->request->add(['account_id' => (int) $checkAccount->account_id]);
        $request->request->add(['change_limit_type_id' => 1]);
        $request->request->add(['ip' => $request->header('ip')]);

        $changeLimitClass->payload = $request;

        $requestChangeLimit = $changeLimitClass->requestChangeLimit();

        if( ! $requestChangeLimit->success ){
            return response()->json(array("error" => $requestChangeLimit->message_pt_br));
        }

        return response()->json(array("success" => $requestChangeLimit->message_pt_br, "data" => $requestChangeLimit->data));        
    }

    public function approveUpdateCardLimit(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'uuid' => ['required', 'string'],
            'token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        // Get change limt data
        if( ! $changeLimitData = ChangeLimit::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->where('change_limit_type_id', '=', 1)->whereNull('deleted_at')->first() ){
            return response()->json(array("error" => "Solicitação de alteração de limite não localizada"));
        }

        // Check if token is set
        if( $changeLimitData->approval_token == null or $changeLimitData->approval_token == '' ){
            return response()->json(array("error" => "Token inválido"));
        }

        // Check if token informed by user is equals change limit data
        if( $request->token != $changeLimitData->approval_token ){
            return response()->json(array("error" => "Token inválido"));
        }

        // Check if token is expired
        if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse( $changeLimitData->approval_token_expiration )->format('Y-m-d H:i:s')) ){
            return response()->json(array("error" => "Token inválido, token gerado a mais de 10 minutos, cancele e refaça o processo de alteração de limite"));
        }

        // Check card data
        if( ! $card = EdenredCard::where('id', '=', $changeLimitData->card_id)->where('account_id', '=', $changeLimitData->account_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Cartão não localizado"));
        }

        // Update card month limit
        $card->card_check_limit = $changeLimitData->check_limit;
        $card->card_monthly_limit = $changeLimitData->new_value;
        $card->save();

        // Update change limit data
        $changeLimitData->limit_changed = 1;
        $changeLimitData->limit_changed_at = \Carbon\Carbon::now();
        $changeLimitData->save();

        return response()->json(array("success" => "Limite mensal de cartão alterado com sucesso"));


    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\EdenredCard  $edenredCard
     * @return \Illuminate\Http\Response
     */
    public function edit(EdenredCard $edenredCard)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\EdenredCard  $edenredCard
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, EdenredCard $edenredCard)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\EdenredCard  $edenredCard
     * @return \Illuminate\Http\Response
     */
    public function destroy(EdenredCard $edenredCard)
    {
        //
    }
}
