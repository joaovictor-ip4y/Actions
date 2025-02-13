<?php

namespace App\Http\Controllers;

use App\Classes\Banking\PixClass;
use App\Libraries\Facilites;
use App\Models\PixFavored;
use App\Models\PixParticipant;
use App\Models\Account;
use App\Models\RegisterMaster;
use App\Models\Register;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PixFavoredController extends Controller
{
    protected function store(Request $request)
    {           
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [358, 359, 487];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //



        //Get Account CPF/CNPJ
        $accountData = Account::where('id', '=', $checkAccount->account_id)->first();
        $registerMasterData = RegisterMaster::where('id', '=',$accountData->register_master_id)->first();
        $registerData = Register::where('id', '=', $registerMasterData->register_id)->first();

        $clientCpfCnpj = $registerData->cpf_cnpj;

        $pixApiId = 9; // Celcoin
        if( $accountData->is_alias_account == 1 and $accountData->alias_account_keep_balance == 1 /*and ($accountData->id == 1 or $accountData->id == 3 )*/ ){
            $pixApiId = 16; // BMP
        }

        if( $request->pix_payment_type_id <= 4 ) {

            $validator = Validator::make($request->all(),[
                'pix_payment_type_id' => ['required', 'integer'],
                'informed_key_or_emv' => ['required', 'string']
            ],[
                'pix_payment_type_id.required' => 'É obrigatório informar o pix_payment_type_id',
                'informed_key_or_emv.required' => 'É obrigatório informar a informed_key_or_emv'
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            switch ($request->pix_payment_type_id) {
                case '1':
                    // validar informed_key_or_emv como cpf/cnpj

                    $validate = new Facilites();
                    $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->informed_key_or_emv);
                    $validate->cpf_cnpj = $cpf_cnpj;

                    if(strlen($cpf_cnpj) == 11) {
                        if( !$validate->validateCPF($cpf_cnpj) ){
                            return response()->json(['error' => 'CPF/CNPJ inválido']);
                        }
                    } else if(strlen($cpf_cnpj) == 14) {
                        if( !$validate->validateCNPJ($cpf_cnpj) ){
                            return response()->json(['error' => 'CPF/CNPJ inválido']);
                        }
                    } else {
                        return response()->json(['error' => 'CPF/CNPJ inválido']);
                    }     
                               
                    break;
                case '2':
                    // validar informed_key_or_emv como celular

                    $phone = (substr($request->informed_key_or_emv, 0, 3) == '+55') ? substr($request->informed_key_or_emv, 3) : $request->informed_key_or_emv;

                    if( strlen($phone) == 13 ){
                        $phone = (substr($request->informed_key_or_emv, 0, 2) == '55') ? substr($request->informed_key_or_emv, 2) : $request->informed_key_or_emv;
                    }

                    if( ! Facilites::validatePhone($phone)) {
                        return response()->json(["error" => "Celular inválido."]);
                    }

                    $request->informed_key_or_emv = '+55'.preg_replace( '/[^0-9]/', '', $phone);
                    break;
                case '3':
                    // validar informed_key_or_emv como e-mail

                    if( ! Facilites::validateEmail($request->informed_key_or_emv)) {
                        return response()->json(["error" => "E-mail inválido."]);
                    }

                    break;
                case '4':
                    // validar informed_key_or_emv como tendo 32 caracteres

                    if(strlen($request->informed_key_or_emv) < 32){
                        return response()->json(["error" => "Não foi possível localizar sua chave, por favor reveja os dados informados e tente novamente."]);
                    } 
                    
                    break;
                default:
                    return response()->json(["error" => "A requisição falhou, tente novamente mais tarde ou entre em contato com o administrador do sistema."]);
                    break;
            }

            //consultar DICT utilizando o parâmetro informed_key_or_emv, no sucesso da consulta inserir na tabela            
            //check dict

            $pixClass = new PixClass();

            $pixClass->payload = (object) [
                'key' => $request->informed_key_or_emv,
                'account_cpf_cnpj' => $clientCpfCnpj,
                'pix_api_id' => $pixApiId,
                'is_alias_account' =>  $accountData->is_alias_account,
                'alias_account_number' => $accountData->alias_account_number,
                'alias_account_agency' => $accountData->alias_account_agency
            ];



            $checkDict = $pixClass->checkDict();

            if ( ! $checkDict->success ){
                return response()->json(array("error" => $checkDict->message_pt_br));
            }
           
            if($pix_favored = PixFavored::create([
                'uuid'                       => Str::orderedUuid(),
                'account_id'                 => $checkAccount->account_id,
                'master_id'                  => $checkAccount->master_id,
                'payment_type_id'            => $request->pix_payment_type_id,
                'informed_key_or_emv'        => $request->informed_key_or_emv,
                'favored_name'               => $checkDict->data->name,
                'favored_cpf_cnpj'           => $checkDict->data->cpf_cnpj,
                'favored_instituition_id'    => $checkDict->data->participant_id,
                'favored_account_type_id'    => $checkDict->data->account_type_id,
                'favored_agency'             => $checkDict->data->agency,
                'favored_account'            => $checkDict->data->account, 
                'created_at'                 => \Carbon\Carbon::now()
            ])) {
                return response()->json(["success" => "Favorecido cadastrado com sucesso.", "data" => $pix_favored]);
            }
            return response()->json(["error" => "Poxa, não foi possível cadastrar o favorecido no momento, por favor tente novamente mais tarde."]);

        } else if ( $request->pix_payment_type_id == 5 ) {

            $validator = Validator::make($request->all(), [
                'pix_payment_type_id'     => ['required', 'integer'],
                'favored_account_type_id' => ['required', 'integer'],
                'favored_name'            => ['required', 'string'],
                'favored_cpf_cnpj'        => ['required', 'string'],
                'favored_instituition_id' => ['required', 'integer'],
                'favored_agency'          => ['required', 'string'],
                'favored_account'         => ['required', 'string']
            ],[
                'pix_payment_type_id.required' => 'É obrigatório informar o pix_payment_type_id',
                'favored_account_type_id.required' => 'É obrigatório informar o favored_account_type_id',
                'favored_name.required' => 'É obrigatório informar o nome do favorecido',
                'favored_cpf_cnpj.required' => 'É obrigatório informar o cpf/cnpj do favorecido',
                'favored_instituition_id.required' => 'É obrigatório informar o favored_instituition_id',
                'favored_agency.required' => 'É obrigatório informar a agência do favorecido',
                'favored_account.required' => 'É obrigatório informar a conta do favorecido'
            ]);

            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            // validar favored_cpf_cnpj como cpf/cnpj
            $validate = new Facilites();
            $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->favored_cpf_cnpj);
            $validate->cpf_cnpj = $cpf_cnpj;

            if(strlen($cpf_cnpj) == 11) {
                if( !$validate->validateCPF($cpf_cnpj) ){
                    return response()->json(['error' => 'CPF/CNPJ inválido']);
                }
            } else if(strlen($cpf_cnpj) == 14) {
                if( !$validate->validateCNPJ($cpf_cnpj) ){
                    return response()->json(['error' => 'CPF/CNPJ inválido']);
                }
            } else {
                return response()->json(['error' => 'CPF/CNPJ inválido']);
            }   

            // validar se favored_instituition_id existe em pix_participants
            // ----------------- Check Favored Instituition Id ----------------- //
            if( ! PixParticipant::where('id', '=', $request->favored_instituition_id)->first()) {
                return response()->json(['error' => 'Favorecido inválido']); //?
            }
            // ----------------- Finish Check Favored Instituition Id ----------------- //

            // validar se favored_name possui ao menos 3 caracteres
            if(strlen($request->favored_name) < 3) {
                return response()->json(['error' => 'Nome inválido']);
            }

            // validar se favored_agency e favored_account possuem ao menos 1 caracter cada
            if(strlen($request->favored_agency) < 1) {
                return response()->json(['error' => 'Agência inválida']);
            }

            if(strlen($request->favored_account) < 1) {
                return response()->json(['error' => 'Conta inválida']);
            }

            if($pix_favored = PixFavored::create([
                'uuid'                       => Str::orderedUuid(),
                'account_id'                 => $checkAccount->account_id,
                'master_id'                  => $checkAccount->master_id,
                'payment_type_id'            => $request->pix_payment_type_id,
                'favored_pix_participant_id' => $request->favored_pix_participant_id,
                'favored_name'               => $request->favored_name,
                'favored_cpf_cnpj'           => $request->favored_cpf_cnpj,
                'favored_instituition_id'    => $request->favored_instituition_id,
                'favored_agency'             => preg_replace('/[^0-9\-]/', "", str_replace('-', '', $request->favored_agency)),
                'favored_account'            => preg_replace('/[^A-Za-z0-9\-]/', "", str_replace('-', '',$request->favored_account)),
                'favored_account_type_id'    => $request->favored_account_type_id,
                'created_at'                 => \Carbon\Carbon::now()
            ])){
                return response()->json(["success" => "Favorecido cadastrado com sucesso.", "data" => $pix_favored]);
            } 
            return response()->json(["error" => "Poxa, não foi possível cadastrar o favorecido no momento, por favor tente novamente mais tarde."]);

            
        } else {
            return response()->json(["error" => "Tipo de PIX não permitido para cadastro de favorecido."]);
        }

    }

    protected function show(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [358, 359, 487];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixFavored              = new PixFavored();
        $pixFavored->id          = $request->id;
        $pixFavored->uuid        = $request->uuid;
        $pixFavored->account_id  = $checkAccount->account_id;
        $pixFavored->master_id   = $checkAccount->master_id;
        $pixFavored->onlyActive  = $request->onlyActive;

        $returnData = [];
        $i = 0;
        foreach ($pixFavored->get() as $pix) {
            array_push($returnData, $pix);

            if ($pix->payment_type_id != 5) {
                $returnData[$i]->favored_cpf_cnpj = Facilites::hideCpfWithOutMask($pix->favored_cpf_cnpj);
                $returnData[$i]->favored_account = '*******';
                $returnData[$i]->favored_agency = '****';
            } 
            
            $i++;
        }
        return response()->json($returnData);
    }

    protected function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [358, 359, 487];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string']
        ],[
            'id.required' => 'É obrigatório informar o id',
            'uuid.required' => 'É obrigatório informar o uuid'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }       
        
        // ----------------- Check Deletion Integrity ----------------- //
        $pixFavored = PixFavored::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->where('master_id', '=', $checkAccount->master_id)
            ->where('account_id', '=', $checkAccount->account_id)
            ->where('deleted_at', '=', null)
            ->first();

        if( !$pixFavored ) {
            return response()->json(["error" => "Favorecido não localizado, tente novamente mais tarde."]);
        }
        // ----------------- Finish Check Deletion Integrity ----------------- //

        $pixFavored->deleted_at = \Carbon\Carbon::now();

        if ($pixFavored->save()) {
            return response()->json(["success" => "Favorecido excluído com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível excluir o favorecido"]);
    }
}
