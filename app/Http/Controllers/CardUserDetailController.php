<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CardUser;
use App\Models\CardUserDetail;
use App\Models\Register;
use App\Models\RegisterMaster;
use App\Libraries\Facilites;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CardUserDetailController extends Controller
{

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $card_user_detail          = new CardUserDetail;
        return response()->json($card_user_detail->cardUserDetailGet());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;
        if(strlen($cpf_cnpj) == 11) {
            if( !$validate->validateCPF($cpf_cnpj) ){
                return response()->json(['error'=>'CPF/CNPJ inválido']);
            }
        }else if(strlen($cpf_cnpj) == 14){
            if( !$validate->validateCNPJ($cpf_cnpj) ){
                return response()->json(['error'=>'CPF/CNPJ inválido']);
            }
        }else{
            return response()->json(['error'=>'CPF/CNPJ inválido']);
        }

        if($card_user = CardUser::where('cpf_cnpj', '=' ,$request->cpf_cnpj)->first()){
            $UserId = $card_user->id;
        }else{
            $card_user = CardUser::Create([
                'cpf_cnpj' => $request->cpf_cnpj,
            ]);
            $card_user->save();
            $UserId = $card_user->id;
        }
        if($request->card_type_id == 1){
            if(CardUserDetail::where('card_user_id','=',$UserId)->where('card_type_id','=',1)->first()){
                return response()->json(array("error"=>"Usuário já possui cadastro PF definido"));
            }else{
                $account     = new Account();
                $account->id = $checkAccount->account_id;
                if($account->getCPF_CNPJRegisters($request->cpf_cnpj) > ''){
                    CardUserDetail::create([
                        "account_id"          => $checkAccount->account_id,
                        "card_type_id"        => $request->card_type_id,
                        "card_user_id"        => $UserId,
                        "name"                => $request->name,
                        "surname"             => $request->surname,
                        "email"               => $request->email,
                        "cell_phone"          => preg_replace( '/[^0-9]/', '', $request->cell_phone),
                        "address"             => $request->address,
                        "address_number"      => $request->address_number,
                        "address_complement"  => $request->address_complement,
                        "address_district"    => $request->address_district,
                        "address_city"        => $request->address_city,
                        "address_state_id"    => $request->address_state_id,
                        "address_zip_code"    => $request->address_zip_code,
                        "rg_number"           => $request->rg_number,
                        "politically_exposed" => $request->politically_exposed,
                        "mother_name"         => $request->mother_name,
                        "date_birth"          => $request->date_birth,
                        "gender_id"           => $request->gender_id,
                        "nationality_id"      => $request->nationality_id,
                        "tax_code"            => preg_replace( '/[^0-9]/', '', $request->cpf_cnpj),
                    ]);
                    return response()->json(array("success"=>"Usuário para cartão cadastrado com sucesso"));
                }else{
                    return response()->json(array("error"=>"CPF informado não corresponde com o CPF do titular da conta"));
                }
            }
        }else if($request->card_type_id == 2){
            if(CardUserDetail::where('card_user_id','=',$card_user->id)->where('account_id','=',$checkAccount->account_id)->where('card_type_id','=',2)->first()){
                return response()->json(array("error"=>"Usuário já possui cadastro com a empresa"));
            }else{
                CardUserDetail::create([
                    "account_id"          => $checkAccount->account_id,
                    "card_type_id"        => $request->card_type_id,
                    "card_user_id"        => $UserId,
                    "name"                => $request->name,
                    "surname"             => $request->surname,
                    "email"               => $request->email,
                    "cell_phone"          => $request->cell_phone,
                    "address"             => $request->address,
                    "address_number"      => $request->address_number,
                    "address_complement"  => $request->address_complement,
                    "address_district"    => $request->address_district,
                    "address_city"        => $request->address_city,
                    "address_state_id"    => $request->address_state_id,
                    "address_zip_code"    => $request->address_zip_code,
                    "rg_number"           => $request->rg_number,
                    "politically_exposed" => $request->politically_exposed,
                    "mother_name"         => $request->mother_name,
                    "date_birth"          => $request->date_birth,
                    "gender_id"           => $request->gender_id,
                    "nationality_id"      => $request->nationality_id,
                    "tax_code"            => preg_replace( '/[^0-9]/', '', $request->cpf_cnpj),
                ]);
                return response()->json(array("success"=>"Usuário para cartão cadastrado com sucesso"));
            }
        }else{
            return response()->json(array("error"=>"Erro ao inserir as informações"));
        }
    }

    protected function update(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [124, 308, 350];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'name'                  => ['required', 'string'],
            'surname'               => ['required', 'string'],
            'email'                 => ['required', 'string', 'email'],
            'cell_phone'            => ['required', 'string'],
            'address'               => ['required', 'string'],
            'address_number'        => ['required', 'string'],
            'address_complement'    => ['required', 'string'],
            'address_district'      => ['required', 'string'],
            'address_city'          => ['required', 'string'],
            'address_state_id'      => ['required', 'string'],
            'address_zip_code'      => ['required', 'string'],
            'rg_number'             => ['required', 'string'],
            'mother_name'           => ['required', 'string'],
            'date_birth'            => ['required', 'string'],
            'gender_id'             => ['required', 'integer'],
        ],[
            'id.required'                    => 'ID',
            'name.required'                  => 'Nome',
            'surname.required'               => 'Sobrenome',
            'email.required'                 => 'Email',
            'cell_phone.required'            => 'Celular',
            'address.required'               => 'Endereço',
            'address_number.required'        => 'Numero da residência',
            'address_complement.required'    => 'Complemento',
            'address_district.required'      => 'Bairro',
            'address_city.required'          => 'Cidade',
            'address_state_id.required'      => 'Estado',
            'address_zip_code.required'      => 'Cep',
            'rg_number.required'             => 'RG',
            'mother_name.required'           => 'Nome da mãe',
            'date_birth.required'            => 'Data de nascimento',
            'gender_id.required'             => 'Genero',
           ]
        );

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()." é obrigatório(a)"));
        }

        if ($card_user_detail = CardUserDetail::where('id','=',$request->id)->where('account_id','=',$checkAccount->account_id)->first()) {

            $card_user_detail->name                 = $request->name;
            $card_user_detail->surname              = $request->surname;
            $card_user_detail->email                = $request->email;
            $card_user_detail->cell_phone           = $request->cell_phone;
            $card_user_detail->address              = $request->address;
            $card_user_detail->address_number       = $request->address_number;
            $card_user_detail->address_complement   = $request->address_complement;
            $card_user_detail->address_district     = $request->address_district;
            $card_user_detail->address_city         = $request->address_city;
            $card_user_detail->address_state_id     = $request->address_state_id;
            $card_user_detail->address_zip_code     = $request->address_zip_code;
            $card_user_detail->rg_number            = $request->rg_number;
            $card_user_detail->mother_name          = $request->mother_name;
            $card_user_detail->date_birth           = $request->date_birth;
            $card_user_detail->gender_id            = $request->gender_id;

            if (!$card_user_detail->save()) {

                return response()->json(array("error" => "Poxa, no momento, não foi possível alterar o cadastro informado, por favor tente mais tarde"));
            }
            return response()->json(array("success" => "Cadastro alterado com sucesso"));
        }
        return response()->json(array("error" => "Poxa, no momento, não foi possível alterar o cadastro informado, por favor tente mais tarde"));
    }
}
