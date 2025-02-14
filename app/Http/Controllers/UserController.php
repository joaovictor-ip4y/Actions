<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\UserMaster;
use App\Models\SendSms;
use App\Models\ApiConfig;
use App\Models\RedefinePassword;
use App\Models\ManagerDetail;
use App\Models\SystemFunctionMaster;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\ApiSendgrid;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\User\UserService;
use App\Services\Register\RegisterService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Classes\Failure\MovimentationFailureClass;
use Illuminate\Support\Str;
use Carbon\Carbon;


class UserController extends Controller
{

    public $masterId = 1;

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [128];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $user = new User();
        return response()->json($user->getUser());
    }

    protected function getUserProfile(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        if (! Auth::check()) {
            return response()->json(array("error" => "Usuário não autenticado, por favor realize o login para continuar" )) ;
        }
        
        $user = Auth::user();


        $userData = new UserMaster();
        $userData->user_id = $user->id;
        $userData->master_id = $request->header('masterId');
        return response()->json(array("success" => "", "data" => $userData->getUserProfile()[0]));
    }

    protected function check(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [128];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $master_id = 1;
        $facilities           = new Facilites();
        $cpf_cnpj             = preg_replace( '/[^0-9]/', '', $request->cpf_cnpj);
        $facilities->cpf_cnpj = $cpf_cnpj;
        if(strlen($cpf_cnpj) == 11) {
            if( !  $facilities->validateCPF($cpf_cnpj) ){
                return response()->json( array("user_exists" => 0, "error" => "CPF inválido", "user_master_id" => null) );
            }
        } else if(strlen($cpf_cnpj) == 14){
            if( ! $facilities->validateCNPJ($cpf_cnpj) ){
                return response()->json( array("user_exists" => 0, "error" => "CNPJ inválido", "user_master_id" => null) );
            }
        } else {
            return response()->json( array("user_exists" => 0, "error" => "CPF ou CNPJ inválido", "user_master_id" => null) );
        }
        if( User::where('cpf_cnpj','=',$cpf_cnpj )->count() > 0 ){
            $user = User::where('cpf_cnpj','=',$cpf_cnpj )->first();
            if( UserMaster::where("user_id","=",$user->id)->where("master_id","=",$master_id)->count() > 0 ){
                $userMaster = UserMaster::where("user_id","=",$user->id)->where("master_id","=",$master_id)->first();
                return response()->json(array("success" => "", "user_exists" => 1, "user_master_id" => $userMaster->id, "name" => $userMaster->user_name, "email" => $user->email ));
            } else {
                return response()->json(array("success" => "", "user_exists" => 2, "user_master_id" => null, "user_mail" => $user->email));
            }
        } else {
            return response()->json(array("success" => "", "user_exists" => 0, "user_master_id" => null));
        }
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        $master_id = 1;
        $userService = new UserService();
        $userService->userData = (object) [
            'usr_cpf_cnpj'  => $request->user_cpf_cnpj,
            'user_password' => Hash::make(base64_decode($request->user_password)),
            'user_name'     => $request->user_name,
            'user_alias'    => $request->user_alias,
            'user_email'    => $request->user_email,
            'user_phone'    => $request->user_phone,
            'master_id'     => $master_id
        ];

        $createUser = $userService->createUserMaster();

        if($createUser->success){
            return response()->json(array("success" => $createUser->message, "user_master_id" => $createUser->user_master_id));
        } else {
            return response()->json(array("error" => $createUser->message, "user_master_id" => null));
        }
    }

    protected function userForgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cpf_cnpj' => ['required', 'string'],
            'recaptcha_response' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if( ! $usr = User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '', $request->cpf_cnpj))->first() ) {
            return response()->json(array("error" => "Ocorreu um erro ao inicar a requisição de alteração de senha"));
        }

        if($usr->status == 3){
            return response()->json(array("error" => "Poxa, não foi possível iniciar a redefinição da sua senha, em caso de dúvidas, por favor entre em contato com nossa equipe de suporte",  "recaptcha_error" => false));
        }

        if($usr->recaptcha_response <> $request->recaptcha_response){
            return response()->json(array("error" => "Código recaptacha informado é inválido, por favor tente novamente", "recaptcha_error" => true));
        }
        return $this->sendTokenPassword($usr->cpf_cnpj, $this->masterId, $request->header('ip'));
    }

    protected function userUpdatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string']
        ],[
            'current_password.required' => 'Informe a senha atual.',
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $usr  = User::where('id','=',$user->id)->first();
            if( Hash::check( base64_decode($request->current_password) , $usr->password) ){
                return $this->sendTokenPassword($usr->cpf_cnpj, $request->header('masterId'), $request->header('ip'));
            } else {
                return response()->json(array("error" => "Senha atual inválida"));
            }
        } else {
            return response()->json(array("error" => "Ocorreu uma falha de autenticação"));
        }
    }

    protected function sendTokenPassword($cpf_cnpj, $master_id, $ip)
    {
        $usr = User::where('cpf_cnpj','=',preg_replace( '/[^0-9]/', '',$cpf_cnpj))->first();
        $token = new Facilites();
        if($redefinePassword = RedefinePassword::create([
            'unique_id'                 => Str::orderedUuid(),
            'user_id'                   => $usr->id,
            'master_id'                 => $master_id,
            'token_confirmation_email'  => $token->createApprovalToken(),
            'token_email_confirmed'     => 0,
            'token_confirmation_phone'  => $token->createApprovalToken(),
            'token_phone_confirmed'     => 0,
            'finished'                  => 0,
            'ip'                        => $ip,
            'phone_token_attempt'       => 0,
            'email_token_attempt'       => 0,
            'invalid'                   => 0,
            'created_at'                => \Carbon\Carbon::now()
        ])){
            if(!$this->sendEmailToken($redefinePassword->id, $master_id, $redefinePassword->token_confirmation_email, $usr->email, $usr->name)){
                return response()->json(array("error" => "Não foi possível enviar o e-mail para redefinição de senha, por favor tente novamente"));
            }
            if(!$this->sendPhoneToken($redefinePassword->id, $master_id, $redefinePassword->token_confirmation_phone, $usr->phone, $usr->name)){
                return response()->json(array("error" => "Não foi possível enviar o token para redefinição de senha, por favor tente novamente"));
            }
            return response()->json(array("success" => "Redefinição de senha enviada com sucesso", "id" => $redefinePassword->id, "unique_id" => $redefinePassword->unique_id));

        } else {
            return response()->json(array("error" => "Não foi possível criar a redefinição de senha, por favor tente mais tarde"));
        }
    }

    protected function redefinePasswordConfirmPhoneToken(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'phone_token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->count() > 0){
            $redefinePassword = RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->first();
            
            if($redefinePassword->phone_token_attempt >= 3) {
                return response()->json(array("error" => "O token enviado para o celular foi informado incorretamente por 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if($redefinePassword->invalid == 1) {
                return response()->json(array("error" => "Requisição de alteração de senha inválida, os tokens de confirmações foram informados incorretamente por mais de 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefinePassword->created_at )->addMinutes(10))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Token expirado, por favor recomece o processo de redefinição de senha"));
            }

            if($redefinePassword->token_confirmation_phone != $request->phone_token){
                $redefinePassword->phone_token_attempt += 1;
                if($redefinePassword->phone_token_attempt >= 3) {
                    
                    $usr = User::where('id','=',$redefinePassword->user_id)->first();

                    $sendFailureAlert = new MovimentationFailureClass();
                    $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO';
                    $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
                    O token enviado para o celular foi informado incorretamente por 3 vezes. <br/><br/>
                    A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
                    Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
                    É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
                    ID da requisição '.$redefinePassword->id;
                    $sendFailureAlert->sendFailures();

                    $redefinePassword->invalid = 1;
                }
                $redefinePassword->save();
                return response()->json(array("error" => "Token informado não confere com o token enviado para o CELULAR"));
            } else {
                $redefinePassword->token_phone_confirmed = 1;
                if($redefinePassword->save()){
                    return response()->json(array("success" => "Token enviado para o CELULAR confirmado com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado para o CELULAR, por favor tente novamente"));
                }
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de senha não encontrada"));
        }
    }

    protected function sendRedefinePasswordEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [139];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!Auth::check()) {
            return response()->json(array("error" => "Usuário não autenticado"));
        }

        $user = Auth::user();
        $usr  = User::where('id','=',$user->id)->first();
        if( !Hash::check(base64_decode($request->password), $usr->password) ){
            return response()->json(array("error" => "Senha inválida"));
        }

        $permitted_strings           = '@!$%';
        $permitted_numbers           = '0123456789';
        $newPassword                 = str_shuffle(substr(str_shuffle($permitted_strings), 0, 2).substr(str_shuffle($permitted_numbers), 0, 4));
        if(User::where('id','=', $request->id)->where('unique_id','=',$request->unique_id)->count() == 0){
            return response()->json(array("error" => "Usuário não localizado"));
        }else{
            $user = User::where('id','=', $request->id)->where('unique_id','=',$request->unique_id)->first();
            $user->password             = Hash::make($newPassword);
            $user->email_verified_at    = null;
            $user->phone_verified_at    = null;
            $user->login_attempt        = 0;
            if(!$user->save()){
                return response()->json(array("error" => "Erro ao redefinir a senha"));
            }else{
                $message = "<table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                </tr>
                <tr>
                    <td>
                        <br>Olá $user->name,
                        <br><br>Visando a melhoria continua dos nossos processos de segurança da informação, é necessário redefinir a sua senha de acesso. Após acessar o sistema com a senha informada abaixo, realizaremos uma validação de token com o seu celular e e-mail, ao concluir a validação será possível utilizar sua conta normalmente.
                        <br><br><b>Não informe sua senha e nem os tokens de validação a ninguem</b>. Nossos colaboradores jamais solicitarão essa informação.
                        <br><br>Sua nova senha temporária de acesso é <b>$newPassword</b>
                        <br><br>Entre em https://ip4y.com.br e acesse sua conta para concluir este procedimento.
                        <br><br><br>Qualquer dúvida entre em contato com o suporte.
                    </td>
                </tr>
                </table>";

                $apiSendGrind = new ApiSendgrid();
                $apiSendGrind->to_email    = $user->email;
                $apiSendGrind->to_name     = $user->name;
                $apiSendGrind->to_cc_email = 'ragazzi@dinari.com.br';
                $apiSendGrind->to_cc_name  = 'Ragazzi';
                $apiSendGrind->subject     = 'Redefinição de senha iP4y';
                $apiSendGrind->content     = $message;

                if($apiSendGrind->sendSimpleEmail()){
                    User::revokeAccess($user->id);
                    return response()->json(array("success" => "Email de redefinição de senha enviado com sucesso para o usuário"));
                }else{
                    return response()->json(array("error" => "Não foi possível enviar o email de redefinição de senha para o usuário"));
                }
            }
        }
    }

    protected function sendWelcomeMail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [142];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $permitted_strings           = '@!$%';
        $permitted_numbers           = '0123456789';
        $newPassword                 = str_shuffle(substr(str_shuffle($permitted_strings), 0, 2).substr(str_shuffle($permitted_numbers), 0, 4));

        if(User::where('id','=', $request->id)->where('unique_id','=',$request->unique_id)->count() == 0){
            return response()->json(array("error" => "Usuário não localizado"));
        }else{
            $user = User::where('id','=', $request->id)->where('unique_id','=',$request->unique_id)->first();
            $user->password             = Hash::make($newPassword);
            $user->email_verified_at    = null;
            $user->phone_verified_at    = null;
            $user->login_attempt        = 0;
            $user->status               = 1;
            $user->welcome_mail_send_at = \Carbon\Carbon::now();
            if(!$user->save()){
                return response()->json(array("error" => "Erro ao redefinir a senha"));
            }else{
                
                if ($user_master = UserMaster::where('user_id','=',$user->id)->whereNull('deleted_at')->first()) {

                    $user_master->status_id = 1;
    
                    $user_master->save();
                }

                $message = "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Olá $user->name, Bem-vindo à plataforma digital iP4y.</font></p></td>
                    </tr>
                    <tr>
                        <td><p><font face='Arial'><br><br>Agora suas transações e movimentações financeiras ficaram mais fáceis e seguras! Informamos que sua conta já está aberta e terá o prazo de 30 dias para ativação e validação.</font></p></td>
                    </tr>
                    <tr>
                        <td>
                            <br>
                            <table border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                            ";
                            $color = 0;
                            $user->onlyActive = 1;
                            foreach($user->getUserAccounts() as $usr){
                            $bg = (($color%2)==0) ? "#f2f2f2" : "#d9d9d9";

                $message .="
                                <tr style='height:15.0pt'>
                                    <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Conta</font> </span> </b></td>
                                    <td  width='552' nowrap='' valign='bottom' style='width:414.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$usr->accnts_account_number - $usr->rgstr_dtls_name</font></span></td>
                                </tr>
                                ";
                             $color++;
                            }
                $message .="
                                <tr style='height:15.0pt'>
                                    <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>E-mail de validação</font> </span> </b></td>
                                    <td  width='552'   nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$user->email</font></span></td>
                                </tr>
                                <tr style='height:15.0pt'>
                                    <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Celular Token</font> </span></b></td>
                                    <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>".Facilites::mask_phone($user->phone)."</font></span></td>
                                </tr>
                                <tr style='height:15.0pt'>
                                    <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>CPF Usuário </font></span></b></td>
                                    <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>".Facilites::mask_cpf_cnpj($user->cpf_cnpj)."</font></span></td>
                                </tr>
                                <tr style='height:15.0pt'>
                                    <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Senha Provisória </font></span></b></td>
                                    <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$newPassword</font></span></td>
                                </tr>
                            </table>
                            <br><br>
                        </td>
                    </tr>
                    <tr>
                        <td><br><p><b><font face='Arial'>Para acessar a conta é muito fácil, siga as instruções abaixo para o primeiro acesso:</font></b></p></td>
                    </tr>
                    <tr>
                        <td><br><p><b><font face='Arial'>Primeiro Acesso.</font></b></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 1 – Acesse o site www.ip4y.com.br</font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 2 -  Clique em <b>Acesse sua Conta.</b></font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 3 -  Informe o CPF do usuário da conta informado neste e-mail no quadro acima.</font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 4 -  Informe a senha provisória também informada neste e-mail no quadro acima.</font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><b><font face='Arial'> No primeiro acesso é necessário validar o e-mail e o celular token. Assim que inserir a senha serão disparados dois códigos diferentes, um para o e-mail informado acima e outro via SMS para o celular token informado.</font></b></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 5 – Insira o código do SMS</font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 6 – Insira o código enviado por e-mail e clique em <b>Continuar.</b></font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 7 – Aceite o termo de uso e clique em <b>Continuar.</b></font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'>Passo 8 – Redefina sua senha pessoal</font></p></td>
                    </tr>
                    <tr>
                        <td><br><p><font face='Arial'><b>Sua conta digital já esta pronta para ser utilizada, qualquer dúvida sinta-se à vontade para entrar em contato através do e-mail faleconosco@ip4y.com.br ou pela central de atendimento no telefone (11)2229-8282. De Segunda a Sexta das 9h às 17:30h exceto feriados.</font></b></p></td>
                    </tr>
                </table>
                ";

                $apiSendGrid = new ApiSendgrid();
                $apiSendGrid->to_email    = $user->email;
                $apiSendGrid->to_name     = $user->name;
                $apiSendGrid->to_cc_email = 'ragazzi@dinari.com.br';
                $apiSendGrid->to_cc_name  = 'Ragazzi';
                $apiSendGrid->subject     = 'Bem-vindo à plataforma digital iP4y';
                $apiSendGrid->content     = $message;
            
                if($apiSendGrid->sendSimpleEmail()){
                    $managerDetails = ManagerDetail::getUserAccountManager($user->id);
                    foreach($managerDetails as $managerDetail){

                        $message2 = "
                            <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                                <tr>
                                    <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                                </tr>
                                <tr>
                                    <td><br><p><font face='Arial'>Olá $managerDetail->manager_name, o e-mail de boas vindas foi enviado para $user->name.</font></p></td>
                                </tr>
                                <tr>
                                    <td><p><font face='Arial'><br><br>Informamos que a conta já está aberta e terá o prazo de 30 dias para ativação e validação.</font></p></td>
                                </tr>
                                <tr>
                                    <td>
                                        <br>
                                        <table border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                                        ";
                                        $color = 0;
                                        foreach($user->getUserAccounts() as $usr){
                                        $bg = (($color%2)==0) ? "#f2f2f2" : "#d9d9d9";
                            $message2 .="
                                            <tr style='height:15.0pt'>
                                                <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Conta</font> </span> </b></td>
                                                <td  width='552' nowrap='' valign='bottom' style='width:414.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$usr->accnts_account_number - $usr->rgstr_dtls_name</font></span></td>
                                            </tr>
                                            ";
                                        $color++;
                                        }
                            $message2 .="
                                            <tr style='height:15.0pt'>
                                                <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>E-mail de validação</font> </span> </b></td>
                                                <td  width='552'   nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$user->email</font></span></td>
                                            </tr>
                                            <tr style='height:15.0pt'>
                                                <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Celular Token</font> </span></b></td>
                                                <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>".Facilites::mask_phone($user->phone)."</font></span></td>
                                            </tr>
                                            <tr style='height:15.0pt'>
                                                <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>CPF Usuário </font></span></b></td>
                                                <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>".Facilites::mask_cpf_cnpj($user->cpf_cnpj)."</font></span></td>
                                            </tr>
                                        </table>
                                        <br><br>
                                    </td>
                                </tr>
                            </table>
                        ";
                        if($managerDetail->manager_email !=''){

                            $apiSendGrindToManager = new ApiSendgrid();
                            $apiSendGrindToManager->to_email    = $managerDetail->manager_email;
                            $apiSendGrindToManager->to_name     = $managerDetail->manager_name;
                            $apiSendGrindToManager->to_cc_email = 'ragazzi@dinari.com.br';
                            $apiSendGrindToManager->to_cc_name  = 'Ragazzi';
                            $apiSendGrindToManager->subject     = 'Bem-vindo à plataforma digital iP4y';
                            $apiSendGrindToManager->content     = $message2;
                            $apiSendGrindToManager->sendSimpleEmail();
                        }
                    }

                    User::revokeAccess($user->id);

                    return response()->json(array("success" => "Email de ativação de conta enviado com sucesso para o usuário"));
                }else{
                    return response()->json(array("error" => "Não foi possível enviar o email de conta para o usuário"));
                }
            }

        }

    }

    protected function redefinePasswordConfirmEmailToken(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'email_token' => ['required', 'string', 'size:8'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->count() > 0){
            $redefinePassword = RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->first();

            if($redefinePassword->token_phone_confirmed == 0){
                return response()->json(array("error" => "É necessário confirmar o token enviado para o seu celular antes de continuar"));
            }

            if($redefinePassword->email_token_attempt >= 3) {
                return response()->json(array("error" => "O token enviado para o e-mail foi informado incorretamente por 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if($redefinePassword->invalid == 1) {
                return response()->json(array("error" => "Requisição de alteração de senha inválida, os tokens de confirmações foram informados incorretamente por mais de 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefinePassword->created_at )->addMinutes(10))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Token expirado, por favor recomece o processo de redefinição de senha"));
            }

            if($redefinePassword->token_confirmation_email != $request->email_token){
                $redefinePassword->email_token_attempt += 1;
                if($redefinePassword->email_token_attempt >= 3) {

                    $usr = User::where('id','=',$redefinePassword->user_id)->first();

                    $sendFailureAlert = new MovimentationFailureClass();
                    $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO';
                    $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
                    O token enviado para o e-mail foi informado incorretamente por 3 vezes. <br/><br/>
                    A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
                    Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
                    É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
                    ID da requisição '.$redefinePassword->id;
                    $sendFailureAlert->sendFailures();

                    $redefinePassword->invalid = 1;
                }
                $redefinePassword->save();
                return response()->json(array("error" => "Token informado não confere com o token enviado por E-MAIL"));
            } else {
                $redefinePassword->token_email_confirmed = 1;
                if($redefinePassword->save()){
                    return response()->json(array("success" => "Token enviado por E-MAIL confirmado com sucesso"));
                } else {
                    return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por E-MAIL, por favor tente novamente"));
                }
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de senha não encontrada"));
        }
    }

    protected function redefinePasswordUpdate(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'password' =>  ['required', 'string'],
            'password_confirm' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->count() > 0){
            $redefinePassword = RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->first();

            if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefinePassword->created_at )->addMinutes(15))->format('Y-m-d H:i:s'))) ){
                return response()->json(array("error" => "Requisição de alteração de senha expirada, por favor recomece o processo de redefinição de senha"));
            }

            if($redefinePassword->invalid == 1) {
                $usr = User::where('id','=',$redefinePassword->user_id)->first();

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO';
                $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
                Os tokens enviados para o celular e o e-mail foram informados incorretamente por 3 vezes. <br/><br/>
                A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
                Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
                É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
                ID da requisição '.$redefinePassword->id;
                $sendFailureAlert->sendFailures();

                return response()->json(array("error" => "Requisição de alteração de senha inválida, os tokens de confirmações foram informados incorretamente por mais de 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if($redefinePassword->token_phone_confirmed == 0 or $redefinePassword->token_email_confirmed == 0){

                $usr = User::where('id','=',$redefinePassword->user_id)->first();

                $sendFailureAlert = new MovimentationFailureClass();
                $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO';
                $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
                Tentativa de alteração de senha sem tokens enviados para celular ou e-mail estarem confirmados. <br/><br/>
                A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
                Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
                É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
                ID da requisição '.$redefinePassword->id;
                $sendFailureAlert->sendFailures();

                $redefinePassword->invalid = 1;
                $redefinePassword->save();

                return response()->json(array("error" => "É necessário confirmar os tokens enviados para o CELULAR e E-MAIL antes de redefinir a senha, por favor reinicie o processo de redefinição de senha."));
            }

            if($request->password != $request->password_confirm){
                return response()->json(array("error" => "Senhas não coincidem"));
            }

            if( (strlen(base64_decode($request->password)) != 6)  ){
                return response()->json(array("error" => "A nova senha deve conter 6 caracteres"));
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
                    } /*else {
                        if($lastNumber == (int) $pass){
                            return response()->json(array("error" => "A senha não pode conter caracteres iguais na sequência"));
                        }
                        if(($lastNumber + 1) == (int) $pass){
                            $passValidate = false;
                            return response()->json(array("error" => "A senha não pode conter números sequenciais crescentes (Como 123...)"));
                        }
                        if(($lastNumber - 1) == (int) $pass){
                            $passValidate = false;
                            return response()->json(array("error" => "A senha não pode conter números sequenciais decrescente (Como 321...)"));
                        }
                    }*/
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

            if(User::where('id','=',$redefinePassword->user_id)->count() > 0 ){
                $usr = User::where('id','=',$redefinePassword->user_id)->first();

                if($usr->status == 3){
                    return response()->json(array("error" => "Poxa, não foi possível redefinir sua senha, em caso de dúvidas, por favor entre em contato com nossa equipe de suporte"));
                }

                $usr->password      = Hash::make(base64_decode($request->password));
                $usr->status        = 1;
                $usr->login_attempt = 0;
                if($usr->save()){
                    $redefinePassword->finished = 1;
                    $redefinePassword->save();
                    return response()->json(array("success" => "Senha redefinida com sucesso"));
                } else {
                    return response()->json(array("error" => "Não foi possível redifinir a senha, por favor tente novamente"));
                }
            } else {
                return response()->json(array("error" => "Usuário não encontrado, por favor tente novamente"));
            }
        } else {
            return response()->json(array("error" => "Requisição de redefinição de senha não encontrada, por favor tente novamente"));
        }
    }
  
 

    protected function updateEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [131];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(User::where("id","=",$request->id)->where("unique_id","=",$request->unique_id)->count() == 0){
            return response()->json(array("error" => "Usuario não localizado"));
        }else{
            if(User::where("email","=",$request->email)->count() > 0){
                return response()->json(array("error" => "E-Mail cadastrado para outro usuário"));
            }else{
                $usr_email = User::where('id', $request->id)->where("unique_id","=",$request->unique_id)->first();
                $usr_email->email             = $request->email;
                $usr_email->email_verified_at = null;
                $usr_email->phone_verified_at = null;
                if($usr_email->save()){
                    return response()->json(array("success" => "E-Mail alterado com sucesso, o usuário precisa validar o e-mail e o celular novamente"));
                } else {
                    return response()->json(array("error" => "Erro ao alterar o e-mail, por favor tente novamente mais tarde"));
                }
            }
        }
    }

    protected function updatePhone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [134];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message, "has_permission" => $checkAccount->has_permission));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(User::where("id","=",$request->id)->where("unique_id","=",$request->unique_id)->count() == 0){
            return response()->json(array("error" => "Usuario não localizado"));
        }else{
            if(User::where("phone","=", preg_replace('/[^0-9]/', '', $request->phone))->count() > 0){
                return response()->json(array("error" => "Celular cadastrado para outro usuário"));
            }else{
                $usr_phone = User::where('id', $request->id)->where("unique_id","=",$request->unique_id)->first();
                $usr_phone->phone             =  preg_replace( '/[^0-9]/', '', $request->phone);
                $usr_phone->email_verified_at = null;
                $usr_phone->phone_verified_at = null;
                if($usr_phone->save()){
                    return response()->json(array("success" => "Celular alterado com sucesso, o usuário precisa validar o e-mail e o celular novamente"));
                } else {
                    return response()->json(array("error" => "Erro ao alterar o celular, por favor tente novamente mais tarde"));
                }
            }
        }
    }

    public function returnUser($usr_cpf_cnpj, $user_name, $user_alias, $user_email, $user_password, $user_phone)
    {
        $facilities           = new Facilites();
        $cpf_cnpj             = preg_replace( '/[^0-9]/', '', $usr_cpf_cnpj);
        $facilities->cpf_cnpj = $cpf_cnpj;
        if(strlen($cpf_cnpj) == 11) {
            if( !  $facilities->validateCPF($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CPF inválido");
            }
        } else if(strlen($cpf_cnpj) == 14){
            if( ! $facilities->validateCNPJ($cpf_cnpj) ){
                return (object) array("status" => 0, "error" => "CNPJ inválido");
            }
        } else {
            return (object) array("status" => 0, "error" => "CPF ou CNPJ inválido");
        }

        if($user_password == "" or $user_password == null){
            $user_password = Hash::make(substr(md5(mt_rand()), 0, 6));
        } else {
            $user_password = Hash::make(base64_decode($user_password));
        }

        if( User::where('cpf_cnpj','=',$cpf_cnpj )->count() == 0 ){
            if( User::where('email','=',$user_email )->count() == 0 ) {
                if($user = User::create([
                    'name'          => $user_name,
                    'alias'         => $user_alias,
                    'email'         => $user_email,
                    'phone'         => $user_phone,
                    'password'      => $user_password,
                    'cpf_cnpj'      => $cpf_cnpj,
                    'status'        => 3,
                    'login_attempt' => 0,
                    'created_at'    => \Carbon\Carbon::now()
                ])){
                    $user->api_token = $user->createToken('DigitalAccountToken')->accessToken;
                    $user->unique_id = md5($user->id.$user->cpf_cnpj.time());
                    $user->save();
                    return (object) array("status" => 1, "success" => $user);
                } else {
                    return (object) array("status" => 0, "error" => "Ocorreu um erro ao cadastrar o usuário");
                }
            } else {
                return (object) array("status" => 0, "error" => "O E-Mail informado já está cadastrado para um usuário do sistema, por favor informe outro e-mail para o usuário que deseja cadastrar. Em caso de duvidas entre em contato com o suporte");
            }
        } else {
            $user = User::where('cpf_cnpj','=',$cpf_cnpj )->first();
            return (object) array("status" => 2, "success" => $user);
        }
    }

    public function sendEmailToken($id, $masterId, $token_confirmation_email, $email, $name)
    {
        $message = "Olá $name, <br>O token ".substr($token_confirmation_email,0,4)."-".substr($token_confirmation_email,4,4)." foi gerado para a verificação de redefinição da sua senha da conta digital. Caso não tenha solicitado uma redefinição, desconsidere este e-mail.";
       
        $apiSendGrid = new ApiSendgrid();
        $apiSendGrid->to_email     = $email;
        $apiSendGrid->to_name      = $name;
        $apiSendGrid->subject      = 'Token para redefinição de senha';
        $apiSendGrid->to_bcc_email = 'ragazzi@dinari.com.br';
        $apiSendGrid->to_bcc_name  = 'Ragazzi';
        $apiSendGrid->content      = $message;

        if($apiSendGrid->sendMail()){
           return true;
        } else {
           return false;
        }
    }

    public function sendPhoneToken($id, $masterId, $token_confirmation_phone, $phone, $name)
    {
        $message = "Olá $name, o token ".substr($token_confirmation_phone,0,4)."-".substr($token_confirmation_phone,4,4)." foi gerado para a verificação de redefinição da sua senha da conta digital.";

        $sendSMS = SendSms::create([
            'external_id' => ("4".(\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('YmdHisu').$id),
            'to'          => "55".$phone,
            'message'     => $message,
            'type_id'     => 4,
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

    public function sendTokenViaWhatsapp(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(! $redefinePassword = RedefinePassword::where('id', '=', $request->id)->where('unique_id', '=', $request->unique_id)->where('finished', '=', 0)->first() ) {
            return response()->json(array("error" => "Redefinição de senha não econtrada"));
        }        

        if((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($redefinePassword->created_at)) <= 60){
            return response()->json(array("error" => "Por favor aguarde 1 minuto para gerar um novo token de confirmação."));
        }
        
        if((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($redefinePassword->updated_at)) <= 60){
            return response()->json(array("error" => "Por favor aguarde 1 minuto para gerar um novo token de confirmação."));
        }

        if( (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s') > (\Carbon\Carbon::parse((\Carbon\Carbon::parse( $redefinePassword->created_at )->addMinutes(15))->format('Y-m-d H:i:s'))) ){
            return response()->json(array("error" => "Requisição de alteração de senha expirada, por favor recomece o processo de redefinição de senha"));
        }

        $usr = User::where('id','=',$redefinePassword->user_id)->first();

        if($redefinePassword->invalid == 1) {
            $sendFailureAlert = new MovimentationFailureClass();
            $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO';
            $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
            Os tokens enviados para o celular e o e-mail foram informados incorretamente por 3 vezes. E houve a tentativa de reenviar o token por WhatsApp. <br/><br/>
            A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
            Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
            É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
            ID da requisição '.$redefinePassword->id;
            $sendFailureAlert->sendFailures();
            return response()->json(array("error" => "Requisição de alteração de senha inválida, os tokens de confirmações foram informados incorretamente por mais de 3 vezes, por favor reinicie o processo de redefinição de senha."));
        }

        if($usr->cpf_cnpj != (preg_replace( '/[^0-9]/', '', $request->cpf_cnpj))) {
            $sendFailureAlert = new MovimentationFailureClass();
            $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO - MANIPULAÇÃO DE REQUISIÇÃO';
            $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
            Os dados para alteração são inconsistentes, o CPF do usuário não condiz com o CPF de verificação da requisição ('.$request->cpf_cnpj.'). <br/><br/>
            A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
            Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
            É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
            ID da requisição '.$redefinePassword->id;
            $sendFailureAlert->sendFailures();

            $redefinePassword->invalid = 1;
            $redefinePassword->save();

            return response()->json(array("error" => "Poxa, não foi possível enviar o token de redefinição da sua senha, em caso de dúvidas, por favor entre em contato com nossa equipe de suporte",  "recaptcha_error" => false));

        }
        
        if($usr->status == 3){
            return response()->json(array("error" => "Poxa, não foi possível enviar o token de redefinição da sua senha, em caso de dúvidas, por favor entre em contato com nossa equipe de suporte",  "recaptcha_error" => false));
        }

        $token = new Facilites();
        $redefinePassword->token_confirmation_phone = $token->createApprovalToken();
        $redefinePassword->save();

        $apiZenviaWhats = new ApiZenviaWhatsapp();
        $apiZenviaWhats->to_number = '55'.$usr->phone;
        $apiZenviaWhats->token     = "*".substr($redefinePassword->token_confirmation_phone,0,4)."-".substr($redefinePassword->token_confirmation_phone,4,4)."*";
        if(isset( $apiZenviaWhats->sendToken()->success ) ){
            return response()->json(array("success" => "Token enviado por WhatsApp, a partir de agora você tem 5 minutos para utilizá-lo, se necessário repita o procedimento para gerar outro token", "id" => $redefinePassword->id, "unique_id" => $redefinePassword->unique_id));
        }
    }

    public function sendRedefinePasswordEmailAllUser()
    {
        /*
        $apiSendGrid = new ApiSendgrid();
        $apiSendGrid->sendEmailWithAttachments();

        
        $users = User::whereNull('deleted_at')->where('updated_at','<=','2020-10-27 21:41:15.470')->get();
        foreach($users as $user){
            $permitted_strings           = '@!$%';
            $permitted_numbers           = '0123456789';
            $newPassword                 = str_shuffle(substr(str_shuffle($permitted_strings), 0, 2).substr(str_shuffle($permitted_numbers), 0, 4));
            $usr                         = User::where('id','=', $user->id)->first();
            $usr->password               = Hash::make($newPassword);
            $usr->email_verified_at      = null;
            $usr->phone_verified_at      = null;
            if($usr->save()){
                $message = "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td><img src='https://conta.dinari.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                </tr>
                <tr>
                    <td>
                        <br>Olá $usr->name,
                        <br><br>Visando a melhoria continua dos nossos processos de segurança da informação, é necessário redefinir a sua senha de acesso. Após acessar o sistema com a senha informada abaixo, realizaremos uma validação de token com o seu celular e e-mail, ao concluir a validação será possível utilizar sua conta normalmente.
                        <br><br><b>Não informe sua senha e nem os tokens de validação a ninguem</b>. Nossos colaboradores jamais solicitarão essa informação.
                        <br><br>Sua nova senha temporária de acesso é <b>$newPassword</b>
                        <br><br>Entre em https://dinari.com.br e acesse sua conta para concluir este procedimento.
                        <br><br><br>Qualquer dúvida entre em contato com o suporte.
                    </td>
                </tr>
                </table>
                ";
                $sendMail = new sendMail();
                $sendMail->to_mail      = $usr->email;
                $sendMail->to_name      = $usr->name;
                $sendMail->send_cc      = 0;
                $sendMail->to_cc_mail   = '';
                $sendMail->to_cc_name   = '';
                $sendMail->send_cco     = 1;
                $sendMail->to_cco_mail  = 'ragazzi@dinari.com.br';
                $sendMail->to_cco_name  = 'Ragazzi';
                $sendMail->attach_pdf   = 0;
                $sendMail->attach_path  = '';
                $sendMail->attach_file  = '';
                $sendMail->subject      = 'Redefinição de senha';
                $sendMail->email_layout = 'emails/confirmEmailAccount';
                $sendMail->bodyMessage  = $message;
                $sendMail->send();
            }
        } */
    }

    public function createUserByRegister(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [130];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerService = new RegisterService();
        $registerService->register_master_id = $request->register_master_id;
        $createUser = $registerService->createUserByRegister();
        if($createUser->success){
            return response()->json(array("success" => $createUser->message));
        } else {
            return response()->json(array("error" => $createUser->message));
        }
    }

    public function sendSimpleEmail()
    {
        $apiSendGrid = new ApiSendgrid();
        $users = User::where('status','=',1)->get();
        foreach($users as $user){
            $apiSendGrid->email = $user->email;
            $apiSendGrid->name = strtoupper($user->name);
            $apiSendGrid->sendTemplateEmail();
        }
        return response()->json(["success"=>true]);
    }

    protected function block(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$user = User::where('id','=',$request->user_id)->where('status','=',1)->whereNull('deleted_at')->first()) {
            return response()->json(array("error"=>"Usuário não localizado"));
        }

        if ($user->status == 3) {
            return response()->json(array("error"=>"Usuário já se encontra bloqueado"));
        }

        $user->status = 3;

        if ($user->save()) {

            if ($user_master = UserMaster::where('user_id','=',$user->id)->whereNull('deleted_at')->first()) {

                $user_master->status_id = 3;

                if ($user_master->save()) {

                    DB::table("oauth_access_tokens")->where('user_id','=',$user->id)->delete();

                    return response()->json(array("success"=>"Usuário bloqueado com sucesso"));
                }
            }
        }
        return response()->json(array("error"=>"Poxa, usuário informado esta incorreto ou foi bloqueado posteriormente, por favor, tente mais tarde"));
    }

    protected function unblock(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [130];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        if (!$user = User::where('id','=',$request->user_id)->where('status','=',3)->whereNull('deleted_at')->first()) {
            return response()->json(array("error"=>"Usuário não localizado"));
        }

        if ($user->status == 1) {
            return response()->json(array("error"=>"Usuário já se encontra desbloqueado"));
        }

        $user->status = 1;

        if ($user->save()) {

            if (!$user_master = UserMaster::where('user_id','=',$user->id)->where('status_id','=',3)->whereNull('deleted_at')->first()) {
                return response()->json(array("error"=>"Usuário não localizado"));
            }

            $user_master->status_id = 1;

            if ($user_master->save()) {
                return response()->json(array("success"=>"Usuário desbloqueado com sucesso"));
            }
        }
        return response()->json(array("error"=>"Poxa, usuário informado esta incorreto ou foi desbloqueado posteriormente, por favor, tente mais tarde"));

    }

    protected function checkSmsValidateUser(Request $request): mixed
    {

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'unique_id' => ['required', 'string'],
            'phone_token' => ['required', 'string', 'size:8']
        ], [
            'id.required' => 'O campo ID é obrigatório.',
            'id.integer' => 'O campo ID deve ser um número inteiro.',
            'unique_id.required' => 'O campo unique_id é obrigatório.',
            'unique_id.string' => 'O campo unique_id deve ser uma string.',
            'phone_token.required' => 'O envio do token é obrigatório.',
            'phone_token.string' => 'O campo phone_token deve ser uma string.',
            'phone_token.size' => 'O token deve ter exatamente 8 caracteres.'
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if(RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->count() > 0){
            $redefinePassword = RedefinePassword::where('id','=',$request->id)->where('unique_id','=',$request->unique_id)->where('finished', '=', 0)->first();
            
            if($redefinePassword->phone_token_attempt >= 3) {
                return response()->json(array("error" => "O token enviado para o celular foi informado incorretamente por 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if($redefinePassword->invalid == 1) {
                return response()->json(array("error" => "Requisição de alteração de senha inválida, os tokens de confirmações foram informados incorretamente por mais de 3 vezes, por favor reinicie o processo de redefinição de senha."));
            }

            if (Carbon::now()->format('Y-m-d H:i:s') > Carbon::parse($redefinePassword->created_at)->addMinutes(10)->format('Y-m-d H:i:s')) {
                return response()->json(["error" => "Token expirado, por favor recomece o processo de redefinição de senha"]);
            }

            $usr = User::where('id','=',$redefinePassword->user_id)->first();
            if($usr->validated_user != 1){
                return response()->json(array("error" => "Requisição de alteração de senha inválida."));
            }

            if($redefinePassword->token_confirmation_phone != $request->phone_token){
                $redefinePassword->phone_token_attempt += 1;
                if($redefinePassword->phone_token_attempt >= 3) {
                    $sendFailureAlert = new MovimentationFailureClass();
                    $sendFailureAlert->title = 'TENTATIVA DE ALTERAÇÃO DE SENHA SEM SUCESSO';
                    $sendFailureAlert->errorMessage = 'Atenção, houve uma <strong>tentativa</strong> de alteração de senha do usuário '.$usr->name.', CPF: '.$usr->cpf_cnpj.'<br/>
                    O token enviado para o celular foi informado incorretamente por 3 vezes. <br/><br/>
                    A tentativa de alteração de senha se originou no IP: '.$redefinePassword->ip.'<br/>
                    Este alerta se originou no IP: '.$request->header('ip').'<br/><br/>
                    É aconselhavel bloquear o usuário caso as tentativas continuem, além disso, também é aconselhavel realizar o bloqueio desses IPs <br/><br/>
                    ID da requisição '.$redefinePassword->id;
                    $sendFailureAlert->sendFailures();

                    $redefinePassword->invalid = 1;
                }
                $redefinePassword->save();
                
                return response()->json(array("error" => "Token informado não confere com o token enviado para o CELULAR"));
            } 
            
            $redefinePassword->token_phone_confirmed = 1;
            $redefinePassword->token_email_confirmed = 1;

            if($redefinePassword->save()){
                $usr->email_verified_at = Carbon::now();
                $usr->phone_verified_at = Carbon::now();
                $usr->save();

                return response()->json(array("success" => "Token enviado para o CELULAR confirmado com sucesso"));
            }
            return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado para o CELULAR, por favor tente novamente"));
        }
        
        return response()->json(array("error" => "Requisição de redefinição de senha não encontrada"));
    }
}

