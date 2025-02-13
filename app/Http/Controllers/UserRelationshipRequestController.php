<?php

namespace App\Http\Controllers;

use App\Libraries\AmazonS3;
use App\Libraries\ApiSendgrid;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Register\RegisterService;
use App\Services\User\UserRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth; 
use App\Models\UserRelationshipRequest;
use App\Models\DocumentIdentificationType;
use App\Models\DocumentType;
use App\Models\Gender;
use App\Models\MaritalStatus;
use App\Models\States;
use App\Models\User;
use App\Models\UserRelationshipRequestPermission;
use App\Models\UserRelationshipRequestType;
use App\Models\Document;
use App\Classes\Token\TokenClass;
use App\Models\UsrRltnshpPrmssn;
use App\Libraries\ApiZenviaWhatsapp;
use Illuminate\Support\Facades\Storage;
use App\Libraries\SimpleZip;

class UserRelationshipRequestController extends Controller
{
    protected function show(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [360, 368];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $userRelationshipRequest                       = new UserRelationshipRequest();
        $userRelationshipRequest->id                   = $request->id;
        $userRelationshipRequest->uuid                 = $request->uuid;
        $userRelationshipRequest->account_id           = $checkAccount->account_id;
        $userRelationshipRequest->user_id              = $request->user_id;
        $userRelationshipRequest->user_relationship_id = $request->user_relationship_id;
        // $userRelationshipRequest->status_id            = $request->status_id;
        $userRelationshipRequest->cpf_cnpj             = $request->cpf_cnpj;
        $userRelationshipRequest->analyzed_by_user_id  = $request->analyzed_by_user_id;
        $userRelationshipRequest->status_id_in         = $request->status_id;
        $userRelationshipRequest->created_at_start     = $request->created_at_start;
        $userRelationshipRequest->created_at_end       = $request->created_at_end;
        $userRelationshipRequest->send_at_start        = $request->send_at_start;
        $userRelationshipRequest->send_at_end          = $request->send_at_end;
        $userRelationshipRequest->analyzed_at_start    = $request->analyzed_at_start;
        $userRelationshipRequest->analyzed_at_end      = $request->analyzed_at_end;
        $userRelationshipRequest->only_active          = $request->only_active;
        return response()->json($userRelationshipRequest->get());
    }

    protected function store(Request $request)
    {   
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'type_id'               => ['required', 'integer'],
            'cpf_cnpj'              => ['required', 'string'],
        ],[
            'type_id.required'      => 'É obrigatório informar o type_id',
            'cpf_cnpj.required'     => 'É obrigatório informar o cpf_cnpj',
            'account_id.required'   => 'É obrigatório informar o account_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if( ! UserRelationshipRequestType::where('id', '=', $request->type_id)
        ->whereNull('deleted_at')
        ->first() ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        $validate = new Facilites();
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $validate->cpf_cnpj = $cpf_cnpj;
        if( strlen($cpf_cnpj) != 11 || !$validate->validateCPF($cpf_cnpj) ) {
            return response()->json(['error' => 'CPF inválido']);
        }


        if( UserRelationshipRequest::where('cpf_cnpj', '=', $request->cpf_cnpj)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('type_id', '=', $request->type_id)
        ->whereIn('status_id', [6, 9])
        ->first() ) {
            return response()->json(["error" => "Já existe uma solicitação de vínculo no CPF informado."]);
        }

        if( $userRelationshipRequest = UserRelationshipRequest::where('cpf_cnpj', '=', $request->cpf_cnpj)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('type_id', '=', $request->type_id)
        ->whereIn('status_id', [6, 9])
        ->first() ) {
            return response()->json(["error" => "Ocorreu uma falha ao salvar os dados da solicitação de vínculo."]);
        }        

        
        if($userRelationshipRequest = UserRelationshipRequest::create([
            'uuid'                            => Str::orderedUuid(),
            'master_id'                       => $checkAccount->master_id,
            'account_id'                      => $checkAccount->account_id,
            'request_by_user_id'              => $checkAccount->user_id,
            'request_by_user_relationship_id' => $checkAccount->user_relationship_id,
            'status_id'                       => 4, 
            'type_id'                         => $request->type_id,
            'cpf_cnpj'                        => $request->cpf_cnpj,
            'created_at'                      => \Carbon\Carbon::now()
        ])){
            return response()->json(["success" => "Solicitação de vínculo incluída com sucesso.", "data" => $userRelationshipRequest]);
        } 
        return response()->json(["error" => "Poxa, não foi possível armazenar os dados da solicitação de vínculo no momento, por favor tente novamente mais tarde."]);
    }

    public function checkAccountVerification(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
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

        if( !$userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('status_id', '=', 4) 
        ->first() ) {
            return response()->json(["error" => "Não foi possível localizar o registro."]);
        }


        if( $userRelationshipRequest->status_id != 4 ) {
            return response()->json(["error" => "Ocorreu uma falha ao realizar a alteração do vínculo."]);
        }


        return $userRelationshipRequest;
    }

    protected function setName(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'name'             => ['required', 'string'],
        ],[
            'id.required'      => 'É obrigatório informar o id',
            'uuid.required'    => 'É obrigatório informar o uuid',
            'name.required'    => 'É obrigatório informar o name',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);  

        if( strlen($request->name) <= 3 ) {
            return response()->json(["error" => "O nome do vínculo precisa conter pelo menos 4 caracteres."]);
        }
        
        $userRelationshipRequest->name = $request->name;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Nome do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o nome do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setBirthDate(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                  => ['required', 'integer'],
            'uuid'                => ['required', 'string'],
            'birth_date'          => ['required', 'string'],
        ],[
            'id.required'         => 'É obrigatório informar o id',
            'uuid.required'       => 'É obrigatório informar o uuid',
            'birth_date.required' => 'É obrigatório informar o birth_date',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);  

        $validate = new Facilites();
        $birth_date = $request->birth_date;
        $validate->date = $birth_date;
        if( !$validate->validateDate($birth_date) ) {
            return response()->json(array('error' => 'Data de nascimento inválida.'));
        }

        if( (\Carbon\Carbon::parse($request->birth_date))->diffInYears(\Carbon\Carbon::now()) < 18  ){
            return response()->json(array("error" => "Não é possível definir a data de nascimento do vínculo para menores de idade."));
        }
        
        $userRelationshipRequest->birth_date = $request->birth_date;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Data de nascimento do vínculo alterada com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar a data de nascimento do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setGender(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                  => ['required', 'integer'],
            'uuid'                => ['required', 'string'],
            'gender_id'           => ['required', 'integer'],
        ],[
            'id.required'         => 'É obrigatório informar o id',
            'uuid.required'       => 'É obrigatório informar o uuid',
            'gender_id.required'  => 'É obrigatório informar o gender_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);  

        if( ! Gender::where('id', '=', $request->gender_id)->first() ) {
            return response()->json(["error" => "Gênero não localizado."]);
        }
        
        $userRelationshipRequest->gender_id = $request->gender_id;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Gênero do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o gênero do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setMaritalStatus(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'marital_status_id'           => ['required', 'integer'],
        ],[
            'id.required'                 => 'É obrigatório informar o id',
            'uuid.required'               => 'É obrigatório informar o uuid',
            'marital_status_id.required'  => 'É obrigatório informar o marital_status_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);  

        if( ! MaritalStatus::where('id', '=', $request->marital_status_id)->first() ) {
            return response()->json(["error" => "Estado civil não localizado."]);
        }

        $userRelationshipRequest->marital_status_id = $request->marital_status_id;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Estado civil do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o estado civil do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setPhone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $validator = Validator::make($request->all(), [
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'phone'              => ['required', 'string'],
        ],[
            'id.required'        => 'É obrigatório informar o id',
            'uuid.required'      => 'É obrigatório informar o uuid',
            'phone.required'     => 'É obrigatório informar o phone',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        $phone = preg_replace( '/[^0-9]/', '', $request->phone);
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $userRelationshipRequest->cpf_cnpj);

        if( strlen($phone) != 11 ) {
            return response()->json(["error" => "O número de celular deve possuir 11 caracteres."]);
        }

        if( User::where('phone', '=', $phone)
        ->where('cpf_cnpj', '<>', $cpf_cnpj)
        ->first() ) {
            return response()->json(["error" => "O número de celular informado pertence a outro usuário."]);
        }
        
        $userRelationshipRequest->phone = $phone;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Número de celular do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o número de celular do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'email'              => ['required', 'string'],
        ],[
            'id.required'        => 'É obrigatório informar o id',
            'uuid.required'      => 'É obrigatório informar o uuid',
            'email.required'     => 'É obrigatório informar o email',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        if( ! Facilites::validateEmail($request->email)) {
            return response()->json(["error" => "E-mail inválido."]);
        }
        
        $cpf_cnpj = preg_replace( '/[^0-9]/', '', $userRelationshipRequest->cpf_cnpj);

        if( User::where('email', '=', $request->email)
        ->where('cpf_cnpj', '<>', $cpf_cnpj)
        ->first() ) {
            return response()->json(["error" => "O e-mail informado pertence a outro usuário."]);
        }

        $userRelationshipRequest->email = $request->email;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "E-mail do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o e-mail do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setPublicPlace(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'public_place'              => ['required', 'string'],
        ],[
            'id.required'               => 'É obrigatório informar o id',
            'uuid.required'             => 'É obrigatório informar o uuid',
            'public_place.required'     => 'É obrigatório informar o public_place',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 
        
        $userRelationshipRequest->public_place = $request->public_place;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Logradouro do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o logradouro do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string'],
            'address'              => ['required', 'string'],
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'address.required'     => 'É obrigatório informar o address',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        $userRelationshipRequest->address = $request->address;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Endereço do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o endereço do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string'],
            'number'               => ['required', 'string'],
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'number.required'      => 'É obrigatório informar o number',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 
        
        $userRelationshipRequest->number = $request->number;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Número do endereço do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o número do endereço do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setComplement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'complement'               => ['required', 'string'],
        ],[
            'id.required'              => 'É obrigatório informar o id',
            'uuid.required'            => 'É obrigatório informar o uuid',
            'complement.required'      => 'É obrigatório informar o complement',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 
        
        $userRelationshipRequest->complement = $request->complement;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Complemento do endereço do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o complemento do endereço do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setDistrict(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'district'               => ['required', 'string'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'district.required'      => 'É obrigatório informar o district',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 
        
        $userRelationshipRequest->district = $request->district;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Bairro do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o bairro do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setCity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                 => ['required', 'integer'],
            'uuid'               => ['required', 'string'],
            'city'               => ['required', 'string'],
        ],[
            'id.required'        => 'É obrigatório informar o id',
            'uuid.required'      => 'É obrigatório informar o uuid',
            'city.required'      => 'É obrigatório informar o city',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 
    
        $userRelationshipRequest->city = $request->city;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Cidade do vínculo alterada com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar a cidade do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setState(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'state_id'               => ['required', 'integer'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'state_id.required'      => 'É obrigatório informar o state_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        if( ! States::where('id', '=', $request->state_id)->first() ) {
            return response()->json(["error" => "Estado não localizado."]);
        }
    
        $userRelationshipRequest->state_id = $request->state_id;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Estado do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o estado do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setZipCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'zip_code'               => ['required', 'string'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'zip_code.required'      => 'É obrigatório informar o zip_code',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        $zip_code = preg_replace( '/[^0-9]/', '', $request->zip_code);
        if (strlen($zip_code) != 8) {
            return response()->json(array("error" => "CEP inválido."));
        }
        $userRelationshipRequest->zip_code = $zip_code;

        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "CEP do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o CEP do vínculo no momento, por favor tente novamente mais tarde."]);
    }
    
    protected function setDocumentNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                            => ['required', 'integer'],
            'uuid'                          => ['required', 'string'],
            'document_number'               => ['required', 'string'],
        ],[
            'id.required'                   => 'É obrigatório informar o id',
            'uuid.required'                 => 'É obrigatório informar o uuid',
            'document_number.required'      => 'É obrigatório informar o document_number',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        $document_number = preg_replace( '/[^0-9]/', '', $request->document_number);
        $userRelationshipRequest->document_number = $document_number;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Número do RG do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o número do RG do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setIncome(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string'],
            'income'               => ['required'],
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'income.required'      => 'É obrigatório informar o income',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        $income = $request->income;
        if( is_string($request->income) ) {
            $income = (float) $request->income;
        } 

        if( !is_float($income) ) {
            return response()->json(["error" => "Renda inválida."]);
        }

        if( $income < 0) {
            return response()->json(["error" => "A renda deverá ser maior ou igual a zero."]);
        }

        $userRelationshipRequest->income = $request->income;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Renda do vínculo alterada com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar a renda do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setMotherName(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string'],
            'mother_name'               => ['required', 'string'],
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid',
            'mother_name.required'      => 'É obrigatório informar o mother_name',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        $userRelationshipRequest->mother_name = $request->mother_name;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Nome da mãe do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o nome da mãe do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function setDocumentIdentificationType(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                       => ['required', 'integer'],
            'uuid'                                     => ['required', 'string'],
            'document_identification_type_id'          => ['required', 'integer'],
        ],[
            'id.required'                              => 'É obrigatório informar o id',
            'uuid.required'                            => 'É obrigatório informar o uuid',
            'document_identification_type_id.required' => 'É obrigatório informar o document_identification_type_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        if( ! DocumentIdentificationType::where('id', '=', $request->document_identification_type_id)->first() ) {
            return response()->json(["error" => "Tipo de documento não localizado."]);
        }

        $userRelationshipRequest->document_identification_type_id = $request->document_identification_type_id;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Tipo de documento importado do vínculo alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o tipo de documento importado do vínculo no momento, por favor tente novamente mais tarde."]);
    }

    protected function fileManagerS3($file_name_request, $file64, $document_type, $file_name_data)
    {
        $ext = strtolower(pathinfo($file_name_request, PATHINFO_EXTENSION));

        if( ! in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf']) ){
            return (object) [
                "success" => false,
                "message" => "Formato de arquivo $ext não permitido, formatos permitidos: jpg, jpeg, png e pdf."
            ];
        }

        $fileName = md5($document_type->id.date('Ymd').time()).'.'.$ext;

        $amazons3 = new AmazonS3();

        if (empty($file_name_data)){
            $amazons3->fileName = $file_name_data;
            $amazons3->path     = $document_type->s3_path;
            $amazons3->fileDeleteAmazon();
        }

        $amazons3->fileName = $fileName;
        $amazons3->file64   = base64_encode(file_get_contents($file64));;
        $amazons3->path     = $document_type->s3_path;
        $upfile             = $amazons3->fileUpAmazon();

        if (!$upfile->success){
            return (object) [
                "success" => false,
                "message" => "Poxa, não foi possível realizar o upload do documento informado, por favor tente novamente mais tarde."
            ];
        }
        return (object) [
            "success" => true,
            "data" => $fileName
        ];
    }

    protected function setAddressProof(Request $request) 
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'file_name'                  => ['required', 'string'],
            'file64'                     => ['required', 'string'],
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'file_name.required'         => 'É obrigatório informar o file_name',
            'file64.required'            => 'É obrigatório informar o file64',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 


        $addressProofDocumentTypeId = 8;

        if ( ! $document_type = DocumentType::where('id', '=', $addressProofDocumentTypeId)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $userRelationshipRequest->address_proof_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }

        $userRelationshipRequest->address_proof_s3_filename = $fileName->data;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Comprovante de endereço enviado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível enviar o comprovante de endereço, por favor tente novamente mais tarde."]);
    }

    protected function setDocumentFront(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'file_name'                  => ['required', 'string'],
            'file64'                     => ['required', 'string'],
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'file_name.required'         => 'É obrigatório informar o file_name',
            'file64.required'            => 'É obrigatório informar o file64',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        switch ($request->document_identification_type_id) {
            case '1':
                $document_type_id = 1;
            break;
            case '2':
                $document_type_id = 3;
            break;
            case '3':
                $document_type_id = 5;
            break;
            default:
                return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $userRelationshipRequest->document_front_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }

        $userRelationshipRequest->document_front_s3_filename = $fileName->data;
        $userRelationshipRequest->document_identification_type_id = $request->document_identification_type_id;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Frente do documento enviada com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível enviar a frente do documento, por favor tente novamente mais tarde."]);
    }

    protected function setDocumentVerse(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                         => ['required', 'integer'],
            'uuid'                       => ['required', 'string'],
            'file_name'                  => ['required', 'string'],
            'file64'                     => ['required', 'string'],
        ],[
            'id.required'                => 'É obrigatório informar o id',
            'uuid.required'              => 'É obrigatório informar o uuid',
            'file_name.required'         => 'É obrigatório informar o file_name',
            'file64.required'            => 'É obrigatório informar o file64',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request); 

        switch ($request->document_identification_type_id) {
            case '1':
                $document_type_id = 2;
            break;
            case '2':
                $document_type_id = 4;
            break;
            case '3':
                $document_type_id = 6;
            break;
            default:
                return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }

        if ( ! $document_type = DocumentType::where('id', '=', $document_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ( ! (($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $userRelationshipRequest->document_verse_s3_filename))->success) ) {
            return response()->json(array("error" => $fileName->message));
        }

        $userRelationshipRequest->document_verse_s3_filename = $fileName->data;
        $userRelationshipRequest->document_identification_type_id = $request->document_identification_type_id;
        if ($userRelationshipRequest->save()) {
            return response()->json(["success" => "Verso do documento enviada com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível enviar o verso do documento, por favor tente novamente mais tarde."]);
    }

    public function sendEmailToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
        ],[
            'id.required'                       => 'É obrigatório informar o id',
            'uuid.required'                     => 'É obrigatório informar o uuid',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);  

        $createToken = new TokenClass();
        $createToken->data = (object) [
            "type_id" => 15,
            "origin_id" => $userRelationshipRequest->id,
            "minutes_to_expiration" => 10
        ];

        $token = $createToken->createToken();
        $userRelationshipRequest->token_confirmation_email = $token->data->token_email;
        $userRelationshipRequest->token_confirmation_phone = $token->data->token_phone;
        $userRelationshipRequest->token_email_expiration = $token->data->token_expiration;
        $userRelationshipRequest->token_phone_expiration = $token->data->token_expiration;

        $userRelationshipRequest->save();

        $userRelationshipRequest = $userRelationshipRequest->get()[0];

        $getPermissions = new UserRelationshipRequestPermission();
        $getPermissions->user_relationship_request_id = $request->id;
        $getPermissions->only_active = 1;
        $p = $getPermissions->get();

        $listPermission = '';
        foreach( $p as $item ){ 
            $listPermission .= "
                <tr>
                    <td>
                        $item->description
                    </td>
                </tr>
            ";
        }


        $apiSendGrid = new ApiSendgrid();
        $title = "Token de confirmação de solicitação de vínculo";

        $apiSendGrid->content = "
            <body>
                <h1>$title</h1>
                <br/>
                <p>Olá $userRelationshipRequest->request_by_user_name,</p>
                <br/>
                <p>Ao final dessa mensagem, existe o token quer deve ser utilizado para confirmar a solicitação de novo vínculo de usuário a conta $userRelationshipRequest->account_number, de titularidade de $userRelationshipRequest->register_name.</p>
                <br/>
                <p>Esses são os dados do novo usuário a ser vinculado na conta:</p>
                <br/>
                <table>
                    <thead>
                        <tr>
                            <th>
                                <p><strong>Nome</strong></p>
                                <p>$userRelationshipRequest->name</p>
                            </th>
                            <th>
                                <p> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </p>
                            </th>
                            <th>
                                <p><strong>CPF</strong></p>
                                <p>".Facilites::mask_cpf_cnpj($userRelationshipRequest->cpf_cnpj)."</p>
                            </th>                    
                        </tr>
                        <tr>
                            <th>
                                <p><strong>Celular</strong></p>
                                <p>".Facilites::mask_phone($userRelationshipRequest->phone)."</p>
                            </th>
                            <th>
                                <p> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </p>
                            </th>
                            <th>
                                <p><strong>E-mail</strong></p>
                                <p>$userRelationshipRequest->email</p>
                            </th>                    
                        </tr>
                    </thead>
                </table>
                <br/>
                <p>Essas são as <strong>permissões</strong> quer serão concedidas ao novo usuário:</p>
                <tbody>
                    $listPermission
                </tbody>
                <br/>
                <p>Token para confirmar a solicitação de vínculo: <strong>".substr($token->data->token_email, 0, 4)."-".substr($token->data->token_email, 4, 4)."</strong></p>
                <br/><br/>
                <p>Ressaltamos que, ninguém da iP4y solicitará esse token, ele deve ser utilizado exclusivamente por você em nossa plataforma.</p>
                <br/>
                <p>Caso não tenha solicitado o vínculo de um novo usuário a sua conta, por favor entre em contato conosco pelo e-mail faleconosco@ip4y.com.br</p>
                <br/>
                <br/>
                <p>Atenciosamente,</p>
                <br/>
                <p>Equipe iP4y</p>
            </body>
        ";
        $apiSendGrid->subject = $title;
        $apiSendGrid->to_email = $userRelationshipRequest->request_by_user_email;
        $apiSendGrid->to_name = strtoupper($userRelationshipRequest->request_by_user_name);
        $apiSendGrid->sendSimpleEmailWithoutCC();
        return response()->json(["success" => true]);
    }

    public function sendPhoneToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
        ],[
            'id.required'                       => 'É obrigatório informar o id',
            'uuid.required'                     => 'É obrigatório informar o uuid',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);


        $token = new TokenClass();
        $token->data = (object) [
            "type_id" => 15,
            "origin_id" => strval($userRelationshipRequest->id),
            "master_id" => $userRelationshipRequest->master_id,
            "token" => $userRelationshipRequest->token_confirmation_phone,
            "phone" => $userRelationshipRequest->get()[0]->request_by_user_phone,
            "minutes_to_expiration" => 10,
            "message" => "Token ".substr($userRelationshipRequest->token_confirmation_phone, 0, 4)."-".substr($userRelationshipRequest->token_confirmation_phone, 4, 4).". Gerado para confirmar a solicitação de vínculo de usuário."
        ];

        $sendToken = $token->sendTokenBySms();

        if( ! $sendToken->success ){
            return response()->json(["error" => $sendToken]);
        } else {
            return response()->json(["success" => true]);
        }

    }

    public function sendTokenByWhatsApp(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                => ['required', 'integer'],
            'uuid'                              => ['required', 'string'],
        ],[
            'id.required'                       => 'É obrigatório informar o id',
            'uuid.required'                     => 'É obrigatório informar o uuid',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequest = $this->validateRegister($request);

        $apiZenviaWhats            = new ApiZenviaWhatsapp();
        $apiZenviaWhats->to_number = $userRelationshipRequest->get()[0]->request_by_user_phone;
        $apiZenviaWhats->token     = "*".substr($userRelationshipRequest->token_confirmation_phone, 0, 4)."-".substr($userRelationshipRequest->token_confirmation_phone,4,4)."*";                     
        if(isset( $apiZenviaWhats->sendToken()->success ) ){
            return response()->json(["success" => true]);
        }

        return response()->json(["error" => $sendToken]);

    }

    protected function checkTokenEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'token_email'            => ['required', 'string', 'size:8'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'token_email.required'   => 'É obrigatório informar o token_email',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        try{
            if (!$userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $this->validateRegister($request); 


        if (!Auth::check()) {
            if ($userRelationshipRequest->status_id == 6 || $userRelationshipRequest->status_id == 9 || $userRelationshipRequest->status_id == 11) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        if ($userRelationshipRequest->token_confirmation_email ==  $request->token_email) {

            $userRelationshipRequest->token_email_confirmed = 1;

            if (!$userRelationshipRequest->save()) {
                return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por e-mail, por favor tente novamente mais tarde."));
            }

            return response()->json(array("success" => "Token enviado por e-mail confirmado com sucesso."));
        }

        $userRelationshipRequest->token_email_confirmed = 0;

        if(\Carbon\Carbon::parse($userRelationshipRequest->token_email_expiration)->format('Y-m-d H:i:s') <= \Carbon\Carbon::now()->format('Y-m-d H:i:s')){
            $userRelationshipRequest->save();
            return response()->json(array("error" => "Token expirado, por favor repita o procedimento"));
        }

        if ($userRelationshipRequest->save()) {
           return response()->json(array("error" => "Token informado não corresponde com o token enviado por e-mail."));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por e-mail, por favor tente novamente mais tarde."));

    }

    protected function checkTokenPhone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [361];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                     => ['required', 'integer'],
            'uuid'                   => ['required', 'string'],
            'token_phone'            => ['required', 'string', 'size:8'],
        ],[
            'id.required'            => 'É obrigatório informar o id',
            'uuid.required'          => 'É obrigatório informar o uuid',
            'token_phone.required'   => 'É obrigatório informar o token_phone',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        try{
            if (!$userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch(\Exception $e) {
            return abort(404, "Not Found");
        }

        $this->validateRegister($request); 


        if (!Auth::check()) {
            if ($userRelationshipRequest->status_id == 6 || $userRelationshipRequest->status_id == 9 || $userRelationshipRequest->status_id == 11) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        if ($userRelationshipRequest->token_confirmation_phone ==  $request->token_phone) {

            $userRelationshipRequest->token_phone_confirmed = 1;
            $userRelationshipRequest->status_id = 6;

            if ( ! $userRelationshipRequest->save() ) {
                return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente mais tarde."));
            }


            switch ($userRelationshipRequest->document_identification_type_id) {
                case 1:
                    $document_front_type_id = 1;
                break;
                case 2:
                    $document_front_type_id = 3;
                break;
                case 3:
                    $document_front_type_id = 5;
                break;
                default:
                    return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
            }

            switch ($userRelationshipRequest->document_identification_type_id) {
                case 1:
                    $document_verse_type_id = 2;
                break;
                case 2:
                    $document_verse_type_id = 4;
                break;
                case 3:
                    $document_verse_type_id = 6;
                break;
                default:
                    return response()->json(array("error"=>"O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
            }


            $filesData = (object) [
                (object) [
                    "filename" => $userRelationshipRequest->address_proof_s3_filename,
                    "prefixFileName" => "ComprovanteEndereço_",
                    "type"     => 8,
                ],
                (object) [
                    "filename" => $userRelationshipRequest->document_front_s3_filename,
                    "prefixFileName" => "DocumentoFrente_",
                    "type"     => $document_front_type_id
                ],
                (object) [
                    "filename" => $userRelationshipRequest->document_verse_s3_filename,
                    "prefixFileName" => "DocumentoVerso_",
                    "type"     => $document_verse_type_id
                ],
            ];
            
            
            $facilites = new Facilites();
            $userRelationshipRequestGet = $userRelationshipRequest->get()[0];

            $bodyMessage = "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                    </tr>
                </table> <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td>
                            Uma nova solicitação de vínculo do tipo $userRelationshipRequestGet->type_description da conta $userRelationshipRequestGet->register_name foi requisitada.<br><br>
                            <b>Dados da Solicitação</b>
                        </td>
                    </tr>
                </table> <br>

                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='60%'><b>Nome</b></td>
                        <td width='20%'><b>CPF</b></td>
                        <td width='20%'><b>Solicitação Criada Em</b></td>
                    </tr>
                    <tr>
                        <td width='60%'>$userRelationshipRequestGet->name</td>
                        <td width='20%'>".$facilites->mask_cpf_cnpj($userRelationshipRequestGet->cpf_cnpj)."</td>
                        <td width='20%'>".\Carbon\Carbon::parse($userRelationshipRequestGet->created_at)->format('d/m/Y H:i:s')."</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>Data de Nascimento</b></td>
                        <td width='25%'><b>Gênero</b></td>
                        <td width='25%'><b>Estado Civil</b></td>
                        <td width='25%'><b>Telefone</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>".\Carbon\Carbon::parse($userRelationshipRequestGet->birth_date)->format('d/m/Y H:i:s')."</td>
                        <td width='25%'>$userRelationshipRequestGet->gender_description</td>
                        <td width='25%'>$userRelationshipRequestGet->marital_status_description</td>
                        <td width='25%'>".$facilites->mask_phone($userRelationshipRequestGet->phone)."</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>E-mail</b></td>
                        <td width='25%'><b>Número do RG</b></td>
                        <td width='25%'><b>Renda</b></td>
                        <td width='25%'><b>Nome da Mãe</b></td>

                    </tr>
                    <tr>
                        <td width='25%'>$userRelationshipRequestGet->email</td>
                        <td width='25%'>$userRelationshipRequestGet->document_number</td>
                        <td width='25%'>R$ ".number_format($userRelationshipRequestGet->income, 2, ',','.')."</td>
                        <td width='25%'>$userRelationshipRequestGet->mother_name</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>CEP</b></td>
                        <td width='25%'><b>Endereço</b></td>
                        <td width='25%'><b>Número</b></td>
                        <td width='25%'><b>Complemento</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$userRelationshipRequestGet->zip_code</td>
                        <td width='25%'>$userRelationshipRequestGet->public_place $userRelationshipRequestGet->address</td>
                        <td width='25%'>$userRelationshipRequestGet->number</td>
                        <td width='25%'>$userRelationshipRequestGet->complement</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>Bairro</b></td>
                        <td width='25%'><b>Cidade</b></td>
                        <td width='25%'><b>Estado</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$userRelationshipRequestGet->district</td>
                        <td width='25%'>$userRelationshipRequestGet->city</td>
                        <td width='25%'>$userRelationshipRequestGet->state_description</td>
                    </tr>
                </table>
                
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td><hr></td>
                    </tr>
                </table>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td>
                            Em anexo os documentos coletados durante a solicitação.
                        </td>
                    </tr>
                </table>
            ";

            $filedata = $this->createZipFile( $filesData );

            $sendMail = $this->sendEmail('Nova Solicitação de Vínculo de Usuário', $bodyMessage, $filedata->filename, $filedata->zipFile64);

            Storage::disk('zip')->delete($filedata->filename);

            if ( $sendMail == true ) {
                return response()->json(array("success" => "Token enviado por SMS confirmado com sucesso."));
            }
            return response()->json(array("success" => "Token enviado por SMS confirmado com sucesso, porém não foi possível enviar o e-mail para o titular."));

        }

        $userRelationshipRequest->token_phone_confirmed = 0;

        if(\Carbon\Carbon::parse($userRelationshipRequest->token_phone_expiration)->format('Y-m-d H:i:s') <= \Carbon\Carbon::now()->format('Y-m-d H:i:s')){      
            $userRelationshipRequest->save();
            return response()->json(array("error" => "Token expirado, por favor repita o procedimento"));
        }

        if ($userRelationshipRequest->save()) {
           return response()->json(array("error" => "Token informado não corresponde com o token enviado por SMS/Whatsapp."));
        }

        return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS/Whatsapp, por favor tente novamente."));
    }

    protected function sendEmail($subject, $bodyMessage, $attach_file_name, $attach_file_base64)
    {
        $apiSendGrind = new ApiSendgrid();
        $apiSendGrind->to_email = 'compliance@ip4y.com.br';
        $apiSendGrind->to_name = null;
        $apiSendGrind->to_cc_email = 'ragazzi@dinari.com.br';
        $apiSendGrind->to_cc_name = 'Ragazzi';
        $apiSendGrind->subject = $subject;
        $apiSendGrind->content = $bodyMessage;

        // set attachment files
        $apiSendGrind->attachment_files = [
            "content" => $attach_file_base64,
            "filename" => $attach_file_name,
            "type" => "application/zip",
            "disposition" => "attachment"
        ];
        
        if ($apiSendGrind->sendMail()) {
            return (object) ["success" => true];
        }
        return (object) ["success" => false];
    }

    protected function createZipFile($filesData)
    {
        $SimpleZip       = new SimpleZip();
        $createZipFolder = $SimpleZip->createZipFolder();

        if ($createZipFolder->success) {

            foreach($filesData as $file) {
            
                if ($documentType = DocumentType::where('id', '=', $file->type)->first()) {                
                    $fileAmazon           = new AmazonS3();
                    $fileAmazon->path     = $documentType->s3_path;
                    $fileAmazon->fileName = $file->filename;

                    if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                        Storage::disk('zip')->put($createZipFolder->folderName.'/'.$file->prefixFileName.$file->filename, base64_decode($fileAmazon->file64));
                    }
                }
            }

            $SimpleZip->fileData = (object) [
                "folderName" => $createZipFolder->folderName,
                "deleteFiles" => true
            ];

            $createZipFile = $SimpleZip->createZipFile();

            if ($createZipFile->success) {
                Storage::disk('zip')->put($createZipFile->zipFileName, base64_decode($createZipFile->zipFile64));
            }
        }

        return (object) [
            "success" => true,
            "filepath" =>  '../storage/app/zip/'.$createZipFile->zipFileName,
            "filename" =>  $createZipFile->zipFileName,
            "zipFile64" =>  $createZipFile->zipFile64,
        ];
    }

    protected function getUserRelationshipRequestDocuments(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [368];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer'],
            'uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data, id and uuid is required");
        }

        if (!$userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }
        
        $addressProof = [];
        $documentFront = [];
        $documentVerse = [];
     
        // return response()->json(array("success" => $userRelationshipRequest));

        switch ($userRelationshipRequest->document_identification_type_id) {
            case (1):
                $document_front_type_id = 1;
                $document_verse_type_id = 2;
            break;
            case (2):
                $document_front_type_id = 3;
                $document_verse_type_id = 4;
            break;
            case (3):
                $document_front_type_id = 5;
                $document_verse_type_id = 6;
            break;
        }
        
        if($userRelationshipRequest->address_proof_s3_filename != null and $userRelationshipRequest->address_proof_s3_filename != ''){
            $addressProofDocumentTypeId = 8;
            if($userRelationshipRequest->register_request_type_id == 2){
                $addressProofDocumentTypeId = 12;
            }
            $documentType = DocumentType::where('id', '=', $addressProofDocumentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $userRelationshipRequest->address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $addressProof = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_Endereco_'.$userRelationshipRequest->address_proof_s3_filename
                ];
            }
        }

        if($userRelationshipRequest->document_front_s3_filename != null and $userRelationshipRequest->document_front_s3_filename != ''){
            $documentType = DocumentType::where('id', '=', $document_front_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $userRelationshipRequest->document_front_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentFront = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Frente_'.$userRelationshipRequest->document_front_s3_filename
                ];
            }
        }

        if($userRelationshipRequest->document_verse_s3_filename != null and $userRelationshipRequest->document_verse_s3_filename != ''){
            $documentType = DocumentType::where('id', '=', $document_verse_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $userRelationshipRequest->document_verse_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentVerse = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Verso_'.$userRelationshipRequest->document_verse_s3_filename
                ];
            }
        }

        return response()->json(array(
            'success' => 'Documentos recuperados com sucesso',
            'data' => [
                'addressProof' => $addressProof,
                'documentFront' => $documentFront,
                'documentVerse' => $documentVerse,
            ]
        ));
    }

    protected function allowUserRelationshipRequest(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [368];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer'],
            'uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data, id and uuid is required");
        }

        if (!$userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }


        // Cria o cadastro 
        $registerService = new RegisterService();


        // Define dados para criar o cadastro
        $observationText                                = 'Aprovado na solicitação de novo usuário para conta';
        $registerService->cpf_cnpj                      = $userRelationshipRequest->cpf_cnpj;
        $registerService->name                          = $userRelationshipRequest->name;
        $registerService->master_id                     = $checkAccount->master_id;
        // $registerService->manager_email                 = $userRelationshipRequest->manager_email;
        $registerService->register_address              = $userRelationshipRequest->address;
        $registerService->register_address_state_id     = $userRelationshipRequest->state_id;
        $registerService->register_address_public_place = $userRelationshipRequest->public_place;
        $registerService->register_address_number       = $userRelationshipRequest->number;
        $registerService->register_address_complement   = $userRelationshipRequest->complement;
        $registerService->register_address_district     = $userRelationshipRequest->district;
        $registerService->register_address_city         = $userRelationshipRequest->city;  
        $registerService->register_address_zip_code     = $userRelationshipRequest->zip_code;
        $registerService->register_address_observation  = $observationText;
        $registerService->register_address_main         = true;
        $registerService->register_email                = $userRelationshipRequest->email;
        $registerService->register_email_observation    = $observationText;
        $registerService->register_email_main           = true;
        $registerService->register_phone                = $userRelationshipRequest->phone;
        $registerService->register_phone_observation    = $observationText;
        $registerService->register_phone_main           = true;
        $registerService->register_mother_name          = $userRelationshipRequest->mother_name;
        $registerService->register_birth_date           = $userRelationshipRequest->birth_date;
        $registerService->register_gender_id            = $userRelationshipRequest->gender_id;
        $registerService->register_marital_status_id    = $userRelationshipRequest->marital_status_id;
        // $registerService->register_pep                  = $userRelationshipRequest->pep;
        $registerService->register_income               = $userRelationshipRequest->income;
        $registerService->register_observation          = $observationText;
        $registerService->register_rg_number            = $userRelationshipRequest->document_number;
        
        // Cria/retorna o cadastro (se o cadastro não existir, ele cria e retorna, se já existir ele retorna)
        $createRegister = $registerService->returnRegister();

        if(!$createRegister->success){
            return response()->json(array("error" => $createRegister->message));
        }
    

        // Define comprovante de endereço
        if($userRelationshipRequest->address_proof_s3_filename != null and $userRelationshipRequest->address_proof_s3_filename != ''){
            $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 8, $userRelationshipRequest->address_proof_s3_filename);
        }

        // Define frente do documento
        if($userRelationshipRequest->document_front_s3_filename != null and $userRelationshipRequest->document_front_s3_filename != ''){
            $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, $userRelationshipRequest->document_identification_type_id, $userRelationshipRequest->document_front_s3_filename);
        }

        // Define verso do documento
        switch($userRelationshipRequest->document_identification_type_id){
            case (1):
                if($userRelationshipRequest->document_verse_s3_filename != null and $userRelationshipRequest->document_verse_s3_filename != ''){
                    $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 2, $userRelationshipRequest->document_verse_s3_filename);
                }
                
            break;
            case (2):
                if($userRelationshipRequest->document_verse_s3_filename != null and $userRelationshipRequest->document_verse_s3_filename != ''){
                    $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 4, $userRelationshipRequest->document_verse_s3_filename);
                }
                    
            break;
            case (3):
                if($userRelationshipRequest->document_verse_s3_filename != null and $userRelationshipRequest->document_verse_s3_filename != ''){
                    $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 6, $userRelationshipRequest->document_verse_s3_filename);
                }
            break;
        }

        // Define id do cadastro para criar novo usuário
        $registerMasterIdToCreateUser = $createRegister->register_master->id;
        

        // Nesse ponto, mudar o status da solicitação para aprovada
        $userRelationshipRequest->status_id = 9;
        $userRelationshipRequest->analyzed_by_user_id = Auth::user()->id;
        $userRelationshipRequest->analyzed_at = \Carbon\Carbon::now();
        $userRelationshipRequest->save();

        //Cria usuário
        $createUser = $this->createrUser($registerMasterIdToCreateUser);
        if(!$createUser->success){
            return response()->json(array("error" => 'A solicitação de cadastro de usuário foi aprovada, porém não foi possível criar o usuário, verifique o motivo e lembre-se de criar o usuário manualmente | '.$createUser->message, "data" => $createUser->data, "i" => $registerMasterIdToCreateUser));
        }


        //Cria vínculo do usuário com conta
        $createUserRelationship = $this->createUserRelationship($checkAccount->master_id, $createUser->data->user_master_id, $userRelationshipRequest->account_id, 3);

        if (! $createUserRelationship->success){
            return response()->json(array("error" => $createUserRelationship->message));
        }


        $getPermissions = new UserRelationshipRequestPermission();
        $getPermissions->user_relationship_request_id = $request->id;
        $permissions = $getPermissions->get();

        foreach( $permissions as $permission ){ 

            // cria permissão
            UsrRltnshpPrmssn::create([
                'usr_rltnshp_id' => $createUserRelationship->data->id,
                'permission_id'  => $permission->permission_id,
                'created_at'     => \Carbon\Carbon::now(),
            ]);

        }
     

        return response()->json(array("success" => "Cadastro aprovado com sucesso"));
    }

    protected function createDocument($register_master_id, $master_id, $document_type_id, $s3_file_name)
    {
        Document::create([
            'register_master_id' => $register_master_id,
            'master_id'          => $master_id,
            'document_type_id'   => $document_type_id,
            's3_file_name'       => $s3_file_name,
            'status_id'          => 9,
            'description'        => 'Aprovado na solicitação de novo usuário para conta',
            'created_by'         => auth('api')->user()->id,//USUÁRIO LOGADO,
            'created_at'         => \Carbon\Carbon::now()
        ]);
    }

    protected function createrUser($registerMasterIdToCreateUser)
    {
        if($registerMasterIdToCreateUser != null){
            $createrUser = new RegisterService();
            $createrUser->register_master_id = $registerMasterIdToCreateUser;
            $newUser = $createrUser->createUserByRegister();
            if(!$newUser->success){
                return (object)[
                    "success" => false,
                    "message" => $newUser->message,
                    "data" =>  $newUser->data
                ];
            }
            return (object) [
                "success" => true,
                "message" => $newUser->message,
                "data" =>  $newUser->data
            ];
        }
        return (object) [
            "success" => false,
            "message" => 'Cadastro não informado para criar usuário',
            "data" => []
        ];
    }

    protected function createUserRelationship($masterId, $userMasterId, $accountId, $userRelationshipId)
    {
        if (
            $masterId != null
            and $userMasterId != null
            and $accountId != null
            and $userRelationshipId != null
        ) {
            $UserRelationshipService = new UserRelationshipService();
            $UserRelationshipService->master_id = $masterId;
            $UserRelationshipService->user_master_id = $userMasterId;
            $UserRelationshipService->account_id = $accountId;
            $UserRelationshipService->relationship_id = $userRelationshipId;
            $createUserRelationship = $UserRelationshipService->createOrReactivateUserRelationship();
            if (! $createUserRelationship->success) {
                return (object) [
                    'success' => false,
                    'message' => $createUserRelationship->message,
                    'data' => $createUserRelationship->data
                ];
            }
            return (object) [
                'success' => true,
                'message' => $createUserRelationship->message,
                'data' => $createUserRelationship->data
            ];
        }
        return (object) [
            "success" => false,
            "message" => 'Dados pendentes para realizar vínculo do usuário',
            "data" => []
        ];
    }

    protected function denyUserRelationshipRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [369];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first();
        
        

        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer'],
            'uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data, id and uuid is required");
        }

        if (!$userRelationshipRequest = UserRelationshipRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, ou ele já foi recusado, por favor verifique os dados informados e tente novamente mais tarde."));
        }

        if($userRelationshipRequest->status_id == 9){
            return response()->json(array("error" => "Não é possível recusar uma solicitação de cadastro que já foi aprovada, em caso de dúvidas entre em contato com o suporte."));
        }

        $userRelationshipRequest->status_id = 49;
        $userRelationshipRequest->analyzed_by_user_id = Auth::user()->id;
        $userRelationshipRequest->analyzed_at = \Carbon\Carbon::now();
        $userRelationshipRequest->deleted_at = \Carbon\Carbon::now();

        if(!$userRelationshipRequest->save()){
            return response()->json(array("error" => "Não foi possível rejeitar a requisição de cadastro, por favor tente novamente mais tarde"));
        }

        $user = User::where('id', '=', $userRelationshipRequest->request_by_user_id)->first();
        $userRelationshipRequestType = UserRelationshipRequestType::where('id', '=', $userRelationshipRequest->type_id)->first();


        $bodyMessage = "
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                </tr>
            </table> <br>
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td>
                    Prezado(a) $user->name, a solicitação de vínculo do tipo $userRelationshipRequestType->description, para $userRelationshipRequest->name, foi recusada. Em caso de dúvidas, entre em contato com seu gerente de relacionamento.
                        <br><br>Obrigado por seu interesse! E contamos com sua compreensão.
                        <br><br><br>Atenciosamente,
                        <br>Equipe iP4y
                    </td>
                </tr>
            </table>
        ";
             

        $sendMail = new sendMail();

        $userRelationshipRequestGet = $userRelationshipRequest->get()[0];
        $userEmail = $userRelationshipRequestGet->request_by_user_email;
        $userName = $userRelationshipRequestGet->request_by_user_name;

        $sendMail->to_mail      = $userEmail;
        $sendMail->to_name      = $userName;
        $sendMail->send_cco     = 0;
        $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
        $sendMail->to_cco_name  = 'Ragazzi';
        $sendMail->attach_pdf   = 0;
        $sendMail->subject      = 'Solicitação de Cadastro de Vínculo iP4y';
        $sendMail->email_layout = 'emails/confirmEmailAccount';
        $sendMail->bodyMessage  = $bodyMessage;
        $sendMail->attach       = 0;
        $sendMail->attach_file  = null;
        $sendMail->attach_path  = null;
        $sendMail->attach_mime  = null;
        if ($sendMail->send()) {
            return response()->json(array("success" => "Solicitação de cadastro rejeitada com sucesso."));
        }
        return response()->json(array("error" => "Solicitação de cadastro rejeitada com sucesso, porém não foi possível enviar o e-mail para o titular."));

    }

}
