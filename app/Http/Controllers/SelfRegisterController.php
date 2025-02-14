<?php

namespace App\Http\Controllers;

use App\Models\SelfRegister;
use App\Models\Register;
use App\Models\RegisterMaster;
use App\Models\RegisterDetail;
use App\Models\RegisterDataPf;
use App\Models\RegisterDataPj;
use App\Models\Account;
use App\Models\SendSms;
use App\Models\ApiConfig;
use App\Models\RegisterAddress;
use App\Models\RegisterPhone;
use App\Models\RegisterEmail;
use App\Models\RgstrTxVlItm;
use App\Models\AccntTxVlItms;
use App\Models\RgstrLmtVlItm;
use App\Models\AccntLmtVlItm;
use App\Models\DocumentIdentificationType;
use App\Models\User;
use App\Models\UserMaster;
use App\Models\UserRelationship;
use App\Models\ManagerDetail;
use App\Models\ManagersRegister;
use App\Models\DocumentRg;
use App\Models\DocumentCnh;
use App\Models\DocumentRne;
use App\Models\DocumentType;
use App\Models\Document;
use App\Models\SystemFunctionMaster;
use App\Libraries\AmazonS3;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Models\Permission;
use App\Models\SecurityQuestion;
use App\Models\UsrRltnshpPrmssn;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Register\RegisterService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Libraries\ApiSendgrid;

class SelfRegisterController extends Controller
{

   /*
   
   public $masterId = 1;

   protected function new(Request $request)
   {

      $validator = Validator::make($request->all(), [
         'recaptcha_response' => ['required', 'string'],
         'cpf_cnpj' => ['required', 'string']
      ]);

     if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
     }

      $masterId = $this->masterId;
      $validate = new Facilites();
      $cpf_cnpj = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
      $validate->cpf_cnpj = $cpf_cnpj;

      if(strlen($cpf_cnpj) == 11) {
         if( !$validate->validateCPF($cpf_cnpj) ){
            return response()->json(array("error" => "CPF inválido", "redirect_login" => 0));
         }
      } else if(strlen($cpf_cnpj) == 14){
         if( !$validate->validateCNPJ($cpf_cnpj) ){
            return response()->json(array("error" => "CNPJ inválido", "redirect_login" => 0));
         }
      } else {
          return response()->json(array("error" => "CPF ou CNPJ inválido", "redirect_login" => 0));
      }

      if(strlen($cpf_cnpj) == 14){
         return response()->json(array("error" => "Para abrir o cadastro de sua empresa, por favor entre em contato conosco através de e-mail ou telefone"));
      }

      if( (\Carbon\Carbon::now())->toTimeString() > '21:00:00' ){
         return response()->json(array("error" => "Poxa, não é possível abrir novas contas a essa hora, por favor tente novamente amanhã"));
      }

      if( (\Carbon\Carbon::now())->toTimeString() < '06:30:00' ){
         return response()->json(array("error" => "Poxa, não é possível abrir novas contas a essa hora, por favor tente novamente mais tarde"));
      }

      $manager_detail_id = null;
      $manager_detail = ManagerDetail::where('manager_code','=',$request->manager_code);
      if($manager_detail->count() == 0){
         return response()->json(array("error" => "Código de gerente inválido, por favor entre em contato com o seu gerente e solicite o código para cadastro"));
      } else {
         $manager_detail_id = ($manager_detail->first())->id;
      }

      if( Register::where('cpf_cnpj', '=', $cpf_cnpj)->count() == 0 ){
         return $this->newSelfRegister($cpf_cnpj, $masterId, $request->header('ip'), $manager_detail_id);
      } else {
         $register        = Register::where('cpf_cnpj', '=', $cpf_cnpj)->first();
         $register_master = RegisterMaster::where('register_id','=',$register->id)->where('master_id','=',$masterId)->first();
         $register_detail = RegisterDetail::where('register_master_id','=',$register_master->id)->first();
         if( ( Account::where('master_id','=',$masterId)->where('register_master_id','=',$register_master->id)->count() )  > 0){
            return response()->json(array("error" => 'CPF/CNPJ já possui usuário, volte para a tela de login, clique em "Esqueceu a senha?", informe seu CPF e em breve receberá um e-mail e um SMS para redefinir a senha e acessar sua conta', "redirect_login" => 1));
         } else {
            return $this->newSelfRegister($cpf_cnpj, $masterId, $request->header('ip'), $manager_detail_id);
         }
      }
   }
   

   protected function newSelfRegister($cpf_cnpj, $masterId, $ip, $manager_detail_id)
   {
      if($selfRegister = SelfRegister::create([
         'cpf_cnpj'          => $cpf_cnpj,
         'unique_id'         => 0,
         'master_id'         => $masterId,
         'finished'          => 0,
         'ip'                => $ip,
         'manager_detail_id' => $manager_detail_id,
         'created_at' => \Carbon\Carbon::now()
      ])){
         $uniqueId = md5($selfRegister->id.$selfRegister->master_id.time());
         $self = SelfRegister::where('id','=',$selfRegister->id)->first();
         $self->unique_id = $uniqueId;
         if($self->save()){
            return response()->json(array("success" => "CPF/CNPJ cadastrado com sucesso", "id" => $self->id, "unique_id" => $self->unique_id));
         } else {
            return response()->json(array("error" => "No momento não foi possível criar uma identificação única para o seu cadastro, por favor tente novamente em alguns minutos ou entre em contato com nosso suporte"));
         }
      } else {
         return response()->json(array("error" => "No momento não foi possível criar seu cadastro, por favor tente novamente em alguns minutos ou entre em contato com nosso suporte"));
      }
   }

   protected function setName(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'name' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->name = $request->name;
         if($self->save()){
            return response()->json(array("success" => "Nome atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o nome, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setPersonCPF(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'cpf' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();

         if(strlen($self->cpf_cnpj) == 14){
            $self->person_cpf = $request->cpf;
            if($self->save()){
               return response()->json(array("success" => "CPF do representante legal da empresa atualizado com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao atualizar o CPF do representante legal da empresa, por favor tente novamente"));
            }
         } else {
            return response()->json(array("error" => "Essa informação deve ser preenchida apenas por pessoas jurídica"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setPersonName(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'name' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();

         if(strlen($self->cpf_cnpj) == 14){
            $self->person_name = $request->name;
            if($self->save()){
               return response()->json(array("success" => "Nome do representante legal da empresa atualizado com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao atualizar o nome do representante legal da empresa, por favor tente novamente"));
            }
         } else {
            return response()->json(array("error" => "Essa informação deve ser preenchida apenas por pessoa jurídica"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setEmail(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'email' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();

         if(strlen($self->cpf_cnpj) == 11){
            if(User::where('email','=', $request->email)->whereNull('deleted_at')->count() > 0 ){
               $user = User::where('email','=', $request->email)->whereNull('deleted_at')->first();
               if($user->cpf_cnpj != $self->cpf_cnpj){
                  return response()->json(array("error" => "E-Mail informado pertence a um usuário já cadastrado, por favor informe outro. Em caso de dúvidas entre em contato com o suporte"));
               }
            }
         }

         $self->email = $request->email;
         if($self->save()){
            return response()->json(array("success" => "E-mail atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o e-mail, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setPhone(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'phone' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();

         if(strlen($self->cpf_cnpj) == 11){
            if(User::where('phone','=', $request->phone)->whereNull('deleted_at')->count() > 0 ){
               $user = User::where('phone','=', $request->phone)->whereNull('deleted_at')->first();
               if($user->cpf_cnpj != $self->cpf_cnpj){
                  return response()->json(array("error" => "Número de celular informado pertence a um usuário já cadastrado, por favor informe outro. Em caso de dúvidas entre em contato com o suporte"));
               }
            }
         }

         $self->phone = $request->phone;
         if($self->save()){
            return response()->json(array("success" => "Número de celular atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o número de celular, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setAddress(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();

         if(strlen($request->address_zip_code) > 8){
            return response()->json(array("error" => "CEP inválido, por favor verifique os dados informados e tente novamente"));
         }

         $self->state_id     = $request->address_state_id;
         $self->public_place = $request->address_public_place;
         $self->address      = $request->address;
         $self->number       = $request->address_number;
         $self->complement   = $request->address_complement;
         $self->district     = $request->address_district;
         $self->city         = $request->address_city;
         $self->zip_code     = $request->address_zip_code;
         $self->ibge_code    = $request->address_ibge_code;
         $self->gia_code     = $request->address_gia_code;
         if($self->save()){
            return response()->json(array("success" => "Endereço do titular da conta atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o endereço do titular da conta, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setBirthDate(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'birth_date' => ['required'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( (\Carbon\Carbon::parse($request->birth_date))->diffInYears(\Carbon\Carbon::now()) < 18  ){
         return response()->json(array("error" => "Não é possível abrir conta para menores de idade"));
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->birth_date = $request->birth_date;
         if($self->save()){
            return response()->json(array("success" => "Data de nascimento atualizada com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a data de nascimento, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setRgNumber(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'rg_number' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->rg_number = preg_replace( '/[^0-9]/', '', $request->rg_number);
         if($self->save()){
            return response()->json(array("success" => "RG atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o RG, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setMotherName(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'mother_name' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->mother_name = $request->mother_name;
         if($self->save()){
            return response()->json(array("success" => "Nome da mãe atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o nome da mãe, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setConstitutionForm(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'constitution_form' => ['required'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->constitution_form = $request->constitution_form;
         if($self->save()){
            return response()->json(array("success" => "Forma de constituição atualizada com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a forma de constituição, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setConstitutionDate(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'constitution_date' => ['required'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->constitution_date = $request->constitution_date;
         if($self->save()){
            return response()->json(array("success" => "Data de constituição atualizada com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a data de constituição, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setMainActivity(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'main_activity' => ['required'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->main_activity = $request->main_activity;
         if($self->save()){
            return response()->json(array("success" => "Atividade principal atualizada com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a atividade principal, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setPersonAddress(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){

         if(strlen($request->person_address_zip_code) > 8){
            return response()->json(array("error" => "CEP inválido, por favor verifique os dados informados e tente novamente"));
         }

         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->person_state_id     = $request->person_address_state_id;
         $self->person_public_place = $request->person_address_public_place;
         $self->person_address      = $request->person_address;
         $self->person_number       = $request->person_address_number;
         $self->person_complement   = $request->person_address_complement;
         $self->person_district     = $request->person_address_district;
         $self->person_city         = $request->person_address_city;
         $self->person_zip_code     = $request->person_address_zip_code;
         $self->person_ibge_code    = $request->person_address_ibge_code;
         $self->person_gia_code     = $request->person_address_gia_code;
         if($self->save()){
            return response()->json(array("success" => "Endereço do representate legal da empresa atualizado com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar o endereço do representante legal da empresa, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function sendToken(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();

         //validar se todos os dados foram preenchidos

         $self->term_agree       = $request->term_ok;
         $self->send_token_agree = $request->info_ok;
         if($self->term_agree == 1 and $self->send_token_agree == 1){
            $token = new Facilites();
            $self->token_confirmation_email = $token->createApprovalToken();
            $self->token_confirmation_phone = $token->createApprovalToken();
            if($self->save()){
               if(!$this->sendEmailToken($self->id, $self->master_id, $self->token_confirmation_email, $self->email, $self->name, $self->person_name)){
                  return response()->json(array("error" => "Não foi possível enviar o token por e-mail, por favor tente novamente"));
               }
               if(!$this->sendPhoneToken($self->id, $self->master_id, $self->token_confirmation_phone, $self->phone, $self->name, $self->person_name)){
                  return response()->json(array("error" => "Não foi possível enviar o token, por favor tente novamente"));
               }
               return response()->json(array("success" => "Tokens enviados com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao atualizar os tokens, por favor tente novamente"));
            }
         } else if($self->term_agree == 0){
            return response()->json(array("error" => "É necessário estar de acordo com o termo de uso do sistema e autorizar o envio dos tokens para continuar"));
         } else{
            return response()->json(array("error" => "É necessário afirmar que as informações prestadas no cadastro são verdadeiras e exatas para continuar"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function confirmPhoneToken(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'phoneToken' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($self->term_agree == '' or $self->term_agree == 0  or $self->term_agree == null or $self->send_token_agree == '' or $self->send_token_agree == 0  or $self->send_token_agree == null){
            return response()->json(array("error" => "É necessário estar de acordo com o termo de uso, autorizar o envio dos tokens de confirmação e afirmar que as informações prestadas são verdadeiras antes de continuar"));
         }
         if($self->token_confirmation_phone ==  $request->phoneToken){
            $self->token_phone_confirmed = 1;
            if($self->save()){
               return response()->json(array("success" => "Token enviado por SMS confirmado com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente"));
            }
         } else {
            $self->token_phone_confirmed = 0;
            if($self->save()){
               return response()->json(array("error" => "Token informado não corresponde com o token enviado por SMS"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente"));
            }
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function confirmEmailToken(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'emailToken' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($self->token_phone_confirmed == '' or $self->token_phone_confirmed == 0  or $self->token_phone_confirmed == null){
            return response()->json(array("error" => "É necessário confirmar o token enviado por SMS antes de continuar"));
         }
         if($self->token_confirmation_email ==  $request->emailToken){
            $self->token_email_confirmed = 1;
            if($self->save()){
               return response()->json(array("success" => "Token enviado por e-mail confirmado com sucesso"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por e-mail, por favor tente novamente"));
            }
         } else {
            $self->token_email_confirmed = 0;
            if($self->save()){
               return response()->json(array("error" => "Token informado não corresponde com o token enviado por e-mail"));
            } else {
               return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por e-mail, por favor tente novamente"));
            }
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setPassword(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'password' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($self->token_email_confirmed == '' or $self->token_email_confirmed == 0  or $self->token_email_confirmed == null){
            return response()->json(array("error" => "É necessário confirmar o token enviado por e-mail antes de continuar"));
         }
         if(strlen(base64_decode($request->password)) < 6){
            return response()->json(array("error" => "A senha deve possuir pelo menos 6 caracteres"));
         }
         if(base64_decode($request->password) != base64_decode($request->password_confirm)){
            return response()->json(array("error" => "A senha informada não bate com a confirmação de senha"));
         }

         $passAllowedNumbers          = [0,1,2,3,4,5,6,7,8,9,'0','1','2','3','4','5','6','7','8','9'];
         $passAllowedSpecialCharacter = ['@','#','!','$','%','&','*'];
         $passArray                   = str_split(base64_decode($request->password));
         $passValidate                = true;
         $hasSpecialCharacter         = false;
         $hasNumber                   = false;
         $lastNumber                  = null;
         $lastSpecialCharacter        = null;

         foreach($passArray as $pass){
               if((in_array($pass, $passAllowedNumbers, true)) and $passValidate){
                  $hasNumber            = true;
                  $lastSpecialCharacter = null;
                  if($lastNumber == null){
                     $lastNumber   = (int) $pass;
                     $passValidate = true;
                  }
                  $lastNumber = (int) $pass;
               } else if((in_array($pass, $passAllowedSpecialCharacter, true)) and $passValidate){
                  $hasSpecialCharacter = true;
                  $lastNumber          = null;
                  if($lastSpecialCharacter == null){
                     $lastSpecialCharacter = $pass;
                     $passValidate         = true;
                  } else {
                     if($lastSpecialCharacter == $pass){
                           return response()->json(array("error" => "A senha não pode conter caracteres iguais na sequência"));
                     }
                  }
               } else {
                  $passValidate = false;
               }
         }

         if(!$passValidate){
               return response()->json(array("error" => "A senha deve conter números de 0 a 9 e caracteres especais (@ # ! $ % & *) "));
         }

         if(!$hasNumber){
               return response()->json(array("error" => "A senha deve conter pelo menos um número"));
         }

         if(!$hasSpecialCharacter){
               return response()->json(array("error" => "A senha deve conter pelo menos um caracter especial"));
         }

         $registerData = app('App\Http\Controllers\RegisterController')->returnRegister($self->cpf_cnpj, $self->name, $self->master_id);
         if($registerData->status == 0 ){
            return response()->json(array("error" => $registerData->error));
         } else {

            if($self->manager_detail_id != null){
               $managers_registers                     = new ManagersRegister();
               $managers_registers->manager_detail_id  = $self->manager_detail_id;
               $managers_registers->register_master_id = $registerData->success->id;
               $managers_registers->commission         = (ManagerDetail::where('id','=',$self->manager_detail_id)->first())->default_commission;
               $managers_registers->save();
            }

               RegisterAddress::Create([
                  'register_master_id' => $registerData->success->id,
                  'contact_type_id'    => null,
                  'state_id'           => $self->state_id,
                  'public_place'       => $self->public_place,
                  'address'            => $self->address,
                  'number'             => $self->number,
                  'complement'         => $self->complement,
                  'district'           => $self->district,
                  'city'               => $self->city,
                  'zip_code'           => $self->zip_code,
                  'ibge_code'          => $self->ibge_code,
                  'gia_code'           => $self->gia_code,
                  'main'               => 1,
                  'observation'        => 'Informado pelo usuário da conta ao realizar o cadastro',
                  'created_at'         => \Carbon\Carbon::now()
               ]);

               RegisterPhone::Create([
                  'register_master_id' => $registerData->success->id,
                  'contact_type_id'    => null,
                  'phone_type_id'      => 2,
                  'number'             => $self->phone,
                  'main'               => 1,
                  'observation'        => 'Informado pelo usuário da conta ao realizar o cadastro',
                  'created_at'         => \Carbon\Carbon::now()
               ]);

               RegisterEmail::Create([
                  'register_master_id' => $registerData->success->id,
                  'email'              => $self->email,
                  'main'               => 1,
                  'observation'        => 'Informado pelo usuário da conta ao realizar o cadastro',
                  'created_at'         => \Carbon\Carbon::now()
               ]);

               $accountType            = 1;
               $relationship           = 3;
               $accountTypeDescription = "Conta PJ";
               $userName               = $self->person_name;
               $userCPF                = $self->person_cpf;
               $approved               = 0;

               if (strlen($self->cpf_cnpj) == 11) {
                  $registerDataPF = RegisterDataPf::where('register_master_id','=',$registerData->success->id)->first();
                  $registerDataPF->mother_name = $self->mother_name;
                  $registerDataPF->date_birth  = $self->birth_date;
                  $registerDataPF->gender_id   = $self->gender_id;
                  $registerDataPF->save();

                  $documentRG = DocumentRg::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                  $documentRG->number = $self->rg_number;
                  $documentRG->save();

                  $accountType            = 2;
                  $relationship           = 4;
                  $accountTypeDescription = "Conta PF";
                  $userName               = $self->name;
                  $userCPF                = $self->cpf_cnpj;
                  $approved               = 1;
               } else {
                  $registerDataPj = RegisterDataPj::where('register_master_id','=',$registerData->success->id)->first();
                  $registerDataPj->foundation_date = $self->constitution_date;
                  $registerDataPj->branch_activity  = $self->main_activity;
                  $registerDataPj->save();
               }

               if ( ( Account::where('master_id','=',$self->master_id)->where('register_master_id','=',$registerData->success->id)->count() )  > 0) {
                  $newSubAccount    = Account::where('master_id','=',$self->master_id)->where('register_master_id','=',$registerData->success->id)->first();
                  $accountNumber    = $newSubAccount->account_register_number;
                  $subAccountNumber = Account::getNewSubAccountNumber($self->master_id, $newSubAccount->account_register_number);
               } else {
                  $accountNumber    = Account::getNewAccountNumber($self->master_id);
                  $subAccountNumber = 1;
               }

               $checkerNumber = app('App\Http\Controllers\AccountController')->createCheckerNumber( str_pad($accountNumber, 7, '0', STR_PAD_LEFT).str_pad($subAccountNumber, 2, '0', STR_PAD_LEFT) );

               if($account = Account::Create([
                  'master_id'                            => $self->master_id,
                  'register_master_id'                   => $registerData->success->id,
                  'account_type_id'                      => $accountType,
                  'profile_id'                           => $registerData->success->profile_id,
                  'srvc_bskt_id'                         => $registerData->success->srvc_bskt_id,
                  'lmt_grp_id'                           => $registerData->success->limit_group_id,
                  'description'                          => $accountTypeDescription,
                  'available_value'                      => 0,
                  'last_check_date'                      => \Carbon\Carbon::now(),
                  'account_available'                    => 1,
                  'account_register_number'              => $accountNumber,
                  'sub_account_register_number'          => $subAccountNumber,
                  'account_checker_number'               => $checkerNumber,
                  'account_number'                       => str_pad($accountNumber, 7, '0', STR_PAD_LEFT).str_pad($subAccountNumber, 2, '0', STR_PAD_LEFT).$checkerNumber,
                  'unique_id'                            => md5($registerData->success->id.date('Ymd').time()),
                  'inclusion_limit_transfer_payment_qtt' => 30,
                  'inclusion_limit_bill_payment_qtt'     => 30,
                  'inclusion_limit_payroll_payment_qtt'  => 30,
                  'inclusion_limit_pix_payment_qtt'      => 30,
                  'created_at'                           => \Carbon\Carbon::now()
               ])){
                  $taxes = RgstrTxVlItm::whereNull('deleted_at')->where('rgstr_id','=',$registerData->success->id)->get();
                  foreach ($taxes as $tax) {
                     AccntTxVlItms::create([
                           'accnt_id'             => $account->id,
                           'tax_id'               => $tax->taxe_id,
                           'srvc_bskt_grp_itm_id' => $tax->srvc_bskt_grp_itm_id,
                           'value'                => $tax->value,
                           'percentage'           => $tax->percentage,
                           'created_at'           => \Carbon\Carbon::now()
                     ]);
                  }
                  $limits = RgstrLmtVlItm::whereNull('deleted_at')->where('rgstr_id','=',$registerData->success->id)->get();
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
               } else {
               return response()->json(array("error" => "Não foi possível criar sua conta, por favor entre em contato com nossa equipe de suporte"));
               }

               $userAlias = (explode(' ',trim($userName)))[0];

               $user = app('App\Http\Controllers\UserController')->returnUser($userCPF, $userName, $userAlias, $self->email, $request->password, $self->phone);

               if ($user->status == 0) {

                  if(strlen($self->cpf_cnpj) == 14) {

                     if(User::where('cpf_cnpj','=',$self->person_cpf)->whereNull('deleted_at')->count() > 0){
                        $user = (object) ["status" => 2, "success" => User::where('cpf_cnpj','=',$self->person_cpf)->whereNull('deleted_at')->first()];
                     }
                  } else {
                     return response()->json(array("error" => $user->error ));
                  }
               }

               if($user->status == 1 || $user->status == 2){

                  $usr = User::where('id','=',$user->success->id)->first();
                  $usr->email_verified_at = \Carbon\Carbon::now();
                  $usr->phone_verified_at = \Carbon\Carbon::now();
                  $usr->save();

                  if( UserMaster::where("user_id","=",$user->success->id)->where("master_id","=",$self->master_id)->count() == 0 ){
                     if($userMaster = UserMaster::create([
                        'user_id'    => $user->success->id,
                        'master_id'  => $self->master_id,
                        'user_name'  => $userName,
                        'status_id'  => 3,
                        'user_admin' => 0,
                        'created_at' => \Carbon\Carbon::now()
                     ])){
                           if($userRelationship = UserRelationship::create([
                              'user_master_id'  => $userMaster->id,
                              'account_id'      => $account->id,
                              'relationship_id' => $relationship,
                              'created_at'      => \Carbon\Carbon::now()
                           ])){
                              $self->finished = 1;
                              $self->approved = $approved;
                              $self->save();

                              $this->permissions($userRelationship->id, $userRelationship->relationship_id);

                              //Cria cadastro do usuário
                              if( $accountType == 2 ){
                                 //Atualiza user_master_id de RegisterMaster se conta PF
                                 $registerMasterUser = RegisterMaster::where('id','=',$registerData->success->id)->first();
                                 $registerMasterUser->user_master_id = $userMaster->id;
                                 $registerMasterUser->save();
                              } else {

                                 if( Register::where('cpf_cnpj','=',$usr->cpf_cnpj)->count() > 0 ){
                                    $register                           = Register::where('cpf_cnpj','=',$usr->cpf_cnpj)->first();
                                    $registerMasterUser                 = RegisterMaster::where('register_id','=',$register->id)->first();
                                    $registerMasterUser->user_master_id = $userMaster->id;
                                    $registerMasterUser->save();
                                 } else {
                                    $registerService                 = new RegisterService();
                                    $registerService->master_id      = $userMaster->master_id;
                                    $registerService->cpf_cnpj       = $usr->cpf_cnpj;
                                    $registerService->name           = $userMaster->user_name;
                                    $registerService->register_phone = $usr->phone;
                                    $registerService->register_email = $usr->email;
                                    $registerService->user_master_id = $userMaster->id;
                                    $registerService->returnRegister();
                                 }
                              }

                              switch($self->dcmnt_idtf_tp_id){
                                 case 1:
                                       $documentRG = DocumentRg::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                       $documentRG->number = $self->rg_number;
                                       $documentRG->save();

                                       if(  $self->document_front != null ){
                                          Document::create([
                                             'register_master_id' => $registerMasterUser->id,
                                             'master_id'          => $self->master_id,
                                             'document_type_id'   => 17,
                                             's3_file_name'       => $self->document_front,
                                             'status_id'          => 50,
                                             'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                             'created_by'         => $user->success->id,
                                             'created_at'         => \Carbon\Carbon::now()
                                          ]);
                                       }

                                       if( $self->document_verse != null ) {
                                          Document::create([
                                             'register_master_id' => $registerMasterUser->id,
                                             'master_id'          => $self->master_id,
                                             'document_type_id'   => 18,
                                             's3_file_name'       => $self->document_verse,
                                             'status_id'          => 50,
                                             'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                             'created_by'         => $user->success->id,
                                             'created_at'         => \Carbon\Carbon::now()
                                          ]);
                                       }
                                 break;
                                 case 2:
                                       $documentCNH = DocumentCnh::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                       $documentCNH->number = $self->rg_number;
                                       $documentCNH->save();

                                       if(  $self->document_front != null ){
                                          Document::create([
                                             'register_master_id' => $registerMasterUser->id,
                                             'master_id'          => $self->master_id,
                                             'document_type_id'   => 19,
                                             's3_file_name'       => $self->document_front,
                                             'status_id'          => 50,
                                             'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                             'created_by'         => $user->success->id,
                                             'created_at'         => \Carbon\Carbon::now()
                                          ]);
                                       }

                                    if(  $self->document_verse != null ){
                                          Document::create([
                                             'register_master_id' => $registerMasterUser->id,
                                             'master_id'          => $self->master_id,
                                             'document_type_id'   => 20,
                                             's3_file_name'       => $self->document_verse,
                                             'status_id'          => 50,
                                             'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                             'created_by'         => $user->success->id,
                                             'created_at'         => \Carbon\Carbon::now()
                                          ]);
                                    }

                                 break;
                                 case 3:
                                       $documentRNE = DocumentRne::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                       $documentRNE->number = $self->rg_number;
                                       $documentRNE->save();

                                       if(  $self->document_front != null ){
                                          Document::create([
                                             'register_master_id' => $registerMasterUser->id,
                                             'master_id'          => $self->master_id,
                                             'document_type_id'   => 21,
                                             's3_file_name'       => $self->document_front,
                                             'status_id'          => 50,
                                             'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                             'created_by'         => $user->success->id,
                                             'created_at'         => \Carbon\Carbon::now()
                                          ]);
                                       }

                                       if ( $self->document_verse != null ) {
                                          Document::create([
                                             'register_master_id' => $registerMasterUser->id,
                                             'master_id'          => $self->master_id,
                                             'document_type_id'   => 22,
                                             's3_file_name'       => $self->document_verse,
                                             'status_id'          => 50,
                                             'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                             'created_by'         => $user->success->id,
                                             'created_at'         => \Carbon\Carbon::now()
                                          ]);
                                       }
                                 break;
                              }

                              if ( $self->selfie != null ) {
                                 Document::create([
                                    'register_master_id' => $registerMasterUser->id,
                                    'master_id'          => $self->master_id,
                                    'document_type_id'   => 23,
                                    's3_file_name'       => $self->selfie,
                                    'status_id'          => 50,
                                    'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                    'created_by'         => $user->success->id,
                                    'created_at'         => \Carbon\Carbon::now()
                                 ]);
                              }

                              if ( $self->address_proof != null ){
                                 Document::create([
                                    'register_master_id' => $registerMasterUser->id,
                                    'master_id'          => $self->master_id,
                                    'document_type_id'   => 24,
                                    's3_file_name'       => $self->address_proof,
                                    'status_id'          => 50,
                                    'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                    'created_by'         => $user->success->id,
                                    'created_at'         => \Carbon\Carbon::now()
                                 ]);
                              }

                              if ($user->status == 1) {

                                 return response()->json(array("success" => "Cadastro realizado com sucesso, agora já é possível acessar sua conta."));
                              } else {
                                 return response()->json(array("success" => "Cadastro realizado com sucesso, CPF do usuário já possuia cadastro no sistema, vínculo realizado com sucesso. *Obs. Os dados de acesso continuam os mesmos, a senha informada aqui foi descartada. Em caso de duvidas entre em contato com o suporte.*"));
                              }
                           } else {
                              return  response()->json(array("error" => "Não foi possível finalizar o cadastro. Sua conta foi aberta, porém não foi possível realizar o vínculo com a mesma, por favor entre em contato com o suporte"));
                           }
                     }
                  } else {
                     $userMaster = UserMaster::where("user_id","=",$user->success->id)->where("master_id","=",$self->master_id)->first();
                     if($userRelationship = UserRelationship::create([
                        'user_master_id'  => $userMaster->id,
                        'account_id'      => $account->id,
                        'relationship_id' => $relationship,
                        'created_at'      => \Carbon\Carbon::now()
                     ])){
                        $self->finished = 1;
                        $self->approved = $approved;
                        $self->save();

                        $this->permissions($userRelationship->id, $userRelationship->relationship_id);

                        //Cria cadastro do usuário
                           if( $accountType == 2 ){
                              //Atualiza user_master_id de RegisterMaster se conta PF
                              $registerMasterUser = RegisterMaster::where('id','=',$registerData->success->id)->first();
                              $registerMasterUser->user_master_id = $userMaster->id;
                              $registerMasterUser->save();
                           } else {

                              if ( Register::where('cpf_cnpj','=',$usr->cpf_cnpj)->count() > 0 ) {
                                 $register                           = Register::where('cpf_cnpj','=',$usr->cpf_cnpj)->first();
                                 $registerMasterUser                 = RegisterMaster::where('register_id','=',$register->id)->first();
                                 $registerMasterUser->user_master_id = $userMaster->id;
                                 $registerMasterUser->save();

                                 $registerDataPF = RegisterDataPf::where('register_master_id','=',$registerMasterUser->id)->first();
                                 $registerDataPF->mother_name = $self->mother_name;
                                 $registerDataPF->date_birth  = $self->birth_date;
                                 $registerDataPF->gender_id   = $self->gender_id;
                                 $registerDataPF->save();

                                 $documentRG = DocumentRg::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                 $documentRG->number = $self->rg_number;
                                 $documentRG->save();

                              } else {

                                 $registerService                 = new RegisterService();
                                 $registerService->master_id      = $userMaster->master_id;
                                 $registerService->cpf_cnpj       = $usr->cpf_cnpj;
                                 $registerService->name           = $userMaster->user_name;
                                 $registerService->register_phone = $usr->phone;
                                 $registerService->register_email = $usr->email;
                                 $registerService->user_master_id = $userMaster->id;
                                 $registerService->returnRegister();

                                 $register = Register::where('cpf_cnpj','=',$usr->cpf_cnpj)->first();

                                 $registerMasterUser = RegisterMaster::where('register_id','=',$register->id)->first();

                                 $registerDataPF = RegisterDataPf::where('register_master_id','=',$registerMasterUser->id)->first();
                                 $registerDataPF->mother_name = $self->mother_name;
                                 $registerDataPF->date_birth  = $self->birth_date;
                                 $registerDataPF->gender_id   = $self->gender_id;
                                 $registerDataPF->save();

                                 switch($self->dcmnt_idtf_tp_id){
                                       case 1:
                                          $documentRG = DocumentRg::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                          $documentRG->number = $self->rg_number;
                                          $documentRG->save();

                                          if(  $self->document_front != null ){
                                             Document::create([
                                                'register_master_id' => $registerMasterUser->id,
                                                'master_id'          => $self->master_id,
                                                'document_type_id'   => 17,
                                                's3_file_name'       => $self->document_front,
                                                'status_id'          => 50,
                                                'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                                'created_by'         => $user->success->id,
                                                'created_at'         => \Carbon\Carbon::now()
                                             ]);
                                          }

                                          if ( $self->document_verse != null ) {
                                             Document::create([
                                                'register_master_id' => $registerMasterUser->id,
                                                'master_id'          => $self->master_id,
                                                'document_type_id'   => 18,
                                                's3_file_name'       => $self->document_verse,
                                                'status_id'          => 50,
                                                'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                                'created_by'         => $user->success->id,
                                                'created_at'         => \Carbon\Carbon::now()
                                             ]);
                                          }
                                       break;
                                       case 2:
                                          $documentCNH = DocumentCnh::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                          $documentCNH->number = $self->rg_number;
                                          $documentCNH->save();

                                          if ( $self->document_front != null ) {
                                             Document::create([
                                                'register_master_id' => $registerMasterUser->id,
                                                'master_id'          => $self->master_id,
                                                'document_type_id'   => 19,
                                                's3_file_name'       => $self->document_front,
                                                'status_id'          => 50,
                                                'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                                'created_by'         => $user->success->id,
                                                'created_at'         => \Carbon\Carbon::now()
                                             ]);
                                          }

                                          if(  $self->document_verse != null ){
                                             Document::create([
                                                'register_master_id' => $registerMasterUser->id,
                                                'master_id'          => $self->master_id,
                                                'document_type_id'   => 20,
                                                's3_file_name'       => $self->document_verse,
                                                'status_id'          => 50,
                                                'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                                'created_by'         => $user->success->id,
                                                'created_at'         => \Carbon\Carbon::now()
                                             ]);
                                          }
                                       break;
                                       case 3:
                                          $documentRNE = DocumentRne::where('rgstr_data_pf_id','=',$registerDataPF->id)->first();
                                          $documentRNE->number = $self->rg_number;
                                          $documentRNE->save();

                                          if(  $self->document_front != null ){
                                             Document::create([
                                                'register_master_id' => $registerMasterUser->id,
                                                'master_id'          => $self->master_id,
                                                'document_type_id'   => 21,
                                                's3_file_name'       => $self->document_front,
                                                'status_id'          => 50,
                                                'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                                'created_by'         => $user->success->id,
                                                'created_at'         => \Carbon\Carbon::now()
                                             ]);
                                          }

                                          if(  $self->document_verse != null ){
                                             Document::create([
                                                'register_master_id' => $registerMasterUser->id,
                                                'master_id'          => $self->master_id,
                                                'document_type_id'   => 22,
                                                's3_file_name'       => $self->document_verse,
                                                'status_id'          => 50,
                                                'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                                'created_by'         => $user->success->id,
                                                'created_at'         => \Carbon\Carbon::now()
                                             ]);
                                          }
                                       break;
                                 }

                                 if(  $self->selfie != null ){
                                       Document::create([
                                          'register_master_id' => $registerMasterUser->id,
                                          'master_id'          => $self->master_id,
                                          'document_type_id'   => 23,
                                          's3_file_name'       => $self->selfie,
                                          'status_id'          => 50,
                                          'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                          'created_by'         => $user->success->id,
                                          'created_at'         => \Carbon\Carbon::now()
                                       ]);
                                 }

                                 if(  $self->address_proof != null ){
                                       Document::create([
                                          'register_master_id' => $registerMasterUser->id,
                                          'master_id'          => $self->master_id,
                                          'document_type_id'   => 24,
                                          's3_file_name'       => $self->address_proof,
                                          'status_id'          => 50,
                                          'description'        => 'Informado pelo usuário ao realizar o cadastro',
                                          'created_by'         => $user->success->id,
                                          'created_at'         => \Carbon\Carbon::now()
                                       ]);
                                 }
                              }
                           }
                           return response()->json(array("success" => "Cadastro realizado com sucesso, CPF do usuário já possuia cadastro no sistema, vínculo realizado com sucesso. *Obs. Os dados de acesso continuam os mesmos, a senha informada aqui foi descartada. Em caso de duvidas entre em contato com o suporte.*"));
                     } else {
                        return  response()->json(array("error" => "Não foi possível finalizar o cadastro. Usuário já possui cadastro no sistema, porém não foi possível realizar o vínculo com a nova conta, por favor entre em contato com o suporte"));
                     }
                  }
               } else {
                  return  response()->json(array("error" => "Não foi possível finalizar o cadastro. Usuário já possui cadastro no sistema, porém o acesso encontra-se desabilitado, por favor entre em contato com o suporte"));
               }
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function permissions($user_relationship_id, $relationship)
   {
        if ($permissions = Permission::where('relationship_id','=',$relationship)->whereNull('deleted_at')->get()) {

            foreach ($permissions as $permission) {
                if (!UsrRltnshpPrmssn::create([
                    'usr_rltnshp_id' => $user_relationship_id,
                    'permission_id'  => $permission->id,
                    'created_at'     => \Carbon\Carbon::now(),
                ])) {
                 array_push($error,["error" => "Dados não inseridos", "permission_id" => $permission->id]);
                }
            }
        }
   }

   protected function aproveSelfRegister(Request $request)
   {
      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->approved = 1;
         if($self->save()){
           return response()->json(array("success" => "Solicitação de cadastro aprovada com sucesso"));
         } else {
           return response()->json(array("error" => "Ocorreu uma falha ao aprovar a solicitação de cadastro, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function reproveSelfRegister(Request $request)
   {
      if( SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->approved = 0;
         if($self->save()){
            return response()->json(array("success" => "Solicitação de cadastro negada com sucesso"));
         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao negar a solicitação de cadastro, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   public function sendEmailToken($id, $masterId, $token_confirmation_email, $email, $name, $person_name)
   {
      if($person_name == '' or $person_name == null){
         $message = "Olá $name, <br>O token ".substr($token_confirmation_email,0,4)."-".substr($token_confirmation_email,4,4)." foi gerado para a confirmação da abertura de sua conta digital. Ao confirmá-lo, você declara que leu e está de acordo com o termo de uso do sistema.";
      } else {
         $message = "Olá $person_name, <br>O token ".substr($token_confirmation_email,0,4)."-".substr($token_confirmation_email,4,4)." foi gerado para a confirmação da abertura da conta digital de ".$name.". Ao confirmá-lo, você declara que leu e está de acordo com o termo de uso do sistema.";
      }

      $apiSendGrid = new ApiSendgrid();
      $apiSendGrid->to_email    = $email;
      $apiSendGrid->to_name     = $name;
      $apiSendGrid->to_cc_email = 'ragazzi@dinari.com.br';
      $apiSendGrid->to_cc_name  = 'Ragazzi';
      $apiSendGrid->subject     = 'Confirmação de Abertura de Conta';
      $apiSendGrid->content     = $message;
      if( $apiSendGrid->sendSimpleEmail()) {
         return true;
      } else {
         return false;
      }

   }

   public function sendPhoneToken($id, $masterId, $token_confirmation_phone, $phone, $name, $person_name)
   {
      if($person_name == '' or $person_name == null){
         $message = "Ola ".(explode(" ",$name))[0].", o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para confirmar a abertura de sua conta digital.";
      } else {
         $message = "Ola ".(explode(" ",$person_name))[0].", o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para confirmar a abertura da conta digital de ".mb_substr($name,0,20).".";
      }
      $sendSMS = SendSms::create([
        'external_id' => ("3".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$id),
        'to'          => "55".$phone,
        'message'     => $message,
        'type_id'     => 3,
        'origin_id'   => $id,
        'created_at'  => \Carbon\Carbon::now()
      ]);
      $apiConfig                     = new ApiConfig();
      $apiConfig->master_id          = $masterId;
      $apiConfig->api_id             = 3;
      $apiConfig->onlyActive         = 1;
      $apiData                       = $apiConfig->getApiConfig()[0];
      $apiZenviaSMS                  = new ApiZenviaSMS();
      $apiZenviaSMS->api_address     = Crypt::decryptString($apiData->api_address);
      $apiZenviaSMS->authorization   = Crypt::decryptString($apiData->api_authentication);
      $apiZenviaSMS->id              = $sendSMS->external_id;
      $apiZenviaSMS->aggregateId     = "001";
      $apiZenviaSMS->to              = $sendSMS->to;
      $apiZenviaSMS->msg             = $sendSMS->message;
      $apiZenviaSMS->callbackOption  = "NONE";

      //Check if should send token by whatsapp
      if( (SystemFunctionMaster::where('system_function_id','=',10)->where('master_id','=',$masterId)->first())->available == 1 ){
         $apiZenviaWhats            = new ApiZenviaWhatsapp();
         $apiZenviaWhats->to_number = $sendSMS->to;
         $apiZenviaWhats->token     = "*".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)."*";
         if(isset( $apiZenviaWhats->sendToken()->success ) ){
            return true;
         }
      }

      if(isset($apiZenviaSMS->sendShortSMS()->success)){
        return true;
      } else {
         return false;
      }
   }

   protected function setSelfie(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'file_name' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($document_type = DocumentType::where('id','=',23)->first()){
            $ext = pathinfo($request->file_name, PATHINFO_EXTENSION);
            $fileName = md5($document_type->id.date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            if(empty($self->selfie)){
                $amazons3->fileName = $self->selfie;
                $amazons3->path     = $document_type->s3_path;
                $amazons3->fileDeleteAmazon();
            }
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
              $self->selfie = $fileName;
              if($self->save()){
               return response()->json(array("success"=>"Selfie enviada com sucesso"));
              }else{
               return response()->json(array("error"=>"Ocorreu uma falha ao enviar a Selfie"));
              }
            }else{
               return response()->json(array("error"=>"Ocorreu uma falha ao enviar a Selfie"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
         }
      }else{
        return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setDocumentIdentificationType(Request $request)
   {
      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->dcmnt_idtf_tp_id   = $request->dcmnt_idtf_tp_id;
         if($self->save()){
            return response()->json(array("success" => "Tipo de documento definido com sucesso"));
         }else{
            return response()->json(array("error" => "Ocorreu uma falha ao definir o documento, por favor tente novamente"));
         }
      }else{
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setDocumentFront(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         switch($self->dcmnt_idtf_tp_id){
            case(1):
              $documentType = 17;
            break;
            case(2):
              $documentType = 19;
            break;
            case(3):
              $documentType = 21;
            break;
            default:
            return response()->json(array("error"=>"Tipo de Documento não definido"));
         }
         if($document_type = DocumentType::where('id','=',$documentType)->first()){
            $ext = pathinfo($request->file_name, PATHINFO_EXTENSION);
            $fileName = md5($document_type->id.date('Ymd').time()).'.'.$ext;
            $amazons3 = new AmazonS3();
            if(empty($self->document_front)){
                $amazons3->fileName = $self->document_front;
                $amazons3->path     = $document_type->s3_path;
                $amazons3->fileDeleteAmazon();
            }
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
              $self->document_front     = $fileName;
              if($self->save()){
               return response()->json(array("success"=>"Frente do documento enviada com sucesso"));
              }else{
               return response()->json(array("error"=>"Ocorreu uma falha ao enviar a frente do documento"));
              }
            }else{
               return response()->json(array("error"=>"Falha ao enviar a frente do documento"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
         }
      }else{
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setDocumentVerse(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
        $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         switch($self->dcmnt_idtf_tp_id){
            case(1):
              $documentType = 18;
            break;
            case(2):
              $documentType = 20;
            break;
            case(3):
              $documentType = 22;
            break;
            default:
            return response()->json(array("error"=>"Tipo de documento não definido"));
         }
         if($document_type = DocumentType::where('id','=',$documentType)->first()){
            $ext = pathinfo($request->file_name, PATHINFO_EXTENSION);
            $fileName = md5($document_type->id.date('Ymd').time()).'.'.$ext;
            $amazons3           = new AmazonS3();
            if(empty($self->document_verse)){
                $amazons3->fileName = $self->document_verse;
                $amazons3->path     = $document_type->s3_path;
                $amazons3->fileDeleteAmazon();
            }
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
              $self->document_verse     = $fileName;
              if($self->save()){
               return response()->json(array("success"=>"Verso do documento enviado com sucesso"));
              }else{
               return response()->json(array("error"=>"Ocorreu uma falha ao enviar o Verso do documento"));
              }
            }else{
               return response()->json(array("error"=>"Falha ao enviar o documento"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de documento não localizado"));
         }
      }else{
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function setAddressProof(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string']
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($document_type = DocumentType::where('id','=',24)->first()){
            $ext        = pathinfo($request->file_name, PATHINFO_EXTENSION);
            $fileName   = md5($document_type->id.date('Ymd').time()).'.'.$ext;
            $amazons3   = new AmazonS3();
            if(empty($self->address_proof)){
                $amazons3->fileName = $self->address_proof;
                $amazons3->path     = $document_type->s3_path;
                $amazons3->fileDeleteAmazon();
            }
            $amazons3->fileName = $fileName;
            $amazons3->file64   = base64_encode(file_get_contents($request->file64));
            $amazons3->path     = $document_type->s3_path;
            $upfile             = $amazons3->fileUpAmazon();
            if($upfile->success){
              $self->address_proof = $fileName;
               if($self->save()){
                  return response()->json(array("success" => "Comprovante de endereço enviado com sucesso"));
               }else{
                  return response()->json(array("error" => "Ocorreu uma falha ao enviar o comprovante de endereço, por favor tente novamente mais tarde"));
               }
            }else{
               return response()->json(array("error" => "Ocorreu uma falha ao enviar o comprovante de endereço"));
            }
         }else{
            return response()->json(array("error" => "Tipo de documento não localizado"));
         }
      }else{
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function downloadMasterFront(Request $request)
   {
      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         switch($self->dcmnt_idtf_tp_id){
            case(1):
              $documentType = 17;
            break;
            case(2):
              $documentType = 19;
            break;
            case(3):
              $documentType = 21;
            break;
            default:
            return response()->json(array("error"=>"Tipo de Documento não definido"));
         }
         if($document_type = DocumentType::where('id','=',$documentType)->first()){
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $self->document_front;
            $amazons3->path     = $document_type->s3_path;
            $downfile            = $amazons3->fileDownAmazon();
            if($downfile->success){
               $ext = pathinfo($self->s3_file_name, PATHINFO_EXTENSION);
               switch($ext){
                  case 'jpg':
                     $mimeType = 'image/jpg';
                  break;
                  case 'jpeg':
                     $mimeType = 'image/jpeg';
                  break;
                  case 'png':
                     $mimeType = 'image/png';
                  break;
                  default:
                     $mimeType = "application/".$ext;
                  break;
               }
               if(isset($downfile->file64)){
                  return response()->json(array(
                     "success"   =>"Download do documento realizado com sucesso",
                     "file_name" => $document_type->description.'_'.$self->id.'.'.$ext,
                     "mime_type" => $mimeType,
                     "base64"    => $downfile->file64
                  ));
               } else {
                  return response()->json(array("error" => "Ocorreu uma falha ao baixar o documento"));
               }
            }else{
               return response()->json(array("error"=>"Falha ao baixar o documento"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de Documento não localizado"));
         }
      }else{
         return response()->json(array("error"=>"Documento não localizado"));
      }
   }

   protected function downloadMasterVerse(Request $request)
   {
      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         switch($self->dcmnt_idtf_tp_id){
           case(1):
             $documentType = 18;
           break;
           case(2):
             $documentType = 20;
           break;
           case(3):
             $documentType = 22;
           break;
           default:
           return response()->json(array("error"=>"Tipo de documento não definido"));
         }
         if($document_type = DocumentType::where('id','=', $documentType)->first()){
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $self->document_verse;
            $amazons3->path     = $document_type->s3_path;
            $downfile            = $amazons3->fileDownAmazon();
            if($downfile->success){
               $ext = pathinfo($self->s3_file_name, PATHINFO_EXTENSION);
               switch($ext){
                  case 'jpg':
                      $mimeType = 'image/jpg';
                  break;
                  case 'jpeg':
                      $mimeType = 'image/jpeg';
                  break;
                  case 'png':
                      $mimeType = 'image/png';
                  break;
                  default:
                      $mimeType = "application/".$ext;
                  break;
               }
               if(isset($downfile->file64)){
                  return response()->json(array(
                      "success"   =>"Download do documento realizado com sucesso",
                      "file_name" => $document_type->description.'_'.$self->id.'.'.$ext,
                      "mime_type" => $mimeType,
                      "base64"    => $downfile->file64
                  ));
               } else {
                  return response()->json(array("error" => "Ocorreu uma falha ao baixar o documento"));
               }
            }else{
               return response()->json(array("error"=>"Falha ao baixar o documento"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de Documento não localizado"));
         }
      }else{
         return response()->json(array("error"=>"Documento não localizado"));
      }
   }

   protected function downloadSelf(Request $request)
   {
      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($document_type = DocumentType::where('id','=',23)->first()){
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $self->selfie;
            $amazons3->path     = $document_type->s3_path;
            $downfile            = $amazons3->fileDownAmazon();
            if($downfile->success){
               $ext = pathinfo($self->s3_file_name, PATHINFO_EXTENSION);
               switch($ext){
                  case 'jpg':
                     $mimeType = 'image/jpg';
                  break;
                  case 'jpeg':
                     $mimeType = 'image/jpeg';
                  break;
                  case 'png':
                     $mimeType = 'image/png';
                  break;
                  default:
                     $mimeType = "application/".$ext;
                  break;
               }
               if(isset($downfile->file64)){
                  return response()->json(array(
                     "success"   =>"Download do documento realizado com sucesso",
                     "file_name" => $document_type->description.'_'.$self->id.'.'.$ext,
                     "mime_type" => $mimeType,
                     "base64"    => $downfile->file64
                  ));
               } else {
                 return response()->json(array("error" => "Ocorreu uma falha ao baixar o documento"));
               }
            }else{
               return response()->json(array("error"=>"Falha ao baixar o documento"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de Documento não localizado"));
         }
      }else{
         return response()->json(array("error"=>"Documento não localizado"));
      }
   }

   protected function downloadAddressProof(Request $request)
   {
      if(SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0){
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         if($document_type = DocumentType::where('id','=',24)->first()){
            $amazons3 = new AmazonS3();
            $amazons3->fileName = $self->address_proof;
            $amazons3->path     = $document_type->s3_path;
            $downfile            = $amazons3->fileDownAmazon();
            if($downfile->success){
               $ext = pathinfo($self->s3_file_name, PATHINFO_EXTENSION);
               switch($ext){
                  case 'jpg':
                     $mimeType = 'image/jpg';
                  break;
                  case 'jpeg':
                     $mimeType = 'image/jpeg';
                  break;
                  case 'png':
                     $mimeType = 'image/png';
                  break;
                  default:
                     $mimeType = "application/".$ext;
                  break;
               }
               if(isset($downfile->file64)){
                  return response()->json(array(
                     "success"   =>"Download do documento realizado com sucesso",
                     "file_name" => $document_type->description.'_'.$self->id.'.'.$ext,
                     "mime_type" => $mimeType,
                     "base64"    => $downfile->file64
                  ));
               } else {
                  return response()->json(array("error" => "Ocorreu uma falha ao baixar o documento"));
               }
            }else{
               return response()->json(array("error"=>"Falha ao baixar o documento"));
            }
         }else{
            return response()->json(array("error"=>"Tipo de Documento não localizado"));
         }
      }else{
         return response()->json(array("error"=>"Documento não localizado"));
      }
   }

   protected function setGender(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'gender_id' => ['required', 'integer'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if (SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0) {
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->gender_id = $request->gender_id;
         if($self->save()){
               return response()->json(array("success" => "Gênero atualizado com sucesso"));
         } else {
               return response()->json(array("error" => "Ocorreu uma falha ao atualizar o gênero, por favor tente novamente"));
         }
      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   protected function get(Request $request)
   {
      // ----------------- Check Account Verification ----------------- //
      $accountCheckService           = new AccountRelationshipCheckService();
      $accountCheckService->request  = $request;
      $checkAccount                  = $accountCheckService->checkAccount();
      if(!$checkAccount->success){
         return response()->json(array("error" => $checkAccount->message));
      }
      // -------------- Finish Check Account Verification -------------- //

      $selfRegister                   = new SelfRegister();
      $selfRegister->id               = $request->id;
      $selfRegister->unique_id        = $request->unique_id;
      $selfRegister->uuid             = $request->uuid;
      $selfRegister->master_id        = $checkAccount->master_id;
      $selfRegister->cpf_cnpj         = $request->cpf_cnpj;
      $selfRegister->person_cpf_cnpj  = $request->person_cpf_cnpj;
      $selfRegister->email            = $request->email;
      $selfRegister->phone            = $request->phone;
      $selfRegister->state            = $request->state;
      $selfRegister->city             = $request->city;
      $selfRegister->term_agree       = $request->term_agree;
      $selfRegister->send_token_agree = $request->send_token_agree;
      $selfRegister->finished         = $request->finished;
      $selfRegister->approved         = $request->approved;

      return response()->json($selfRegister->getSelfRegisters());
   }

    protected function setSecurityQuestionOne(Request $request)
    {

      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'security_question_1_id' => ['required', 'integer'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if (!SecurityQuestion::where('id','=',$request->security_question_1_id)->first()) {
         return response()->json(array("error" => "Poxa, não foi possível localizar a pergunta, por favor tente mais tarde"));
      }

      if (SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0) {
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->security_question_1_id = $request->security_question_1_id;

         if ($self->save()) {
            return response()->json(array("success" => "Primeira pergunta de segurança atualizada com sucesso"));

         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a primeira pergunta de segurança, por favor tente novamente"));
         }

      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
    }

    protected function setSecurityQuestionOneAnswer(Request $request)
    {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'security_question_1_answer' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if (SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0) {
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->security_question_1_answer = $request->security_question_1_answer;

         if ($self->save()) {
            return response()->json(array("success" => "Resposta da primeira pergunta de segurança atualizada com sucesso"));

         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a resposta da primeira pergunta de segurança, por favor tente novamente"));
         }

      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
    }

    protected function setSecurityQuestionTwo(Request $request)
    {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'security_question_2_id' => ['required', 'integer'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if (!SecurityQuestion::where('id','=',$request->security_question_2_id)->first()) {
         return response()->json(array("error" => "Poxa, não foi possível localizar a pergunta, por favor tente mais tarde"));
      }

      if (SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0) {
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->security_question_2_id = $request->security_question_2_id;

         if ($self->save()) {
            return response()->json(array("success" => "Segunda pergunta de segurança atualizada com sucesso"));

         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a segunda pergunta de segurança, por favor tente novamente"));
         }

      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
    }

   protected function setSecurityQuestionAnswerTwo(Request $request)
   {
      $validator = Validator::make($request->all(), [
         'id' => ['required', 'integer'],
         'unique_id' => ['required', 'string'],
         'security_question_2_answer' => ['required', 'string'],
      ]);

      if ($validator->fails()) {
         return abort(404, "Not Found | Invalid Data");
      }

      if (SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->count() > 0) {
         $self = SelfRegister::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished','=',0)->whereNull('deleted_at')->first();
         $self->security_question_2_answer = $request->security_question_2_answer;

         if ($self->save()) {
            return response()->json(array("success" => "Resposta da segunda pergunta de segurança atualizada com sucesso"));

         } else {
            return response()->json(array("error" => "Ocorreu uma falha ao atualizar a resposta da segunda pergunta de segurança, por favor tente novamente"));
         }

      } else {
         return response()->json(array("error" => "Solicitação de cadastro não localizada"));
      }
   }

   */

}
