<?php

namespace App\Http\Controllers;

use App\Models\PixPaymentV2;
use App\Models\PixFavored;
use App\Models\Account;
use App\Models\RegisterMaster;
use App\Models\Register;
use App\Models\CelcoinAccount;
use App\Classes\Banking\PixClass;
use App\Libraries\Facilites;
use App\Models\PixParticipant;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Classes\Failure\TransactionFailureClass;
use App\Models\SystemFunctionMaster;
use Illuminate\Support\Facades\Auth;

class PixPaymentV2Controller extends Controller
{
    public function checkServiceAvailable(Request $request)
    {
        if( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$request->header('masterId'))->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível realizar pix."));
        } else {
            return response()->json(array("success" => ""));
        }
    }

    protected function store(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        if( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível realizar pix."));
        }

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'pix_payment_type_id' => ['required', 'integer'],
        ],[
            'pix_payment_type_id.required' => 'Informe o tipo de pagamento PIX.'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $getAccountInclusionLimitQtt = Account::where('id','=',$checkAccount->account_id)->whereNull('deleted_at')->first()->inclusion_limit_pix_payment_qtt;
        if($getAccountInclusionLimitQtt == null) {
            $getAccountInclusionLimitQtt = 0;
        }

        $paymentWaitingApprovingQtt = PixPaymentV2::where('master_id', '=', $checkAccount->master_id)->where('account_id', '=', $checkAccount->account_id)->whereNull('payment_date')->whereNull('deleted_at')->where('value', '>', 0)->whereIn('status_id', [5])->count();

        if ($paymentWaitingApprovingQtt >= $getAccountInclusionLimitQtt ) {
            return response()->json(array("error" => "Sua conta possuí $paymentWaitingApprovingQtt PIX aguardando aprovação, por favor realize a aprovação para continuar"));
        }


        //Get Account CPF/CNPJ
        $accountData = Account::where('id', '=', $checkAccount->account_id)->first();
        $registerMasterData = RegisterMaster::where('id', '=',$accountData->register_master_id)->first();
        $registerData = Register::where('id', '=', $registerMasterData->register_id)->first();


        $pixApiId = 9; // Celcoin

        if ($accountData->pix_api_id == 18) { // Indireto Rendimento
            $pixApiId = 18;
        }

        if( $accountData->is_alias_account == 1 and $accountData->alias_account_keep_balance == 1 /*and ($accountData->id == 1 or $accountData->id == 3 )*/ ){
            $pixApiId = 16; // BMP
        }

        $clientCpfCnpj = $registerData->cpf_cnpj;

        // check if account has Celcoin Account
        if($pixApiId == 9) {
            if ( ! $getCelcoinAccount = CelcoinAccount::where('account_id', '=', $checkAccount->account_id)->whereNull('deleted_at')->first() ){

                $sendFailureAlert = new TransactionFailureClass();
                $sendFailureAlert->title = 'Falha Inclusão PIX - Conta Celcoin Não Cadastrada';
                $sendFailureAlert->errorMessage = 'Atenção, a conta '.$accountData->account_number.' não possui conta aberta na Celcoin, realize o processo de abertura de conta na celcoin para que consigam realizar PIX.';
                if($user = Auth::user()) {
                    $sendFailureAlert->errorMessage .= '<br/><br/>Usuário logado: '.$user->name.'<br/>
                    E-Mail: '.$user->email.'<br/>
                    Celular: '.$user->phone;
                }
                
                $sendFailureAlert->sendFailures();
    
                return response()->json(["error" => "No momento, não é possível realizar PIX na sua conta, por favor entre em contato com seu gerente."]);    
            } 
        }

        if( $request->pix_payment_type_id <= 4) {
            
            $validator = Validator::make($request->all(), [
                'pix_payment_type_id'            => ['required', 'integer'],
                'informed_key_or_emv'            => ['required', 'string'],
            ],[
                'pix_payment_type_id.required'   => 'É obrigatório informar o tipo de pagamento PIX',
                'informed_key_or_emv.required'   => 'É obrigatório informar a chave ou o QR Code',
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

                    $request->informed_key_or_emv = strtolower($request->informed_key_or_emv);
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
                    return response()->json(["error" => "Tipo de PIX não definido, por favor verifique."]);
                break;
            }

            
        
            //check dict
            $pixClass = new PixClass();
            $pixClass->payload = (object) [
                'key' => $request->informed_key_or_emv,
                'account_cpf_cnpj' => $clientCpfCnpj,
                'pix_api_id' => $pixApiId,
                'is_alias_account' =>  $accountData->is_alias_account,
                'alias_account_number' => $accountData->alias_account_number,
                'alias_account_agency' => $accountData->alias_account_agency,
                'account_id' => $checkAccount->account_id
            ];

            $checkDict = $pixClass->checkDict();

            if ( ! $checkDict->success ){
                return response()->json(array("error" => $checkDict->message_pt_br));
            }

            // set tax
            $tax = 0;
            $taxPercentage = 0;
            $getTax = Account::getTax($checkAccount->account_id, 21, $checkAccount->master_id);
            if($getTax->value > 0){
                $tax = $getTax->value;
            } else if($getTax->percentage > 0){
                $taxPercentage = $getTax->percentage/100;
            }
           
            if($pixPaymentV2 = PixPaymentV2::create([
                'uuid'                              => Str::orderedUuid(),
                'account_id'                        => $checkAccount->account_id,
                'master_id'                         => $checkAccount->master_id,
                'payment_type_id'                   => $request->pix_payment_type_id,
                'informed_key_or_emv'               => $request->informed_key_or_emv,
                'end_to_end'                        => $checkDict->data->end_to_end,
                'favored_key'                       => $checkDict->data->key,
                'favored_name'                      => $checkDict->data->name,
                'favored_cpf_cnpj'                  => $checkDict->data->cpf_cnpj,
                'favored_pix_participant_id'        => $checkDict->data->participant_id,
                'favored_account_type_id'           => $checkDict->data->account_type_id,
                'favored_agency'                    => $checkDict->data->agency,
                'favored_account'                   => $checkDict->data->account,
                'indirect_pix_dict_consultation_id' => isset($checkDict->data->indirect_pix_dict_consultation_id) ? $checkDict->data->indirect_pix_dict_consultation_id : null,
                'value'                             => 0,
                'tax_value'                         => $tax,
                'status_id'                         => 38,
                'api_id'                            => $checkDict->data->api_id,
                'included_by_user_id'               => $checkAccount->user_id,
                'included_by_user_relationship_id'  => $checkAccount->user_relationship_id,
                'created_at'                        => \Carbon\Carbon::now()
            ])) {

                if( 
                    ( PixFavored::where('account_id', '=', $checkAccount->account_id)
                    ->where('master_id', '=', $checkAccount->master_id)
                    ->where('payment_type_id', '=', $request->pix_payment_type_id)
                    ->where('favored_cpf_cnpj', '=', $checkDict->data->cpf_cnpj)
                    ->where('favored_account_type_id', '=', $checkDict->data->account_type_id)
                    ->where('favored_instituition_id', '=', $checkDict->data->participant_id)
                    ->where('favored_agency', '=', $checkDict->data->agency)
                    ->where('favored_account', '=', $checkDict->data->account)
                    ->count() ) == 0
                ){
                    PixFavored::create([
                        'uuid'                    => Str::orderedUuid(),
                        'account_id'              => $checkAccount->account_id,
                        'master_id'               => $checkAccount->master_id,
                        'payment_type_id'         => $request->pix_payment_type_id,
                        'informed_key_or_emv'     => $request->informed_key_or_emv,
                        'favored_name'            => $checkDict->data->name,
                        'favored_cpf_cnpj'        => $checkDict->data->cpf_cnpj,
                        'favored_instituition_id' => $checkDict->data->participant_id,
                        'favored_account_type_id' => $checkDict->data->account_type_id,
                        'favored_agency'          => $checkDict->data->agency,
                        'favored_account'         => $checkDict->data->account, 
                        'created_at'              => \Carbon\Carbon::now()
                    ]);
                }

                $pixData = PixClass::maskPixSensibleData($pixPaymentV2->get());
              
                return response()->json(["success" => "PIX incluído com sucesso.", "data" => $pixData]);
            }
            return response()->json(["error" => "Poxa, não foi possível incluir o PIX no momento, por favor tente novamente mais tarde."]);

        } else if ( $request->pix_payment_type_id == 5 ) {

            $validator = Validator::make($request->all(), [
                'pix_payment_type_id'              => ['required', 'integer'],
                'favored_account_type_id'          => ['required', 'integer'],
                'favored_name'                     => ['required', 'string'],
                'favored_cpf_cnpj'                 => ['required', 'string'],
                'favored_instituition_id'          => ['required', 'integer'],
                'favored_agency'                   => ['required', 'string'],
                'favored_account'                  => ['required', 'string'],
            ],[
                'pix_payment_type_id.required'     => 'É obrigatório informar o tipo de pagamento PIX',
                'favored_account_type_id.required' => 'É obrigatório informar o tipo de conta do favorecido',
                'favored_name.required'            => 'É obrigatório informar o nome do favorecido',
                'favored_cpf_cnpj.required'        => 'É obrigatório informar o CPF/CNPJ do favorecido',
                'favored_instituition_id.required' => 'É obrigatório informar a instituição',
                'favored_agency.required'          => 'É obrigatório informar a agência do favorecido',
                'favored_account.required'         => 'É obrigatório informar a conta do favorecido',
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
                return response()->json(['error' => 'Instituição do favorecido inválida']); //?
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



            // set tax
            $tax = 0;
            $taxPercentage = 0;
            $getTax = Account::getTax($checkAccount->account_id, 21, $checkAccount->master_id);
            if($getTax->value > 0){
                $tax = $getTax->value;
            } else if($getTax->percentage > 0){
                $taxPercentage = $getTax->percentage/100;
            }

            if($pixPaymentV2 = PixPaymentV2::create([
                'uuid'                             => Str::orderedUuid(),
                'account_id'                       => $checkAccount->account_id,
                'master_id'                        => $checkAccount->master_id,
                'api_id'                           => $pixApiId,
                'payment_type_id'                  => $request->pix_payment_type_id,
                'favored_pix_participant_id'       => $request->favored_instituition_id,
                'favored_name'                     => $request->favored_name,
                'favored_cpf_cnpj'                 => $request->favored_cpf_cnpj,
                'favored_instituition_id'          => $request->favored_instituition_id,
                'favored_agency'                   => preg_replace('/[^0-9\-]/', "", str_replace('-', '', $request->favored_agency)),
                'favored_account'                  => preg_replace('/[^A-Za-z0-9\-]/', "", str_replace('-', '', $request->favored_account)),
                'favored_account_type_id'          => $request->favored_account_type_id,
                'value'                            => 0,
                'tax_value'                        => $tax,
                'status_id'                        => 38,
                'included_by_user_id'              => $checkAccount->user_id,
                'included_by_user_relationship_id' => $checkAccount->user_relationship_id,
                'created_at'                       => \Carbon\Carbon::now()
            ])){

                if( 
                    ( PixFavored::where('account_id', '=', $checkAccount->account_id)
                    ->where('master_id', '=', $checkAccount->master_id)
                    ->where('payment_type_id', '=', $request->pix_payment_type_id)
                    ->where('favored_cpf_cnpj', '=', $request->favored_cpf_cnpj)
                    ->where('favored_account_type_id', '=', $request->favored_account_type_id)
                    ->where('favored_instituition_id', '=', $request->favored_instituition_id)
                    ->where('favored_agency', '=', preg_replace('/[^0-9\-]/', "", str_replace('-', '', $request->favored_agency)))
                    ->where('favored_account', '=', preg_replace('/[^A-Za-z0-9\-]/', "", str_replace('-', '', $request->favored_account)))
                    ->count() ) == 0
                ){
                    PixFavored::create([
                        'uuid'                    => Str::orderedUuid(),
                        'account_id'              => $checkAccount->account_id,
                        'master_id'               => $checkAccount->master_id,
                        'payment_type_id'         => $request->pix_payment_type_id,
                        'favored_name'            => $request->favored_name,
                        'favored_cpf_cnpj'        => $request->favored_cpf_cnpj,
                        'favored_instituition_id' => $request->favored_instituition_id,
                        'favored_account_type_id' => $request->favored_account_type_id,
                        'favored_agency'          => preg_replace('/[^0-9\-]/', "", str_replace('-', '', $request->favored_agency)),
                        'favored_account'         => preg_replace('/[^A-Za-z0-9\-]/', "", str_replace('-', '', $request->favored_account)), 
                        'created_at'              => \Carbon\Carbon::now()
                    ]);
                }

                $pixData = PixClass::maskPixSensibleData($pixPaymentV2->get());

                return response()->json(["success" => "PIX incluído com sucesso, informe o valor que deseja transferir para continuar.", "data" => $pixData]);
            } 
            return response()->json(["error" => "Poxa, não foi possível incluir o PIX no momento, por favor tente novamente mais tarde."]);
        } else if ( $request->pix_payment_type_id > 5 ) {

            $validator = Validator::make($request->all(), [
                'pix_payment_type_id'            => ['required', 'integer'],
                'informed_key_or_emv'            => ['required', 'string'],
            ],[
                'pix_payment_type_id.required'   => 'É obrigatório informar o tipo de pagamento PIX',
                'informed_key_or_emv.required'   => 'É obrigatório informar a chave ou QR Code',
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            // validar informed_key_or_emv como tendo 32 caracteres

            if(strlen($request->informed_key_or_emv) < 32){
                return response()->json(["error" => "Não foi possível localizar sua chave, por favor reveja os dados informados e tente novamente."]);
            } 

            //check dict
            $pixClass = new PixClass();
            $pixClass->payload = (object) [
                'emv' => $request->informed_key_or_emv,
                'account_cpf_cnpj' => $clientCpfCnpj,
                'is_alias_account' =>  $accountData->is_alias_account,
                'alias_account_number' => $accountData->alias_account_number,
                'alias_account_agency' => $accountData->alias_account_agency,
                'account_id' => $checkAccount->account_id,
                'pix_api_id' => $pixApiId,
            ];

            $checkEmv = $pixClass->checkEmv();

            if ( ! $checkEmv->success ){
                return response()->json(array("error" => $checkEmv->message_pt_br));
            }

            // set tax
            $tax = 0;
            $taxPercentage = 0;
            $getTax = Account::getTax($checkAccount->account_id, 21, $checkAccount->master_id);
            if($getTax->value > 0){
                $tax = $getTax->value;
            } else if($getTax->percentage > 0){
                $taxPercentage = $getTax->percentage/100;
            }

            if($taxPercentage > 0){
                $tax = round( $taxPercentage * $checkEmv->data->value );
            }
           
            if($pixPaymentV2 = PixPaymentV2::create([
                'uuid'                                 => Str::orderedUuid(),
                'account_id'                           => $checkAccount->account_id,
                'master_id'                            => $checkAccount->master_id,
                'payment_type_id'                      => $request->pix_payment_type_id,
                'informed_key_or_emv'                  => $request->informed_key_or_emv,
                'end_to_end'                           => $checkEmv->data->end_to_end,
                'favored_name'                         => $checkEmv->data->name,
                'favored_cpf_cnpj'                     => $checkEmv->data->cpf_cnpj,
                'favored_pix_participant_id'           => $checkEmv->data->participant_id,
                'favored_account_type_id'              => $checkEmv->data->account_type_id,
                'favored_agency'                       => $checkEmv->data->agency,
                'favored_account'                      => $checkEmv->data->account,
                'favored_key'                          => $checkEmv->data->key,
                'qr_code_type_id'                      => $checkEmv->data->qr_code_type_id,
                'qr_code_transaction_identification'   => $checkEmv->data->qr_code_transaction_identification,
                'qr_code_url'                          => $checkEmv->data->qr_code_url,
                'qr_code_created_at'                   => $checkEmv->data->qr_code_created_at,
                'qr_code_original_value'               => $checkEmv->data->qr_code_original_value,
                'qr_code_abatement'                    => $checkEmv->data->qr_code_abatement,
                'qr_code_discount'                     => $checkEmv->data->qr_code_discount,
                'qr_code_interest'                     => $checkEmv->data->qr_code_interest,
                'qr_code_fine'                         => $checkEmv->data->qr_code_fine,
                'qr_code_final_value'                  => $checkEmv->data->qr_code_final_value,
                'qr_code_due_date'                     => $checkEmv->data->qr_code_due_date,
                'qr_code_expiration'                   => $checkEmv->data->qr_code_expiration,
                'qr_code_expiration_date'              => $checkEmv->data->qr_code_expiration_date,
                'qr_code_valid_after_expiration'       => $checkEmv->data->qr_code_valid_after_expiration,
                'qr_code_payer_cpf_cnpj'               => $checkEmv->data->qr_code_payer_cpf_cnpj,
                'qr_code_payer_name'                   => $checkEmv->data->qr_code_payer_name,
                'qr_code_beneficiary_cpf_cnpj'         => $checkEmv->data->qr_code_beneficiary_cpf_cnpj,
                'qr_code_beneficiary_name'             => $checkEmv->data->qr_code_beneficiary_name,
                'value'                                => $checkEmv->data->value > 0 ? $checkEmv->data->value : 0,
                'indirect_pix_qr_code_consultation_id' => isset($checkEmv->data->indirect_pix_qr_code_consultation_id) ? $checkEmv->data->indirect_pix_qr_code_consultation_id : null,
                'qr_code_pix_withdraw_change_value'    => isset($checkEmv->data->qr_code_pix_withdraw_change_value) ? $checkEmv->data->qr_code_pix_withdraw_change_value : null,
                'tax_value'                            => $tax,
                'status_id'                            => 38,
                'api_id'                               => $checkEmv->data->api_id,
                'included_by_user_id'                  => $checkAccount->user_id,
                'included_by_user_relationship_id'     => $checkAccount->user_relationship_id,
                'created_at'                           => \Carbon\Carbon::now()
            ])) {
                
                $pixData = PixClass::maskPixSensibleData($pixPaymentV2->get());

                return response()->json(["success" => "PIX incluído com sucesso.", "data" => $pixData ]);
            }
            return response()->json(["error" => "Poxa, não foi possível incluir o PIX no momento, por favor tente novamente mais tarde."]);

        } 
    }

    protected function show(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));
        
        $pixPaymentV2 = new PixPaymentV2();
        $pixPaymentV2->id                 = $request->id;
        $pixPaymentV2->uuid               = $request->uuid;
        $pixPaymentV2->status_id          = $request->status_id;
        $pixPaymentV2->account_id         = $checkAccount->account_id;
        $pixPaymentV2->master_id          = $checkAccount->master_id;
        $pixPaymentV2->onlyActive         = $request->onlyActive;
        $pixPaymentV2->payment_date_start = $request->payment_date_start;
        $pixPaymentV2->payment_date_end   = $request->payment_date_end;
        return response()->json(PixClass::maskPixSensibleData($pixPaymentV2->get()));
    }

    protected function destroy(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $validator = Validator::make($request->all(), [
            'id'            => ['required', 'integer'],
            'uuid'          => ['required', 'string']
        ],[
            'id.required'   => 'É obrigatório informar o id',
            'uuid.required' => 'É obrigatório informar o uuid'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // ----------------- Check Deletion Integrity ----------------- //
        $pixPaymentV2 = PixPaymentV2::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->where('master_id', '=', $checkAccount->master_id)
            ->where('account_id', '=', $checkAccount->account_id)
            ->whereNull('payment_date')
            ->whereNull('deleted_at')
            ->whereIn('status_id', [5, 6, 38])
            ->first();

        if( !$pixPaymentV2 ) {
            return response()->json(["error" => "PIX a pagar não localizado, tente novamente mais tarde."]);
        }
        // ----------------- Finish Check Deletion Integrity ----------------- //

        $pixPaymentV2->deleted_at = \Carbon\Carbon::now();

        if ($pixPaymentV2->save()) {
            return response()->json(["success" => "PIX a pagar excluído com sucesso"]);
        }
    }

    protected function setValue(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $validator = Validator::make($request->all(), [
            'id'             => ['required', 'integer'],
            'uuid'           => ['required', 'string'],
            'value'          => ['required', 'numeric']
        ],[
            'id.required'    => 'É obrigatório informar o id',
            'uuid.required'  => 'É obrigatório informar o uuid',
            'value.required' => 'É obrigatório informar o valor',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // ----------------- Check Update Integrity ----------------- //
        $pixPaymentV2 = PixPaymentV2::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->where('master_id', '=', $checkAccount->master_id)
            ->where('account_id', '=', $checkAccount->account_id)
            ->whereNull('schedule_date')
            ->whereNull('payment_date')
            ->whereNull('deleted_at')
            ->where('value', '<=', 0)
            ->where('status_id', '=', 38)
            ->first();

        if( !$pixPaymentV2 ) {
            return response()->json(["error" => "PIX não localizado, tente novamente mais tarde."]);
        }
        // ----------------- Finish Check Update Integrity ----------------- //

        if( $request->value <= 0 ){
            return response()->json(["error" => "Defina um valor maior que zero para realizar o PIX."]);
        }
        
        if( $request->value > 100000  and $pixPaymentV2->api_id == 9){
            return response()->json(["error" => "Por medidas de segurança, o valor máximo permitido por pix é de R$ 100.000,00. Se necessário, realize outros pix até atingir o valor desejado."]);
        }

        if( $request->value > 100000  and $pixPaymentV2->api_id == 18){
            return response()->json(["error" => "Por medidas de segurança, o valor máximo permitido por pix é de R$ 100.000,00. Se necessário, realize outros pix até atingir o valor desejado."]);
        }

        $pixPaymentV2->value = $request->value;

        if ($pixPaymentV2->save()) {

            $pixData = PixClass::maskPixSensibleData($pixPaymentV2->get());

            return response()->json(["success" => "Valor do PIX definido com sucesso.", "data" => $pixData ]);
        }

        return response()->json(["error" => "Não foi possível definir o valor do PIX agora, por favor verifique se já foi definido, se esse PIX já foi realizado, ou, tente novamente mais tarde."]);
    }

    protected function setSchedule(Request $request)
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
            'id'             => ['required', 'integer'],
            'uuid'           => ['required', 'string'],
            'date'           => ['required', 'date']
        ],[
            'id.required'    => 'É obrigatório informar o id',
            'uuid.required'  => 'É obrigatório informar o uuid',
            'date.required'  => 'É obrigatório informar a data de agendamento',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $getAccountInclusionLimitQtt = Account::where('id','=',$checkAccount->account_id)->whereNull('deleted_at')->first()->inclusion_limit_pix_payment_qtt;
        if($getAccountInclusionLimitQtt == null) {
            $getAccountInclusionLimitQtt = 0;
        }

        $paymentWaitingApprovingQtt = PixPaymentV2::where('master_id', '=', $checkAccount->master_id)->where('account_id', '=', $checkAccount->account_id)->whereNull('payment_date')->whereNull('deleted_at')->where('value', '>', 0)->whereIn('status_id', [5])->count();

        if ($paymentWaitingApprovingQtt >= $getAccountInclusionLimitQtt ) {
            return response()->json(array("error" => "Sua conta possuí $paymentWaitingApprovingQtt PIX aguardando aprovação, por favor realize a aprovação para continuar"));
        }

        $today = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d');

        if( $request->date < $today ){
            return response()->json(["error" => "A data de agendamento deve ser maior ou igual à data corrente."]);
        }

        // ----------------- Check Update Integrity ----------------- //
        $pixPaymentV2 = PixPaymentV2::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->where('master_id', '=', $checkAccount->master_id)
            ->where('account_id', '=', $checkAccount->account_id)
            ->whereNull('payment_date')
            ->whereNull('deleted_at')
            ->where('value', '>', 0)
            ->whereIn('status_id', [5, 38])
            ->first();

        if( !$pixPaymentV2 ) {
            return response()->json(["error" => "PIX agendado não localizado, tente novamente mais tarde."]);
        }
        // ----------------- Finish Check Update Integrity ----------------- //


        $pixPaymentV2->schedule_date = $request->date;
        $pixPaymentV2->description = mb_substr($request->description, 0, 255);
        $pixPaymentV2->status_id = 5;

        if ($pixPaymentV2->save()) {

            $pixData = PixClass::maskPixSensibleData($pixPaymentV2->get());

            return response()->json(["success" => "PIX agendado com sucesso, realize a aprovação para efetivá-lo.", "data" => $pixData]);
        }

        return response()->json(["error" => "Não foi possível definir o agendamento do PIX agora, por favor verifique se já foi definido, se esse PIX já foi realizado, ou, tente novamente mais tarde."]);

    }

    protected function cancelSchedule(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [184, 265, 422];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'            => ['required', 'integer'],
            'uuid'          => ['required', 'string']
        ],[
            'id.required'   => 'É obrigatório informar o id',
            'uuid.required' => 'É obrigatório informar o uuid'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        // ----------------- Check Cancel Schedule Integrity ----------------- //
        $pixPaymentV2 = PixPaymentV2::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->where('master_id', '=', $checkAccount->master_id)
            ->where('account_id', '=', $checkAccount->account_id)
            ->whereNotNull('schedule_date')
            ->whereNull('payment_date')
            ->whereNull('deleted_at')
            ->where('status_id', '=', 7)
            ->first();

        if( !$pixPaymentV2 ) {
            return response()->json(["error" => "Agendamento de PIX não localizado, tente novamente mais tarde."]);
        }
        // ----------------- Finish Cancel Schedule Integrity ----------------- //

        $pixPaymentV2->status_id = 8;

        if ($pixPaymentV2->save()) {
            return response()->json(["success" => "Agendamento de PIX cancelado com sucesso."]);
        }

        return response()->json(["success" => "Não foi possível cancelar o agendamento do PIX agora, por favor verifique se já foi cancelado, se esse PIX já foi realizado, ou, tente novamente mais tarde."]);

    }

    protected function approve(Request $request)
    {
            
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'idIn'=> ['required', 'array'],
            'idIn.*'=> ['required', 'integer'],
            'uuidIn'=> ['required', 'array'],
            'uuidIn.*'=> ['required', 'string'],
            'password'=> ['required', 'string'],
        ],[
            'id.required' => 'Informe o id de pagamento.',
            'password.required' => 'Informe a senha.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $pixClass = new PixClass();
        $pixClass->payload = $request;
        $pixClass->account_id = $checkAccount->account_id;
        $sendToken = $pixClass->sendTokenBySMS();

        if ( ! $sendToken->success ){
            return response()->json(array("error" => $sendToken->message_pt_br));
        }

        return response()->json(array("success" => $sendToken->message_pt_br, "data" => $sendToken->data));
    }
    
    protected function resendTokenByWhatsApp(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'idIn'=> ['required', 'array'],
            'idIn.*'=> ['required', 'integer'],
            'uuidIn'=> ['required', 'array'],
            'uuidIn.*'=> ['required', 'string'],
            'batch_id'=> ['required', 'string'],
        ],[
            'id.required' => 'Informe o id de pagamento.',
            'batch_id.required' => 'Informe o lote de pagamento.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        $pixClass = new PixClass();
        $pixClass->payload = $request;
        $sendToken = $pixClass->resendTokenByWhatsApp();

        if ( ! $sendToken->success ){
            return response()->json(array("error" => $sendToken->message_pt_br));
        }

        return response()->json(array("success" => $sendToken->message_pt_br, "data" => $sendToken->data));
    }

    protected function pay(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        // ----------------- Validate received data ----------------- //
        $validator = Validator::make($request->all(), [
            'idIn'=> ['required', 'array'],
            'idIn.*'=> ['required', 'integer'],
            'uuidIn'=> ['required', 'array'],
            'uuidIn.*'=> ['required', 'string'],
            'batch_id'=> ['required', 'string'],
            'token'=> ['required', 'string', 'size:8'],
        ],[
            'idIn.required' => 'Informe o id de pagamento.',
            'uuidIn.required' => 'Informe o uuid de pagamento.',
            'batch_id.required' => 'Informe o lote de pagamento.',
            'token.required' => 'Informe o token.',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }
        // ----------------- Finish validate received data ----------------- //

        if( count($request->idIn) > 1 ) {
            return response()->json(array("error" => "Não é possível enviar mais de um pagamento na mesma requisição"));
        }

        if( (SystemFunctionMaster::where('system_function_id','=',7)->where('master_id','=',$checkAccount->master_id)->first())->available == 0 ){
            return response()->json(array("error" => "Devido a instabilidade com a rede de Bancos Correspondentes, no momento não é possível realizar pix."));
        }

        $pixClass = new PixClass();
        $pixClass->payload = $request;
        $payPix = $pixClass->approve();

        if ( ! $payPix->success ){
            return response()->json(array("error" => $payPix->message_pt_br, "data" => $payPix->data, "invalid_token" => isset($payPix->invalid_token) ? $payPix->invalid_token : false));
        }

        return response()->json(array("success" => $payPix->message_pt_br, "data" => $payPix->data, "invalid_token" => isset($payPix->invalid_token) ? $payPix->invalid_token : false));
    }

    protected function getReceipt(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $pixClass = new PixClass();
        $pixClass->payload = $request;
        $receiptPix = $pixClass->getReceipt();

        if ( ! $receiptPix->success ){
            return response()->json(array("error" => $receiptPix->message_pt_br, "data" => $receiptPix->data));
        }

        return response()->json(array("success" => $receiptPix->message_pt_br, "data" => $receiptPix->data));
    }

    protected function sendReceiptByMail(Request $request)
    {
        $checkAccount = json_decode($request->headers->get('check-account'));

        $pixClass = new PixClass();
        $pixClass->payload = $request;
        $receiptPix = $pixClass->sendReceiptByMail();

        if ( ! $receiptPix->success ){
            return response()->json(array("error" => $receiptPix->message_pt_br, "data" => $receiptPix->data));
        }

        return response()->json(array("success" => $receiptPix->message_pt_br, "data" => $receiptPix->data));
    }

    public function paySchedule(PixClass $pixClass)
    {
        return $pixClass->paySchedule();
    }

    public function removeNotApprovedPayments()
    {
        $notApprovedPayments = PixPaymentV2::where('schedule_date', '<', (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d'))->where('status_id', '=', 5)->whereNull('payment_date')->whereNull('deleted_at')->get();
        foreach( $notApprovedPayments as $notApprovedPayment ){
            $payment = PixPaymentV2::where('id', '=', $notApprovedPayment->id)->first();
            $payment->status_id = 10;
            $payment->save();
        }
    }

}
