<?php

namespace App\Http\Controllers;

use App\Models\Limit;
use App\Models\AccntLmtVlItm;
use App\Models\AccountUpdateLimit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\Token\TokenClass;

class AccountUpdateLimitController extends Controller
{
    public function checkAccountVerification(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        return $checkAccount;
    }

    public function validateRegister(Request $request)
    {
        $checkAccount = $this->checkAccountVerification($request);

        if( ! $acc_updt_lmt = AccountUpdateLimit::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('status_id', '=', 4) 
        ->whereNull('deleted_at')
        ->first() ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        return $acc_updt_lmt;
    }

    protected function store(Request $request) 
    {

         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id  = [363, 366];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'accnt_lmt_vl_itm_id'          => ['required', 'integer'],
            'new_value'                    => ['required'],
        ],[
            'accnt_lmt_vl_itm_id.required' => 'É obrigatório informar o accnt_lmt_vl_itm_id',
            'new_value.required'           => 'É obrigatório informar o new_value',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        $checkAccount = $this->checkAccountVerification($request);


        $new_value = $request->new_value;
        if( is_string($request->new_value) ) {
            $new_value = (float) $request->new_value;
        } 

        if( !is_float($new_value) ) {
            return response()->json(["error" => "Novo limite inválido."]);
        }

        if( $new_value < 0) {
            return response()->json(["error" => "Novo limite deve ser maior ou igual a zero."]);
        }


        if( ! $acc_lmt_vl_item = AccntLmtVlItm::where('id', '=', $request->accnt_lmt_vl_itm_id)
        ->where('accnt_id', '=', $checkAccount->account_id)
        ->whereNull('deleted_at')
        ->first()) {
            return response()->json(["error" => "Limite não localizado."]);
        }

        if( ! Limit::where('id', '=', $acc_lmt_vl_item->limit_id)
        ->where('is_update_by_account', '=', 1)
        ->whereNull('deleted_at')
        ->first()) {
            return response()->json(["error" => "Limite não permite atualização."]);
        }

        if( $acc_lmt_vl_item->value == $new_value ) {
            return response()->json(["error" => "O novo limite deve ser diferente do limite atual."]);
        }
        

        if( $acc_updt_lmt = AccountUpdateLimit::create([
            'uuid'                            => Str::orderedUuid(),
            'master_id'                       => $checkAccount->master_id,
            'account_id'                      => $checkAccount->account_id,
            'status_id'                       => 4,
            'request_by_user_id'              => Auth::user()->id,
            'accnt_lmt_vl_itm_id'             => $acc_lmt_vl_item->id,
            'old_value'                       => $acc_lmt_vl_item->value, 
            'new_value'                       => $request->new_value, 
            'created_at'                      => \Carbon\Carbon::now()
        ])){
            return response()->json(["success" => "", "data" => $acc_updt_lmt]);
        } 
        return response()->json(["error" => "Poxa, não foi possível armazenar os dados do novo limite no momento, por favor tente novamente mais tarde."]);
        
    }

    protected function sendToken(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService                = new AccountRelationshipCheckService();
         $accountCheckService->request       = $request;
         $accountCheckService->permission_id  = [363, 366];
         $checkAccount                       = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'password'                     => ['required'],
            'accnt_lmt_vl_itm_id'          => ['required', 'integer'],
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'password.required'            => 'É obrigatório informar o password',
            'accnt_lmt_vl_itm_id.required' => 'É obrigatório informar o accnt_lmt_vl_itm_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        
        $acc_updt_lmt = $this->validateRegister($request);  


        $user = User::where('id', '=', Auth::user()->id)->first();
        if( ! Hash::check(base64_decode($request->password), $user->password) ) {
            return response()->json(["error" => "Senha incorreta."]);
        }
        

        $createToken = new TokenClass();
        $createToken->data = (object) [
            "type_id" => 15,
            "origin_id" => $acc_updt_lmt->id,
            "minutes_to_expiration" => 10
        ];

        $token = $createToken->createToken();
        $acc_updt_lmt->token_confirmation_phone = $token->data->token_phone;
        $acc_updt_lmt->token_phone_expiration = $token->data->token_expiration;
        $acc_updt_lmt->save();


        $acc_lmt_vl_itm = AccntLmtVlItm::where('id', '=', $request->accnt_lmt_vl_itm_id)->first();
        $limit = Limit::where('id', '=', $acc_lmt_vl_itm->limit_id)->first();


        $user = User::where('id', '=', $acc_updt_lmt->request_by_user_id)->first();
        $token_phone = new TokenClass();
        $token_phone->data = (object) [
            "type_id" => 15,
            "origin_id" => strval($acc_updt_lmt->id),
            "master_id" => $acc_updt_lmt->master_id,
            "token" => $acc_updt_lmt->token_confirmation_phone,
            "phone" => $user->phone,
            "minutes_to_expiration" => 10,
            "message" => "Token ".substr($acc_updt_lmt->token_confirmation_phone, 0, 4)."-".substr($acc_updt_lmt->token_confirmation_phone, 4, 4).", gerado para aprovar a alteração do limite $limit->description para $acc_updt_lmt->new_value."
        ];

        $sendToken = $token_phone->sendTokenBySms();

        if( ! $sendToken->success ){
            return response()->json(["error" => $sendToken]);
        } 

        return response()->json(["success" => true]);
    }

    protected function checkToken(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [363, 366];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'token'                     => ['required', 'string', 'size:8'],
        ],[
            'id.required'               => 'É obrigatório informar o id',
            'uuid.required'             => 'É obrigatório informar o uuid',
            'token.required'            => 'É obrigatório informar o token',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $checkAccount = $this->checkAccountVerification($request);
        $acc_updt_lmt = $this->validateRegister($request);  


        if ($acc_updt_lmt->token_confirmation_phone ==  $request->token) {

            $acc_updt_lmt->token_phone_confirmed = 1;
            $acc_updt_lmt->approved_at = \Carbon\Carbon::now();

            if ( ! $acc_updt_lmt->save()) {
               return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente mais tarde."));
            }
        } else {

            $acc_updt_lmt->token_phone_confirmed = 0;

            if(\Carbon\Carbon::parse($acc_updt_lmt->token_phone_expiration)->format('Y-m-d H:i:s') <= \Carbon\Carbon::now()->format('Y-m-d H:i:s')){      
                $acc_updt_lmt->save();
                return response()->json(array("error" => "Token expirado, por favor repita o procedimento"));
            }
    
            $acc_updt_lmt->save();
    
            return response()->json(array("error" => "Token inválido."));
        }


        if( $acc_updt_lmt->new_value < $acc_updt_lmt->old_value ) {

            $acc_lmt_vl_item = AccntLmtVlItm::where('id', '=', $acc_updt_lmt->accnt_lmt_vl_itm_id)
            ->where('accnt_id', '=', $checkAccount->account_id)
            ->whereNull('deleted_at')
            ->first();

            $acc_lmt_vl_item->value = $acc_updt_lmt->new_value;
            $acc_lmt_vl_item->save();

            $acc_updt_lmt->status_id = 9;
            $acc_updt_lmt->approved_by_user_id = Auth::user()->id;
            $acc_updt_lmt->applied_at = \Carbon\Carbon::now();

            if( $acc_updt_lmt->save() ) {
               return response()->json(array("success" => "Limite atualizado com sucesso."));
            }

        } else if ( $acc_updt_lmt->new_value > $acc_updt_lmt->old_value ) {

            $acc_updt_lmt->status_id = 7;
            
            if( $acc_updt_lmt->save() ) {
                return response()->json(array("success" => "Como o valor do novo limite é superior ao limite atual, esta alteração levará de 24 a 48 horas para ser analisada, após a análise, sua solicitação poderá ser aprovada ou recusada pela agência. Em caso de dúvidas, entre em contato com seu gerente de relacionamento."));
            }
            
        }
        return response()->json(array("error" => "Ocorreu uma falha ao atualizar o novo limite, por favor tente novamente mais tarde."));
    }

    public function updateLimit() 
    {
        $acc_updt_lmts = AccountUpdateLimit::where('status_id', '=', 7)
        ->whereNull('applied_at')
        ->whereNull('deleted_at')
        ->get();
        
        foreach( $acc_updt_lmts as $acc_updt_lmt ) {
            
            if($acc_updt_lmt->new_value <= 200000) {
                if( \Carbon\Carbon::now()->diffInHours( \Carbon\Carbon::parse($acc_updt_lmt->approved_at) ) > 24 ) {

                    $acc_lmt_vl_item = AccntLmtVlItm::where('id', '=', $acc_updt_lmt->accnt_lmt_vl_itm_id)
                    ->where('accnt_id', '=', $acc_updt_lmt->account_id)
                    ->whereNull('deleted_at')
                    ->first();

                    $acc_lmt_vl_item->value = $acc_updt_lmt->new_value;
                    $acc_lmt_vl_item->save();

                    $AccountUpdateLimit = AccountUpdateLimit::where('id', '=', $acc_updt_lmt->id)->first();
                    $AccountUpdateLimit->status_id = 9;
                    $AccountUpdateLimit->applied_at = \Carbon\Carbon::now();
                    $AccountUpdateLimit->save();
                }
            }

        }

    }

    protected function get(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [372, 362, 365];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //



        $acc_updt_lmt                       = new AccountUpdateLimit();
        $acc_updt_lmt->id                   = $request->id;
        $acc_updt_lmt->uuid                 = $request->uuid;
        $acc_updt_lmt->account_id           = $checkAccount->account_id;
        $acc_updt_lmt->status_id_in         = $request->status_id;
        $acc_updt_lmt->request_by_user_id   = $request->request_by_user_id;
        $acc_updt_lmt->approved_by_user_id  = $request->approved_by_user_id;
        $acc_updt_lmt->accnt_lmt_vl_itm_id  = $request->accnt_lmt_vl_itm_id;
        $acc_updt_lmt->onlyActive           = $request->onlyActive;
        return response()->json($acc_updt_lmt->get());
    }

    protected function sendTokenAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [370];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'password'                     => ['required'],
            'accnt_lmt_vl_itm_id'          => ['required', 'integer'],
            'account_id'                   => ['required', 'integer'],
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'password.required'            => 'É obrigatório informar o password',
            'accnt_lmt_vl_itm_id.required' => 'É obrigatório informar o accnt_lmt_vl_itm_id',
            'account_id.required'          => 'É obrigatório informar o account_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        
        $checkAccount = $this->checkAccountVerification($request);

        if( ! $acc_updt_lmt = AccountUpdateLimit::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $request->account_id)
        ->where('status_id', '=', 7) 
        ->whereNull('deleted_at')
        ->first() ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        $user = User::where('id', '=', Auth::user()->id)->first();
        if( ! Hash::check(base64_decode($request->password), $user->password) ) {
            return response()->json(["error" => "Senha incorreta."]);
        }

        $createToken = new TokenClass();
        $createToken->data = (object) [
            "type_id" => 15,
            "origin_id" => $acc_updt_lmt->id,
            "minutes_to_expiration" => 10
        ];

        $token = $createToken->createToken();
        $acc_updt_lmt->agency_token_confirmation = $token->data->token_phone;
        $acc_updt_lmt->save();


        $acc_lmt_vl_itm = AccntLmtVlItm::where('id', '=', $request->accnt_lmt_vl_itm_id)->first();
        $limit = Limit::where('id', '=', $acc_lmt_vl_itm->limit_id)->first();

        $token_phone = new TokenClass();
        $token_phone->data = (object) [
            "type_id" => 15,
            "origin_id" => strval($acc_updt_lmt->id),
            "master_id" => $acc_updt_lmt->master_id,
            "token" => $acc_updt_lmt->agency_token_confirmation,
            "phone" => $user->phone,
            "minutes_to_expiration" => 10,
            "message" => "Token ".substr($acc_updt_lmt->agency_token_confirmation, 0, 4)."-".substr($acc_updt_lmt->agency_token_confirmation, 4, 4).", gerado para aprovar a alteração do limite $limit->description para $acc_updt_lmt->new_value."
        ];

        $sendToken = $token_phone->sendTokenBySms();

        if( ! $sendToken->success ){
            return response()->json(["error" => $sendToken]);
        } 

        return response()->json(["success" => "Token enviado para seu celular."]);
    }

    protected function confirmTokenAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [370];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'token'                     => ['required', 'string', 'size:8'],
            'account_id'                => ['required', 'integer'],
        ],[
            'id.required'               => 'É obrigatório informar o id',
            'uuid.required'             => 'É obrigatório informar o uuid',
            'token.required'            => 'É obrigatório informar o token',
            'account_id.required'       => 'É obrigatório informar o account_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        $checkAccount = $this->checkAccountVerification($request);

        if( ! $acc_updt_lmt = AccountUpdateLimit::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $request->account_id)
        ->where('status_id', '=', 7) 
        ->whereNull('deleted_at')
        ->first() ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        if ($acc_updt_lmt->agency_token_confirmation ==  $request->token) {

            $acc_lmt_vl_item = AccntLmtVlItm::where('id', '=', $acc_updt_lmt->accnt_lmt_vl_itm_id)
            ->where('accnt_id', '=', $request->account_id)
            ->whereNull('deleted_at')
            ->first();

            $acc_lmt_vl_item->value = $acc_updt_lmt->new_value;
            $acc_lmt_vl_item->save();

            $acc_updt_lmt->approved_by_user_at = \Carbon\Carbon::now();
            $acc_updt_lmt->approved_by_user_id = Auth::user()->id;
            $acc_updt_lmt->applied_at = \Carbon\Carbon::now();    
            $acc_updt_lmt->status_id = 9;
            
            if ( $acc_updt_lmt->save() ) {
                return response()->json(array("success" => "Limite atualizado com sucesso."));
            }
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o novo limite, por favor tente novamente mais tarde."));
        } 
        return response()->json(array("error" => "Token inválido."));
    }

    protected function denyAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id  = [371];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                           => ['required', 'integer'],
            'uuid'                         => ['required', 'string'],
            'password'                     => ['required'],
            'account_id'                   => ['required', 'integer'],
        ],[
            'id.required'                  => 'É obrigatório informar o id',
            'uuid.required'                => 'É obrigatório informar o uuid',
            'password.required'            => 'É obrigatório informar o password',
            'account_id.required'          => 'É obrigatório informar o account_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $checkAccount = $this->checkAccountVerification($request);

        if( ! $acc_updt_lmt = AccountUpdateLimit::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $request->account_id)
        ->where('status_id', '=', 7) 
        ->whereNull('deleted_at')
        ->first() ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }

        $user = User::where('id', '=', Auth::user()->id)->first();
        if( ! Hash::check(base64_decode($request->password), $user->password) ) {
            return response()->json(["error" => "Senha incorreta."]);
        }

        $acc_updt_lmt->status_id = 48;
        $acc_updt_lmt->deleted_at = \Carbon\Carbon::now();

        if ( $acc_updt_lmt->save() ) {
            return response()->json(array("success" => "Alteração de limite recusada com sucesso."));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao recusar o novo limite, por favor tente novamente mais tarde."));
        
    }

}
