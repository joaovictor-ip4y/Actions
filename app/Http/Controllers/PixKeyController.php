<?php

namespace App\Http\Controllers;

use App\Models\PixKey;
use App\Models\Account;
use App\Models\CelcoinAccount;
use App\Models\IndirectPixAddressingKey;
use App\Models\PixParticipant;
use App\Models\PixKeyConfirmation;
use App\Models\IndirectPixKeyVindication;
use App\Classes\Celcoin\CelcoinClass;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\BancoRendimento\IndirectPix\Key\IncludePixKeyClass;
use App\Classes\BancoRendimento\IndirectPix\Key\RemovePixKeyClass;
use App\Classes\BancoRendimento\IndirectPix\Vindication\IncludePixVindicationClass;
use App\Classes\BancoRendimento\IndirectPix\Vindication\CancelPixVindicationClass;
use Illuminate\Http\Request;
use App\Libraries\Facilites;
use Illuminate\Support\Facades\Validator;
use App\Classes\Failure\MovimentationFailureClass;
use Illuminate\Support\Facades\Auth;

class PixKeyController extends Controller
{
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
        $accountCheckService->permission_id = [358, 359, 487];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'key_type_id' => ['required', 'integer'],
        ],[
            'key_type_id.required' => 'Informe o tipo de chave.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $key = null;
        $keyType = null;
        $keyTypeId = null;

        $getAccountData = new Account;
        $getAccountData->id = $checkAccount->account_id;
        $accountData = $getAccountData->returnAccountData();

        switch ($request->key_type_id) {
            case 1:
                if (strlen($accountData->cpf_cnpj) > 11 ) {
                    return response()->json(array("error" => "Conta PJ não permite cadastro de chave CPF"));
                }
                $key = $accountData->cpf_cnpj;
            break;
            case 2:
                if (strlen($accountData->cpf_cnpj) < 14 ) {
                    return response()->json(array("error" => "Conta PF não permite cadastro de chave CNPJ"));
                }
                $key = $accountData->cpf_cnpj;
            break;
            case 3:

                $validator = Validator::make($request->all(), [
                    'key' => ['required', 'string'],
                    'confirmation_key_id' => ['required', 'integer'],
                    'confirmation_key_uuid' => ['required', 'string'],
                    'confirmation_key_token' => ['required', 'string'],
                ],[
                    'key.required' => 'Informe a chave pix.',
                    'confirmation_key_id.required' => 'Informe o id de confirmação.',
                    'confirmation_key_uuid.required' => 'Informe o uuid de confirmação.',
                    'confirmation_key_token.required' => 'Informe o token de confirmação.',
                ]);
                
                if ($validator->fails()) {
                    return response()->json(["error" => $validator->errors()->first()]);
                }


                if (!Facilites::validatePhone($request->key)) {
                    return response()->josn(array("error" => "Número de celular inválido"));
                }

                $key = "+55".preg_replace('/[^0-9]/', '', $request->key);

                if (! $pixKeyConfirmation = PixKeyConfirmation::where('id', '=', $request->confirmation_key_id)->where('uuid', '=', $request->confirmation_key_uuid)->where('account_id', '=', $checkAccount->account_id)->where('key', '=', $key)->first() ) {
                    return response()->json(array("error" => "Por favor realize a confirmação da chave com o token enviado para o celular"));
                }

                if($pixKeyConfirmation->token_attempt >= 3){
                    return response()->json(array("error" => "Token informado incorretamente por mais de 3 vezes, por favor reinicie o processo de confirmação da chave.", "invalid_token" => true));
                }

                if ($pixKeyConfirmation->approval_token != $request->confirmation_key_token) {
                    $pixKeyConfirmation->token_attempt += 1;
                    $pixKeyConfirmation->save();

                    if ($pixKeyConfirmation->token_attempt >= 3) {
                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Token para Cadastro de Chave Pix Inválido';
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.(Account::where('id', '=', $checkAccount->account_id)->first())->account_number.'<br/><br/>
                        Informou incorretamente o token para confirmar o cadastro de chave PIX por mais de 3 vezes.<br/><br/>
                        Por esse motivo, não conseguiu realizar o cadastro da chave '.$key.'<br/><br/>
                        ID de confirmação '.$pixKeyConfirmation->id.'<br/><br/>';
                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }
                        $sendFailureAlert->sendFailures();
                    }

                    return response()->json(array("error" => "Token inválido, por favor verifique e tente novamente", "invalid_token" => true));
                }

                $now = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s');

                if( $now > (\Carbon\Carbon::parse($pixKeyConfirmation->approval_token_expiration)->format('Y-m-d H:i:s')) ) {
                    return response()->json(array("error" => "Token expirado, por favor refaça o processo de cadastro de chave para gerar um novo token de confirmação.", "invalid_token" => true));
                }

                $pixKeyConfirmation->token_validated = 1;
                $pixKeyConfirmation->token_validated_at = \Carbon\Carbon::now();
                $pixKeyConfirmation->save();

            break;
            case 4:
                $validator = Validator::make($request->all(), [
                    'key' => ['required', 'string'],
                    'confirmation_key_id' => ['required', 'integer'],
                    'confirmation_key_uuid' => ['required', 'string'],
                    'confirmation_key_token' => ['required', 'string', 'size:8'],
                ],[
                    'key.required' => 'Informe a chave pix.',
                    'confirmation_key_id.required' => 'Informe o id de confirmação.',
                    'confirmation_key_uuid.required' => 'Informe o uuid de confirmação.',
                    'confirmation_key_token.required' => 'Informe o token de confirmação.',
                ]);
                
                if ($validator->fails()) {
                    return response()->json(["error" => $validator->errors()->first()]);
                }

                if (!Facilites::validateEmail($request->key)) {
                    return response()->josn(array("error" => "E-Mail inválido"));
                }

                $key = $request->key;

                if (! $pixKeyConfirmation = PixKeyConfirmation::where('id', '=', $request->confirmation_key_id)->where('uuid', '=', $request->confirmation_key_uuid)->where('account_id', '=', $checkAccount->account_id)->where('key', '=', $key)->first() ) {
                    return response()->json(array("error" => "Por favor realize a confirmação da chave com o token enviado para o e-mail"));
                }

                if($pixKeyConfirmation->token_attempt >= 3){
                    return response()->json(array("error" => "Token informado incorretamente por mais de 3 vezes, por favor reinicie o processo de confirmação da chave.", "invalid_token" => true));
                }

                if ($pixKeyConfirmation->approval_token != $request->confirmation_key_token) {
                    $pixKeyConfirmation->token_attempt += 1;
                    $pixKeyConfirmation->save();

                    if ($pixKeyConfirmation->token_attempt >= 3) {
                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Token para Cadastro de Chave Pix Inválido';
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.(Account::where('id', '=', $checkAccount->account_id)->first())->account_number.'<br/><br/>
                        Informou incorretamente o token para confirmar o cadastro de chave PIX por mais de 3 vezes.<br/><br/>
                        Por esse motivo, não conseguiu realizar o cadastro da chave '.$key.'<br/><br/>
                        ID de confirmação '.$pixKeyConfirmation->id.'<br/><br/>';
                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }
                        $sendFailureAlert->sendFailures();
                    }

                    return response()->json(array("error" => "Token inválido, por favor verifique e tente novamente", "invalid_token" => true));
                }

                $now = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s');

                if( $now > (\Carbon\Carbon::parse($pixKeyConfirmation->approval_token_expiration)->format('Y-m-d H:i:s')) ) {
                    return response()->json(array("error" => "Token expirado, por favor refaça o processo de cadastro de chave para gerar um novo token de confirmação.", "invalid_token" => true));
                }

                $pixKeyConfirmation->token_validated = 1;
                $pixKeyConfirmation->token_validated_at = \Carbon\Carbon::now();
                $pixKeyConfirmation->save();

            break;
            case 5:
                $key = null;
            break;
            default:
                return response()->json(array("error" => "Tipo de chave não definido"));
            break;
        }

        if ($request->key_type_id < 5) {
            if (PixKey::where('key', '=', $key)->whereNull('deleted_at')->count() > 0 ) {
                return response()->json(array("error" => "Chave Pix já cadastrada, em caso de dúvidas, entre em contato com o suporte", "data" => ["allow_vindication" => false]));
            }
        }
    
        switch ($request->instituition_id) {
            case 647:

                $keyType = 'EVP';
                $keyTypeId = 5;

                if ( ! $account = CelcoinAccount::where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first() ) {
                    return response()->json(array("error" => "Conta não possuí cadastro na Celcoin, em caso de dúvidas entre em contato com seu gerente de relacionamento."));
                }

                if ($account->close_at != null and $account->close_at != '' ) {
                    return response()->json(array("error" => "Conta encerrada na Celcoin, em caso de dúvidas entre em contato com seu gerente de relacionamento."));
                }

                if ($account->deactivate_at != null and $account->deactivate_at != '' ) {
                    return response()->json(array("error" => "Conta bloqueada na Celcoin, em caso de dúvidas entre em contato com seu gerente de relacionamento."));
                }

                $celcoinClass = new CelcoinClass();

                $celcoinClass->account = $account->account;
                $celcoinClass->account_id = $account->account_id;
                $celcoinClass->celcoin_account_id = $account->id;
                $celcoinClass->key_type = $keyType;
                $celcoinClass->key_type_id = $keyTypeId;
                $celcoinClass->key = $key;

                $createKey = $celcoinClass->baasCreateKey();

                if( ! $createKey->success ) {
                    return response()->json(array("error" => $createKey->message_pt_br));
                }

            break;
            case 201:
                if ( ! $account = Account::where('id', '=', $checkAccount->account_id)->where('is_alias_account', '=', 1)->whereNull('alias_account_deleted_at')->whereNull('deleted_at')->first() ) {
                    return response()->json(array("error" => "Conta não possuí cadastro no BMP, em caso de dúvidas entre em contato com seu gerente de relacionamento."));
                }
            break;
            case 0:
               

                $createKey = IncludePixKeyClass::execute(
                    $checkAccount->account_id, 
                    $accountData->account_agency, 
                    $accountData->account_number,
                    $accountData->created_at, 
                    $accountData->cpf_cnpj, 
                    $accountData->name, 
                    '', 
                    $accountData->address_public_place.' '.$accountData->address.' '.$accountData->address_number.' '.$accountData->address_complement.' '.$accountData->address_district,
                    $accountData->address_city,
                    $accountData->address_state_short_description,
                    $accountData->address_zip_code,
                    $request->key_type_id,
                    $key
                );
                if (! $createKey["success"]) {
                    return response()->json(array("error" => $createKey["message"], "data" => $createKey["data"]));
                }

                return response()->json(array("success" => "Chave Pix cadastrada com sucesso."));
            break;
            default:
                return response()->json(array("error" => "Instituição não permite cadastro de chave Pix."));
            break;
        }

        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PixKey  $pixKey
     * @return \Illuminate\Http\Response
     */
    public function show(PixKey $pixKey, Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359, 487];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixKey->account_id = $checkAccount->account_id;
        $pixKey->master_id = $checkAccount->master_id;
        $pixKey->pix_key_type_id  = $request->pix_key_type_id;
        $pixKey->pix_participant_id = $request->pix_participant_id;
        $pixKey->onlyActive = $request->only_active;

        return response()->json( $pixKey->get() );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PixKey  $pixKey
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'uuid' => ['required', 'string'],
        ],[
            'id.required' => 'Informe o id.',
            'id.uuid' => 'Informe o uuid.'
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if (! $pixKey = PixKey::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Chave Pix não localizada" ));
        }

        $getPixParticipantId = (PixParticipant::where('ispb', '=', '11491029')->first())->id;

        if ($pixKey->pix_participant_id == $getPixParticipantId) {
            
            if (! $getIndirectPixKey = IndirectPixAddressingKey::where('pix_key', '=', $pixKey->key)->where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Chave Pix não localizada" ));
            }

            $removeKey = RemovePixKeyClass::execute($checkAccount->account_id, $getIndirectPixKey->id, $getIndirectPixKey->uuid, $pixKey->key);

            if (! $removeKey["success"] ) {
                return response()->json(array("error" => "Poxa, ocorreu uma falha ao excluir a chave Pix, por favor, tente novamente mais tarde.", "data" => $removeKey ));
            }

            return response()->json(array("success" => "Chave Pix excluida com sucesso"));
        }
    }

    public function showVindication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $getVindications = IndirectPixKeyVindication::when($checkAccount->account_id, function ($query, $accountId) {
            return $query->where('account_id', '=', $accountId);
        })->get();

        $vindications = [];

        foreach($getVindications as $vindication) {
            array_push($vindications, [
                'id' => $vindication->id,
                'uuid' => $vindication->uuid,
                'vindication_identification' => $vindication->vindication_identification,
                'status' => $vindication->pix_vindication_status_id,
                'key' => $vindication->pix_vindication_key,
                'participant_name' => PixParticipant::returnPixParticipantName($vindication->donor_institution),
                'created_at' => $vindication->created_at,
                'deadline_at' => $vindication->resolution_deadline_at,
                'last_update_at' => $vindication->status_last_modification_at,
            ]);
        }

        return response()->json($vindications);
    }

    public function storeVindication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'key_type_id' => ['required', 'integer'],
        ],[
            'key_type_id.required' => 'Informe o tipo de chave.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $key = null;
        $keyType = null;
        $keyTypeId = null;

        $getAccountData = new Account;
        $getAccountData->id = $checkAccount->account_id;
        $accountData = $getAccountData->returnAccountData();

        switch ($request->key_type_id) {
            case 1:
                if (strlen($accountData->cpf_cnpj) > 11 ) {
                    return response()->json(array("error" => "Conta PJ não permite portabilidade de chave CPF"));
                }
                $key = $accountData->cpf_cnpj;
            break;
            case 2:
                if (strlen($accountData->cpf_cnpj) < 14 ) {
                    return response()->josn(array("error" => "Conta PF não permite portabilidade de chave CNPJ"));
                }
                $key = $accountData->cpf_cnpj;
            break;
            case 3:

                $validator = Validator::make($request->all(), [
                    'key' => ['required', 'string'],
                    'confirmation_key_id' => ['required', 'integer'],
                    'confirmation_key_uuid' => ['required', 'string'],
                    'confirmation_key_token' => ['required', 'string'],
                ],[
                    'key.required' => 'Informe a chave pix.',
                    'confirmation_key_id.required' => 'Informe o id de confirmação.',
                    'confirmation_key_uuid.required' => 'Informe o uuid de confirmação.',
                    'confirmation_key_token.required' => 'Informe o token de confirmação.',
                ]);
                
                if ($validator->fails()) {
                    return response()->json(["error" => $validator->errors()->first()]);
                }


                if (!Facilites::validatePhone($request->key)) {
                    return response()->josn(array("error" => "Número de celular inválido"));
                }

                $key = "+55".preg_replace('/[^0-9]/', '', $request->key);

                if (! $pixKeyConfirmation = PixKeyConfirmation::where('id', '=', $request->confirmation_key_id)->where('uuid', '=', $request->confirmation_key_uuid)->where('account_id', '=', $checkAccount->account_id)->where('key', '=', $key)->first() ) {
                    return response()->json(array("error" => "Por favor realize a confirmação da chave com o token enviado para o celular"));
                }

                if($pixKeyConfirmation->token_attempt >= 3){
                    return response()->json(array("error" => "Token informado incorretamente por mais de 3 vezes, por favor reinicie o processo de confirmação da chave.", "invalid_token" => true));
                }

                if ($pixKeyConfirmation->approval_token != $request->confirmation_key_token) {
                    $pixKeyConfirmation->token_attempt += 1;
                    $pixKeyConfirmation->save();

                    if ($pixKeyConfirmation->token_attempt >= 3) {
                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Token para Cadastro de Chave Pix Inválido';
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.(Account::where('id', '=', $checkAccount->account_id)->first())->account_number.'<br/><br/>
                        Informou incorretamente o token para confirmar o cadastro de chave PIX por mais de 3 vezes.<br/><br/>
                        Por esse motivo, não conseguiu realizar o cadastro da chave '.$key.'<br/><br/>
                        ID de confirmação '.$pixKeyConfirmation->id.'<br/><br/>';
                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }
                        $sendFailureAlert->sendFailures();
                    }

                    return response()->json(array("error" => "Token inválido, por favor verifique e tente novamente", "invalid_token" => true));
                }

                $now = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s');

                if( $now > (\Carbon\Carbon::parse($pixKeyConfirmation->approval_token_expiration)->format('Y-m-d H:i:s')) ) {
                    return response()->json(array("error" => "Token expirado, por favor refaça o processo de cadastro de chave para gerar um novo token de confirmação.", "invalid_token" => true));
                }

                $pixKeyConfirmation->token_validated = 1;
                $pixKeyConfirmation->token_validated_at = \Carbon\Carbon::now();
                $pixKeyConfirmation->save();

            break;
            case 4:
                $validator = Validator::make($request->all(), [
                    'key' => ['required', 'string'],
                    'confirmation_key_id' => ['required', 'integer'],
                    'confirmation_key_uuid' => ['required', 'string'],
                    'confirmation_key_token' => ['required', 'string', 'size:8'],
                ],[
                    'key.required' => 'Informe a chave pix.',
                    'confirmation_key_id.required' => 'Informe o id de confirmação.',
                    'confirmation_key_uuid.required' => 'Informe o uuid de confirmação.',
                    'confirmation_key_token.required' => 'Informe o token de confirmação.',
                ]);
                
                if ($validator->fails()) {
                    return response()->json(["error" => $validator->errors()->first()]);
                }

                if (!Facilites::validateEmail($request->key)) {
                    return response()->josn(array("error" => "E-Mail inválido"));
                }

                $key = $request->key;

                if (! $pixKeyConfirmation = PixKeyConfirmation::where('id', '=', $request->confirmation_key_id)->where('uuid', '=', $request->confirmation_key_uuid)->where('account_id', '=', $checkAccount->account_id)->where('key', '=', $key)->first() ) {
                    return response()->json(array("error" => "Por favor realize a confirmação da chave com o token enviado para o e-mail"));
                }

                if($pixKeyConfirmation->token_attempt >= 3){
                    return response()->json(array("error" => "Token informado incorretamente por mais de 3 vezes, por favor reinicie o processo de confirmação da chave.", "invalid_token" => true));
                }

                if ($pixKeyConfirmation->approval_token != $request->confirmation_key_token) {
                    $pixKeyConfirmation->token_attempt += 1;
                    $pixKeyConfirmation->save();

                    if ($pixKeyConfirmation->token_attempt >= 3) {
                        $sendFailureAlert = new MovimentationFailureClass();
                        $sendFailureAlert->title = 'Token para Cadastro de Chave Pix Inválido';
                        $sendFailureAlert->errorMessage = 'Atenção, a conta: '.(Account::where('id', '=', $checkAccount->account_id)->first())->account_number.'<br/><br/>
                        Informou incorretamente o token para confirmar o cadastro de chave PIX por mais de 3 vezes.<br/><br/>
                        Por esse motivo, não conseguiu realizar o cadastro da chave '.$key.'<br/><br/>
                        ID de confirmação '.$pixKeyConfirmation->id.'<br/><br/>';
                        if($user = Auth::user()) {
                            $sendFailureAlert->errorMessage .= 'Usuário logado: '.$user->name.'<br/>
                            E-Mail: '.$user->email.'<br/>
                            Celular: '.$user->phone;
                        }
                        $sendFailureAlert->sendFailures();
                    }

                    return response()->json(array("error" => "Token inválido, por favor verifique e tente novamente", "invalid_token" => true));
                }

                $now = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s');

                if( $now > (\Carbon\Carbon::parse($pixKeyConfirmation->approval_token_expiration)->format('Y-m-d H:i:s')) ) {
                    return response()->json(array("error" => "Token expirado, por favor refaça o processo de cadastro de chave para gerar um novo token de confirmação.", "invalid_token" => true));
                }

                $pixKeyConfirmation->token_validated = 1;
                $pixKeyConfirmation->token_validated_at = \Carbon\Carbon::now();
                $pixKeyConfirmation->save();

            break;
            //case 5:
            //    $key = null;
            //break;
            default:
                return response()->json(array("error" => "Tipo de chave não definido"));
            break;
        }

        if ($request->key_type_id < 5) {
            if (PixKey::where('key', '=', $key)->whereNull('deleted_at')->count() > 0 ) {
                return response()->json(array("error" => "Chave Pix já cadastrada, em caso de dúvidas, entre em contato com o suporte", "data" => ["allow_vindication" => false]));
            }
        }
    
        switch ($request->instituition_id) {
            case 0:
                $vindicationKey = IncludePixVindicationClass::execute(
                    $checkAccount->account_id, 
                    $accountData->account_agency, 
                    $accountData->account_number,
                    $accountData->created_at, 
                    $accountData->cpf_cnpj, 
                    $accountData->name, 
                    '', 
                    $accountData->address_public_place.' '.$accountData->address.' '.$accountData->address_number.' '.$accountData->address_complement.' '.$accountData->address_district,
                    $accountData->address_city,
                    $accountData->address_state_short_description,
                    $accountData->address_zip_code,
                    $request->key_type_id,
                    $key
                );
                if (! $vindicationKey["success"]) {
                    return response()->json(array("error" => $vindicationKey["message"], "data" => $vindicationKey["data"]));
                }

                return response()->json(array("success" => "Portabilidade de chave Pix solicitada com sucesso."));
            break;
            default:
                return response()->json(array("error" => "Instituição não permite portabilidade de chave Pix."));
            break;
        }

        
    }

    public function destroyVindication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [358, 359];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'portability_id' => ['required', 'integer'],
            'portability_uuid' => ['required', 'string'],
            'key' => ['required', 'string'],
        ],[
            'portability_id.required' => 'Informe o id da portabilidade.',
            'portability_uuid.required' => 'Informe o uuid da portabilidade.',
            'key.required' => 'Informe a chave Pix.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if ( ! IndirectPixKeyVindication::where('account_id', '=', $checkAccount->account_id)->where('id', '=', $request->portability_id)->where('uuid', '=', $request->portability_uuid)->whereIn('pix_vindication_status_id', [1,2])->first() ) {
            return response()->json(array("error" => "Portabilidade de chave Pix não localizada"));
        }

        $getAccountData = new Account;
        $getAccountData->id = $checkAccount->account_id;
        $accountData = $getAccountData->returnAccountData();

        $cancelVindicationKey = CancelPixVindicationClass::execute(
            $checkAccount->account_id, 
            $accountData->account_agency, 
            $accountData->account_number,
            $request->key
        );
        if (! $cancelVindicationKey["success"]) {
            return response()->json(array("error" => $cancelVindicationKey["message"], "data" => $cancelVindicationKey["data"]));
        }

        return response()->json(array("success" => "Solicitação de portabilidade de chave Pix cancelada com sucesso."));
    }
}
