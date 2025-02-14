<?php

namespace App\Http\Controllers;

use App\Actions\Setup\SetupAccountAction;
use App\Libraries\AmazonS3;
use App\Libraries\ApiZenviaSMS;
use App\Libraries\ApiZenviaWhatsapp;
use App\Libraries\Facilites;
use App\Libraries\sendMail;
use App\Libraries\SimpleZip;
use App\Libraries\ApiGoogle;
use App\Models\ApiConfig;
use App\Models\DocumentType;
use App\Models\Document;
use App\Models\Gender;
use App\Models\LegalNature;
use App\Models\RegisterRequest;
use App\Models\RegisterRequestRepresentativesPermissions;
use App\Models\SendSms;
use App\Models\SystemFunctionMaster;
use App\Models\ManagerDetail;
use App\Models\RegisterRequestRepresentative;
use App\Models\RegisterDataPj;
use App\Models\RegisterAddress;
use App\Models\RegisterDetail;
use App\Models\PjPartner;
use App\Models\Permission;
use App\Services\Account\AccountRelationshipCheckService;
use App\Services\Account\AccountService;
use App\Services\ProcessDialingService;
use App\Services\Register\RegisterService;
use App\Services\User\UserRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Libraries\ApiSendgrid;
use App\Models\RegisterRequestAccountType;
use App\Models\RegisterRequestBillingPlan;
use App\Models\RegisterRequestBusinessLine;
use App\Models\User;
use App\Models\UserRelationship;
use App\Classes\RegisterRequest\RegisterRequestsOrRepresentativeLogsClass;
use App\Classes\Encrypt\CryptographyHelper;
use App\Classes\BancoRendimento\IndirectPix\Key\IncludePixKeyClass;
use App\Models\Account;
use App\Models\Manager;
use App\Models\Register;
use App\Models\RegisterMaster;
use App\Models\RegisterRequestRepresentativesMatches;
use App\Models\UserMaster;
use App\Models\CelcoinAccount;
use App\Models\UsrRltnshpPrmssn;
use App\Models\PayrollEmployee;
use App\Models\PayrollEmployeeDetail;
use App\Models\ManagersRegister;
use App\Services\RegisterRequestService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

use function PHPUnit\Framework\isNull;

class RegisterRequestController extends Controller
{
    protected $registerRequestService;

    public function __construct(RegisterRequestService $registerRequestService)
    {
        $this->registerRequestService = $registerRequestService;
    }
    public function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerRequest = new RegisterRequest();
        $registerRequest->id = $request->id;
        $registerRequest->uuid = $request->uuid;
        $registerRequest->status_id_in = $request->status_id;
        $registerRequest->cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj);
        $registerRequest->document_type_id = $request->document_type_id;
        $registerRequest->legal_nature_id = $request->legal_nature_id;
        $registerRequest->representative_document_type_id = $request->representative_document_type_id;
        $registerRequest->register_request_type_id = $request->register_request_type_id;
        $registerRequest->approved_by = $request->approved_by;
        $registerRequest->analyzed_by = $request->analyzed_by;
        $registerRequest->only_active = $request->onlyActive;
        $registerRequest->name = $request->name;
        $registerRequest->created_at_start = $request->created_at_start;
        $registerRequest->created_at_end = $request->created_at_end;
        $registerRequest->action_status_id = $request->action_status_id;
        return response()->json($registerRequest->get());
    }

    public function getRegisters(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $registerRequest = new RegisterRequest();
        return response()->json($registerRequest->getRegisters());
    }

    /*public function new(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recaptcha_response'       => ['required', 'string'],
            'register_request_type_id' => ['required', 'integer']
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        $apiGoogle = new ApiGoogle();
        $apiGoogle->secret      = '6Lei3ekUAAAAADAxd1BowsNIqPhOY2A3rb6UHWSW';
        $apiGoogle->response    = $request->recaptcha_response;
        $apiGoogle->response_ip = $request->header('ip');
        $checkRecaptcha         = $apiGoogle->checkRecaptcha();

        if (!$checkRecaptcha->body->success) {
            return response()->json(array("error" => "reCaptcha Inválido, por favor selecione e preencha o reCaptcha corretamente", "recaptcha_error" => true));
        }

        if ($registerRequest = RegisterRequest::create([
            'uuid'                      => Str::orderedUuid(),
            'register_request_type_id'  => $request->register_request_type_id,
            'status_id'                 => 4,
            'ip'                        => $request->header('ip'),
            'latitude'                  => $request->latitude,
            'longitude'                 => $request->longitude,
            'accuracy'                  => $request->accuracy,
            'altitude'                  => $request->altitude,
            'altitude_accuracy'         => $request->altitude_accuracy,
            'heading'                   => $request->heading,
            'speed'                     => $request->speed,
            'geo_location_type_id'      => $request->geo_location_type_id,
            'width'                     => $request->width,
            'height'                    => $request->height,
            'browser_id'                => $request->browser_id,
            'created_at'                => \Carbon\Carbon::now()
        ])) {
            return response()->json(
                array(
                    "success" => "Tipo de cadastro definido com sucesso",
                    "data" =>
                    array(
                        "id" => $registerRequest->id,
                        "uuid" => $registerRequest->uuid
                    )
                )
            );
        }
        return response()->json(array("error" => "Não foi possível definir o tipo de cadastro, por favor tente novamente mais tarde."));
    } */

    public function EnumStatusId($nomeStatus)
    {

        switch ($nomeStatus) {
            case 'emAndamento':
                $status_id = 4;
                break;
            case 'pendente': //agência
                $status_id = 6;
                break;
            case 'aprovado':
                $status_id = 9;
                break;
            case 'naoAprovado': //agência
                $status_id = 49;
                break;
            case 'emAnalise':
                $status_id = 55;
                break;
            default:
                break;
        }

        return $status_id;
    }

    public function EnumFaseId($nomeStatus)
    {

        switch ($nomeStatus) {
            case 'fase1':
                $status_id = 1;
                break;
            case 'fase2':
                $status_id = 2;
                break;
            case 'aprovada':
                $status_id = 3;
                break;
            case 'recusada':
                $status_id = 4;
                break;
            default:
                break;
        }

        return $status_id;
    }

    public function EnumStatusAcaoId($nomeStatus)
    {

        switch ($nomeStatus) {
            case 'aguardandoCliente':
                $status_id = 1;
                break;
            case 'aguardandoCompliance':
                $status_id = 2;
                break;
            case 'concluido':
                $status_id = 3;
                break;
            default:
                break;
        }

        return $status_id;
    }

    public function analyzeRegisterRequest(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('aprovado')) {
            return response()->json(array("error" => "Não é possível modificar quem fez a análise uma vez que a solicitação que já foi aprovada."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível modificar quem fez a análise uma vez que a solicitação que já foi recusada."));
        }


        $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
        $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('analyzed_by', $registerRequest->analyzed_by, Auth::user()->id, $request->id, null);

        if (!$response->success) {
            Log::debug([
                "type"            => "Register Request Log",
                "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                "method"          => "POST",
                "status_code"     => $response->status_code,
                "response"        => $response->message
            ]);
        }


        $registerRequest->analyzed_by = Auth::user()->id;
        $registerRequest->status_id = $this->EnumStatusId('emAnalise');
        if ($registerRequest->save()) {
            return response()->json(array("success" => "Usuário atribuído à análise do cadastro com sucesso."));
        }
        return response()->json(array("error" => "Não é possível modificar quem fez a análise uma vez que a solicitação que já foi recusada."));
    }

    public function getUsersRegisterRequest(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $ag_usr_rlts = new UserRelationship();
        $ag_usr_rlts->relationship_id = 2;
        return response()->json($ag_usr_rlts->getUserRelationshipForSelect());
    }

    public function checkIfExistsAnalyst(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                                       => ['nullable', 'integer'],
            'uuid'                                     => ['nullable', 'string'],
            'register_request_representative_id'       => ['nullable', 'integer'],
            'register_request_representative_uuid'     => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (isset($request->id)) {

                if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
                }
            } else if (isset($request->register_request_representative_id)) {

                $registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->register_request_representative_id)->where('uuid', '=', $request->register_request_representative_uuid)->whereNull('deleted_at')->first();

                if (!$registerRequestRepresentative) {
                    return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
                }

                $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já finalizada, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->analyzed_by == null) {
            return response()->json(array("error" => "Para a alteração dos dados da solicitação de cadastro, é preciso que a análise seja atribuída primeiramente."));
        }

        return response()->json(array("success" => true));
    }

    public function checkContactDataExists(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'cpf'   => ['required', 'string'],
            'email' => ['required', 'string'],
            'phone'   => ['required', 'string']
        ], [
            'cpf.required' => 'CPF não informado',
            'email.required' => 'E-mail não informado',
            'phone.required' => 'Telefone não informado',
        ]);
        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()), 400);
        }


        $cpf = preg_replace('/[^0-9]/', '', $request->cpf);
        $facilites = new Facilites();
        $facilites->cpf_cnpj = $cpf;

        if (!$facilites->validateCPF($cpf)) {
            return array("error" => "CPF inválido.");
        }

        if (!Facilites::validatePhone($request->phone)) {
            return array("error" => 'Telefone inválido.');
        }

        $userEmail = User::where('email', '=', $request->email)->where('cpf_cnpj', '<>', $cpf)->whereNull('deleted_at')->first();
        $userPhone = User::where('phone', '=', $request->phone)->where('cpf_cnpj', '<>', $cpf)->whereNull('deleted_at')->first();
        if (isset($userEmail) || isset($userPhone)) {
            return false;
        }

        return true;
    }

    public function createDinamicalRegisterRequest(Request $request)
    {
        $companyData = $request->json('company');

        if (RegisterRequest::where('uuid_company_cad', '=',  $companyData['uuid'])->whereNull('deleted_at')->count() > 0) { //se já tiver registro com o msm uuid

            Log::debug('Registro de Cadastro - Existente');
            Log::debug(json_encode($request->all()));

            $registerRequest = RegisterRequest::where('uuid_company_cad', '=',  $companyData['uuid'])->whereNull('deleted_at')->first();
            //continua com a atribuição dos dados. entrará aqui caso haja algum problema ao popular os dados devido alguma instabilidade, erro, ou o cara terminou de preencher alguns dados depois (tipo validação de usuário)
            $setData = $this->setRegisterRequestData($registerRequest, $request);
            return response()->json($setData);
        }

        Log::debug('Registro de Cadastro - novo');
        Log::debug(json_encode($request->all()));

        //caso ainda não exista essa solicitação de cadastro, cria-se uma
        if (!$registerRequest = RegisterRequest::create([
            'uuid'                      => Str::orderedUuid(),
            'register_request_type_id'  => 2, //conta PJ
            'status_id'                 => 6,
            'send_at'                   => \Carbon\Carbon::now(),
            'created_at'                => \Carbon\Carbon::parse($companyData['created_at'])->format('Y-m-d H:i:s'),
            'uuid_company_cad'          => $companyData['uuid']
        ])) {
            return response()->json(array("error" => "Não foi possível criar o cadastro, por favor tente novamente mais tarde."));
        }

        $data = json_decode($request->getContent(), true);

        $keyMappings = [
            'name' => 'Nome',
            'surname' => 'Sobrenome',
            'cpf' => 'CPF',
            'company_name' => 'Nome da Empresa',
            'cnpj' => 'CNPJ'
        ];

        $tableStyle = 'border-collapse: collapse; width: 80%; margin-bottom: 20px;';
        $headerCellStyle = 'border: 1px solid #ddd; padding: 8px; background-color: #f9f9f9; font-weight: bold;';
        $cellStyle = 'border: 1px solid #ddd; padding: 8px;';


        $html = '';


        $html .= '<h2>Dados do Solicitante</h2>';
        $html .= '<table style="' . $tableStyle . '">';
        foreach (['name', 'surname'] as $key) {
            if (isset($data['company'][$key])) {
                $html .= '<tr>';
                $html .= '<td style="' . $headerCellStyle . '">' . $keyMappings[$key] . '</td>';
                $html .= '<td style="' . $cellStyle . '">' . $data['company'][$key] . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table>';


        $html .= '<h2>Dados da Empresa</h2>';
        $html .= '<table style="' . $tableStyle . '">';
        foreach (['company_name', 'cnpj'] as $key) {
            if (isset($data['company'][$key])) {
                $html .= '<tr>';
                $html .= '<td style="' . $headerCellStyle . '">' . $keyMappings[$key] . '</td>';
                $html .= '<td style="' . $cellStyle . '">' . $data['company'][$key] . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table>';

        // Criar tabelas para os parceiros ou usuários
        $html .= '<h2>Sócios e Usuários</h2>';

        foreach ($data['partners_or_users'] as $index => $partnerOrUser) {
            $html .= '<h3>Sócio ou Usuário ' . ($index + 1) . '</h3>';
            $html .= '<table style="' . $tableStyle . '">';
            foreach ($partnerOrUser as $key => $value) {
                if (array_key_exists($key, $keyMappings)) {
                    $html .= '<tr>';
                    $html .= '<td style="' . $headerCellStyle . '">' . $keyMappings[$key] . '</td>';
                    $html .= '<td style="' . $cellStyle . '">' . $value . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</table>';
        }

        $apiSendGrid = new ApiSendgrid();
        $apiSendGrid->to_email    = config('mail.innternal_emails.compliance');
        $apiSendGrid->to_name     = 'Compliance';
        $apiSendGrid->to_cc_email = config('mail.innternal_emails.another_team');
        $apiSendGrid->to_cc_name  = 'Ragazzi';
        $apiSendGrid->subject     = 'Nova Solicitação Web / Cadastro';
        $apiSendGrid->content     = $html;
        $apiSendGrid->sendSimpleEmail();

        $setData = $this->setRegisterRequestData($registerRequest, $request);
        return response()->json($setData);
    }

    public function setRegisterRequestData($registerRequest, $request)
    {

        //preenche os dados da empresa
        $companyData = $request->get('company');

        $cnpj_cpf = preg_replace('/[^0-9]/', '', $companyData['cnpj']);
        $facilites = new Facilites();
        $facilites->cpf_cnpj = $cnpj_cpf;

        if (!$facilites->validateCpfCnpj($cnpj_cpf)) {
            return array(["success" => false, "error" => "CNPJ/CPF inválido."]);
        }

        $registerRequest->name                           = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($companyData['company_name']));
        $registerRequest->cpf_cnpj                       = $cnpj_cpf;
        $registerRequest->email                          = mb_strtolower($companyData['email']);
        $registerRequest->phone                          = preg_replace('/[^0-9]/', '', $companyData['phone']);
        $registerRequest->representative_name            = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($companyData['name']));
        $registerRequest->representative_surname         = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($companyData['surname']));
        $registerRequest->partners_quantity              = $companyData['partners_quantity'];
        $registerRequest->employee_quantity              = $companyData['employee_quantity'];
        $registerRequest->site                           = mb_strtolower($companyData['site']);
        $registerRequest->account_type_id                = $companyData['account_type_id'];
        $registerRequest->business_line_id               = $companyData['business_line_id'];
        $registerRequest->billing_plan_id                = $companyData['billing_plan_id'];

        if (isset($companyData['indication_code'])) {
            $registerRequest->indication_code = Facilites::removeAccentSpecialCharactersSpace($companyData['indication_code']);
        } else {
            $registerRequest->indication_code = null;
        }

        if (isset($companyData['invoicing_value'])) {
            $registerRequest->income = $companyData['invoicing_value'];
        } else {
            return array("error" => "Não é possível salvar os dados da solicitação de cadastro sem o valor do faturamento.");
        }

        if (isset($companyData['birth_date'])) {
            $registerRequest->birth_date = \Carbon\Carbon::parse($companyData['birth_date'])->format('Y-m-d');
        } else {
            $registerRequest->birth_date = null;
        }

        //mapa ligando as naturezas jurídicas da solicitação de cadastro com oq temos hj na conta digital
        switch ($companyData['legal_nature_id']) {
            case '1':
                $legal_nature = 6;
                break;
            case '2':
                $legal_nature = 3;
                break;
            case '3':
                $legal_nature = 5;
                break;
            case '4':
                $legal_nature = 1;
                break;
            case '5':
                $legal_nature = 8;
                break;
            case '6':
                $legal_nature = 2;
                break;
            default:
                return array("error" => "O tipo de natureza jurídica definida está incorreto para essa ação, por favor tente novamente mais tarde.");
        }

        $registerRequest->legal_nature_id                = $legal_nature;


        //endereço
        $address                                         = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($companyData['address']));
        $addressArray                                    = explode(' ', $address, 2);
        if (count($addressArray) >= 2) {
            $registerRequest->public_place               = trim($addressArray[0], " ");
            $registerRequest->address                    = trim($addressArray[1], " ");
        } else {
            $registerRequest->address                    = $address;
        }

        $registerRequest->zip_code                       = preg_replace('/[^0-9]/', '', $companyData['address_zip_code']);
        $registerRequest->number                         = preg_replace('/[^0-9]/', '', $companyData['address_number']);
        $registerRequest->complement                     = mb_strtoupper($companyData['address_complement']);
        $registerRequest->district                       = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($companyData['address_district']));
        $registerRequest->city                           = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($companyData['address_city']));
        $registerRequest->state_id                       = $companyData['address_state_id'];


        //docs
        $registerRequest->billing_statement_s3_filename  = $companyData['billing_statement_s3_filename'];
        $registerRequest->address_proof_s3_filename      = $companyData['residence_proof_s3_filename'];
        $registerRequest->social_contract_s3_filename    = $companyData['social_contract_s3_filename'];
        $registerRequest->other_document_1_s3_filename   = $companyData['other_document_1_s3_filename'];
        $registerRequest->other_document_2_s3_filename   = $companyData['other_document_2_s3_filename'];
        $registerRequest->other_document_3_s3_filename   = $companyData['other_document_3_s3_filename'];
        $registerRequest->other_document_1_description   = $companyData['other_document_1_description'];
        $registerRequest->other_document_2_description   = $companyData['other_document_2_description'];
        $registerRequest->other_document_3_description   = $companyData['other_document_3_description'];

        if (isset($companyData['election_minutes_s3_filename'])) { //ata de eleição apenas se natureza jurídica = sociedade anônima
            $registerRequest->election_minutes_s3_filename = $companyData['election_minutes_s3_filename'];
        } else {
            $registerRequest->election_minutes_s3_filename = null;
        }

        if (isset($companyData['letter_of_attorney_s3_filename'])) { //procuração não é obrigatório
            $registerRequest->letter_of_attorney_s3_filename = $companyData['letter_of_attorney_s3_filename'];
        } else {
            $registerRequest->letter_of_attorney_s3_filename = null;
        }


        $registerRequest->save();

        //-------------------------------------------------------------------------------------------------------- */

        //preenche os dados do representante/usuários adicionais
        $partnersOrUsersData = $request->get('partners_or_users');

        if(isset($partnersOrUsersData->document_type_front)) {
            $cpf_cnpj = preg_replace('/[^0-9]/', '', $partnersOrUsersData['cpf']);
                
            $otherRegisterRequests = RegisterRequestRepresentative::where('register_request_id', '=',  $registerRequest->id)->where('cpf_cnpj', '=', $cpf_cnpj)->whereNull('deleted_at')->first();
            $nextcode = $partnersOrUsersData->document_type_front;
            
            RegisterRequestRepresentativesMatches::create([
                'cpf'           => $cpf_cnpj,
                'name'          => mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['name'])) . ' ' . mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['surname'])),
                'face_match'    => $nextcode->consult_face_match->data->match,
                'is_alive'      => $nextcode->consult_liveness->data->isAlive,
                'register_request_representatives_id' => $otherRegisterRequests->id
            ]);
        }

        foreach ($partnersOrUsersData as $key => $value) {

            Log::debug('partner aqui: ',(array) $value);

            $cpf_cnpj = preg_replace('/[^0-9]/', '', $value['cpf']);
            $facilites = new Facilites();
            $facilites->cpf_cnpj = $cpf_cnpj;

            if (strlen($cpf_cnpj) == 11) {
                if (!$facilites->validateCPF($cpf_cnpj)) {
                    return response()->json(array("error" => "CPF inválido."));
                }
            } else if (strlen($cpf_cnpj) == 14) {
                if (!$facilites->validateCNPJ($cpf_cnpj)) {
                    return response()->json(array("error" => "CNPJ inválido."));
                }
            } else {
                return response()->json(array("error" => "CPF ou CNPJ inválido."));
            }

            if ($otherRegisterRequests = RegisterRequestRepresentative::where('register_request_id', '=',  $registerRequest->id)->where('cpf_cnpj', '=', $cpf_cnpj)->whereNull('deleted_at')->count() > 0) {
                $otherRegisterRequests = RegisterRequestRepresentative::where('register_request_id', '=',  $registerRequest->id)->where('cpf_cnpj', '=', $cpf_cnpj)->whereNull('deleted_at')->first();
            } else {
                $otherRegisterRequests = RegisterRequestRepresentative::create([
                    'uuid' =>  Str::orderedUuid(),
                    'register_request_id' => $registerRequest->id,
                    'cpf_cnpj' =>  $cpf_cnpj
                ]);
            }


            if ($value['is_user_owner'] == 1) {
                $otherRegisterRequests->uuid_representative_cad         = $value['uuid'];
                $otherRegisterRequests->is_representative = 1;
            } else {
                $otherRegisterRequests->uuid_other_representatives_cad  = $value['uuid'];
                $otherRegisterRequests->is_representative = 0;
            }


            $otherRegisterRequests->cpf_cnpj                 = $cpf_cnpj;
            $otherRegisterRequests->name                     = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['name']));
            $otherRegisterRequests->surname                  = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['surname']));
            $otherRegisterRequests->birth_date               = \Carbon\Carbon::parse($value['birth_date'])->format('Y-m-d');
            $otherRegisterRequests->phone                    = preg_replace('/[^0-9]/', '', $value['phone']);
            $otherRegisterRequests->email                    = mb_strtolower($value['email']);
            $otherRegisterRequests->gender_id                = $value['gender_id'];
            $otherRegisterRequests->marital_status_id        = $value['marital_status_id'];
            $otherRegisterRequests->mother_name              = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['mother_name']));
            $otherRegisterRequests->pep                      = $value['is_ppe'];
            $otherRegisterRequests->nationality_id           = $value['nationality_id'];
            $otherRegisterRequests->account_password         = $value['account_password'];


            //endereço
            $othersReqAddress                                 = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['address']));
            $othersReqAddressArray                            = explode(' ', $othersReqAddress, 2);
            if (count($othersReqAddressArray) >= 2) {
                $otherRegisterRequests->public_place          = trim($othersReqAddressArray[0], " ");
                $otherRegisterRequests->address               = trim($othersReqAddressArray[1], " ");
            } else {
                $otherRegisterRequests->address               = $othersReqAddress;
            }

            $otherRegisterRequests->zip_code                 = preg_replace('/[^0-9]/', '',  $value['address_zip_code']);
            $otherRegisterRequests->number                   = preg_replace('/[^0-9]/', '', $value['address_number']);
            $otherRegisterRequests->complement               = mb_strtoupper($value['address_complement']);
            $otherRegisterRequests->district                 = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['address_district']));
            $otherRegisterRequests->city                     = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['address_city']));
            $otherRegisterRequests->state_id                 = $value['address_state_id'];


            if ($value['document_type_id']) {

                switch ($value['document_type_id']) {
                    case 1:
                        $document_front_type_id = 1;
                        $document_verse_type_id = 2;
                        break;
                    case 2:
                        $document_front_type_id = 3;
                        $document_verse_type_id = 4;
                        break;
                    case 3:
                        $document_front_type_id = 5;
                        $document_verse_type_id = 6;
                        break;
                    default:
                        return array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde.");
                }

                //docs
                $otherRegisterRequests->document_type_id           = $value['document_type_id'];
                $otherRegisterRequests->document_front_type_id     = $document_front_type_id;
                $otherRegisterRequests->document_verse_type_id     = $document_verse_type_id;
                $otherRegisterRequests->document_front_s3_filename = $value['document_front_s3_filename'];
                $otherRegisterRequests->document_verse_s3_filename = $value['document_back_s3_filename'];
                $otherRegisterRequests->selfie_s3_filename         = $value['selfie_s3_filename'];

                if (isset($value['wedding_certificate_s3_filename'])) { //certidão de casamento não é obrigatório
                    $otherRegisterRequests->wedding_certificate_s3_filename = $value['wedding_certificate_s3_filename'];
                } else {
                    $otherRegisterRequests->wedding_certificate_s3_filename = null;
                }

                if (isset($value['address_proof_s3_filename'])) { //comprovante de residência não é obrigatório
                    $otherRegisterRequests->address_proof_s3_filename = $value['address_proof_s3_filename'];
                } else {
                    $otherRegisterRequests->address_proof_s3_filename = null;
                }
            }
            if ($value['is_partner'] == 1) {
                $otherRegisterRequests->type_id = 1; //Sócio
                if ($value['is_user'] == 1) {
                    $otherRegisterRequests->will_be_user = 1;
                } else {
                    $otherRegisterRequests->will_be_user = 0;
                }
            } else if ($value['is_user'] == 1) {
                $otherRegisterRequests->type_id = 7; //Outros
                $otherRegisterRequests->will_be_user = 1;
            } else {
                $otherRegisterRequests->will_be_user = 0;
            }

            $otherRegisterRequests->save();

            if(isset($value['document_type_front'])) {
                Log::debug('aqui');
                $nextcode = json_decode($value['document_type_front']);

                RegisterRequestRepresentativesMatches::create([
                    'cpf'           => $cpf_cnpj,
                    'name'          => mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['name'])) . ' ' . mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($value['surname'])),
                    'face_match'    => $nextcode->consult_face_match->data->match,
                    'is_alive'      => $nextcode->consult_liveness->data->isAlive,
                    'register_request_representatives_id' => $otherRegisterRequests->id
                ]);
            }
        }


        //-------------------------------------------------------------------------------------------------------- */

        //preenche os dados do indicante, caso tenha
        $indicate = $request->get('indicate');

        if (isset($indicate) && isset($indicate['cpf'])) {


            $cpf = preg_replace('/[^0-9]/', '', $indicate['cpf']);
            $facilites = new Facilites();
            $facilites->cpf_cnpj = $cpf;

            if (!$facilites->validateCPF($cpf)) {
                return array("error" => "CPF inválido.");
            }

            $data = (object) [
                'manager_cpf_cnpj' => $cpf,
                'name' => $indicate['name'],
                'email' => $indicate['email'],
                'phone' => $indicate['phone'],
                'indicate_code' => Facilites::removeAccentSpecialCharactersSpace($indicate['indicate_code'])
            ];

            if (Manager::where('cpf_cnpj', '=', $cpf)->count() === 0) {
                $managerCheck = $this->createManager($data);

                if (!$managerCheck) {
                    return response()->json(array("error" => "Houve um erro ao criar o indicante, por favor tente novamente."));
                }
            }
        }


        if ($registerRequest->save()) {
            return array("success" => "Solicitação de cadastro criada com sucesso.");
        }

        return array("error" => "Houve um erro ao salvar a solicitação de cadastro, por favor tente novamente mais tarde.");
    }

    public function createDinamicalRegisterRequestPf(Request $request)
    {

        try {
            if (empty($request->all())) {
                return response()->json([
                    'message' => 'Error',
                    'data' => null,
                    'errors' => [
                        'message' => 'Request vazia'
                    ]
                ], 400);
            }
            $companyData = $request['company'];
            $registerRequest = RegisterRequest::where('uuid_company_cad',  $companyData['uuid'])->whereNull('deleted_at')->first();
            
            
            if (!empty($registerRequest)) { //se já tiver registro com o msm uuid
                if($registerRequest['register_request_type_id'] == 3){
                    $registerRequest->deleted_at = \Carbon\Carbon::now();
                    $registerRequest->save();
                    $registerRequest = null;
                }
                else {
                    Log::debug('Registro de Cadastro - Existente');
                    Log::debug(json_encode($request->all()));
                    
                    $response = $this->registerRequestService->setRegisterRequestData($registerRequest, $request);
                    
                    return response()->json([
                        'message' =>  $response['message'],
                        'data' => $response['data'],
                        'error' => $response['errors']['message'] ?? null
                    ], $response['status']);
                }
            }

            Log::debug('Registro de Cadastro - novo');
            Log::debug(json_encode($request->all()));

            //caso ainda não exista essa solicitação de cadastro, cria-se uma
            if (!$registerRequest = RegisterRequest::create([
                'uuid'                      => Str::orderedUuid(),
                'register_request_type_id'  => $companyData['is_employee'] == '1' ? 3 : 1 , //conta PF
                'status_id'                 => 6,
                'send_at'                   => \Carbon\Carbon::now(),
                'created_at'                => \Carbon\Carbon::parse($companyData['created_at'])->format('Y-m-d H:i:s'),
                'uuid_company_cad'          => $companyData['uuid'],
                'account_phase_id'          => $this->EnumFaseId('fase2'),
                'action_status_id'          => $this->EnumStatusAcaoId('aguardandoCompliance')
            ])) {
                return response()->json([
                    'message' => 'Error',
                    'data' => null,
                    'errors' => [
                        'message' => 'Não foi possível criar o cadastro, por favor tente novamente mais tarde.'
                    ]
                ], 500);
            }
            
           
            if($companyData['is_employee'] == '1'){
                $response = $this->registerRequestService->setRegisterRequestData($registerRequest, $request);

                $employee = RegisterRequest::where('cpf_cnpj',  $companyData['cnpj'])->where('register_request_type_id', 3)->whereNull('deleted_at')->latest('created_at')->first();

                if($employee['employee_account_status_id'] != 2){
                    
                    $createAccountEmployee = $this->createAccountEmployee($employee);
                 
                    if(!empty($createAccountEmployee->error)){
                        return response()->json(['error' => $createAccountEmployee->error]);
                    }

                    $accountData = $createAccountEmployee->getData(true);


                    $accountData = $accountData['account_data']['data'] ? $accountData['account_data']['data'] : $accountData['account_data'];

                    $payroll_employee = PayrollEmployee::where('cpf_cnpj', $employee['cpf_cnpj'])->whereNull('deleted_at')->first();
                    $payroll_employee_detail = PayrollEmployeeDetail::where('employee_id', $payroll_employee['id'])->whereNull('deleted_at')->first();
                    

                    $payroll_employee_detail->bank_number  = '000';
                    $payroll_employee_detail->bank_agency  = '0001';
                    $payroll_employee_detail->bank_account = $accountData["account_number"];
                    $payroll_employee_detail->register_request_id = $employee["id"];
                    $payroll_employee_detail->save();

                    $createUserEmployee = $this->createUserAccPfEmployee($request, $employee);
                    
                    if(!$createUserEmployee->success) {
                        return response()->json(['error' => $createUserEmployee->message]);
                    }

                    $employee->employee_account_status_id = 2;
                    $employee->save();
                }
            }

            $data = json_decode($request->getContent(), true);
            $keyMappings = [
                'name' => 'Nome',
                'surname' => 'Sobrenome',
                'cpf' => 'CPF',
                'company_name' => 'Nome da Empresa',
                'cnpj' => 'CNPJ'
            ];

            $html = '';

            $html .= '<h2>Dados da Empresa</h2>';
            $html .= '<table border="1">';
            foreach ($data['company'] as $key => $value) {
                if (array_key_exists($key, $keyMappings)) {
                    $html .= '<tr>';
                    $html .= '<td>' . $keyMappings[$key] . '</td>';
                    $html .= '<td>' . $value . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</table>';

            // Criar tabelas para os parceiros ou usuários
            $html .= '<h2>Sócios e Usuários</h2>';

            $html .= '<h3>Sócio ou Usuário' . '</h3>';
            $html .= '<table border="1">';
            foreach ($data['partners_or_users'] as $key => $value) {
                if (array_key_exists($key, $keyMappings)) {
                    $html .= '<tr>';
                    $html .= '<td>' . $keyMappings[$key] . '</td>';
                    $html .= '<td>' . $value . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</table>';


            $apiSendGrid = new ApiSendgrid();
            $apiSendGrid->to_email    = config('mail.innternal_emails.compliance');
            $apiSendGrid->to_name     = 'Compliance';
            $apiSendGrid->to_cc_email = config('mail.innternal_emails.another_team');
            $apiSendGrid->to_cc_name  = 'Ragazzi';
            $apiSendGrid->subject     = 'Nova Solicitação Web / Cadastro';
            $apiSendGrid->content     = $html;
            $return = $apiSendGrid->sendSimpleEmail();

            Log::info(json_encode($return));

            $response = $this->registerRequestService->setRegisterRequestData($registerRequest, $request);

            return response()->json([
                'message' =>  $response['message'],
                'data' => $response['data'],
                'error' => $response['errors']['message'] ?? null
            ], $response['status']);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Error',
                'data' => null,
                'errors' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
            ], 500);
        }
    }

    protected function createManager($data)
    {
        if (Manager::create([
            'cpf_cnpj' => $data->manager_cpf_cnpj,
            'created_at' => \Carbon\Carbon::now()
        ])) {
            if ($this->createManagerDetail($data)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    protected function createManagerDetail($data)
    {
        $manager = Manager::where('cpf_cnpj', '=', $data->manager_cpf_cnpj)->first();
        if ($managerDetailCreated = ManagerDetail::create([
            'manager_id' => $manager->id,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'manager_code' => $data->indicate_code,
            'default_commission' => 0,
            'observation' => 'Criado via indicante - sis. cadastro',
            'unique_id' => md5($manager->id . date('Ymd') . time()),
            'master_id' => 1,
            'manager_type_id' => 2,
            'created_at' => \Carbon\Carbon::now()
        ])) {
            $managerDetail = ManagerDetail::where('id', '=', $managerDetailCreated->id)->first();
            $managerDetail->unique_id = md5($managerDetailCreated->id . date('Ymd') . time());
            $managerDetail->save();
            return true;
        } else {
            return false;
        }
    }

    public function setCpfCnpj(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'       => ['required', 'integer'],
            'uuid'     => ['required', 'string'],
            'cpf_cnpj' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if ($registerRequest = (RegisterRequest::where('cpf_cnpj', '=',  preg_replace('/[^0-9]/', '', $request->cpf_cnpj))->where('status_id', '=', $this->EnumStatusId('pendente'))->whereNull('deleted_at')->count() > 0)) {
                return response()->json(array("error" => "Uma solicitação de cadastro para este CPF/CNPJ está em análise, e por esse motivo não é possível realizar outra solicitação, em breve você receberá uma posição. Em caso de dúvidas entre em contato com a nossa equipe de suporte."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;

        if ($registerRequest->register_request_type_id == 1) {
            if (!$facilites->validateCPF($cpf_cnpj)) {
                return response()->json(array("error" => "CPF inválido."));
            }
        }

        if ($registerRequest->register_request_type_id == 2) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->cpf_cnpj != $cpf_cnpj) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('cpf_cnpj', $registerRequest->cpf_cnpj, $request->cpf_cnpj, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->cpf_cnpj =  $cpf_cnpj;

        if ($registerRequest->save()) {
            if ($registerRequest->register_request_type_id == 1) {
                return response()->json(array("success" => "CPF definido com sucesso."));
            }
            return response()->json(array("success" => "CNPJ definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CPF/CNPJ, por favor tente novamente mais tarde."));
    }

    public function setName(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'       => ['required', 'integer'],
            'uuid'     => ['required', 'string'],
            'name'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $name = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->name));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->name != $name) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('name', $registerRequest->name, $name, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->name = $name;

        if ($registerRequest->save()) {
            if ($registerRequest->register_request_type_id == 1) {
                return response()->json(array("success" => "Nome definido com sucesso."));
            }
            return response()->json(array("success" => "Razão social definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o nome, por favor tente novamente mais tarde."));
    }

    public function setSurname(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'       => ['required', 'integer'],
            'uuid'     => ['required', 'string'],
            'surname'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $surname = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->surname));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->surname != $surname) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('surname', $registerRequest->surname, $surname, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->surname = $surname;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Sobrenome definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o sobrenome, por favor tente novamente mais tarde."));
    }

    public function setPep(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'       => ['required', 'integer'],
            'uuid'     => ['required', 'string'],
            'pep'      => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->pep != $request->pep) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('pep', $registerRequest->pep, $request->pep, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->pep = $request->pep;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "PEP definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o PEP, por favor tente novamente mais tarde."));
    }

    public function setBirthDate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'birth_date' => ['nullable', 'date']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->birth_date != $request->birth_date) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('birth_date', $registerRequest->birth_date, $request->birth_date, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->birth_date = $request->birth_date;

        if ($registerRequest->save()) {
            if ($registerRequest->register_request_type_id == 1) {
                return response()->json(array("success" => "Data de nascimento definida com sucesso."));
            }
            return response()->json(array("success" => "Data da fundação definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a data, por favor tente novamente mais tarde."));
    }

    public function setPhone(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'phone'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->phone != $request->phone) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('phone', $registerRequest->phone, $phone, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->phone = $phone;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Telefone definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o telefone, por favor tente novamente mais tarde."));
    }

    public function setEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'email'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $email = mb_strtolower($request->email);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->email != $email) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('email', $registerRequest->email, $email, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->email = $email;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Email definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o email, por favor tente novamente mais tarde."));
    }

    public function setPublicPlace(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'public_place' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $public_place = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->public_place));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->public_place != $public_place) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('public_place', $registerRequest->public_place, $public_place, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->public_place = $public_place;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Logradouro definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o logradouro, por favor tente novamente mais tarde."));
    }

    public function setAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'address'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $address = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->address));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->address != $address) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address', $registerRequest->address, $address, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->address = $address;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Endereço definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o endereço, por favor tente novamente mais tarde."));
    }

    public function setNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'number'       => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $number = preg_replace('/[^0-9]/', '', $request->number);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->number != $number) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('number', $registerRequest->number, $number, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->number = $number;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Número definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o número, por favor tente novamente mais tarde."));
    }

    public function setComplement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'complement'   => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $complement = mb_strtoupper($request->complement);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->complement != $complement) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('complement', $registerRequest->complement, $complement, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->complement = $complement;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Complemento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o complemento, por favor tente novamente mais tarde."));
    }

    public function setDistrict(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'district'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $district = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->district));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->district != $district) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('district', $registerRequest->district, $district, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->district = $district;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Bairro definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o bairro, por favor tente novamente mais tarde."));
    }

    public function setCity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'city'         => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $city = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->city));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->city != $city) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('city', $registerRequest->city, $city, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->city = $city;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Cidade definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a cidade, por favor tente novamente mais tarde."));
    }

    public function setState(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'state_id'     => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->state_id != $request->state_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('state_id', $registerRequest->state_id, $request->state_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->state_id = $request->state_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Estado definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado, por favor tente novamente mais tarde."));
    }

    public function setZipCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $validator = Validator::make($request->all(), [
            'id'           => ['required', 'integer'],
            'uuid'         => ['required', 'string'],
            'zip_code'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $zip_code = preg_replace('/[^0-9]/', '', $request->zip_code);
        if (strlen($zip_code) != 8) {
            return response()->json(array("error" => "CEP inválido."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->zip_code != $zip_code) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('zip_code', $registerRequest->zip_code, $zip_code, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->zip_code = $zip_code;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "CEP definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CEP, por favor tente novamente mais tarde."));
    }

    public function setDocumentTypeFront(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'document_type_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $document_front_type_id = '';

        switch ($request->document_type_id) {
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
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if (!DocumentType::where('id', '=', $document_front_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_front_type_id != $document_front_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_front_type_id', $registerRequest->document_front_type_id, $document_front_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->document_front_type_id = $document_front_type_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo da frente do documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo de documento, por favor tente novamente mais tarde."));
    }

    public function setDocumentTypeVerse(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'document_type_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


        $document_verse_type_id = '';

        switch ($request->document_type_id) {
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
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if (!DocumentType::where('id', '=', $document_verse_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_verse_type_id != $document_verse_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_verse_type_id', $registerRequest->document_verse_type_id, $document_verse_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }


        $registerRequest->document_verse_type_id = $document_verse_type_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo do verso do documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo de documento, por favor tente novamente mais tarde."));
    }

    public function setDocumentType(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'document_type_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        switch ($request->document_type_id) {
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
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }

        switch ($request->document_type_id) {
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
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_verse_type_id != $document_verse_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_verse_type_id', $registerRequest->document_verse_type_id, $document_verse_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }

            if ($registerRequest->document_front_type_id != $document_front_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_front_type_id', $registerRequest->document_front_type_id, $document_front_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }
        $registerRequest->document_front_type_id = $document_front_type_id;
        $registerRequest->document_verse_type_id = $document_verse_type_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo de documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo de documento, por favor tente novamente mais tarde."));
    }

    public function setGender(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'gender_id'        => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!Gender::where('id', '=', $request->gender_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Gênero não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->gender_id != $request->gender_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('gender_id', $registerRequest->gender_id, $request->gender_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->gender_id = $request->gender_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Gênero definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o gênero, por favor tente novamente mais tarde."));
    }

    public function setDocumentNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'document_number'  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $document_number = preg_replace('/[^0-9]/', '', $request->document_number);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_number != $document_number) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_number', $registerRequest->document_number, $document_number, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->document_number = $document_number;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Número do documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o número do documento, por favor tente novamente mais tarde."));
    }

    public function setIncome(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'income'           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->income != $request->income) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('income', $registerRequest->income, $request->income, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->income = $request->income;

        if ($registerRequest->save()) {
            if ($registerRequest->register_request_type_id == 1) {
                return response()->json(array("success" => "Renda definida com sucesso."));
            }
            return response()->json(array("success" => "Faturamento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a renda, por favor tente novamente mais tarde."));
    }

    public function setCpfCnpjWorkCompany(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'cpf_cnpj_work_company' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj_work_company);
        $facilites->cpf_cnpj = $cpf_cnpj;

        if (strlen($cpf_cnpj) == 11) {
            if (!$facilites->validateCPF($cpf_cnpj)) {
                return response()->json(array("error" => "CPF inválido."));
            }
        } else if (strlen($cpf_cnpj) == 14) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido."));
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->cpf_cnpj_work_company != $cpf_cnpj) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('cpf_cnpj_work_company', $registerRequest->cpf_cnpj_work_company, $cpf_cnpj, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->cpf_cnpj_work_company =  $cpf_cnpj;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "CNPJ da empresa em que trabalha definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CNPJ da empresa em que trabalha, por favor tente novamente mais tarde."));
    }

    public function setMotherName(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'mother_name'           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $mother_name = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->mother_name));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->mother_name != $mother_name) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('mother_name', $registerRequest->mother_name, $mother_name, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->mother_name = $mother_name;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Nome da mãe definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o nome da mãe, por favor tente novamente mais tarde."));
    }

    public function setFatherName(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'father_name'           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $father_name = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->father_name));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->father_name != $father_name) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('father_name', $registerRequest->father_name, $father_name, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->father_name = $father_name;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Nome do pai definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o nome do pai, por favor tente novamente mais tarde."));
    }

    public function setAddressProofS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $addressProofDocumentTypeId = 8;
        if ($registerRequest->register_request_type_id == 2) {
            $addressProofDocumentTypeId = 12;
        }

        if (!$document_type = DocumentType::where('id', '=', $addressProofDocumentTypeId)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->address_proof_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->address_proof_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address_proof_s3_filename', $registerRequest->address_proof_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->address_proof_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Comprovante de endereço enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o comprovante de endereço, por favor tente novamente mais tarde."));
    }

    public function setDocumentFrontS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $documentTypeId = '';

        switch ($request->document_type_id) {
            case 1:
                $documentTypeId = 1;
                break;
            case 2:
                $documentTypeId = 3;
                break;
            case 3:
                $documentTypeId = 5;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
                break;
        }

        if (!$document_type = DocumentType::where('id', '=', $documentTypeId)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }


        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->document_front_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_front_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_front_s3_filename', $registerRequest->document_front_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->document_front_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Frente do documento enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a frente do documento, por favor tente novamente mais tarde."));
    }

    public function setDocumentVerseS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $documentTypeId = '';

        switch ($request->document_type_id) {
            case 1:
                $documentTypeId = 2;
                break;
            case 2:
                $documentTypeId = 4;
                break;
            case 3:
                $documentTypeId = 6;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
                break;
        }

        if (!$document_type = DocumentType::where('id', '=', $documentTypeId)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }


        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->document_verse_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_verse_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_verse_s3_filename', $registerRequest->document_verse_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->document_verse_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Verso do documento enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o verso do documento, por favor tente novamente mais tarde."));
    }

    public function setBillingStatementS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 14)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->representative_document_billing_statement_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->billing_statement_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('billing_statement_s3_filename', $registerRequest->billing_statement_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->billing_statement_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Declaração de faturamento do representante enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a declaração de faturamento do representante, por favor tente novamente mais tarde."));
    }

    public function setSelfieS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 7)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->selfie_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->selfie_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('selfie_s3_filename', $registerRequest->selfie_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->selfie_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Selfie enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a selfie, por favor tente novamente mais tarde."));
    }

    public function isBase64Valid($base64String, $fileName)
    {
        // Remove caracteres inválidos (quebra de linha, espaços, etc.)
        $base64String = trim($base64String);

        // Verifica se o comprimento da string é múltiplo de 4
        if (strlen($base64String) % 4 !== 0) {
            return false;
        }
        

        // Verifica se a string contém apenas caracteres válidos
        if (!preg_match('/^[a-zA-Z0-9\/\+\=]+$/', $base64String)) {
            return false;
        }
        

        // Tenta decodificar a string Base64
        $decodedData = base64_decode($base64String, true);

        // Verifica se a decodificação foi bem-sucedida
        if ($decodedData === false) {
            return false;
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {

            // Verifica se os dados decodificados são uma imagem válida
            $imageInfo = getimagesizefromstring($decodedData);
        
            if ($imageInfo === false) {
                return false; // Dados decodificados não formam uma imagem válida
            }
    
            $mimeType = $imageInfo['mime'];
            $validMimeTypes = [
                "image/png",
                "image/jpeg",
                "image/jpg",
                "image/bmp",
                "application/pdf",
                "application/xml",
                "application/octet-stream"
            ];        
            
            if (!in_array($mimeType, $validMimeTypes)) {
                return false; // Tipo de arquivo inválido
            }
    
            $width = $imageInfo[0];
            $height = $imageInfo[1];
    
            if ($width == 0 || $height == 0) {
                return false; // Dimensões inválidas
            }            
        }
            

        // Se todas as verificações passaram, a string Base64 é válida e representa uma imagem válida
        return true;
    }

    public function setKycS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 27)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }                    
        
        $file64 = base64_encode(file_get_contents($request->file64));

        if (!$this->isBase64Valid($file64, $request->file_name)) {
            return response()->json(array("error" => "Não foi possível realizar a alteração pois o arquivo é inválido ou está corrompido."));            
        } 

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->kyc_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->kyc_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('kyc_s3_filename', $registerRequest->kyc_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->kyc_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "KYC enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o KYC, por favor tente novamente mais tarde."));
    }

    public function setLetterOfAttorneyS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->letter_of_attorney_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->letter_of_attorney_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('letter_of_attorney_s3_filename', $registerRequest->letter_of_attorney_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->letter_of_attorney_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Procuração enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a procuração, por favor tente novamente mais tarde."));
    }

    public function setElectionMinutesS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->legal_nature_id != 2) {
            return ["error" => "Só é possível alterar a ata de eleição para empresas de natureza jurídica Sociedade Anônima"];
        }

        if (!$document_type = DocumentType::where('id', '=', 30)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->election_minutes_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->election_minutes_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('election_minutes_s3_filename', $registerRequest->election_minutes_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->election_minutes_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Ata de eleição enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a ata de eleição, por favor tente novamente mais tarde."));
    }

    public function setOtherDocument1S3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 31)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->other_document_1_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->other_document_1_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('other_document_1_s3_filename', $registerRequest->other_document_1_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->other_document_1_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "O primeiro 'outro documento' enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o primeiro 'outro documento', por favor tente novamente mais tarde."));
    }

    public function setOtherDocument2S3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 31)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->other_document_2_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->other_document_2_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('other_document_2_s3_filename', $registerRequest->other_document_2_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->other_document_2_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "O segundo 'outro documento' enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o segundo 'outro documento', por favor tente novamente mais tarde."));
    }

    public function setOtherDocument3S3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 31)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->other_document_3_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->other_document_3_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('other_document_3_s3_filename', $registerRequest->other_document_3_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->other_document_3_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "O terceiro 'outro documento' enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o terceiro 'outro documento', por favor tente novamente mais tarde."));
    }

    public function fileManagerS3($file_name_request, $file64, $document_type, $file_name_data)
    {
        // extensão do arquivo minuscula.
        $ext = strtolower(pathinfo($file_name_request, PATHINFO_EXTENSION));

        // verifica se é um arquivo com extensão permitida.
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf'])) {
            return (object) [
                "success" => false,
                "message" => "Formato de arquivo $ext não permitido, formatos permitidos: jpg, jpeg, png e pdf."
            ];
        }

        // cria um nome único para o arquivo, acrescentado a extensão ao final (XPTO.exe)
        $fileName = md5($document_type->id . date('Ymd') . time()) . '.' . $ext;

        // instancia amazonS3
        $amazons3 = new AmazonS3();

        // exclui arquivo caso já esteja defindo o nome do mesmo no campo.
        if (empty($file_name_data)) {
            $amazons3->fileName = $file_name_data;
            $amazons3->path     = $document_type->s3_path;
            $amazons3->fileDeleteAmazon();
        }

        // define os parâmetros do novo arquivo para realizar o upload.
        $amazons3->fileName = $fileName;
        $amazons3->file64   = base64_encode(file_get_contents($file64));;
        $amazons3->path     = $document_type->s3_path;

        // realiza o upload do arquivo.
        $upfile             = $amazons3->fileUpAmazon();

        // checa se não foi sucesso e por fim realiza os retorns necessários
        if (!$upfile->success) {
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

    public function setAgreeTerm(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'term_agree'            => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $registerRequest->term_agree = $request->term_agree;

        if ($registerRequest->term_agree == 1) {

            $token = new Facilites();
            $registerRequest->token_confirmation_email = $token->createApprovalToken();
            $registerRequest->token_confirmation_phone = $token->createApprovalToken();

            if (!$registerRequest->save()) {
                return response()->json(array("error" => "Ocorreu uma falha ao atualizar os tokens, por favor tente novamente mais tarde."));
            }

            $email = $registerRequest->representative_email;

            if ($registerRequest->register_request_type_id == 1) {
                $email = $registerRequest->email;
            }

            if (!(($this->sendEmailToken($registerRequest->id, $registerRequest->token_confirmation_email, $email, $registerRequest->name, 'Confirmação de Solicitação de Abertura de Conta'))->success)) {
                return response()->json(array("error" => "Não foi possível enviar o token por e-mail, por favor tente novamente mais tarde."));
            }

            $registerRequest->token_confirmation_email_send_at = \Carbon\Carbon::now();

            $phone = $registerRequest->representative_phone;

            if ($registerRequest->register_request_type_id == 1) {
                $phone = $registerRequest->phone;
            }

            if (!(($this->sendPhoneToken($registerRequest->id, $registerRequest->token_confirmation_phone, $phone, $registerRequest->name))->success)) {
                return response()->json(array("error" => "Não foi possível enviar o SMS, por favor, verifique o número informado ou tente novamente mais tarde."));
            }

            $registerRequest->token_confirmation_phone_send_at = \Carbon\Carbon::now();

            if ($registerRequest->save()) {
                return response()->json(array("success" => "Tokens enviados com sucesso."));
            }
        }
        return response()->json(array("error" => "É necessário afirmar que as informações prestadas no cadastro são verdadeiras e exatas para continuar."));
    }

    public function sendEmailToken($id, $token_confirmation_email, $email, $name, $subject)
    {

        $apiSendGrid = new ApiSendgrid();
        $apiSendGrid->to_email    = $email;
        $apiSendGrid->to_name     = $name;
        $apiSendGrid->to_cc_email = 'ragazzi@dinari.com.br';
        $apiSendGrid->to_cc_name  = 'Ragazzi';
        $apiSendGrid->subject     = $subject;
        $apiSendGrid->content     = "Olá $name, <br>O token " . substr($token_confirmation_email, 0, 4) . "-" . substr($token_confirmation_email, 4, 4) . " foi gerado para confirmação de solicitação de abertura de sua conta.";
        if ($apiSendGrid->sendSimpleEmail()) {
            return (object) ["success" => true];
        }
        return (object) ["success" => false];
    }

    public function sendPhoneToken($id, $token_confirmation_phone, $phone, $name)
    {
        $sendSMS = SendSms::create([
            'external_id' => ("3" . (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('YmdHisu') . $id),
            'to'          => "55" . $phone,
            'message'     => "Ola " . (explode(" ", $name))[0] . ", o token " . substr($token_confirmation_phone, 0, 4) . "-" . substr($token_confirmation_phone, 4, 4) . " foi gerado para confirmação de solicitação de abertura de sua conta.",
            'type_id'     => 3,
            'origin_id'   => $id,
            'created_at'  => \Carbon\Carbon::now()
        ]);

        $apiConfig                     = new ApiConfig();
        $apiConfig->master_id          = null;
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

        if (isset($apiZenviaSMS->sendShortSMS()->success)) {
            return (object) ["success" => true];
        }

        //Check if should send token by whatsapp
        if ((SystemFunctionMaster::where('system_function_id', '=', 10)->first())->available == 1) {
            $apiZenviaWhats            = new ApiZenviaWhatsapp();
            $apiZenviaWhats->to_number = $sendSMS->to;
            $apiZenviaWhats->token     = "*" . substr($token_confirmation_phone, 0, 4) . "-" . substr($token_confirmation_phone, 4, 4) . "*";
            if (isset($apiZenviaWhats->sendToken()->success)) {
                return (object) ["success" => true];
            }
        }

        return (object) ["success" => false];
    }

    public function confirmPhoneToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'token_confirmation_phone' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->term_agree == '' or $registerRequest->term_agree == 0  or $registerRequest->term_agree == null) {
            return response()->json(array("error" => "É necessário estar de acordo com o termo de uso."));
        }

        if ($registerRequest->token_confirmation_phone ==  $request->token_confirmation_phone) {

            $registerRequest->token_phone_confirmed = 1;

            if ($registerRequest->save()) {
                return response()->json(array("success" => "Token enviado por SMS confirmado com sucesso."));
            }
            return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente mais tarde."));
        }

        $registerRequest->token_phone_confirmed = 0;

        if ($registerRequest->save()) {
            return response()->json(array("error" => "Token informado não corresponde com o token enviado por SMS."));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por SMS, por favor tente novamente."));
    }

    public function confirmEmailToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'token_confirmation_email' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->term_agree == '' or $registerRequest->term_agree == 0  or $registerRequest->term_agree == null) {
            return response()->json(array("error" => "É necessário estar de acordo com o termo de uso."));
        }

        if ($registerRequest->token_phone_confirmed == '' or $registerRequest->token_phone_confirmed == 0  or $registerRequest->token_phone_confirmed == null) {
            return response()->json(array("error" => "É necessário confirmar o token enviado por SMS antes de continuar."));
        }

        if ($registerRequest->token_confirmation_email ==  $request->token_confirmation_email) {

            $registerRequest->token_email_confirmed = 1;

            if (!$registerRequest->save()) {
                return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por e-mail, por favor tente novamente mais tarde."));
            }

            $data = [];

            $documentType = $registerRequest->representative_document_type_id;

            if ($registerRequest->document_type_id != null) {
                $documentType = $registerRequest->document_type_id;
            }

            switch ($documentType) {
                case (1):
                    $document_type_id = 2;
                    break;
                case (3):
                    $document_type_id = 4;
                    break;
                case (5):
                    $document_type_id = 6;
                    break;
            }

            $facilites = new Facilites();

            $registerRequestData     = new RegisterRequest();
            $registerRequestData->id = $registerRequest->id;
            $registerRequestData     = $registerRequestData->get()[0];

            $bodyMessage = "Olá, <br>
                Segue anexo os documentos referente ao cadastro feito pelo(a) $registerRequestData->name<b> <br>
                <b>Abaixo, segue os dados do cadastro:</b><br>
                <b>Tipo de cadastro: </b>$registerRequestData->register_request_type_description<br>
                <b>CPF/CNPJ: </b>" . ($facilites->mask_cpf_cnpj($registerRequestData->cpf_cnpj)) . "<br>
                <b>Nome: </b>$registerRequestData->name<br>
                <b>Data de aniversário: </b>" . \Carbon\Carbon::parse($registerRequestData->birth_date)->format('d-m-Y') . "<br>
                <b>Telefone: </b>" . ($facilites->mask_phone($registerRequestData->phone)) . "<br>
                <b>E-mail: </b>$registerRequestData->email<br>
                <b>CNPJ da empresa em que trabalha: </b>" . ($facilites->mask_cpf_cnpj($registerRequestData->cpf_cnpj_work_company)) . "<br>
                <b>Nome da mãe: </b>$registerRequestData->mother_name<br>
                <b>Local: </b>$registerRequestData->public_place<br>
                <b>Endereço: </b>$registerRequestData->address<br>
                <b>Número: </b>$registerRequestData->number<br>
                <b>Complemento: </b>$registerRequestData->complement<br>
                <b>Bairro: </b>$registerRequestData->district<br>
                <b>Cidade: </b>$registerRequestData->city<br>
                <b>Estado: </b>$registerRequestData->state_description<br>
                <b>Cep: </b>" . substr($registerRequestData->zip_code, 0, -3) . "-" . substr($registerRequestData->zip_code, -3) . "<br>
                <b>Renda: </b>R$ " . number_format($registerRequestData->income, 2, ',', '.') . "<br>
                <b>Gênero: </b>$registerRequestData->gender_description<br>
                <b>Documento: </b>$registerRequestData->document_type_description<br>
                <b>Número do documento: </b>$registerRequestData->document_number<br>
                <b>Status do cadastro: </b>$registerRequestData->status_description<br>
                <b>Data de criação do cadastro: </b>" . \Carbon\Carbon::parse($registerRequestData->created_at)->format('d-m-Y') . "<br>
                <br><br>";

            $data = [
                [
                    "filename" => $registerRequest->address_proof_s3_filename,
                    "type"     => 8
                ],
                [
                    "filename" => $registerRequest->document_front_s3_filename,
                    "type"     => $registerRequest->document_type_id

                ],
                [
                    "filename" => $registerRequest->document_verse_s3_filename,
                    "type"     => $document_type_id
                ],
                [
                    "filename" => $registerRequest->selfie_s3_filename,
                    "type" => 7
                ]
            ];

            if ($registerRequest->register_request_type_id == 2) {
                $bodyMessage = "Olá, <br>
                    Segue anexo os documentos referente ao cadastro feito pelo(a) $registerRequestData->representative_name representando a empresa $registerRequest->name.<b> <br>
                    <b>Abaixo, segue os dados do cadastro:</b><br>
                    <b>Tipo de cadastro: </b>$registerRequestData->register_request_type_description<br>
                    <b>CNPJ da empresa: </b>" . ($facilites->mask_cpf_cnpj($registerRequestData->cpf_cnpj)) . "<br>
                    <b>Nome da empresa: </b>$registerRequestData->name<br>
                    <b>Data de fundação  da empresa: </b>" . (\Carbon\Carbon::parse($registerRequestData->birth_date)->format('d-m-Y')) . "<br>
                    <b>Telefone da empresa: </b>" . ($facilites->mask_phone($registerRequestData->phone)) . "<br>
                    <b>E-mail da empresa: </b>$registerRequestData->email<br>
                    <b>Local da empresa: </b>$registerRequestData->public_place<br>
                    <b>Endereço da empresa: </b>$registerRequestData->address<br>
                    <b>Número da empresa: </b>$registerRequestData->number<br>
                    <b>Complemento da empresa: </b>$registerRequestData->complement<br>
                    <b>Bairro da empresa: </b>$registerRequestData->district<br>
                    <b>Cidade da empresa: </b>$registerRequestData->city<br>
                    <b>Estado da empresa: </b>$registerRequestData->state_description<br>
                    <b>Cep da empresa: </b>" . substr($registerRequestData->zip_code, 0, -3) . "-" . substr($registerRequestData->zip_code, -3) . "<br>
                    <b>Faturamento: </b> R$ " . number_format($registerRequestData->income, 2, ',', '.') . "<br>
                    <b>Nome do representante: </b>$registerRequestData->representative_name<br>
                    <b>CPF/CNPJ do representante: </b>" . ($facilites->mask_cpf_cnpj($registerRequestData->representative_cpf_cnpj)) . "<br>
                    <b>Data de aniversário do representante: </b>" . \Carbon\Carbon::parse($registerRequestData->representative_birth_date)->format('d-m-Y') . "<br>
                    <b>Telefone do representante: </b>" . ($facilites->mask_phone($registerRequestData->representative_phone)) . "<br>
                    <b>Email do representante: </b>$registerRequestData->representative_email<br>
                    <b>Local do representante: </b>$registerRequestData->representative_public_place<br>
                    <b>Endereço do representante: </b>$registerRequestData->representative_address<br>
                    <b>Número do representante: </b>$registerRequestData->representative_number<br>
                    <b>Complemento do representante: </b>$registerRequestData->representative_complement<br>
                    <b>Bairro do representante: </b>$registerRequestData->representative_district<br>
                    <b>Cidade do representante: </b>$registerRequestData->representative_city<br>
                    <b>Estado do representante: </b>$registerRequestData->representative_state_description<br>
                    <b>Cep do representante: </b>" . substr($registerRequestData->representative_zip_code, 0, -3) . "-" . substr($registerRequestData->representative_zip_code, -3) . "<br>
                    <b>Tipo de documento do representante: </b>$registerRequestData->representative_document_type_description<br>
                    <b>Gênero do representante: </b>$registerRequestData->representative_gender_description<br>
                    <b>Número do documento do representante: </b>$registerRequestData->representative_document_number<br>
                    <b>Status do cadastro: </b>$registerRequestData->status_description<br>
                    <b>Data de criação do cadastro: </b>" . (\Carbon\Carbon::parse($registerRequestData->created_at)->format('d-m-Y')) . "<br>
                    <br><br>";

                $data = [
                    [
                        "filename" => $registerRequest->social_contract_s3_filename,
                        "type"     => 11
                    ],
                    [
                        "filename" => $registerRequest->billing_statement_s3_filename,
                        "type" => 14
                    ],
                    [
                        "filename" => $registerRequest->representative_address_proof_s3_filename,
                        "type"     => 8
                    ],
                    [
                        "filename" => $registerRequest->representative_document_front_s3_filename,
                        "type"     => $registerRequest->representative_document_type_id
                    ],
                    [
                        "filename" => $registerRequest->representative_document_verse_s3_filename,
                        "type" => $document_type_id
                    ],
                    [
                        "filename" => $registerRequest->representative_selfie_s3_filename,
                        "type" => 7
                    ]
                ];
            }

            $filedata = $this->zipFile($data);

            $this->sendEmail('Nova Solicitação de Cadastro', $bodyMessage, $filedata->filename, $filedata->zipBase64);

            Storage::disk('zip')->delete($filedata->filename);

            return response()->json(array("success" => "Token enviado por e-mail confirmado com sucesso."));
        }

        $registerRequest->token_email_confirmed = 0;

        if ($registerRequest->save()) {
            return response()->json(array("error" => "Token informado não corresponde com o token enviado por e-mail."));
        }
        return response()->json(array("error" => "Ocorreu uma falha ao confirmar o token enviado por e-mail, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeCpfCnpj(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'representative_cpf_cnpj'  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->representative_cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;

        if (strlen($cpf_cnpj) == 11) {
            if (!$facilites->validateCPF($cpf_cnpj)) {
                return response()->json(array("error" => "CPF inválido."));
            }
        }

        if (strlen($cpf_cnpj) == 14) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_cpf_cnpj != $cpf_cnpj) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_cpf_cnpj', $registerRequest->representative_cpf_cnpj, $cpf_cnpj, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_cpf_cnpj =  $cpf_cnpj;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "CPF/CNPJ do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CPF/CNPJ do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeName(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'representative_name'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_name = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->representative_name));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_name != $request->representative_name) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_name', $registerRequest->representative_name, $request->representative_name, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_name = $representative_name;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Nome do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o nome do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeBirthDate(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'representative_birth_date' => ['nullable', 'date']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_birth_date != $request->representative_birth_date) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_birth_date', $registerRequest->representative_birth_date, $request->representative_birth_date, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_birth_date = $request->representative_birth_date;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Data de nascimento do representante definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a data de nascimento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativePhone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'representative_phone'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_phone =  preg_replace('/[^0-9]/', '', $request->representative_phone);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_phone != $representative_phone) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_phone', $registerRequest->representative_phone, $representative_phone, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_phone = $representative_phone;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Telefone do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o telefone do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                        => ['required', 'integer'],
            'uuid'                      => ['required', 'string'],
            'representative_email'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_email = mb_strtolower($request->representative_email);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_email != $representative_email) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_email', $registerRequest->representative_email, $representative_email, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_email = $representative_email;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Email do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o email do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativePublicPlace(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_public_place' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_public_place = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->representative_public_place));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_public_place != $representative_public_place) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_public_place', $registerRequest->representative_public_place, $representative_public_place, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_public_place = $representative_public_place;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Logradouro do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o logradouro do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_address'      => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_address = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->representative_address));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_address != $representative_address) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_address', $registerRequest->representative_address, $representative_address, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_address = $representative_address;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Endereço do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o endereço do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_number'       => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_number = preg_replace('/[^0-9]/', '', $request->representative_number);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_number != $representative_number) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_number', $registerRequest->representative_number, $representative_number, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_number = $representative_number;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Número do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o número do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeComplement(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_complement'   => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_complement = mb_strtoupper($request->representative_complement);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_complement != $representative_complement) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_complement', $registerRequest->representative_complement, $representative_complement, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_complement = $representative_complement;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Complemento do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o complemento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDistrict(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_district'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_district = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->representative_district));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_district != $representative_district) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_district', $registerRequest->representative_district, $representative_district, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_district = $representative_district;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Bairro do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o bairro do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeCity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_city'         => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_city = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->representative_city));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_city != $representative_city) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_city', $registerRequest->representative_city, $representative_city, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_city = $representative_city;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Cidade do representante definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o cidade do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeState(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_state_id'     => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_state_id != $request->representative_state_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_state_id', $registerRequest->representative_state_id, $request->representative_state_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_state_id = $request->representative_state_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Estado do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeZipCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'representative_zip_code'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_zip_code = preg_replace('/[^0-9]/', '', $request->representative_zip_code);
        if (strlen($representative_zip_code) != 8) {
            return response()->json(array("error" => "CEP inválido."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_zip_code != $representative_zip_code) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_zip_code', $registerRequest->representative_zip_code, $representative_zip_code, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_zip_code = $representative_zip_code;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "CEP do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CEP do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDocumentTypeFront(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                              => ['required', 'integer'],
            'uuid'                            => ['required', 'string'],
            'representative_document_type_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $representative_document_front_type_id = '';

        switch ($request->representative_document_type_id) {
            case 1:
                $representative_document_front_type_id = 1;
                break;
            case 2:
                $representative_document_front_type_id = 3;
                break;
            case 3:
                $representative_document_front_type_id = 5;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if (!DocumentType::where('id', '=', $representative_document_front_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_document_front_type_id != $representative_document_front_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_front_type_id', $registerRequest->representative_document_front_type_id, $representative_document_front_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_document_front_type_id = $representative_document_front_type_id;


        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo da frente do documento do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo da frente do documento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDocumentTypeVerse(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                              => ['required', 'integer'],
            'uuid'                            => ['required', 'string'],
            'representative_document_type_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $representative_document_verse_type_id = '';

        switch ($request->representative_document_type_id) {
            case 1:
                $representative_document_verse_type_id = 2;
                break;
            case 2:
                $representative_document_verse_type_id = 4;
                break;
            case 3:
                $representative_document_verse_type_id = 6;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }


        if (!DocumentType::where('id', '=', $representative_document_verse_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_document_verse_type_id != $representative_document_verse_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_verse_type_id', $registerRequest->representative_document_verse_type_id, $representative_document_verse_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_document_verse_type_id = $representative_document_verse_type_id;


        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo do verso do documento do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo do verso do documento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDocumentType(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                              => ['required', 'integer'],
            'uuid'                            => ['required', 'string'],
            'representative_document_type_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        switch ($request->representative_document_type_id) {
            case 1:
                $representative_document_front_type_id = 1;
                break;
            case 2:
                $representative_document_front_type_id = 3;
                break;
            case 3:
                $representative_document_front_type_id = 5;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }

        switch ($request->representative_document_type_id) {
            case 1:
                $representative_document_verse_type_id = 2;
                break;
            case 2:
                $representative_document_verse_type_id = 4;
                break;
            case 3:
                $representative_document_verse_type_id = 6;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_document_front_type_id != $representative_document_front_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_front_type_id', $registerRequest->representative_document_front_type_id, $representative_document_front_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }

            if ($registerRequest->representative_document_verse_type_id != $representative_document_verse_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_verse_type_id', $registerRequest->representative_document_verse_type_id, $representative_document_verse_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }


        $registerRequest->representative_document_front_type_id = $representative_document_front_type_id;
        $registerRequest->representative_document_verse_type_id = $representative_document_verse_type_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo de documento do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo de documento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDocumentNumber(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                              => ['required', 'integer'],
            'uuid'                            => ['required', 'string'],
            'representative_document_number'  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $representative_document_number = preg_replace('/[^0-9]/', '', $request->representative_document_number);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_document_number != $representative_document_number) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_number', $registerRequest->representative_document_number, $representative_document_number, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_document_number = $representative_document_number;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Número do documento do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o número do documento do representante, por favor tente novamente mais tarde."));
    }

    public function setSocialContractS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 11)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->social_contract_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->social_contract_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('social_contract_s3_filename', $registerRequest->social_contract_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->social_contract_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Contrato social enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o contrato social, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeAddressProofS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 8)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->representative_address_proof_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_address_proof_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_address_proof_s3_filename', $registerRequest->representative_address_proof_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_address_proof_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Comprovante de endereço do representante enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o comprovante de endereço do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDocumentFrontS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $documentTypeId = '';

        switch ($request->document_type_id) {
            case 1:
                $documentTypeId = 1;
                break;
            case 2:
                $documentTypeId = 3;
                break;
            case 3:
                $documentTypeId = 5;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
                break;
        }

        if (!$document_type = DocumentType::where('id', '=', $documentTypeId)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->representative_document_front_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_document_front_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_front_s3_filename', $registerRequest->representative_document_front_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_document_front_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Frente do documento do representante enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a frente do documento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeDocumentVerseS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $documentTypeId = '';

        switch ($request->document_type_id) {
            case 1:
                $documentTypeId = 2;
                break;
            case 2:
                $documentTypeId = 4;
                break;
            case 3:
                $documentTypeId = 6;
                break;
            default:
                return response()->json(array("error" => "O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde."));
                break;
        }

        if (!$document_type = DocumentType::where('id', '=', $documentTypeId)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->representative_document_verse_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_document_verse_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_verse_s3_filename', $registerRequest->representative_document_verse_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_document_verse_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Verso do documento do representante enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o verso do documento do representante, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeSelfieS3Filename(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'         => ['required', 'integer'],
            'uuid'       => ['required', 'string'],
            'file_name'  => ['nullable', 'string'],
            'file64'     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($request->replaced_by_agency != null && $registerRequest->status_id == $this->EnumStatusId('pendente')) {
                // continues the method, to edit the document
            } else if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!$document_type = DocumentType::where('id', '=', 7)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->representative_selfie_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_selfie_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_selfie_s3_filename', $registerRequest->representative_selfie_s3_filename, $fileName->data, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_selfie_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Selfie do representante enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a selfie do representante, por favor tente novamente mais tarde."));
    }

    public function setLegalNature(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'legal_nature_id'  => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!LegalNature::where('id', '=', $request->legal_nature_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "A natureza jurídica não foi localizada, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->legal_nature_id != $request->legal_nature_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('legal_nature_id', $registerRequest->legal_nature_id, $request->legal_nature_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->legal_nature_id = $request->legal_nature_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Natureza jurídica definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a natureza jurídica, por favor tente novamente mais tarde."));
    }

    public function setBusinessLine(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'business_line_id'  => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!RegisterRequestBusinessLine::where('id', '=', $request->business_line_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "O ramo não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->business_line_id != $request->business_line_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('business_line_id', $registerRequest->business_line_id, $request->business_line_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->business_line_id = $request->business_line_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Ramo definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o ramo, por favor tente novamente mais tarde."));
    }

    public function resendConfirmationPhoneToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->token_confirmation_phone_send_at == null) {
            return response()->json(array("error" => "Nenhum token foi gerado, por favor, confirme se está de acordo com o termo de uso ou tente mais tarde."));
        }

        if ((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($registerRequest->token_confirmation_phone_send_at)) <= 60) {
            return response()->json(array("error" => "Por favor, aguarde 1 minuto para reenviar o token para o celular."));
        }

        $phone = $registerRequest->representative_phone;

        if ($registerRequest->register_request_type_id == 1) {
            $phone = $registerRequest->phone;
        }

        $apiZenviaWhats            = new ApiZenviaWhatsapp();
        $apiZenviaWhats->to_number = "55" . $phone;
        $apiZenviaWhats->token     = "*" . substr($registerRequest->token_confirmation_phone, 0, 4) . "-" . substr($registerRequest->token_confirmation_phone, 4, 4) . "*";

        if (!isset($apiZenviaWhats->sendToken()->success)) {
            return response()->json(array("error" => "Não foi possível reenviar o token via Whatsapp, por favor tente mais tarde."));
        }

        $registerRequest->token_confirmation_phone_send_at = \Carbon\Carbon::now();

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Token reenviado por Whatsapp com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível reenviar o token por WhatsApp, por favor tente mais tarde"));
    }

    public function resendConfirmationEmailToken(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->token_confirmation_phone_send_at == null) {
            return response()->json(array("error" => "Nenhum token foi gerado, por favor, verifique se está de acordo com o termo de uso ou tente mais tarde."));
        }

        if ((\Carbon\Carbon::parse(\Carbon\Carbon::now()))->diffInSeconds(\Carbon\Carbon::parse($registerRequest->token_confirmation_email_send_at)) <= 60) {
            return response()->json(array("error" => "Por favor, aguarde 1 minuto para reenviar o token por e-mail."));
        }

        $email = $registerRequest->representative_email;

        if ($registerRequest->register_request_type_id == 1) {
            $email = $registerRequest->email;
        }

        if (!(($this->sendEmailToken($registerRequest->id, $registerRequest->token_confirmation_email, $email, $registerRequest->name, 'Token Reenviado para Solicitação de Abertura de Conta'))->success)) {
            return response()->json(array("error" => "Não foi possível reenviar o token por e-mail, por favor tente novamente mais tarde."));
        }

        $registerRequest->token_confirmation_email_send_at = \Carbon\Carbon::now();

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Token reenviado por email com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível realizar essa operação, por favor tente mais tarde."));
    }

    public function setRepresentativeGender(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'representative_gender_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!Gender::where('id', '=', $request->representative_gender_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Gênero não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_gender_id != $request->representative_gender_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_gender_id', $registerRequest->representative_gender_id, $request->representative_gender_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_gender_id = $request->representative_gender_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Gênero do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o gênero do representante, por favor tente novamente mais tarde."));
    }

    public function setMainActivity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'main_activity'            => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $main_activity = mb_strtoupper(Facilites::removeAccentAndSpecialCharacters($request->main_activity));

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->main_activity != $main_activity) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('main_activity', $registerRequest->main_activity, $main_activity, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->main_activity = $main_activity;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Atividade principal definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a atividade principal, por favor tente novamente mais tarde."));
    }

    public function setPartnerQuantity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'partners_quantity'        => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->partners_quantity != $request->partners_quantity) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('partners_quantity', $registerRequest->partners_quantity, $request->partners_quantity, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->partners_quantity = $request->partners_quantity;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Quantidade de sócios definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a quantidade de sócios, por favor tente novamente mais tarde."));
    }

    public function setEmployeeQuantity(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'employee_quantity'        => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->employee_quantity != $request->employee_quantity) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('employee_quantity', $registerRequest->employee_quantity, $request->employee_quantity, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->employee_quantity = $request->employee_quantity;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Quantidade de funcionários definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a quantidade de funcionários, por favor tente novamente mais tarde."));
    }

    public function setSite(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'site'                     => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $site = mb_strtolower($request->site);

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->site != $request->site) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('site', $registerRequest->site, $site, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->site = $site;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Site definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o site, por favor tente novamente mais tarde."));
    }

    public function setIndicationCode(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'indication_code'          => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->indication_code != $request->indication_code) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('indication_code', $registerRequest->indication_code, $request->indication_code, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->indication_code = mb_strtolower($request->indication_code);

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Código de indicação definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o código de indicação, por favor tente novamente mais tarde."));
    }

    public function setAccountType(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'account_type_id'  => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!RegisterRequestAccountType::where('id', '=', $request->account_type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "O tipo da conta não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->account_type_id != $request->account_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('account_type_id', $registerRequest->account_type_id, $request->account_type_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->account_type_id = $request->account_type_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Tipo da conta definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo da conta, por favor tente novamente mais tarde."));
    }

    public function setBillingPlan(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'               => ['required', 'integer'],
            'uuid'             => ['required', 'string'],
            'billing_plan_id'  => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if (!RegisterRequestBillingPlan::where('id', '=', $request->billing_plan_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "O plano da conta não foi localizado, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->billing_plan_id != $request->billing_plan_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('billing_plan_id', $registerRequest->billing_plan_id, $request->billing_plan_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->billing_plan_id = $request->billing_plan_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Plano da conta definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o plano da conta, por favor tente novamente mais tarde."));
    }

    public function setMaritalStatus(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                       => ['required', 'integer'],
            'uuid'                     => ['required', 'string'],
            'marital_status_id'        => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->marital_status_id != $request->marital_status_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('marital_status_id', $registerRequest->marital_status_id, $request->marital_status_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->marital_status_id = $request->marital_status_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Estado civil definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado civil, por favor tente novamente mais tarde."));
    }

    public function setRepresentativeMaritalStatus(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'representative_marital_status_id' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->representative_marital_status_id != $request->representative_marital_status_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_marital_status_id', $registerRequest->representative_marital_status_id, $request->representative_marital_status_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->representative_marital_status_id = $request->representative_marital_status_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Estado civil do representante definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado civil do representante, por favor tente novamente mais tarde."));
    }

    public function setManagerEmail(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string'],
            'manager_email'                    => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $managerDetailId = null;

        if ($managerDetail = ManagerDetail::where('email', '=', $request->manager_email)->first()) {
            $managerDetailId = $managerDetail->id;
        }

        $registerRequest->manager_detail_id = $managerDetailId;
        $registerRequest->manager_email = mb_strtolower($request->manager_email);

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Gerente definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o gerente, por favor tente novamente mais tarde."));
    }

    public function sendRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                               => ['required', 'integer'],
            'uuid'                             => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível reenviar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $registerRequest->send_at = \Carbon\Carbon::now();
        $registerRequest->status_id = $this->EnumStatusId('pendente');

        if (!$registerRequest->save()) {
            return response()->json(array("error" => "Não foi possível definir a solicitação como enviada, por favor tente novamente mais tarde."));
        }

        $this->sendRegisterRequestMail($registerRequest->id);

        return response()->json(array("success" => "Solicitação de cadastro realizada com sucesso, em breve retornaremos com uma posição."));
    }

    public function sendRegisterRequestMail($registerRequestId)
    {

        $registerRequest = new RegisterRequest();
        $registerRequest->id = $registerRequestId;
        $registerRequestData = $registerRequest->get()[0];


        $facilites = new Facilites();

        $bodyMessage = "
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                </tr>
            </table> <br>
            <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                <tr>
                    <td>
                        Uma nova solicitação de abertura de <b>Conta $registerRequestData->register_request_type_description</b> foi requisitada.<br><br>
                        <b>Dados da Solicitação</b>
                    </td>
                </tr>
            </table> <br>
        ";

        if ($registerRequestData->register_request_type_id == 1) {
            $bodyMessage .= "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='80%'><b>Nome</b></td>
                        <td><b>CPF</b></td>
                    </tr>
                    <tr>
                        <td width='80%'>$registerRequestData->name</td>
                        <td>" . $facilites->mask_cpf_cnpj($registerRequestData->cpf_cnpj) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='20%'><b>Data Nascimento</b></td>
                        <td width='20%'><b>Gênero</b></td>
                        <td width='20%'><b>Estado Civil</b></td>
                        <td><b>Nome da Mãe</b></td>
                    </tr>
                    <tr>
                        <td width='20%'>" . \Carbon\Carbon::parse($registerRequestData->birth_date)->format('d/m/Y') . "</td>
                        <td width='20%'>$registerRequestData->gender_description</td>
                        <td width='20%'>$registerRequestData->marital_status_description</td>
                        <td>$registerRequestData->mother_name</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>Documento</b></td>
                        <td width='25%'><b>Número Documento</b></td>
                        <td width='25%'><b>Renda Mensal</b></td>
                        <td><b>CNPJ do Emprego</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$registerRequestData->document_type_description</td>
                        <td width='25%'>$registerRequestData->document_number</td>
                        <td width='25%'>R$ " . number_format($registerRequestData->income, 2, ',', '.') . "</td>
                        <td>" . $facilites->mask_cpf_cnpj($registerRequestData->cpf_cnpj_work_company) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='50%'><b>Celular</b></td>
                        <td><b>E-Mail</b></td>
                    </tr>
                    <tr>
                        <td width='50%'>" . $facilites->mask_phone($registerRequestData->phone) . "</td>
                        <td>$registerRequestData->email</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='35%'><b>Endereço</b></td>
                        <td width='15%'><b>Nº</b></td>
                        <td width='20%'><b>Complemento</b></td>
                        <td><b>Bairro</b></td>
                    </tr>
                    <tr>
                        <td width='35%'>$registerRequestData->public_place $registerRequestData->address</td>
                        <td width='15%'>$registerRequestData->number</td>
                        <td width='20%'>$registerRequestData->complement</td>
                        <td>$registerRequestData->district</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='33%'><b>Cidade</b></td>
                        <td width='33%'><b>Estado</b></td>
                        <td><b>CEP</b></td>
                    </tr>
                    <tr>
                        <td width='33%'>$registerRequestData->city</td>
                        <td width='33%'>$registerRequestData->state_description</td>
                        <td>" . $facilites->mask_cep($registerRequestData->zip_code) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>ID Solicitação</b></td>
                        <td width='25%'><b>Iniciada Em</b></td>
                        <td width='25%'><b>Enviada Em</b></td>
                        <td><b>IP</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$registerRequestData->uuid</td>
                        <td width='25%'>" . \Carbon\Carbon::parse($registerRequestData->created_at)->format('d/m/Y H:i:s') . "</td>
                        <td width='25%'>" . \Carbon\Carbon::parse($registerRequestData->send_at)->format('d/m/Y H:i:s') . "</td>
                        <td>$registerRequestData->ip</td>
                    </tr>
                </table>
            ";

            $filesData = (object) [
                (object) [
                    "filename" => $registerRequestData->address_proof_s3_filename,
                    "prefixFileName" => "ComprovanteEndereço_",
                    "type"     => 8,
                ],
                (object) [
                    "filename" => $registerRequestData->document_front_s3_filename,
                    "prefixFileName" => "DocumentoFrente_",
                    "type"     => $registerRequestData->document_front_type_id

                ],
                (object) [
                    "filename" => $registerRequestData->document_verse_s3_filename,
                    "prefixFileName" => "DocumentoVerso_",
                    "type"     => $registerRequestData->document_verse_type_id
                ],
                (object) [
                    "filename" => $registerRequestData->selfie_s3_filename,
                    "prefixFileName" => "Selfie_",
                    "type" => 7
                ]
            ];
        } else {
            $bodyMessage .= "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='80%'><b>Razão Social</b></td>
                        <td><b>CNPJ</b></td>
                    </tr>
                    <tr>
                        <td width='80%'>$registerRequestData->name</td>
                        <td>" . $facilites->mask_cpf_cnpj($registerRequestData->cpf_cnpj) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>Natureza Jurídica</b></td>
                        <td width='25%'><b>Data de Fundação</b></td>
                        <td width='25%'><b>Segmento</b></td>
                        <td><b>Faturamento Mensal</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$registerRequestData->legal_nature_description</td>
                        <td width='25%'>" . \Carbon\Carbon::parse($registerRequestData->birth_date)->format('d/m/Y') . "</td>
                        <td width='25%'>$registerRequestData->main_activity</td>
                        <td>R$ " . number_format($registerRequestData->income, 2, ',', '.') . "</td>
                    </tr>
                </table>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='50%'><b>Celular</b></td>
                        <td><b>E-Mail</b></td>
                    </tr>
                    <tr>
                        <td width='50%'>" . $facilites->mask_phone($registerRequestData->phone) . "</td>
                        <td>$registerRequestData->email</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='35%'><b>Endereço</b></td>
                        <td width='15%'><b>Nº</b></td>
                        <td width='20%'><b>Complemento</b></td>
                        <td><b>Bairro</b></td>
                    </tr>
                    <tr>
                        <td width='35%'>$registerRequestData->public_place $registerRequestData->address</td>
                        <td width='15%'>$registerRequestData->number</td>
                        <td width='20%'>$registerRequestData->complement</td>
                        <td>$registerRequestData->district</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='33%'><b>Cidade</b></td>
                        <td width='33%'><b>Estado</b></td>
                        <td><b>CEP</b></td>
                    </tr>
                    <tr>
                        <td width='33%'>$registerRequestData->city</td>
                        <td width='33%'>$registerRequestData->state_description</td>
                        <td>" . $facilites->mask_cep($registerRequestData->zip_code) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td ><hr></td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td><b>Dados do Representante</b></td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='80%'><b>Nome</b></td>
                        <td><b>CPF</b></td>
                    </tr>
                    <tr>
                        <td width='80%'>$registerRequestData->representative_name</td>
                        <td>" . $facilites->mask_cpf_cnpj($registerRequestData->representative_cpf_cnpj) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='20%'><b>Data Nascimento</b></td>
                        <td width='20%'><b>Gênero</b></td>
                        <td width='20%'><b>Estado Civil</b></td>
                        <td><b>Nome da Mãe</b></td>
                    </tr>
                    <tr>
                        <td width='20%'>" . \Carbon\Carbon::parse($registerRequestData->representative_birth_date)->format('d/m/Y') . "</td>
                        <td width='20%'>$registerRequestData->representative_gender_description</td>
                        <td width='20%'>$registerRequestData->representative_marital_status_description</td>
                        <td>$registerRequestData->representative_mother_name</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='50%'><b>Documento</b></td>
                        <td ><b>Número Documento</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$registerRequestData->representative_document_type_description</td>
                        <td width='25%'>$registerRequestData->representative_document_number</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='50%'><b>Celular</b></td>
                        <td><b>E-Mail</b></td>
                    </tr>
                    <tr>
                        <td width='50%'>" . $facilites->mask_phone($registerRequestData->representative_phone) . "</td>
                        <td>$registerRequestData->representative_email</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='35%'><b>Endereço</b></td>
                        <td width='15%'><b>Nº</b></td>
                        <td width='20%'><b>Complemento</b></td>
                        <td><b>Bairro</b></td>
                    </tr>
                    <tr>
                        <td width='35%'>$registerRequestData->representative_public_place $registerRequestData->representative_address</td>
                        <td width='15%'>$registerRequestData->representative_number</td>
                        <td width='20%'>$registerRequestData->representative_complement</td>
                        <td>$registerRequestData->representative_district</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='33%'><b>Cidade</b></td>
                        <td width='33%'><b>Estado</b></td>
                        <td><b>CEP</b></td>
                    </tr>
                    <tr>
                        <td width='33%'>$registerRequestData->representative_city</td>
                        <td width='33%'>$registerRequestData->representative_state_description</td>
                        <td>" . $facilites->mask_cep($registerRequestData->representative_zip_code) . "</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='25%'><b>ID Solicitação</b></td>
                        <td width='25%'><b>Iniciada Em</b></td>
                        <td width='25%'><b>Enviada Em</b></td>
                        <td><b>IP</b></td>
                    </tr>
                    <tr>
                        <td width='25%'>$registerRequestData->uuid</td>
                        <td width='25%'>" . \Carbon\Carbon::parse($registerRequestData->created_at)->format('d/m/Y h:i:s') . "</td>
                        <td width='25%'>" . \Carbon\Carbon::parse($registerRequestData->send_at)->format('d/m/Y h:i:s') . "</td>
                        <td>$registerRequestData->ip</td>
                    </tr>
                </table>
                <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td width='40%'><b>E-Mail Gerente</b></td>
                    </tr>
                    <tr>
                        <td width='40%'>$registerRequestData->manager_email</td>
                    </tr>
                </table>
            ";


            $filesDatas = [];
            $fileDataFinal = [];

            array_push($filesDatas, [
                (object) [
                    "filename" => $registerRequestData->social_contract_s3_filename,
                    "prefixFileName" => "ContratoSocial_",
                    "type" => 11
                ],
                (object) [
                    "filename" => $registerRequestData->billing_statement_s3_filename,
                    "prefixFileName" => "DeclaraçãoFaturamento_",
                    "type" => 14
                ],
                (object) [
                    "filename" => $registerRequestData->address_proof_s3_filename,
                    "prefixFileName" => "ComprovanteEndereço_",
                    "type" =>  12,
                ],
                (object) [
                    "filename" => $registerRequestData->representative_address_proof_s3_filename,
                    "prefixFileName" => "ComprovanteEnderecoRepresentante_",
                    "type" => 8
                ],
                (object) [
                    "filename" => $registerRequestData->representative_document_front_s3_filename,
                    "prefixFileName" => "DocumentoRepresentanteFrente_",
                    "type" => $registerRequestData->representative_document_front_type_id
                ],
                (object) [
                    "filename" => $registerRequestData->representative_document_verse_s3_filename,
                    "prefixFileName" => "DocumentoRepresentanteVerso_",
                    "type" => $registerRequestData->representative_document_verse_type_id
                ],
            ]);

            $registerRequestRepresentatives                      = new RegisterRequestRepresentative();
            $registerRequestRepresentatives->register_request_id = $registerRequestId;
            $registerRequestRepresentatives->onlyActive          = 1;


            if (count($registerRequestRepresentatives->get()) > 0) {
                foreach ($registerRequestRepresentatives->get() as $registerRequestRepresentative) {

                    array_push($filesDatas, [
                        (object) [
                            "filename" => $registerRequestRepresentative->address_proof_s3_filename,
                            "prefixFileName" => "ComprovanteEndereço_" . $registerRequestRepresentative->address_proof_s3_filename,
                            "type" =>  8,
                        ],
                        (object) [
                            "filename" => $registerRequestRepresentative->document_front_s3_filename,
                            "prefixFileName" => "DocumentoFrente_" . $registerRequestRepresentative->document_front_s3_filename,
                            "type"     => $registerRequestRepresentative->document_front_type_id
                        ],
                        (object) [
                            "filename" => $registerRequestRepresentative->document_verse_s3_filename,
                            "prefixFileName" => "DocumentoVerso_" . $registerRequestRepresentative->document_verse_s3_filename,
                            "type"     => $registerRequestRepresentative->document_verse_type_id
                        ],
                    ]);
                }
            }

            $fileDataFinal = array_merge([], ...$filesDatas);
        }

        $bodyMessage .= "
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
                        <br>
                        Acesse a conta para aprovar ou recusar a solicitação.
                    </td>
                </tr>
            </table>
        ";


        if (isset($filesData)) { //register_request_type = 1
            $filedata = $this->createZipFile($filesData);
        } else { //register_request_type = 2
            $filedata = $this->createZipFile($fileDataFinal);
        }

        $this->sendEmail('Nova Solicitação de Cadastro', $bodyMessage, $filedata->filename, $filedata->zipFile64);

        Storage::disk('zip')->delete($filedata->filename);

        return true;
    }

    public function sendEmail($subject, $bodyMessage, $attach_file_name, $attach_file_base64)
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

    public function setObservation(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'          => ['required', 'integer'],
            'uuid'        => ['required', 'string'],
            'observation' => ['nullable']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já analisada, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->observation != $request->observation) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('observation', $registerRequest->observation, $request->observation, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->observation = $request->observation;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Observação definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a observação, por favor tente novamente mais tarde."));
    }

    public function zipFile($data)
    {
        $SimpleZip       = new SimpleZip();
        $createZipFolder = $SimpleZip->createZipFolder();

        if (!$createZipFolder->success) {
            return (object) ["success" => false];
        }

        foreach ($data as $d) {

            if (!$documentType = DocumentType::where('id', '=', $d['type'])->first()) {
                $error[] = (object) [
                    "success" => false,
                    "filename" => $d['filename']
                ];
            }
            $fileAmazon           = new AmazonS3();
            $fileAmazon->path     = $documentType->s3_path;
            $fileAmazon->fileName = $d['filename'];

            if (!$fileAmazon = $fileAmazon->fileDownAmazon()) {
                $error[] = (object) [
                    "success" => false,
                    "filename" => $d['filename']
                ];
            }

            if (!Storage::disk('zip')->put($createZipFolder->folderName . '/' . $d['filename'], base64_decode($fileAmazon->file64))) {
                $error[] = (object) [
                    "success" => false,
                    "filename" => $d['filename']
                ];
            }
        }

        $SimpleZip->fileData = (object) [
            "folderName" => $createZipFolder->folderName,
            "deleteFiles" => true
        ];

        $createZipFile = $SimpleZip->createZipFile();

        if (!$createZipFile->success) {
            return response()->json(array("error" => "Não foi possível criar o arquivo zip"));
        }

        if (!Storage::disk('zip')->put($createZipFile->zipFileName, base64_decode($createZipFile->zipFile64))) {
            $error[] = (object) [
                "success" => false,
                "filename" => $d['filename']
            ];
        }

        return (object) [
            "success" => true,
            "filepath" =>  '../storage/app/zip/' . $createZipFile->zipFileName,
            "filename" =>  $createZipFile->zipFileName,
            "zipFile64" =>  $createZipFile->zipFile64,
        ];
    }

    public function createZipFile($filesData)
    {
        $SimpleZip       = new SimpleZip();
        $createZipFolder = $SimpleZip->createZipFolder();

        if ($createZipFolder->success) {

            foreach ($filesData as $file) {

                if ($documentType = DocumentType::where('id', '=', $file->type)->first()) {
                    $fileAmazon           = new AmazonS3();
                    $fileAmazon->path     = $documentType->s3_path;
                    $fileAmazon->fileName = $file->filename;

                    if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                        Storage::disk('zip')->put($createZipFolder->folderName . '/' . $file->prefixFileName . $file->filename, base64_decode($fileAmazon->file64));
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
            "filepath" =>  '../storage/app/zip/' . $createZipFile->zipFileName,
            "filename" =>  $createZipFile->zipFileName,
            "zipFile64" =>  $createZipFile->zipFile64,
        ];
    }

    public function getRegisterRequestDocuments(Request $request)
    {


        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }

        $addressProof = [];
        $documentFront = [];
        $documentVerse = [];
        $selfie = [];
        $kyc = [];

        $billingStatement = [];
        $socialContract = [];
        $electionMinutes = [];
        $letterOfAttorney = [];
        $otherDocument1 = [];
        $otherDocument2 = [];
        $otherDocument3 = [];
        $representativeAddressProof = [];
        $representativeDocumentFront = [];
        $representativeDocumentVerse = [];
        $representativeSelfie = [];



        if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
            $addressProofDocumentTypeId = 8;
            if ($registerRequest->register_request_type_id == 2) {
                $addressProofDocumentTypeId = 12;
            }
            $documentType = DocumentType::where('id', '=', $addressProofDocumentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $addressProof = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_Residencia_' . $registerRequest->address_proof_s3_filename
                ];
            }
        }

        if ($registerRequest->document_front_s3_filename != null and $registerRequest->document_front_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequest->document_front_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->document_front_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentFront = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Frente_' . $registerRequest->document_front_s3_filename
                ];
            }
        }

        if ($registerRequest->document_verse_s3_filename != null and $registerRequest->document_verse_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequest->document_verse_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->document_verse_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentVerse = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Verso_' . $registerRequest->document_verse_s3_filename
                ];
            }
        }

        if ($registerRequest->selfie_s3_filename != null and $registerRequest->selfie_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 7)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->selfie_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $selfie = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Selfie_' . $registerRequest->selfie_s3_filename
                ];
            }
        }

        if ($registerRequest->kyc_s3_filename != null and $registerRequest->kyc_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 27)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->kyc_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $kyc = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'KYC_' . $registerRequest->kyc_s3_filename
                ];
            }
        }

        if ($registerRequest->social_contract_s3_filename != null and $registerRequest->social_contract_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 11)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->social_contract_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $socialContract = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Contrato_Social_' . $registerRequest->social_contract_s3_filename
                ];
            }
        }

        if ($registerRequest->election_minutes_s3_filename != null and $registerRequest->election_minutes_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 30)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->election_minutes_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $electionMinutes = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Ata_Eleicao_' . $registerRequest->election_minutes_s3_filename
                ];
            }
        }

        if ($registerRequest->letter_of_attorney_s3_filename != null and $registerRequest->letter_of_attorney_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 13)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->letter_of_attorney_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $letterOfAttorney = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Procuracao_' . $registerRequest->letter_of_attorney_s3_filename
                ];
            }
        }

        if ($registerRequest->other_document_1_s3_filename != null and $registerRequest->other_document_1_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 31)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->other_document_1_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $otherDocument1 = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Outros_Documentos_1_' . $registerRequest->other_document_1_s3_filename
                ];
            }
        }

        if ($registerRequest->other_document_2_s3_filename != null and $registerRequest->other_document_2_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 31)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->other_document_2_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $otherDocument2 = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Outros_Documentos_2_' . $registerRequest->other_document_2_s3_filename
                ];
            }
        }

        if ($registerRequest->other_document_3_s3_filename != null and $registerRequest->other_document_3_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 31)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->other_document_3_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $otherDocument3 = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Outros_Documentos_3_' . $registerRequest->other_document_3_s3_filename
                ];
            }
        }

        if ($registerRequest->billing_statement_s3_filename != null and $registerRequest->billing_statement_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 14)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->billing_statement_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $billingStatement = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Declaração_de_Faturamento_' . $registerRequest->billing_statement_s3_filename
                ];
            }
        }

        if ($registerRequest->representative_address_proof_s3_filename != null and $registerRequest->representative_address_proof_s3_filename != '') {

            $documentType = DocumentType::where('id', '=', 8)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->representative_address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $representativeAddressProof = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_Endereco_Representante_' . $registerRequest->representative_address_proof_s3_filename
                ];
            }
        }

        if ($registerRequest->representative_document_front_s3_filename != null and $registerRequest->representative_document_front_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequest->representative_document_front_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->representative_document_front_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $representativeDocumentFront = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Representante_Frente_' . $registerRequest->representative_document_front_s3_filename
                ];
            }
        }

        if ($registerRequest->representative_document_verse_s3_filename != null and $registerRequest->representative_document_verse_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequest->representative_document_verse_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->representative_document_verse_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $representativeDocumentVerse = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Representante_Verso_' . $registerRequest->representative_document_verse_s3_filename
                ];
            }
        }

        if ($registerRequest->representative_selfie_s3_filename != null and $registerRequest->representative_selfie_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 7)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->representative_selfie_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $representativeSelfie = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Selfie_Representante_' . $registerRequest->representative_selfie_s3_filename
                ];
            }
        }

        return response()->json(array(
            'success' => 'Documentos recuperados com sucesso',
            'data' => [
                'addressProof' => $addressProof,
                'documentFront' => $documentFront,
                'documentVerse' => $documentVerse,
                'selfie' => $selfie,
                'kyc' => $kyc,

                'billingStatement' => $billingStatement,
                'socialContract' => $socialContract,
                'electionMinutes' => $electionMinutes,
                'letterOfAttorney' => $letterOfAttorney,
                'otherDocument1' => $otherDocument1,
                'otherDocument2' => $otherDocument2,
                'otherDocument3' => $otherDocument3,

                'representativeAddressProof' => $representativeAddressProof,
                'representativeDocumentFront' => $representativeDocumentFront,
                'representativeDocumentVerse' => $representativeDocumentVerse,
                'representativeSelfie' => $representativeSelfie
            ]
        ));
    }

    public function getRegisterRequestPfDocs(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do cliente, por favor tente novamente mais tarde."));
        }

        $jsonData_front = $registerRequest->document_front_ocr;
        $jsonData_back = $registerRequest->document_back_ocr;

        $value_front = null;
        $value_back = null;

        // Decodifica o JSON dos documentos frontal e traseiro
        $data_front = json_decode($jsonData_front, true);
        $data_back = json_decode($jsonData_back, true);

        // Verifica se o JSON foi decodificado corretamente e extrai o CPF
        if (json_last_error() === JSON_ERROR_NONE && isset($data_front['consult_ocr']['document_cpf'])) {
            $value_front = $data_front['consult_ocr']['document_cpf'];

        }

        if (json_last_error() === JSON_ERROR_NONE && isset($data_back['consult_ocr']['document_cpf'])) {
            $value_back = $data_back['consult_ocr']['document_cpf'];

        }

        // Verifica se algum CPF foi encontrado nos documentos
        $response_document_check_cpf = (!empty($value_front) && $value_front != "") || (!empty($value_back) && $value_back != "") ? true : false;


        $documentFront = [];
        $documentVerse = [];
        $selfie = [];
        $kyc = [];
        $addressProof = [];


        if ($registerRequest->document_front_s3_filename != null and $registerRequest->document_front_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequest->document_front_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->document_front_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentFront = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Frente_' . $registerRequest->document_front_s3_filename,
                    'document_type' => 'front'
                ];
            }
        }

        if ($registerRequest->document_verse_s3_filename != null and $registerRequest->document_verse_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequest->document_verse_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->document_verse_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentVerse = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Verso_' . $registerRequest->document_verse_s3_filename,
                    'document_type' => 'verse'
                ];
            }
        }

        if ($registerRequest->selfie_s3_filename != null and $registerRequest->selfie_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 7)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->selfie_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $selfie = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Selfie_' . $registerRequest->selfie_s3_filename,
                    'document_type' => 'selfie'
                ];
            }
        }

        if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 8)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $addressProof = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_residencia_' . $registerRequest->address_proof_s3_filename,
                    'document_type' => 'addressProof'
                ];
            }
        }

        if ($registerRequest->kyc_s3_filename != null and $registerRequest->kyc_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 27)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->kyc_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $kyc = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'KYC_' . $registerRequest->kyc_s3_filename,
                    'document_type' => 'kyc'
                ];
            }
        }


        return response()->json(array(
            'success' => 'Documentos recuperados com sucesso',
            'data' => [
                $documentFront,
                $documentVerse,
                $kyc,
                $selfie,
                $addressProof,
                $response_document_check_cpf
            ]
        ));
    }

    public function getRegisterRequestPjDocs(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do cliente, por favor tente novamente mais tarde."));
        }        


        $socialContract = [];
        $addressProof = [];
        $billingStatement = [];
        $electionMinutes = [];
        $letterOfAttorney = [];
        $otherDocument1 = [];
        $otherDocument2 = [];
        $otherDocument3 = [];

        if ($registerRequest->social_contract_s3_filename != null and $registerRequest->social_contract_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 11)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->social_contract_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $socialContract = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Contrato_Social_' . $registerRequest->social_contract_s3_filename,
                    'document_type' => 'social_contract'
                ];
            }
        }

        if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 12)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $addressProof = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_Residencia_' . $registerRequest->address_proof_s3_filename,
                    'document_type' => 'addressProof'
                ];
            }
        }

        if ($registerRequest->billing_statement_s3_filename != null and $registerRequest->billing_statement_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 14)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->billing_statement_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $billingStatement = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Declaração_de_Faturamento_' . $registerRequest->billing_statement_s3_filename,
                    'document_type' => 'billingStatement'
                ];
            }
        }

        if ($registerRequest->election_minutes_s3_filename != null and $registerRequest->election_minutes_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 30)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->election_minutes_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $electionMinutes = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Ata_Eleicao_' . $registerRequest->election_minutes_s3_filename,
                    'document_type' => 'electionMinutes'
                ];
            }
        }

        if ($registerRequest->letter_of_attorney_s3_filename != null and $registerRequest->letter_of_attorney_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 13)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->letter_of_attorney_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $letterOfAttorney = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Procuracao_' . $registerRequest->letter_of_attorney_s3_filename,
                    'document_type' => 'letterOfAttorney'
                ];
            }
        }

        if ($registerRequest->other_document_1_s3_filename != null and $registerRequest->other_document_1_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 31)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->other_document_1_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $otherDocument1 = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Outros_Documentos_1_' . $registerRequest->other_document_1_s3_filename,
                    'document_type' => 'otherDocs1'
                ];
            }
        }

        if ($registerRequest->other_document_2_s3_filename != null and $registerRequest->other_document_2_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 31)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->other_document_2_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $otherDocument2 = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Outros_Documentos_2_' . $registerRequest->other_document_2_s3_filename,
                    'document_type' => 'otherDocs2'
                ];
            }
        }

        if ($registerRequest->other_document_3_s3_filename != null and $registerRequest->other_document_3_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 31)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequest->other_document_3_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $otherDocument3 = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Outros_Documentos_3_' . $registerRequest->other_document_3_s3_filename,
                    'document_type' => 'otherDocs3'
                ];
            }
        }
    

        return response()->json(array(
            'success' => 'Documentos recuperados com sucesso',
            'data' => [
                $socialContract,
                $addressProof,
                $billingStatement,
                $electionMinutes,
                $letterOfAttorney,
                $otherDocument1,
                $otherDocument2,
                $otherDocument3
            ]
        ));
    }

    public function denyRegisterRequest(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer'],
            'uuid' => ['required', 'string'],
            'sendMail' => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data, id and uuid is required");
        }

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, ou ele já foi recusado, por favor verifique os dados informados e tente novamente mais tarde."));
        }


        if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já finalizada, por favor verifique os dados informados ou inicie uma nova solicitação."));
        }


        $registerRequest->status_id = $this->EnumStatusId('naoAprovado');
        $registerRequest->deleted_at = \Carbon\Carbon::now();
        $registerRequest->refused_by = Auth::user()->id;
        $registerRequest->analyzed_at = \Carbon\Carbon::now();

        if ($registerRequest->register_request_type_id == 1) {
            $registerRequest->account_phase_id = $this->EnumFaseId('recusada');
            $registerRequest->action_status_id = $this->EnumStatusAcaoId('concluido');
        }

        if (!$registerRequest->save()) {
            return response()->json(array("error" => "Não foi possível rejeitar a requisição de cadastro, por favor tente novamente mais tarde"));
        }

        $payload = ['company_uuid' => $registerRequest->uuid_company_cad, 'success' => false];
        // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');

        $response = Http::post(config('register_request.url') . 'approved-refused-companies', $payload);


        Log::debug('Envio para sistema de cadastro de solicitação de cadastro negada');
        Log::debug(json_encode($registerRequest));
        Log::debug('uuid_company_cad:');
        Log::debug($registerRequest->uuid_company_cad);
        Log::debug('Resposta do sistema de cadastro:');
        Log::debug($response->body());


        if ($request->sendMail == 1) {

            if ($registerRequest->register_request_type_id == 1) {
                $name = $registerRequest->name;
            } else if ($registerRequest->register_request_type_id == 2) {
                $name = $registerRequest->representative_name;
            } else {
                $name = '';
            }

            $bodyMessage = "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                    </tr>
                </table> <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td>
                            Olá, <b>$name</b>. Como vai? Lamentamos em informar que a sua solicitação de abertura de conta foi negada. Mesmo com os documentos e informações disponibilizados por você, não foi possível realizar a abertura da sua conta  no momento.
                            <br><br>
                            Nossa instituição é regida por elevados padrões de segurança e transparência para garantir aos nossos clientes uma experiência única de atendimento personalizado com total segurança e flexibilidade da gestão financeira.
                            <br><br>
                            Contamos com a sua compreensão e esperamos que em breve possamos potencializar o crescimento da sua empresa por meio da expertise em gestão financeira do nosso time de especialistas.
                            <br><br><br>Atenciosamente,
                            <br>Equipe iP4y
                        </td>
                    </tr>
                </table>
            ";

            $apiSendGrid = new ApiSendgrid();
            $apiSendGrid->to_email    = $registerRequest->email;
            $apiSendGrid->to_name     = $registerRequest->name;
            $apiSendGrid->to_cc_email = 'ragazzi@dinari.com.br';
            $apiSendGrid->to_cc_name  = 'Ragazzi';
            $apiSendGrid->subject     = 'Solicitação de Cadastro iP4y';
            $apiSendGrid->content     = $bodyMessage;
            if ($apiSendGrid->sendSimpleEmail()) {
                return response()->json(array("success" => "Solicitação de cadastro rejeitada e envio de e-mail para o titular realizados com sucesso."));
            }
            return response()->json(array("error" => "Solicitação de cadastro rejeitada com sucesso, porém não foi possível enviar o e-mail para o titular."));
        }

        return response()->json(array("success" => "Solicitação de cadastro rejeitada com sucesso."));
    }
    public function rejectedInconsistent(Request $request)
    {

        try{
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        $registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first();
        $cpf = Facilites::mask_cpf_cnpj($registerRequest->cnpj);
        $manager = User::where('id', $registerRequest['id_manager'])->first();

        if (!$registerRequest) {
            return response()->json(array("error" => "Não foi possível localizar o registro, ou ele já foi recusado, por favor verifique os dados informados e tente novamente mais tarde."));
        }


        if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já finalizada, por favor verifique os dados informados ou inicie uma nova solicitação."));
        }


        $registerRequest->status_id = $this->EnumStatusId('naoAprovado');
        $registerRequest->deleted_at = Carbon::now();
        $registerRequest->refused_by = Auth::user()->id;
        $registerRequest->analyzed_at = Carbon::now();

        if ($registerRequest->register_request_type_id == 1) {
            $registerRequest->account_phase_id = $this->EnumFaseId('recusada');
            $registerRequest->action_status_id = $this->EnumStatusAcaoId('concluido');
        }

        if (!$registerRequest->save()) {
            return response()->json(array("error" => "Não foi possível rejeitar a requisição de cadastro, por favor tente novamente mais tarde"));
        }

        $payload = ['company_uuid' => $registerRequest->uuid_company_cad, 'success' => false];
        $response = Http::post(config('register_request.url') . 'rejected-inconsistent', $payload);
        Log::debug('Envio para sistema de cadastro de solicitação de cadastro negada por inconsistencia');
        Log::debug(json_encode($payload));
        Log::debug('Resposta do sistema de cadastro:');
        Log::debug($response->body());

            if ($registerRequest->register_request_type_id == 1) {
                $name = $registerRequest->name;
            } else if ($registerRequest->register_request_type_id == 2) {
                $name = $registerRequest->representative_name;
            } else {
                $name = '';
            }

            $bodyMessage = "
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='791' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td><img src='https://conta.ip4y.com.br/image/tarjaDinariBank.png' border='0' width='792' height='133,41'></td>
                    </tr>
                </table> <br>
                <table align='center' border='0' cellspacing='0' cellpadding='0' width='1000' style='width:593.0pt;border-collapse:collapse'>
                    <tr>
                        <td>
                            Olá, <b>$name</b>. Como vai? Lamentamos em informar que a sua solicitação de abertura de conta foi negada. Mesmo com os documentos e informações disponibilizados por você, não foi possível realizar a abertura da sua conta  no momento.
                            <br><br>
                            Nossa instituição é regida por elevados padrões de segurança e transparência para garantir aos nossos clientes uma experiência única de atendimento personalizado com total segurança e flexibilidade da gestão financeira.
                            <br><br>
                            Contamos com a sua compreensão e esperamos que em breve possamos potencializar o crescimento da sua empresa por meio da expertise em gestão financeira do nosso time de especialistas.
                            <br><br><br>Atenciosamente,
                            <br>Equipe iP4y
                        </td>
                    </tr>
                </table>
            ";

            $apiSendGrid = new ApiSendgrid();
            $apiSendGrid->to_email    = $registerRequest->email;
            $apiSendGrid->to_name     = $registerRequest->name;
            $apiSendGrid->to_cc_email = 'ragazzi@dinari.com.br';
            $apiSendGrid->to_cc_name  = 'Ragazzi';
            $apiSendGrid->subject     = 'Solicitação de Cadastro iP4y';
            $apiSendGrid->content     = $bodyMessage;

            if ($apiSendGrid->sendSimpleEmail() ) {
                return response()->json(array("success" => "Solicitação de cadastro rejeitada e envio de e-mail para o titular realizados com sucesso."));
            } else if(!$apiSendGrid->sendSimpleEmail()){
                return response()->json(array("error" => "Solicitação de cadastro rejeitada com sucesso, porém não foi possível enviar o e-mail para o titular."));
            } 

        return response()->json(array("success" => "Solicitação de cadastro rejeitada com sucesso."));
        }catch(Exception $e){
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
            return response()->json(array("error" => "Problema ao rejeitar o cadastro."));

        }
    }

    public function refuseIndividual(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer'],
            'uuid' => ['required', 'string'],
            'refusal_reason' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data, id, uuid and refusal reason is required");
        }

        if ($registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNotNull('deleted_at')->first()) {
            return response()->json(array("error" => "O sócio/usuário já foi previamente recusado."));
        }

        if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }

        $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();


        $registerRequestRepresentative->refusal_reason = $request->refusal_reason;
        $registerRequestRepresentative->status = false;
        $registerRequestRepresentative->evaluator_by_user_id = Auth::user()->id;


        if (!$registerRequestRepresentative->save()) {
            return response()->json(array("error" => "Não foi possível recusar o usuário/sócio, por favor tente novamente mais tarde"));
        }


        $payload = [
            'accepted' => false,
            'uuid_other_representatives_cad' => $registerRequestRepresentative->uuid_other_representatives_cad,
            'uuid_representative_cad' => $registerRequestRepresentative->uuid_representative_cad,
            'refusal_reason' => $registerRequestRepresentative->refusal_reason
        ];

        //TODO $encrypt not found precisa ajustar essa lógica para retornar esse
        // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');

        $response = Http::post(config('register_request.url') . 'approved-refused-users-partners', $payload);

        Log::debug('Envio sócio/usuário recusado individualmente');
        Log::debug(json_encode($registerRequestRepresentative));
        Log::debug($response->body());
        Log::debug($payload);


        if ($request->is_representative == 1) {
            return response()->json(array("success" => "Representante recusado com sucesso."));
        } else if ($request->is_representative == 0) {
            return response()->json(array("success" => "Outro representante recusado com sucesso."));
        }
    }

    public function allowRegisterRequest(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }


        if ($registerRequest->register_account_user_created_at == null) {
            return response()->json(array("error" => "Antes de aprovar a requisição de cadastro, crie o cadastro e a conta."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já finalizada, por favor verifique os dados informados ou inicie uma nova solicitação."));
        }

        if ($registerRequest->register_request_type_id == 1 && $registerRequest->account_phase_id == $this->EnumFaseId('fase2') && $registerRequest->action_status_id == $this->EnumStatusAcaoId('aguardandoCliente')) {
            return response()->json(array("error" => "Não é possível aprovar a solicitação com o status de ação aguardando cliente."));
        }

        if ($registerRequest->analyzed_by != Auth::user()->id) {
            return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
        }


        //validar todas as informações necessárias para abertura de cadastro
        if ($registerRequest->cpf_cnpj == null or  $registerRequest->cpf_cnpj == '') {
            return response()->json(array("error" => "CPF/CNPJ do cadastro não definido"));
        }

        if ($registerRequest->name == null or  $registerRequest->name == '') {
            return response()->json(array("error" => "Nome/Razão Social do cadastro não definido"));
        }

        if ($registerRequest->zip_code == null or  $registerRequest->zip_code == '') {
            return response()->json(array("error" => "CEP do cadastro não definido"));
        }

        if ($registerRequest->register_request_type_id == 1) {

            if ($registerRequest->email == null or  $registerRequest->email == '') {
                return response()->json(array("error" => "E-Mail do cadastro não definido"));
            }

            if ($registerRequest->phone == null or  $registerRequest->phone == '') {
                return response()->json(array("error" => "Telefone do cadastro não definido"));
            }

            if ($registerRequest->birth_date == null or  $registerRequest->birth_date == '') {
                return response()->json(array("error" => "Data de Nascimento não definida"));
            }

            if ($registerRequest->selfie_s3_filename == null or $registerRequest->selfie_s3_filename == '') {
                return response()->json(array("error" => "Selfie não importada para o cadastro"));
            }

            if ($registerRequest->document_front_s3_filename == null or $registerRequest->document_front_s3_filename == '') {
                return response()->json(array("error" => "Frente do documento não importada para o cadastro"));
            }

            if ($registerRequest->document_verse_s3_filename == null or $registerRequest->document_verse_s3_filename == '') {
                return response()->json(array("error" => "Verso do documento não importado para o cadastro"));
            }

            if ($registerRequest->kyc_s3_filename == null or $registerRequest->kyc_s3_filename == '') {
                return response()->json(array("error" => "KYC não importado para o cadastro"));
            }

            $registerRequest->account_phase_id = $this->EnumFaseId('aprovada');
            $registerRequest->action_status_id = $this->EnumStatusAcaoId('concluido');
            $createUserPf = $this->createUserAccPf($request, $registerRequest, $checkAccount);


            if (!$createUserPf->success) {
                return response()->json(['error' => $createUserPf->message]);
            }
        } else if ($registerRequest->register_request_type_id == 2) {

            if ($registerRequest->address_proof_s3_filename == null or $registerRequest->address_proof_s3_filename == '') {
                return response()->json(array("error" => "Comprovante de endereço não importado para o cadastro"));
            }

            if ($registerRequest->social_contract_s3_filename == null or $registerRequest->social_contract_s3_filename == '') {
                return response()->json(array("error" => "Contrato social não importado para o cadastro"));
            }
        }

        $registerRequest->status_id = $this->EnumStatusId('aprovado');
        $registerRequest->approved_by = Auth::user()->id;
        $registerRequest->analyzed_at = \Carbon\Carbon::now();

        if ($registerRequest->register_request_type_id == 2) {

            $this->sendEmailFinishRegister($registerRequest->email, $registerRequest->representative_name, $registerRequest->name);

            $approvedUsers = RegisterRequestRepresentative::where('register_request_id', $registerRequest->id)->where('status', '1')->get();

            if ($approvedUsers->isNotEmpty()) {
                foreach ($approvedUsers as $user) {
                    $this->sendEmailToApprovedUsers($user->email, $user->name, $registerRequest->name, $registerRequest->email);
                }
            }
        }

        if ($registerRequest->save()) {

            try {

                $payload = ['company_uuid' => $registerRequest->uuid_company_cad, 'success' => true];
                // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');

                $response = Http::post(config('register_request.url') . 'approved-refused-companies', $payload);


                Log::debug('Envio para sistema de cadastro de solicitação de cadastro aprovada');
                Log::debug(json_encode($registerRequest));
                Log::debug('uuid_company_cad:');
                Log::debug($registerRequest->uuid_company_cad);
                Log::debug('Resposta do sistema de cadastro:');
                Log::debug($response->body());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Ocorreu um erro ao aprovar o cadastro. Por favor tente novamente mais tarde.']);
            }
        }

        if (isset($createUserPf)) {
            return response()->json(array("success" => $createUserPf->message));
        }

        return response()->json(array("success" => "Cadastro aprovado com sucesso, lembre-se de conceder as permissões para os usuários"));
    }
  
    public function allowRegisterRequestEmployee(Request $request){

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer'],
            'uuid' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(array( "Not Found | Invalid Data, id and uuid is required"), 404);
        }

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar a solicitação de cadastro, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
        
        if ($registerRequest->register_request_type_id != 3) {
            return response()->json(array("error" => "Não foi possivel realizar a aprovação desta conta."));
        }

        if ($registerRequest->kyc_s3_filename == null || $registerRequest->kyc_s3_filename == '') {
            return response()->json(array("error" => "É necessário o envio do documento KYC."));
        }

        if (!$user = User::where('cpf_cnpj', '=', $registerRequest->cpf_cnpj)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
       
        if (!$userMaster = UserMaster::where('user_id', '=', $user->id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
       
        if (!$userRelationship = UserRelationship::where('user_master_id', '=', $userMaster->id)->where('relationship_id', 7)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o vínculo do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
        
        if (!$account = Account::where('id', $userRelationship->account_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar a conta do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
        

        if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já finalizada, por favor verifique os dados informados ou inicie uma nova solicitação."));
        }

        if ($registerRequest->analyzed_by != Auth::user()->id) {
            return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
        }

        //validar todas as informações necessárias para abertura de cadastro
        if ($registerRequest->cpf_cnpj == null or  $registerRequest->cpf_cnpj == '') {
            return response()->json(array("error" => "CPF/CNPJ do cadastro não definido"));
        }

        if ($registerRequest->name == null or  $registerRequest->name == '') {
            return response()->json(array("error" => "Nome/Razão Social do cadastro não definido"));
        }

        if ($registerRequest->zip_code == null or  $registerRequest->zip_code == '') {
            return response()->json(array("error" => "CEP do cadastro não definido"));
        }

        if ($registerRequest->email == null or  $registerRequest->email == '') {
            return response()->json(array("error" => "E-Mail do cadastro não definido"));
        }

        if ($registerRequest->phone == null or  $registerRequest->phone == '') {
            return response()->json(array("error" => "Telefone do cadastro não definido"));
        }

        if ($registerRequest->birth_date == null or  $registerRequest->birth_date == '') {
            return response()->json(array("error" => "Data de Nascimento não definida"));
        }

        if ($registerRequest->selfie_s3_filename == null or $registerRequest->selfie_s3_filename == '') {
            return response()->json(array("error" => "Selfie não importada para o cadastro"));
        }

        if ($registerRequest->document_front_s3_filename == null or $registerRequest->document_front_s3_filename == '') {
            return response()->json(array("error" => "Frente do documento não importada para o cadastro"));
        }

        if ($registerRequest->document_verse_s3_filename == null or $registerRequest->document_verse_s3_filename == '') {
            return response()->json(array("error" => "Verso do documento não importado para o cadastro"));
        }

        if (!$register = Register::where('cpf_cnpj', $registerRequest->cpf_cnpj)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
       
        if (!$registerMaster = RegisterMaster::where('register_id', $register->id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }

        $permission = new Permission();
        $permission->relationship_id = 7;
        $permission->onlyActive = 1;
        $permission->usr_rltnshp_id = $userRelationship->id;
        $permissions = $permission->getPermission();
   
        $error   = [];
        $success = [];
        $permission = '';

        foreach ($permissions as $req) {

            if ($usr_rltnshp_prmssn = UsrRltnshpPrmssn::where('permission_id','=',$req->id)->where('usr_rltnshp_id','=',$userRelationship->id)->whereNull('deleted_at')->first()) {

                array_push($error,[
                    "error"  => "Permissão já concedida para o vínculo",
                    "id"     => $usr_rltnshp_prmssn->permission_id]
                );

            } else {

                if (!$permission = Permission::where('id','=',$req->id)->whereNull('deleted_at')->first()) {
                    array_push($error,["error" => "Poxa, não localizamos a permissão, reveja os dados informados e tente novamente", "id"=>$req]);
                    continue;
                }

                if (!UserRelationship::where('id','=',$userRelationship->id)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Poxa, não localizamos o relacionamento da permissão com usuario, reveja os dados informados e tente novamente"));
                }

                if (UsrRltnshpPrmssn::create([
                    'usr_rltnshp_id' => $userRelationship->id,
                    'prmssn_grp_id'  => null,
                    'permission_id'  => $permission->id,
                    'created_at'     => \Carbon\Carbon::now(),
                ])) {
                    array_push($success,["success" => "Permissão concedida ao vínculo com sucesso", "id"=>$permission->id]);
                } else {
                    array_push($error,["error" => "Poxa, não foi possível conceder a permissão para o vínculo no momento, por favor tente novamente mais tarde", "id"=>$permission->id]);
                }

            }
        }

        if ($error != null) {
            return response()->json(array(
                "error"        => "Atenção, não foi possível conceder algumas permissões",
                "error_list"   => $error,
                "success_list" => $success,
            ));
        }

        if (!$address = RegisterAddress::where('register_master_id', $registerMaster->id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }
        
        if (!$registerDetail = RegisterDetail::where('register_master_id', $registerMaster->id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do usuário, por favor tente novamente mais tarde ou entre em contato com o suporte."));
        }

        $pixKey = new IncludePixKeyClass();
        $result = $pixKey->execute(
            $account->id,
            '0001',
            $account->account_number,
            $account->created_at->format('Y-m-d'),
            $register->cpf_cnpj,
            $registerDetail->name,
            $account->fantasy_name,
            $address->public_place.' '.$address->address.' '.$address->number,
            $address->city,
            $address->disctrict,
            $address->zip_code,
            5,
            '' 
        );

        if(!$result['success']){  
            return response()->json(array(
                "error"        => "Atenção, não foi possivel gerar chave pix do usuário",
                "error_list"   => $error,
                "success_list" => $success,
            ));
        }
        

        $registerRequest->status_id = $this->EnumStatusId('aprovado');
        $registerRequest->account_phase_id = $this->EnumFaseId('aprovada'); 
        $registerRequest->action_status_id = $this->EnumStatusAcaoId('concluido'); 
        $registerRequest->approved_by = Auth::user()->id;
        $registerRequest->analyzed_at = \Carbon\Carbon::now();
        $registerRequest->employee_account_status_id = 4;

        $registerRequest->save();

        return response()->json(array("success" => "Cadastro aprovado com sucesso."));        
    }

    public function createUserAccPfEmployee($request, $registerRequest) {

        $registerService = new RegisterService();

        $observationText                                = 'Aprovado individualmente na solicitação de cadastro - conta pf';
        $registerService->cpf_cnpj                      = $registerRequest->cpf_cnpj;
        $registerService->name                          = $registerRequest->name . ' ' . $registerRequest->surname;
        $registerService->register_birth_date           = $registerRequest->birth_date;
        $registerService->master_id                     = 1 ;
        $registerService->manager_email                 = $registerRequest->manager_email;
        $registerService->register_address              = $registerRequest->address;
        $registerService->register_address_state_id     = $registerRequest->state_id;
        $registerService->register_address_public_place = $registerRequest->public_place;
        $registerService->register_address_number       = $registerRequest->number;
        $registerService->register_address_complement   = $registerRequest->complement;
        $registerService->register_address_district     = $registerRequest->district;
        $registerService->register_address_city         = $registerRequest->city;
        $registerService->register_address_zip_code     = $registerRequest->zip_code;
        $registerService->register_address_observation  = $observationText;
        $registerService->register_address_main         = true;
        $registerService->register_email                = $registerRequest->email;
        $registerService->register_email_observation    = $observationText;
        $registerService->register_email_main           = true;
        $registerService->register_phone                = $registerRequest->phone;
        $registerService->register_phone_observation    = $observationText;
        $registerService->register_phone_main           = true;
        $registerService->register_gender_id            = $registerRequest->gender_id;
        $registerService->register_marital_status_id    = $registerRequest->marital_status_id;
        $registerService->register_mother_name          = $registerRequest->mother_name;
        $registerService->register_father_name          = $registerRequest->father_name;
        $registerService->register_observation          = $observationText;
        $registerService->register_rg_number            = $registerRequest->document_number;

        $createRepresentativeRegister = $registerService->returnRegister();

        if (!$createRepresentativeRegister->success) {
            return response()->json(array("error" => $createRepresentativeRegister->message));
        }

        //Create and approve user
        $createUser = $this->createrUser($createRepresentativeRegister->register_master->id, $registerRequest);
        if (!$createUser->success) {
            return (object) [
                "success" => false, 
                "message" => $createUser->message
            ];
        }              

        $cnpj = preg_replace('/[^0-9]/', '', $registerRequest->cpf_cnpj);
        $register_company = Register::where('cpf_cnpj', '=', $cnpj)->first();
        $register_master_company = RegisterMaster::where('register_id', '=', $register_company->id)->where('master_id', '=', 1 )->first();
        $register_master_company_id = $register_master_company->id;
        $account = Account::where('register_master_id', '=', $register_master_company_id)->where('master_id', '=', 1 )->first();

        if (!isset($account)) {
            return (object) [
                "success" => false, 
                "message" => "É preciso que seja criada a conta para o vínculo de usuário."
            ];
        }

        $relationshipId = 7; // funcionario
        // Create User Relationship
        $this->createUserRelationship(1 , $createUser->data->user_master_id, $account->id, $relationshipId, $registerRequest);          

        $user = User::where('cpf_cnpj', '=', $registerRequest->cpf_cnpj)->first();
        
        try {
            $registerRequest->pf_acc_is_user_created = true;
            $registerRequest->pf_acc_user_created_at = \Carbon\Carbon::now();
            $registerRequest->pf_acc_status = true;
            $registerRequest->pf_user_id = $user->id;
            $registerRequest->employee_account_status_id = 2;
            $registerRequest->save();            

            $user->email_verified_at = \Carbon\Carbon::now();
            $user->phone_verified_at = \Carbon\Carbon::now();
            $user->status = 1;
            $user->welcome_mail_send_at = \Carbon\Carbon::now();
            $user->save();

            if ($updateUserMaster = UserMaster::where('id', '=', $createUser->data->user_master_id)->where('user_id', '=', $user->id)->first()) {
                $updateUserMaster->status_id = 1;
                $updateUserMaster->save();
            }

            $registerRequest->welcome_mail_send_at = \Carbon\Carbon::now();
            $registerRequest->pf_acc_user_id = $user->id;
            $registerRequest->pf_acc_user_master_id = $createUser->data->user_master_id;
            $registerRequest->save();      
            
            $sendWelcomeEmail = $this->sendWelcomeMailEmployee($request, $user);

            if(!$sendWelcomeEmail->success) {
                return (object) [
                    "success" => false, 
                    "message" => $sendWelcomeEmail->message
                ];
            }

            return (object) [
                "success" => true, 
                "message" => "Cadastro e usuário aprovados e criados com sucesso, lembre-se de conceder as permissões."
            ]; 
                                        

                                                                                                                         
                

        } catch (\Exception $e) {
            Log::debug('debug atual');
            Log::debug($e);
            return (object) [
                "success" => false, 
                "message" => "Ocorreu um erro ao aprovar e criar o usuário. Por favor tente novamente mais tarde."
            ];
        }
                    
    }

    public function sendWelcomeMailEmployee($request, $user_) {

        $permitted_strings           = '@!$%';
        $permitted_numbers           = '0123456789';
        $newPassword                 = str_shuffle(substr(str_shuffle($permitted_strings), 0, 2).substr(str_shuffle($permitted_numbers), 0, 4));

        if(User::where('id','=', $user_->id)->where('unique_id','=',$user_->unique_id)->count() == 0){
            return (object) [
                "success" => false, 
                "message" => "Usuário não localizado."
            ];
        } else {
            $user = User::where('id','=', $user_->id)->where('unique_id','=',$user_->unique_id)->first();
            $user->password             = Hash::make($newPassword);
            $user->email_verified_at    = null;
            $user->phone_verified_at    = null;
            $user->login_attempt        = 0;
            $user->status               = 1;
            $user->welcome_mail_send_at = \Carbon\Carbon::now();
            if(!$user->save()){
                return (object) [
                    "success" => false, 
                    "message" => "Erro ao redefinir a senha."
                ];
            } else {
                
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
                        if($managerDetail->manager_email != ''){

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

                    return (object) [
                        "success" => true, 
                        "message" => "Email de ativação de conta enviado com sucesso para o usuário."
                    ];
                }else{
                    return (object) [
                        "success" => false, 
                        "message" => "Não foi possível enviar o email de conta para o usuário."
                    ];
                }
            }

        }
    }

    public function createUserAccPf($request, $registerRequest, $checkAccount) {

        $registerService = new RegisterService();

        $observationText                                = 'Aprovado individualmente na solicitação de cadastro - conta pf';
        $registerService->cpf_cnpj                      = $registerRequest->cpf_cnpj;
        $registerService->name                          = $registerRequest->name . ' ' . $registerRequest->surname;
        $registerService->register_birth_date           = $registerRequest->birth_date;
        $registerService->master_id                     = $checkAccount->master_id;
        $registerService->manager_email                 = $registerRequest->manager_email;
        $registerService->register_address              = $registerRequest->address;
        $registerService->register_address_state_id     = $registerRequest->state_id;
        $registerService->register_address_public_place = $registerRequest->public_place;
        $registerService->register_address_number       = $registerRequest->number;
        $registerService->register_address_complement   = $registerRequest->complement;
        $registerService->register_address_district     = $registerRequest->district;
        $registerService->register_address_city         = $registerRequest->city;
        $registerService->register_address_zip_code     = $registerRequest->zip_code;
        $registerService->register_address_observation  = $observationText;
        $registerService->register_address_main         = true;
        $registerService->register_email                = $registerRequest->email;
        $registerService->register_email_observation    = $observationText;
        $registerService->register_email_main           = true;
        $registerService->register_phone                = $registerRequest->phone;
        $registerService->register_phone_observation    = $observationText;
        $registerService->register_phone_main           = true;
        $registerService->register_gender_id            = $registerRequest->gender_id;
        $registerService->register_marital_status_id    = $registerRequest->marital_status_id;
        $registerService->register_mother_name          = $registerRequest->mother_name;
        $registerService->register_father_name          = $registerRequest->father_name;
        $registerService->register_observation          = $observationText;
        $registerService->register_rg_number            = $registerRequest->document_number;

        $createRepresentativeRegister = $registerService->returnRegister();

        if (!$createRepresentativeRegister->success) {
            return response()->json(array("error" => $createRepresentativeRegister->message));
        }

        if ($registerRequest->pf_acc_is_user_created != true) {
            if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, 8, $registerRequest->address_proof_s3_filename, $registerRequest);
            }

            if ($registerRequest->selfie_s3_filename != null and $registerRequest->selfie_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, 7, $registerRequest->selfie_s3_filename, $registerRequest);
            }

            if ($registerRequest->document_front_s3_filename != null and $registerRequest->document_front_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, $registerRequest->document_front_type_id, $registerRequest->document_front_s3_filename, $registerRequest);
            }

            if ($registerRequest->document_verse_s3_filename != null and $registerRequest->document_verse_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, $registerRequest->document_verse_type_id, $registerRequest->document_verse_s3_filename, $registerRequest);
            }

            if ($registerRequest->kyc_s3_filename != null and $registerRequest->kyc_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, 27, $registerRequest->kyc_s3_filename, $registerRequest);
            }
        }

        //Create and approve user
        $createUser = $this->createrUser($createRepresentativeRegister->register_master->id, $registerRequest);
        if (!$createUser->success) {
            return (object) [
                "success" => false,
                "message" => $createUser->message
            ];
        }


        $cnpj = preg_replace('/[^0-9]/', '', $registerRequest->cpf_cnpj);
        $register_company = Register::where('cpf_cnpj', '=', $cnpj)->first();
        $register_master_company = RegisterMaster::where('register_id', '=', $register_company->id)->where('master_id', '=', $checkAccount->master_id )->first();
        $register_master_company_id = $register_master_company->id;
        $account = Account::where('register_master_id', '=', $register_master_company_id)->where('master_id', '=', $checkAccount->master_id )->first();

        if (!isset($account)) {
            return (object) [
                "success" => false,
                "message" => "É preciso que seja criada a conta para o vínculo de usuário."
            ];
        }

        $relationshipId = 4; // pf
        // Create User Relationship
        $this->createUserRelationship($checkAccount->master_id, $createUser->data->user_master_id, $account->id, $relationshipId, $registerRequest);

        $user = User::where('cpf_cnpj', '=', $registerRequest->cpf_cnpj)->first();

        try {

            $registerRequest->pf_acc_is_user_created = true;
            $registerRequest->pf_acc_user_created_at = \Carbon\Carbon::now();
            $registerRequest->pf_acc_user_created_by = $checkAccount->user_id;
            $registerRequest->pf_acc_status = true;
            $registerRequest->pf_acc_evaluator_by_user_id = Auth::user()->id;
            $registerRequest->pf_user_id = $user->id;
            $registerRequest->save();

            $payload = [
                'accepted' => true,
                'uuid_representative_cad' => $registerRequest->pf_acc_uuid_representative_cad
            ];

            //TODO $encrypt not found precisa ajustar essa lógica para retornar esse
            // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');

            $response = Http::post(config('register_request.url') . 'approved-refused-users-partners', $payload);

            Log::debug('Envio usuário conta pf aprovado e criado');
            Log::debug(json_encode($registerRequest));
            Log::debug($response->body());
            Log::debug($payload);


            if ($user->welcome_mail_send_at == null) {

                $user->email_verified_at = \Carbon\Carbon::now();
                $user->phone_verified_at = \Carbon\Carbon::now();
                $user->status = 1;
                $user->accepted_term = 1;
                $user->welcome_mail_send_at = \Carbon\Carbon::now();
                $user->save();

                if ($updateUserMaster = UserMaster::where('id', '=', $createUser->data->user_master_id)->where('user_id', '=', $user->id)->first()) {
                    $updateUserMaster->status_id = 1;
                    $updateUserMaster->save();
                }

                $registerRequest->welcome_mail_send_at = \Carbon\Carbon::now();
                $registerRequest->pf_acc_user_id = $user->id;
                $registerRequest->pf_acc_user_master_id = $createUser->data->user_master_id;
                $registerRequest->save();

                $sendWelcomeEmail = $this->sendWelcomeMail($request, $user);

                if (!$sendWelcomeEmail->success) {
                    return (object) [
                        "success" => false,
                        "message" => $sendWelcomeEmail->message
                    ];
                }

                return (object) [
                    "success" => true,
                    "message" => "Cadastro e usuário aprovados e criados com sucesso, lembre-se de conceder as permissões."
                ];
            }

            return (object) [
                "success" => true,
                "message" => "Cadastros e usuários aprovados e criados com sucesso, porém, como o usuário já possuía cadastro, não foi enviado o e-mail de boas de vindas."
            ];
        } catch (\Exception $e) {
            return (object) [
                "success" => false,
                "message" => "Ocorreu um erro ao aprovar e criar o usuário. Por favor tente novamente mais tarde."
            ];
        }
    }

    public function sendWelcomeMail($request, $user_)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [142];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return (object) [
                "success" => false,
                "message" => $checkAccount->message
            ];
        }
        // -------------- Finish Check Account Verification -------------- //

        $permitted_strings           = '@!$%';
        $permitted_numbers           = '0123456789';
        $newPassword                 = str_shuffle(substr(str_shuffle($permitted_strings), 0, 2) . substr(str_shuffle($permitted_numbers), 0, 4));

        if (User::where('id', '=', $user_->id)->where('unique_id', '=', $user_->unique_id)->count() == 0) {
            return (object) [
                "success" => false,
                "message" => "Usuário não localizado."
            ];
        } else {
            $user = User::where('id', '=', $user_->id)->where('unique_id', '=', $user_->unique_id)->first();
            $user->password             = Hash::make($newPassword);
            $user->email_verified_at    = null;
            $user->phone_verified_at    = null;
            $user->login_attempt        = 0;
            $user->status               = 1;
            $user->welcome_mail_send_at = \Carbon\Carbon::now();
            if (!$user->save()) {
                return (object) [
                    "success" => false,
                    "message" => "Erro ao redefinir a senha."
                ];
            } else {

                if ($user_master = UserMaster::where('user_id', '=', $user->id)->whereNull('deleted_at')->first()) {

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
                foreach ($user->getUserAccounts() as $usr) {
                    $bg = (($color % 2) == 0) ? "#f2f2f2" : "#d9d9d9";

                    $message .= "
                                <tr style='height:15.0pt'>
                                    <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Conta</font> </span> </b></td>
                                    <td  width='552' nowrap='' valign='bottom' style='width:414.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$usr->accnts_account_number - $usr->rgstr_dtls_name</font></span></td>
                                </tr>
                                ";
                    $color++;
                }
                $message .= "
                                <tr style='height:15.0pt'>
                                    <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>E-mail de validação</font> </span> </b></td>
                                    <td  width='552'   nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$user->email</font></span></td>
                                </tr>
                                <tr style='height:15.0pt'>
                                    <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Celular Token</font> </span></b></td>
                                    <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>" . Facilites::mask_phone($user->phone) . "</font></span></td>
                                </tr>
                                <tr style='height:15.0pt'>
                                    <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>CPF Usuário </font></span></b></td>
                                    <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>" . Facilites::mask_cpf_cnpj($user->cpf_cnpj) . "</font></span></td>
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

                if ($apiSendGrid->sendSimpleEmail()) {
                    $managerDetails = ManagerDetail::getUserAccountManager($user->id);
                    foreach ($managerDetails as $managerDetail) {

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
                        foreach ($user->getUserAccounts() as $usr) {
                            $bg = (($color % 2) == 0) ? "#f2f2f2" : "#d9d9d9";
                            $message2 .= "
                                            <tr style='height:15.0pt'>
                                                <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Conta</font> </span> </b></td>
                                                <td  width='552' nowrap='' valign='bottom' style='width:414.0pt;background:$bg ;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$usr->accnts_account_number - $usr->rgstr_dtls_name</font></span></td>
                                            </tr>
                                            ";
                            $color++;
                        }
                        $message2 .= "
                                            <tr style='height:15.0pt'>
                                                <td  width='239px' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>E-mail de validação</font> </span> </b></td>
                                                <td  width='552'   nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>$user->email</font></span></td>
                                            </tr>
                                            <tr style='height:15.0pt'>
                                                <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>Celular Token</font> </span></b></td>
                                                <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#f2f2f2;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>" . Facilites::mask_phone($user->phone) . "</font></span></td>
                                            </tr>
                                            <tr style='height:15.0pt'>
                                                <td width='239' nowrap='' valign='bottom' style='width:179.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><b><span style='color:black'><font face='Arial'>CPF Usuário </font></span></b></td>
                                                <td width='552' nowrap='' valign='bottom' style='width:414.0pt;background:#d9d9d9;padding:0cm 3.5pt 0cm 3.5pt;height:15.0pt'><span style='color:black'><font face='Arial'>" . Facilites::mask_cpf_cnpj($user->cpf_cnpj) . "</font></span></td>
                                            </tr>
                                        </table>
                                        <br><br>
                                    </td>
                                </tr>
                            </table>
                        ";
                        if ($managerDetail->manager_email != '') {

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

                    return (object) [
                        "success" => true,
                        "message" => "Email de ativação de conta enviado com sucesso para o usuário."
                    ];
                } else {
                    return (object) [
                        "success" => false,
                        "message" => "Não foi possível enviar o email de conta para o usuário."
                    ];
                }
            }
        }
    }

    public function sendEmailToApprovedUsers($email, $name, $companyName, $request_user_email)
    {

        $apiKey = config('mail.sendgrid_api_key');

        // Defina a URL do endpoint SendGrid
        $url = 'https://api.sendgrid.com/v3/mail/send';

        // Construa o corpo da solicitação em JSON
        $data = [
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $email,
                            "name" => $name
                        ]
                    ],
                    "cc" => [
                        [
                            "email" => $request_user_email
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "noreply@ip4y.com.br",
                            "name"  => "iP4y"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "first_name" => $name
                    ]
                ]
            ],
            "from" => [
                "email" => "noreply@ip4y.com.br",
                "name" => "iP4y - Instituição de Pagamento"
            ],
            "subject" => "Sua conta já está pronta!",
            "template_id" => 'd-6297a1b20d64489896bb43eb5dfd22d4',
        ];

        // Configurar a solicitação usando a fachada Http do Laravel
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, $data);

            // Check if the request was not successful (e.g., status code other than 2xx)
            if ($response->failed()) {
                return response()->json(['error' => 'Falha ao enviar o e-mail de boas de vindas', 'response' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ocorreu um erro ao enviar o e-mail de boas vindas. Por favor tente novamente mais tarde.']);
        }

        Log::debug("Email Sendgrid boas-vindas enviado, email => $email, nome => $name, empresa => $companyName, response => " . $response->body());

        if ($response->successful()) {
            return (object) ["success" => true];
        }
        return (object) ["success" => false];
    }

    public function sendEmailFinishRegister($email, $name, $companyName)
    {

        $apiKey = config('mail.sendgrid_api_key');

        // Defina a URL do endpoint SendGrid
        $url = 'https://api.sendgrid.com/v3/mail/send';

        // Construa o corpo da solicitação em JSON
        $data = [
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $email,
                            "name" => $name
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "noreply@ip4y.com.br",
                            "name"  => "iP4y"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "business_name" => $companyName
                    ]
                ]
            ],
            "from" => [
                "email" => "noreply@ip4y.com.br",
                "name" => "iP4y - Instituição de Pagamento"
            ],
            "subject" => "Sua conta já está pronta!",
            "template_id" => 'd-2cdb5dce495340989ee2e12df36712aa',
        ];

        // Configurar a solicitação usando a fachada Http do Laravel
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        Log::debug("Email Sendgrid conta aprovada enviado, email => $email, nome => $name, empresa => $companyName, response => " . $response->body());

        if ($response->successful()) {
            return (object) ["success" => true];
        }
        return (object) ["success" => false];
    }

    public function approveIndividual(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        $registerRequestRepresentative = RegisterRequestRepresentative::where('id', $request->id)
            ->where('uuid', $request->uuid)
            ->whereNull('deleted_at')
            ->first();

        if (!$registerRequestRepresentative) {
            return response()->json(["error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."]);
        }


        $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();


        if ($registerRequest->register_account_user_created_at == null) {
            return response()->json(array("error" => "Antes de aprovar e criar o usuário, crie o cadastro e a conta"));
        }

        //validar todas as informações necessárias para abertura de cadastro
        if ($registerRequestRepresentative->cpf_cnpj == null or  $registerRequestRepresentative->cpf_cnpj == '') {
            return response()->json(array("error" => "CPF/CNPJ do sócio/usuário do cadastro não definido"));
        }

        if ($registerRequestRepresentative->name == null or  $registerRequestRepresentative->name == '') {
            return response()->json(array("error" => "Nome do sócio/usuário do cadastro não definido"));
        }

        if ($registerRequestRepresentative->zip_code == null or  $registerRequestRepresentative->zip_code == '') {
            return response()->json(array("error" => "CEP do sócio/usuário do cadastro não definido"));
        }

        if ($registerRequestRepresentative->birth_date == null or  $registerRequestRepresentative->birth_date == '') {
            return response()->json(array("error" => "Data de Nascimento do sócio/usuário do cadastro não definida"));
        }


        $registerServiceToRepresentative = new RegisterService();

        $observationText                                                = 'Aprovado individualmente na solicitação de cadastro';
        $registerServiceToRepresentative->cpf_cnpj                      = $registerRequestRepresentative->cpf_cnpj;
        $registerServiceToRepresentative->name                          = $registerRequestRepresentative->name . ' ' . $registerRequestRepresentative->surname;
        $registerServiceToRepresentative->register_birth_date           = $registerRequestRepresentative->birth_date;
        $registerServiceToRepresentative->master_id                     = $checkAccount->master_id;
        $registerServiceToRepresentative->manager_email                 = $registerRequestRepresentative->manager_email;
        $registerServiceToRepresentative->register_address              = $registerRequestRepresentative->address;
        $registerServiceToRepresentative->register_address_state_id     = $registerRequestRepresentative->state_id;
        $registerServiceToRepresentative->register_address_public_place = $registerRequestRepresentative->public_place;
        $registerServiceToRepresentative->register_address_number       = $registerRequestRepresentative->number;
        $registerServiceToRepresentative->register_address_complement   = $registerRequestRepresentative->complement;
        $registerServiceToRepresentative->register_address_district     = $registerRequestRepresentative->district;
        $registerServiceToRepresentative->register_address_city         = $registerRequestRepresentative->city;
        $registerServiceToRepresentative->register_address_zip_code     = $registerRequestRepresentative->zip_code;
        $registerServiceToRepresentative->register_address_observation  = $observationText;
        $registerServiceToRepresentative->register_address_main         = true;
        $registerServiceToRepresentative->register_email                = $registerRequestRepresentative->email;
        $registerServiceToRepresentative->register_email_observation    = $observationText;
        $registerServiceToRepresentative->register_email_main           = true;
        $registerServiceToRepresentative->register_phone                = $registerRequestRepresentative->phone;
        $registerServiceToRepresentative->register_phone_observation    = $observationText;
        $registerServiceToRepresentative->register_phone_main           = true;
        $registerServiceToRepresentative->register_gender_id            = $registerRequestRepresentative->gender_id;
        $registerServiceToRepresentative->register_marital_status_id    = $registerRequestRepresentative->marital_status_id;
        $registerServiceToRepresentative->register_mother_name          = $registerRequestRepresentative->mother_name;
        $registerServiceToRepresentative->register_observation          = $observationText;
        $registerServiceToRepresentative->register_rg_number            = $registerRequestRepresentative->document_number;

        $createRepresentativeRegister = $registerServiceToRepresentative->returnRegister();

        if (!$createRepresentativeRegister->success) {
            return response()->json(array("error" => $createRepresentativeRegister->message));
        }

        if ($registerRequestRepresentative->is_user_created != true) {
            if ($registerRequestRepresentative->address_proof_s3_filename != null and $registerRequestRepresentative->address_proof_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, 8, $registerRequestRepresentative->address_proof_s3_filename, $registerRequestRepresentative);
            }

            if ($registerRequestRepresentative->selfie_s3_filename != null and $registerRequestRepresentative->selfie_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, 7, $registerRequestRepresentative->selfie_s3_filename, $registerRequestRepresentative);
            }

            if ($registerRequestRepresentative->document_front_s3_filename != null and $registerRequestRepresentative->document_front_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, $registerRequestRepresentative->document_front_type_id, $registerRequestRepresentative->document_front_s3_filename, $registerRequestRepresentative);
            }

            if ($registerRequestRepresentative->document_verse_s3_filename != null and $registerRequestRepresentative->document_verse_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, $registerRequestRepresentative->document_verse_type_id, $registerRequestRepresentative->document_verse_s3_filename, $registerRequestRepresentative);
            }

            if ($registerRequestRepresentative->wedding_certificate_s3_filename != null and $registerRequestRepresentative->wedding_certificate_s3_filename != '') {
                $this->createDocument($createRepresentativeRegister->register_master->id, $checkAccount->master_id, 10, $registerRequestRepresentative->wedding_certificate_s3_filename, $registerRequestRepresentative);
            }
        }


        $registerMasterIdToCreateUser = $createRepresentativeRegister->register_master->id;


        if ($request->is_representative == 0 && $registerRequestRepresentative->will_be_user == 0) { //se for outro rep, e nao usuário
            return response()->json(array("success" => "Apenas o cadastro foi criado, pois sócio/representante em questão não será usuário."));
        }


        //Create and approve user
        $createUser = $this->createrUser($registerMasterIdToCreateUser, $registerRequestRepresentative);
        if (!$createUser->success) {
            return response()->json(array("error" => $createUser->message));
        }

        $relationshipId = 3;
        if (strlen($registerRequest->cpf_cnpj) == 11) {
            $relationshipId = 4;
        }


        $cnpj = preg_replace('/[^0-9]/', '', $registerRequest->cpf_cnpj);
        $register_company = Register::where('cpf_cnpj', '=', $cnpj)->first();
        $register_master_company = RegisterMaster::where('register_id', '=', $register_company->id)->where('master_id', '=', $checkAccount->master_id)->first();
        $register_master_company_id = $register_master_company->id;
        $account = Account::where('register_master_id', '=', $register_master_company_id)->where('master_id', '=', $checkAccount->master_id)->first();

        if (!isset($account)) {
            return response()->json(array("error" => "É preciso que seja criada a conta para o vínculo de usuário."));
        }

        // Create User Relationship
        $createUserRelationship = $this->createUserRelationship($checkAccount->master_id, $createUser->data->user_master_id, $account->id, $relationshipId, $registerRequestRepresentative);

        if ($createUserRelationship->success) {
            $registerRequestRepresentative->user_relationship_created = 1;
            $registerRequestRepresentative->save();
        }

        try {

            $updateRegisterRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $registerRequestRepresentative->id)->first();

            $updateRegisterRequestRepresentative->is_user_created = true;
            $updateRegisterRequestRepresentative->user_created_at = \Carbon\Carbon::now();
            $updateRegisterRequestRepresentative->user_created_by = $checkAccount->user_id;
            $updateRegisterRequestRepresentative->status = true;
            $updateRegisterRequestRepresentative->evaluator_by_user_id = Auth::user()->id;
            $updateRegisterRequestRepresentative->save();

            $payload = [
                'accepted' => true,
                'uuid_other_representatives_cad' => $updateRegisterRequestRepresentative->uuid_other_representatives_cad,
                'uuid_representative_cad' => $updateRegisterRequestRepresentative->uuid_representative_cad
            ];

            //TODO $encrypt not found precisa ajustar essa lógica para retornar esse
            // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');

            $response = Http::post(config('register_request.url') . 'approved-refused-users-partners', $payload);

            Log::debug('Envio sócio/usuário aprovado e criado individualmente');
            Log::debug(json_encode($updateRegisterRequestRepresentative));
            Log::debug($response->body());
            Log::debug($payload);


            // Lógica para envio de e-mail para usuário aprovado
            // Se a solicitação de cadastro estiver definida como aprovada
            if ($registerRequest->status_id == $this->EnumStatusId('aprovado')) {

                // 1 - Setar usuário com validado (celular, email, termo de uso)

                if ($user = User::where('cpf_cnpj', '=', $registerRequestRepresentative->cpf_cnpj)->first()) {

                    if ($user->welcome_mail_send_at == null) {
                        $user->email_verified_at = \Carbon\Carbon::now();
                        $user->phone_verified_at = \Carbon\Carbon::now();
                        $user->status = 1;
                        $user->accepted_term = 1;
                        $user->welcome_mail_send_at = \Carbon\Carbon::now();
                        $user->save();

                        if ($updateUserMaster = UserMaster::where('id', '=', $createUser->data->user_master_id)->where('user_id', '=', $user->id)->first()) {
                            $updateUserMaster->status_id = 1;
                            $updateUserMaster->save();
                        }

                        $updateRegisterRequestRepresentative->user_id = $user->id;
                        $updateRegisterRequestRepresentative->user_master_id = $createUser->data->user_master_id;
                        $updateRegisterRequestRepresentative->save();

                        // 2 - Enviar e-mail de boas vindas para usuário
                        $apiKey = config('mail.sendgrid_api_key');

                        // Defina a URL do endpoint SendGrid
                        $url = 'https://api.sendgrid.com/v3/mail/send';

                        // Construa o corpo da solicitação em JSON
                        $data = [
                            "personalizations" => [
                                [
                                    "to" => [
                                        [
                                            "email" => $registerRequestRepresentative->email,
                                            "name" => $registerRequestRepresentative->name
                                        ]
                                    ],
                                    "cc" => [
                                        [
                                            "email" => $registerRequest->email
                                        ]
                                    ],
                                    "bcc" => [
                                        [
                                            "email" => "noreply@ip4y.com.br",
                                            "name"  => "iP4y"
                                        ]
                                    ],
                                    "dynamic_template_data" => [
                                        "first_name" => $registerRequestRepresentative->name
                                    ]
                                ]
                            ],
                            "from" => [
                                "email" => "noreply@ip4y.com.br",
                                "name" => "iP4y - Instituição de Pagamento"
                            ],
                            "subject" => "Sua conta já está pronta!",
                            "template_id" => 'd-6297a1b20d64489896bb43eb5dfd22d4',
                        ];

                        // Configurar a solicitação usando a fachada Http do Laravel
                        try {
                            $response = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Content-Type' => 'application/json',
                            ])->post($url, $data);

                            // Check if the request was not successful (e.g., status code other than 2xx)
                            if ($response->failed()) {
                                return response()->json(['error' => 'Falha ao enviar o e-mail de boas de vindas', 'response' => $response->json()], $response->status());
                            }
                        } catch (\Exception $e) {
                            return response()->json(['error' => 'Ocorreu um erro ao enviar o e-mail de boas vindas. Por favor tente novamente mais tarde.']);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ocorreu um erro ao aprovar e criar o usuário. Por favor tente novamente mais tarde.']);
        }

        return response()->json(array("success" => "Usuário aprovado e criado com sucesso, lembre-se de conceder as permissões."));
    }

    public function createAccountEmployee($registerRequest)
    {

        if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível aprovar uma solicitação que já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
        }

        //validar todas as informações necessárias para abertura de cadastro
        if ($registerRequest->cpf_cnpj == null or  $registerRequest->cpf_cnpj == '') {
            return response()->json(array("error" => "CPF/CNPJ do cadastro não definido"));
        }

        if ($registerRequest->name == null or  $registerRequest->name == '') {
            return response()->json(array("error" => "Nome/Razão Social do cadastro não definido"));
        }

        if ($registerRequest->zip_code == null or  $registerRequest->zip_code == '') {
            return response()->json(array("error" => "CEP do cadastro não definido"));
        }

        if ($registerRequest->email == null or  $registerRequest->email == '') {
            return response()->json(array("error" => "E-Mail do cadastro não definido"));
        }

        if ($registerRequest->phone == null or  $registerRequest->phone == '') {
            return response()->json(array("error" => "Telefone do cadastro não definido"));
        }

        if ($registerRequest->selfie_s3_filename == null or $registerRequest->selfie_s3_filename == '') {
            return response()->json(array("error" => "Selfie não importado para o cadastro"));
        }

        if ($registerRequest->document_front_s3_filename == null or $registerRequest->document_front_s3_filename == '') {
            return response()->json(array("error" => "Frente do documento não importado para o cadastro"));
        }

        if ($registerRequest->document_verse_s3_filename == null or $registerRequest->document_verse_s3_filename == '') {
            return response()->json(array("error" => "Verso do documento não importado para o cadastro"));
        }

        $registerService = new RegisterService();

        $observationText                                = 'Criado na solicitação de cadastro';
        $registerService->cpf_cnpj                      = $registerRequest->cpf_cnpj;
        $registerService->name                          = $registerRequest->name . ' ' . $registerRequest->surname;
        $registerService->master_id                     = 1;
        $registerService->manager_email                 = $registerRequest->manager_email;
        $registerService->register_address              = $registerRequest->address;
        $registerService->register_address_state_id     = $registerRequest->state_id;
        $registerService->register_address_public_place = $registerRequest->public_place;
        $registerService->register_address_number       = $registerRequest->number;
        $registerService->register_address_complement   = $registerRequest->complement;
        $registerService->register_address_district     = $registerRequest->district;
        $registerService->register_address_city         = $registerRequest->city;
        $registerService->register_address_zip_code     = $registerRequest->zip_code;
        $registerService->register_address_observation  = $observationText;
        $registerService->register_address_main         = true;
        $registerService->register_email                = $registerRequest->email; 

        $registerService->register_email_observation    = $observationText;
        $registerService->register_email_main           = true;
        $registerService->register_phone                = $registerRequest->phone;
        $registerService->register_phone_observation    = $observationText;
        $registerService->register_phone_main           = true;
        $registerService->register_mother_name          = $registerRequest->mother_name;
        $registerService->register_birth_date           = $registerRequest->birth_date;
        $registerService->register_gender_id            = $registerRequest->gender_id;
        $registerService->register_marital_status_id    = $registerRequest->marital_status_id;
        $registerService->register_pep                  = $registerRequest->pep;
        $registerService->register_income               = $registerRequest->income;
        $registerService->register_observation          = $observationText;
        $registerService->register_branch_activity      = $registerRequest->main_activity;
        $registerService->register_foundation_date      = $registerRequest->birth_date;
        $registerService->register_revenue              = $registerRequest->income;
        $registerService->register_rg_number            = $registerRequest->document_number;

        if($registerRequest->register_request_type_id == 1) {
            $registerService->register_birth_date           = $registerRequest->birth_date;
            $registerService->register_father_name          = $registerRequest->father_name;
        }


        $createRegister = $registerService->returnRegister();

        if (!$createRegister->success) {
            return response()->json(array("error" => $createRegister->message));
        }

        if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
            $this->createDocument($createRegister->register_master->id, 1, 8, $registerRequest->address_proof_s3_filename, $registerRequest);
        }

        if ($registerRequest->selfie_s3_filename != null and $registerRequest->selfie_s3_filename != '') {
            $this->createDocument($createRegister->register_master->id, 1, 7, $registerRequest->selfie_s3_filename, $registerRequest);
        }

        if ($registerRequest->document_front_s3_filename != null and $registerRequest->document_front_s3_filename != '') {
            $this->createDocument($createRegister->register_master->id, 1, $registerRequest->document_front_type_id, $registerRequest->document_front_s3_filename, $registerRequest);
        }

        if ($registerRequest->document_verse_s3_filename != null and $registerRequest->document_verse_s3_filename != '') {
            $this->createDocument($createRegister->register_master->id, 1,  $registerRequest->document_verse_type_id, $registerRequest->document_verse_s3_filename, $registerRequest);
        }

        $registerMasterIdToCreateUser = $createRegister->register_master->id;
    


        //Create Account
        $accountService = new AccountService();
        $accountService->register_master_id = $registerMasterIdToCreateUser;
        $accountService->master_id          = 1;
        $accountService->return_existent_account = true;
        $accountService->account_type_id    = 10;
        $createAccount                      = $accountService->createAccount();
        if (!$createAccount->success) {
            if (!isset($createAccount->status_code) || $createAccount->status_code != 100) {  //se der erro e n for o de já não possuir alguma conta                
                return response()->json(array("error" => $createAccount->message));
            }
        }

        $registerRequest->register_account_user_created_at = \Carbon\Carbon::now();
        $registerRequest->save();

        return response()->json(array("success" => "Cadastro e conta criados com sucesso.", "account_data" => $createAccount));
    }

    public function createAccountRegister(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [6];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
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

        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
            return response()->json(array("error" => "Não é possível aprovar uma solicitação que já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
        }

        //validar todas as informações necessárias para abertura de cadastro
        if ($registerRequest->cpf_cnpj == null or  $registerRequest->cpf_cnpj == '') {
            return response()->json(array("error" => "CPF/CNPJ do cadastro não definido"));
        }

        if ($registerRequest->name == null or  $registerRequest->name == '') {
            return response()->json(array("error" => "Nome/Razão Social do cadastro não definido"));
        }

        if ($registerRequest->zip_code == null or  $registerRequest->zip_code == '') {
            return response()->json(array("error" => "CEP do cadastro não definido"));
        }

        if ($registerRequest->register_request_type_id == 1) {

            if ($registerRequest->email == null or  $registerRequest->email == '') {
                return response()->json(array("error" => "E-Mail do cadastro não definido"));
            }

            if ($registerRequest->phone == null or  $registerRequest->phone == '') {
                return response()->json(array("error" => "Telefone do cadastro não definido"));
            }

            // if ($registerRequest->address_proof_s3_filename == null or $registerRequest->address_proof_s3_filename == '') {
            //     return response()->json(array("error" => "Comprovante de endereço não importado para o cadastro"));
            // }

            if ($registerRequest->selfie_s3_filename == null or $registerRequest->selfie_s3_filename == '') {
                return response()->json(array("error" => "Selfie não importado para o cadastro"));
            }

            if ($registerRequest->document_front_s3_filename == null or $registerRequest->document_front_s3_filename == '') {
                return response()->json(array("error" => "Frente do documento não importado para o cadastro"));
            }

            if ($registerRequest->document_verse_s3_filename == null or $registerRequest->document_verse_s3_filename == '') {
                return response()->json(array("error" => "Verso do documento não importado para o cadastro"));
            }
        } else if ($registerRequest->register_request_type_id == 2) {

            if ($registerRequest->address_proof_s3_filename == null or $registerRequest->address_proof_s3_filename == '') {
                return response()->json(array("error" => "Comprovante de endereço não importado para o cadastro"));
            }

            if ($registerRequest->social_contract_s3_filename == null or $registerRequest->social_contract_s3_filename == '') {
                return response()->json(array("error" => "Contrato social não importado para o cadastro"));
            }
        }


        $registerMail = $registerRequest->email;
        if ($registerRequest->register_request_type_id == 2) {
            $registerMail = $registerRequest->representative_email;
        }

        if ($registerMail == null) {
            $getRegisterMainMail = RegisterRequestRepresentative::where('register_request_id', '=', $registerRequest->id)->whereNotNull('email')->whereNull('deleted_at')->first();
            if (isset($getRegisterMainMail->email)) {
                $registerMail = $getRegisterMainMail->email;
            }
        }


        $registerPhone = null;
        $getRegisterMainPhone = RegisterRequestRepresentative::where('register_request_id', '=', $registerRequest->id)->whereNotNull('phone')->whereNull('deleted_at')->first();
        if (isset($getRegisterMainPhone->phone)) {
            $registerPhone = $getRegisterMainPhone->phone;
        }

        if ($registerPhone == null) {
            $registerPhone = $registerRequest->phone;
        }

        $registerService = new RegisterService();

        $observationText                                = 'Criado na solicitação de cadastro';
        $registerService->cpf_cnpj                      = $registerRequest->cpf_cnpj;
        $registerService->name                          = $registerRequest->name . ' ' . $registerRequest->surname;
        $registerService->master_id                     = $checkAccount->master_id;
        $registerService->manager_email                 = $registerRequest->manager_email;
        $registerService->register_address              = $registerRequest->address;
        $registerService->register_address_state_id     = $registerRequest->state_id;
        $registerService->register_address_public_place = $registerRequest->public_place;
        $registerService->register_address_number       = $registerRequest->number;
        $registerService->register_address_complement   = $registerRequest->complement;
        $registerService->register_address_district     = $registerRequest->district;
        $registerService->register_address_city         = $registerRequest->city;
        $registerService->register_address_zip_code     = $registerRequest->zip_code;
        $registerService->register_address_observation  = $observationText;
        $registerService->register_address_main         = true;
        $registerService->register_email                = $registerMail; //$registerRequest->email;

        $registerService->register_email_observation    = $observationText;
        $registerService->register_email_main           = true;
        $registerService->register_phone                = $registerPhone; //$registerRequest->phone;
        $registerService->register_phone_observation    = $observationText;
        $registerService->register_phone_main           = true;
        $registerService->register_mother_name          = $registerRequest->mother_name;
        $registerService->register_birth_date           = $registerRequest->birth_date;
        $registerService->register_gender_id            = $registerRequest->gender_id;
        $registerService->register_marital_status_id    = $registerRequest->marital_status_id;
        $registerService->register_pep                  = $registerRequest->pep;
        $registerService->register_income               = $registerRequest->income;
        $registerService->register_observation          = $observationText;
        $registerService->register_branch_activity      = $registerRequest->main_activity;
        $registerService->register_foundation_date      = $registerRequest->birth_date;
        $registerService->register_revenue              = $registerRequest->income;
        $registerService->register_rg_number            = $registerRequest->document_number;

        if ($registerRequest->register_request_type_id == 1) {
            $registerService->register_birth_date           = $registerRequest->birth_date;
            $registerService->register_father_name          = $registerRequest->father_name;
        }


        $createRegister = $registerService->returnRegister();


        if (!$createRegister->success) {
            return response()->json(array("error" => $createRegister->message));
        }


        if ($registerRequest->register_request_type_id == 1) { //cadastro PF

            if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 8, $registerRequest->address_proof_s3_filename, $registerRequest);
            }

            if ($registerRequest->selfie_s3_filename != null and $registerRequest->selfie_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 7, $registerRequest->selfie_s3_filename, $registerRequest);
            }

            if ($registerRequest->document_front_s3_filename != null and $registerRequest->document_front_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, $registerRequest->document_front_type_id, $registerRequest->document_front_s3_filename, $registerRequest);
            }

            if ($registerRequest->document_verse_s3_filename != null and $registerRequest->document_verse_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id,  $registerRequest->document_verse_type_id, $registerRequest->document_verse_s3_filename, $registerRequest);
            }

            $registerMasterIdToCreateUser = $createRegister->register_master->id;
        } else { //REMOVIDO POR MIKE, NÃO ESTAVA DEFININDO OS DOCUMENTOS DO CADASTRO. //else if($registerRequest->register_request_type_id == 2 && $registerRequest->representative_cpf_cnpj != null) { //esquema antigo, pega representante na tabela registerRequest
            if ($registerRequest->social_contract_s3_filename != null and $registerRequest->social_contract_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 11, $registerRequest->social_contract_s3_filename, $registerRequest);
            }

            if ($registerRequest->address_proof_s3_filename != null and $registerRequest->address_proof_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 12, $registerRequest->address_proof_s3_filename, $registerRequest);
            }

            if ($registerRequest->letter_of_attorney_s3_filename != null and $registerRequest->letter_of_attorney_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 13, $registerRequest->letter_of_attorney_s3_filename, $registerRequest);
            }

            if ($registerRequest->billing_statement_s3_filename != null and $registerRequest->billing_statement_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 14, $registerRequest->billing_statement_s3_filename, $registerRequest);
            }

            if ($registerRequest->election_minutes_s3_filename != null and $registerRequest->election_minutes_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 30, $registerRequest->election_minutes_s3_filename, $registerRequest);
            }

            if ($registerRequest->other_document_1_s3_filename != null and $registerRequest->other_document_1_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 31, $registerRequest->other_document_1_s3_filename, $registerRequest);
            }

            if ($registerRequest->other_document_2_s3_filename != null and $registerRequest->other_document_2_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 31, $registerRequest->other_document_2_s3_filename, $registerRequest);
            }

            if ($registerRequest->other_document_3_s3_filename != null and $registerRequest->other_document_3_s3_filename != '') {
                $this->createDocument($createRegister->register_master->id, $checkAccount->master_id, 31, $registerRequest->other_document_3_s3_filename, $registerRequest);
            }
            
        }


        //Create Account
        $accountService = new AccountService();
        $accountService->register_master_id = $createRegister->register_master->id;
        $accountService->master_id          = $checkAccount->master_id;

        //mapa ligando o tipo de conta da solicitação de cadastro com oq temos hj na conta digital
        switch ($registerRequest->account_type_id) {
            case '1':
                $account_type_id = 3;
                break;
            case '2':
                $account_type_id = 5;
                break;
            case '3':
                $account_type_id = 4;
                break;
            default:
                return array("error" => "O tipo de conta está incorreto para essa ação, por favor tente novamente mais tarde.");
        }

        $accountService->account_type_id    = $account_type_id;
        $createAccount                      = $accountService->createAccount();
        if (!$createAccount->success) {
            if (!isset($createAccount->status_code) || $createAccount->status_code != 100) {  //se der erro e n for o de já não possuir alguma conta
                return response()->json(array("error" => $createAccount->message));
            }
        }

        //adicionar representantes/outros representantes
        $registerRequestRepresentatives = RegisterRequestRepresentative::where('register_request_id', '=', $registerRequest->id)->whereNull('deleted_at')->get();


        foreach ($registerRequestRepresentatives as $registerRequestRepresentative) {

            $registerServiceToAnotherRepresentative = new RegisterService();

            $registerServiceToAnotherRepresentative->cpf_cnpj                      = $registerRequestRepresentative->cpf_cnpj;
            $registerServiceToAnotherRepresentative->name                          = $registerRequestRepresentative->name . ' ' . $registerRequestRepresentative->surname;
            $registerServiceToAnotherRepresentative->register_birth_date           = $registerRequestRepresentative->birth_date;
            $registerServiceToAnotherRepresentative->master_id                     = $checkAccount->master_id;
            $registerServiceToAnotherRepresentative->manager_email                 = $registerRequest->manager_email;
            $registerServiceToAnotherRepresentative->register_address              = $registerRequestRepresentative->address;
            $registerServiceToAnotherRepresentative->register_address_state_id     = $registerRequestRepresentative->state_id;
            $registerServiceToAnotherRepresentative->register_address_public_place = $registerRequestRepresentative->public_place;
            $registerServiceToAnotherRepresentative->register_address_number       = $registerRequestRepresentative->number;
            $registerServiceToAnotherRepresentative->register_address_complement   = $registerRequestRepresentative->complement;
            $registerServiceToAnotherRepresentative->register_address_district     = $registerRequestRepresentative->district;
            $registerServiceToAnotherRepresentative->register_address_city         = $registerRequestRepresentative->city;
            $registerServiceToAnotherRepresentative->register_address_zip_code     = $registerRequestRepresentative->zip_code;
            $registerServiceToAnotherRepresentative->register_address_observation  = $observationText . ' de ' . $registerService->name;
            $registerServiceToAnotherRepresentative->register_address_main         = true;
            $registerServiceToAnotherRepresentative->register_email                = $registerRequestRepresentative->email;
            $registerServiceToAnotherRepresentative->register_email_observation    = $observationText . ' de ' . $registerService->name;
            $registerServiceToAnotherRepresentative->register_email_main           = true;
            $registerServiceToAnotherRepresentative->register_phone                = $registerRequestRepresentative->phone;
            $registerServiceToAnotherRepresentative->register_phone_observation    = $observationText . ' de ' . $registerService->name;
            $registerServiceToAnotherRepresentative->register_phone_main           = true;
            $registerServiceToAnotherRepresentative->register_gender_id            = $registerRequestRepresentative->gender_id;
            $registerServiceToAnotherRepresentative->register_marital_status_id    = $registerRequestRepresentative->marital_status_id;
            $registerServiceToAnotherRepresentative->register_mother_name          = $registerRequestRepresentative->mother_name;
            $registerServiceToAnotherRepresentative->register_observation          = $observationText . ' de ' . $registerService->name;
            $registerServiceToAnotherRepresentative->register_pep                  = $registerRequestRepresentative->pep;
            $registerServiceToAnotherRepresentative->register_income               = $registerRequestRepresentative->income;
            $registerServiceToAnotherRepresentative->register_rg_number            = $registerRequestRepresentative->document_number;

            $createAnotherRepresentativeRegister = $registerServiceToAnotherRepresentative->returnRegister();

            if ($createAnotherRepresentativeRegister->success) {

                if ($registerRequestRepresentative->address_proof_s3_filename != null and $registerRequestRepresentative->address_proof_s3_filename != '') {
                    $this->createDocument($createAnotherRepresentativeRegister->register_master->id, $checkAccount->master_id, 8, $registerRequestRepresentative->address_proof_s3_filename, $registerRequestRepresentative);
                }

                if ($registerRequestRepresentative->selfie_s3_filename != null and $registerRequestRepresentative->selfie_s3_filename != '') {
                    $this->createDocument($createAnotherRepresentativeRegister->register_master->id, $checkAccount->master_id, 7, $registerRequestRepresentative->selfie_s3_filename, $registerRequestRepresentative);
                }

                if ($registerRequestRepresentative->document_front_s3_filename != null and $registerRequestRepresentative->document_front_s3_filename != '') {
                    $this->createDocument($createAnotherRepresentativeRegister->register_master->id, $checkAccount->master_id, $registerRequestRepresentative->document_front_type_id, $registerRequestRepresentative->document_front_s3_filename, $registerRequestRepresentative);
                }

                if ($registerRequestRepresentative->document_verse_s3_filename != null and $registerRequestRepresentative->document_verse_s3_filename != '') {
                    $this->createDocument($createAnotherRepresentativeRegister->register_master->id, $checkAccount->master_id, $registerRequestRepresentative->document_verse_type_id, $registerRequestRepresentative->document_verse_s3_filename, $registerRequestRepresentative);
                }

                if ($registerRequestRepresentative->wedding_certificate_s3_filename != null and $registerRequestRepresentative->wedding_certificate_s3_filename != '') {
                    $this->createDocument($createAnotherRepresentativeRegister->register_master->id, $checkAccount->master_id, 10, $registerRequestRepresentative->wedding_certificate_s3_filename, $registerRequestRepresentative);
                }


                //criar cadastro de outros representantes na tabela pj_partners
                if ($registerRequestRepresentative->type_id != null) {
                    if ($registerDataPj = RegisterDataPj::where('register_master_id', '=',  $createRegister->register_master->id)->first()) {
                        $pjPartner = PjPartner::Create([
                            'register_master_id' => $createAnotherRepresentativeRegister->register_master->id,
                            'register_data_pj_id' => $registerDataPj->id,
                            'partner_type_id' => $registerRequestRepresentative->type_id,
                            'uuid' => Str::orderedUuid(),
                            'created_at' => \Carbon\Carbon::now()
                        ]);
                    }
                }
            }
        }

        $registerRequest->register_account_user_created_at = \Carbon\Carbon::now();
        $registerRequest->register_account_user_created_by_user_id = $checkAccount->user_id;
        $registerRequest->save();

        SetupAccountAction::run($createAccount->data->account_id);

        return response()->json(array("success" => "Cadastro e conta criados com sucesso."));
    }

    public function createrUser($registerMasterIdToCreateUser, $registerRequestRepresentative)
    {
        // se já tiver um usuário criado com o mesmo cpf, telefone e email já retorna
        if (User::where('cpf_cnpj', '=', $registerRequestRepresentative->cpf_cnpj)->where('phone', '=', $registerRequestRepresentative->phone)->where('email', '=', $registerRequestRepresentative->email)->whereNull('deleted_at')->count() > 0) {

            $user = User::where('cpf_cnpj', '=', $registerRequestRepresentative->cpf_cnpj)->where('phone', '=', $registerRequestRepresentative->phone)->where('email', '=', $registerRequestRepresentative->email)->whereNull('deleted_at')->first();
            $userMaster = UserMaster::where("user_id", "=", $user->id)->first();

            return (object) [
                "success" => true,
                "message" => '',
                "data" => (object) ['user_master_id' => $userMaster->id]
            ];
        }

        // se já tiver um usuário criado com um cpf diferente, mas o mesmo email
        if (User::where('email', '=', $registerRequestRepresentative->email)->where('cpf_cnpj', '<>', $registerRequestRepresentative->cpf_cnpj)->whereNull('deleted_at')->count() > 0) {
            return (object) array(
                "success" => false,
                "message" => "Já existe um usuário cadastrado com esse e-mail",
                "data" => ''
            );
        }

        // se já tiver um usuário criado com um cpf diferente, mas o mesmo telefone
        if (User::where('phone', '=', $registerRequestRepresentative->phone)->where('cpf_cnpj', '<>', $registerRequestRepresentative->cpf_cnpj)->whereNull('deleted_at')->count() > 0) {
            return (object) array(
                "success" => false,
                "message" => "Já existe um usuário cadastrado com esse celular",
                "data" => ''
            );
        }

        if ($registerMasterIdToCreateUser != null) {
            $createrUser = new RegisterService();
            $createrUser->register_master_id = $registerMasterIdToCreateUser;
            $createrUser->password = $registerRequestRepresentative->account_password ? $registerRequestRepresentative->account_password : null;
            $createrUser->password = $registerRequestRepresentative->account_password ? $registerRequestRepresentative->account_password : null;
            $createrUser->validated_user = $registerRequestRepresentative->register_request_type_id == 3 ? true : false ;
            $newUser = $createrUser->createUserByRegister();
            if (!$newUser->success) {
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

    public function createUserRelationship($masterId, $userMasterId, $accountId, $userRelationshipId, $registerRequestRepresentative)
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

            // return $createUserRelationship;

            if (!$createUserRelationship->success) {
                return (object) [
                    'success' => false,
                    'message' => $createUserRelationship->message,
                    'data' => $createUserRelationship->data
                ];
            }

            $permissions = RegisterRequestRepresentativesPermissions::where('representative_id', '=', $registerRequestRepresentative->id)->whereNull('deleted_at')->get();

            foreach ($permissions as $permission) {
                UsrRltnshpPrmssn::create([
                    'usr_rltnshp_id' => $userMasterId,
                    'permission_id'  => $permission->permission_id,
                    'created_at'     => \Carbon\Carbon::now(),
                ]);
            }

            $userRelationshipCreated = UserRelationship::where('id', $createUserRelationship->data->id)->first();
            $registerRequestRep = RegisterRequestRepresentative::where('id', '=', $registerRequestRepresentative->id)->whereNull('deleted_at')->first();

            if(!is_null($registerRequestRep)){
                $userRelationshipCreated->is_administrator = $registerRequestRep->is_administrator;

                $userRelationshipCreated->save();
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

    public function createDocument($register_master_id, $master_id, $document_type_id, $s3_file_name, $registerRequest)
    {
        if (Document::where('register_master_id', '=', $register_master_id)->where('s3_file_name', '=', $s3_file_name)->whereNull('deleted_at')->count() == 0) {

            $user_id = null;

            switch($document_type_id) {
                case '1':
                    $is_expired = $registerRequest->is_expired_document_front;
                    $expired_document_deadline_at = $registerRequest->expired_document_front_deadline_at;
                    break;
                case '3':
                    $is_expired = $registerRequest->is_expired_document_front;
                    $expired_document_deadline_at = $registerRequest->expired_document_front_deadline_at;
                    break;
                case '5':
                    $is_expired = $registerRequest->is_expired_document_front;
                    $expired_document_deadline_at = $registerRequest->expired_document_front_deadline_at;
                    break;
                case '2':
                    $is_expired = $registerRequest->is_expired_document_verse;
                    $expired_document_deadline_at = $registerRequest->expired_document_verse_deadline_at;
                    break;
                case '4':
                    $is_expired = $registerRequest->is_expired_document_verse;
                    $expired_document_deadline_at = $registerRequest->expired_document_verse_deadline_at;
                    break;
                case '6':
                    $is_expired = $registerRequest->is_expired_document_verse;
                    $expired_document_deadline_at = $registerRequest->expired_document_verse_deadline_at;
                    break;
                case '8':
                    $is_expired = $registerRequest->is_expired_address_proof;
                    $expired_document_deadline_at = $registerRequest->expired_address_proof_deadline_at;
                    break;
                case '11':
                    $is_expired = $registerRequest->is_expired_social_contract;
                    $expired_document_deadline_at = $registerRequest->expired_social_contract_deadline_at;
                    break;
                case '12':
                    $is_expired = $registerRequest->is_expired_address_proof;
                    $expired_document_deadline_at = $registerRequest->expired_address_proof_deadline_at;
                    break;
                case '13':
                    $is_expired = $registerRequest->is_expired_letter_of_attorney;
                    $expired_document_deadline_at = $registerRequest->expired_letter_of_attorney_deadline_at;
                    break;
                case '14':
                    $is_expired = $registerRequest->is_expired_billing_statement;
                    $expired_document_deadline_at = $registerRequest->expired_billing_statement_deadline_at;
                    break;
                case '30':
                    $is_expired = $registerRequest->is_expired_election_minutes;
                    $expired_document_deadline_at = $registerRequest->expired_election_minutes_deadline_at;
                    break;
                default:
                    Log::info("Id: $document_type_id não possui documento a vencer ao criar o documento na aprovação da conta");
                    break;
            }

            Document::create([
                'register_master_id'           => $register_master_id,
                'master_id'                    => $master_id,
                'document_type_id'             => $document_type_id,
                's3_file_name'                 => $s3_file_name,
                'status_id'                    => $this->EnumStatusId('aprovado'),
                'description'                  => 'Aprovado na solicitação de cadastro',
                'created_by'                   => $user_id,
                'created_at'                   => \Carbon\Carbon::now(),
                'is_expired'                   => !empty($is_expired) ? $is_expired : null,
                'expired_document_deadline_at' => !empty($expired_document_deadline_at) ? $expired_document_deadline_at : null
            ]);

        }
    }

    public function sendEmailUpdateDocument(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'pending_docs'    => ['required', 'string'],
            'id'              => ['required', 'integer'],
            'uuid'            => ['required', 'string'],
        ], [
            'id.required' => 'Identificador do usuário não informado',
            'uuid.required' => 'Identificador único do usuário não informado',
            'pending_docs.required' => 'Documento não informado',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }


        if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return ["error" => "Ocorreu um erro ao localizar a solicitação de cadastro"];
        }

        if ($request->pending_docs == 'election_minutes' && $registerRequest->legal_nature_id != 2) {
            return ["error" => "A ata de eleição só é válida para empresas de natureza jurídica Sociedade Anônima"];
        }

        $token = Str::random(60);
        $token_expiration = \Carbon\Carbon::now()->addDays(30);

        $registerRequest->token_new_document_email = $token;
        $registerRequest->token_new_document_email_expiration = $token_expiration;
        $registerRequest->save();

        if ($request->pending_docs == 'document_front' || $request->pending_docs == 'document_verse') {
            $nomeDocumento = 'document_front_verse';
        } else {
            $nomeDocumento = $this->EnumDocNames($request->pending_docs);
        }

        $apiKey = config('mail.sendgrid_api_key');

        // Defina a URL do endpoint SendGrid
        $url = 'https://api.sendgrid.com/v3/mail/send';

        // Construa o corpo da solicitação em JSON
        $data = [
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $registerRequest->email,
                            "name"  => $registerRequest->representative_name
                        ]
                    ],
                    "dynamic_template_data" => [
                        "first_name" => Facilites::firstLetterUpperCase($registerRequest->name),
                        "pending_docs" => $nomeDocumento,
                        "validation_url" => config('services.url.front') . "/compliance/edit/document/?document_name=$nomeDocumento&" .
                            CryptographyHelper::encrypt(
                                "token=$token&id=$registerRequest->id&uuid=$registerRequest->uuid&document_type=$request->pending_docs",
                                "AES-256-ECB",
                                config('services.crpt_link_mail_upload.secret')
                            )
                    ]
                ]
            ],
            "from" => [
                "email" => "faleconosco@ip4y.com.br",
                "name" => "IP4Y"
            ],
            "subject" => "Atualização de documento",
            "template_id" => 'd-694859796b514a5fbf6e09e2b6e632a6',
        ];


        // Configurar a solicitação usando a fachada Http do Laravel
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);


        if ($response->successful()) {
            return ["success" => "E-mail para alteração de documento enviado com sucesso", "data" => $registerRequest];
        }
        return ["error" => "Ocorreu um erro ao enviar o e-mail para alteração de documento, por favor, tente novamente mais tarde"];
    }

    public function EnumDocNames($document)
    {

        switch ($document) {
            case 'social_contract':
                $name = "Contrato Social";
                break;
            case 'residence_proof':
                $name = "Comprovante de Endereço";
                break;
            case 'billing_statement':
                $name = "Declaração de Faturamento";
                break;
            case 'election_minutes':
                $name = "Ata de Eleição";
                break;
            case 'letter_of_attorney':
                $name = "Procuração";
                break;
            case 'other_document_1':
                $name = "Outros Documentos 1";
                break;
            case 'other_document_2':
                $name = "Outros Documentos 2";
                break;
            case 'other_document_3':
                $name = "Outros Documentos 3";
                break;
            case 'document_front_verse':
                $name = "Frente e verso";
                break;
            case 'selfie':
                $name = "Selfie";
                break;
            case 'address_proof':
                $name = "Comprovante de Residência";
                break;
            default:
                break;
        }

        return $name;
    }

    public function updateNewDocument(Request $request)
    {

        if (!empty($request->document_front)) {
            return response()->json(array("success" => true));
        }

        $crpt_url = CryptographyHelper::decrypt(
            $request->crpt_url,
            "AES-256-ECB",
            config('services.crpt_link_mail_upload.secret')
        );

        if (!preg_match('/^token=(\w+)&id=(\w+)&uuid=(.+)&document_type=(\w+)$/', $crpt_url, $matches)) {
            return response()->json(["error" => 'Ocorreu um erro na requisição, por favor, contate a equipe de suporte']);
        }

        $req_token = $matches[1];
        $req_id = $matches[2];
        $req_uuid = $matches[3];
        $req_document_type = $matches[4];


        if (!$registerRequest = RegisterRequest::where('id', '=', $req_id)
            ->where('uuid', '=', $req_uuid)
            ->whereNull('deleted_at')
            ->first()) {
            return response()->json(['error' => 'Desculpe, mas não localizamos os dados do registro para a alteração do documento em nossa plataforma']);
        }

        if ($registerRequest->token_new_document_email != $req_token) {
            return response()->json(['error' => 'Token inválido']);
        }


        $dataAtual = \Carbon\Carbon::now();
        $dataExpiracao = \Carbon\Carbon::parse($registerRequest->token_new_document_email_expiration);

        if ($dataAtual->greaterThan($dataExpiracao)) {
            return response()->json(['error' => 'Token expirado']);
        }


        try {
            switch ($req_document_type) {
                case 'social_contract':
                    $update_document = $this->setSocialContractS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'residence_proof':
                    $update_document = $this->setResidenceProofS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'billing_statement':
                    $update_document = $this->setBillingStatementS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'election_minutes':
                    $update_document = $this->setElectionMinutesS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'letter_of_attorney':
                    $update_document = $this->setLetterOfAttorneyS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'other_document_1':
                    $update_document = $this->setOtherDocument1S3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'other_document_2':
                    $update_document = $this->setOtherDocument2S3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'other_document_3':
                    $update_document = $this->setOtherDocument3S3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'document_front_verse': //pf
                    $update_document = $this->setDocumentFrontVerseS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'selfie': //pf
                    $update_document = $this->setSelfieS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                case 'address_proof': //pf
                    $update_document = $this->setAddressProofS3FilenameUpdateDoc($registerRequest, $request);
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
            return abort(400, $e);
        }

        if ($update_document->success) {

            $registerRequest->token_new_document_email_expiration = \Carbon\Carbon::now();
            $registerRequest->save();

            if ($req_document_type == 'residence_proof') {
                $s3_filename = 'address_proof_s3_filename';
            } else {
                $s3_filename = $req_document_type . '_s3_filename';
            }

            if ($req_document_type == 'document_front_verse') {

                $payload = [
                    'pf_representative_uuid' => $registerRequest->pf_acc_uuid_representative_cad,
                    'document_type_id' => $registerRequest->document_type_id,
                    'front' => [
                        'document_s3_filename' => $registerRequest->document_front_s3_filename,
                        'document_imported_filename' => $request->file_name_front,
                        'document_type' => 'document_front'
                    ],
                    'back' => [
                        'document_s3_filename' => $registerRequest->document_verse_s3_filename,
                        'document_imported_filename' => $request->file_name_back,
                        'document_type' => 'document_back'
                    ],
                ];
            } else {

                $payload = [
                    'company_uuid' => $registerRequest->uuid_company_cad,
                    'document_imported_filename' => $request->file_name,
                    'document_type' => $req_document_type,
                    'document_s3' => $registerRequest->$s3_filename,
                    'pf_representative_uuid' => !empty($registerRequest->pf_acc_uuid_representative_cad) ? $registerRequest->pf_acc_uuid_representative_cad : null,
                ];
            }


            // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');
            Log::debug('Payload');
            Log::debug($payload);


            $response = Http::post(config('register_request.url') . 'update-document-requester', $payload);

            $doc_name = mb_strtolower($this->EnumDocNames($req_document_type));
            Log::debug("Envio do documento $doc_name para sistema de cadastro do documento atualizado pelo solicitante");
            Log::debug(json_encode($registerRequest));
            Log::debug($registerRequest->uuid_company_cad);
            Log::debug($response->body());

            return response()->json(array("success" => $update_document->message));
        }

        return response()->json(array("error" => $update_document->message));
    }

    public function setSocialContractS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 11)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->social_contract_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->social_contract_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('social_contract_s3_filename', $registerRequest->social_contract_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->social_contract_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Contrato social enviado com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar o contrato social, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setResidenceProofS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 12)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->address_proof_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->address_proof_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address_proof_s3_filename', $registerRequest->address_proof_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }


        $registerRequest->address_proof_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Comprovante de endereço enviado com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar o comprovante de endereço, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setBillingStatementS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 14)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->representative_document_billing_statement_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->billing_statement_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('billing_statement_s3_filename', $registerRequest->billing_statement_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->billing_statement_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Declaração de faturamento enviada com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar a declaração de faturamento, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setLetterOfAttorneyS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 13)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->letter_of_attorney_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->letter_of_attorney_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('letter_of_attorney_s3_filename', $registerRequest->letter_of_attorney_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->letter_of_attorney_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Procuração enviada com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar a procuração, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setElectionMinutesS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 30)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->election_minutes_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->election_minutes_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('election_minutes_s3_filename', $registerRequest->election_minutes_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->election_minutes_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Ata de eleição enviada com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar a ata de eleição, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setOtherDocument1S3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 31)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->other_document_1_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->other_document_1_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('other_document_1_s3_filename', $registerRequest->other_document_1_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->other_document_1_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'O documento foi enviado com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar o documento, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setOtherDocument2S3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 31)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->other_document_2_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->other_document_2_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('other_document_2_s3_filename', $registerRequest->other_document_2_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->other_document_2_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'O documento foi enviado com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar o documento, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setOtherDocument3S3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 31)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->other_document_3_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->other_document_3_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('other_document_3_s3_filename', $registerRequest->other_document_3_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->other_document_3_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'O documento foi enviado com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar o documento, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setDocumentFrontVerseS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        $documentFrontTypeId = '';

        switch ($request->document_type_id) {
            case 1:
                $documentFrontTypeId = 1;
                break;
            case 2:
                $documentFrontTypeId = 3;
                break;
            case 3:
                $documentFrontTypeId = 5;
                break;
            default:
                return (object) [
                    'success' => false,
                    'message' => 'O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde.',
                    'status_code' => 500,
                ];
                break;
        }


        if (!$document_type_front = DocumentType::where('id', '=', $documentFrontTypeId)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }


        if (!(($fileNameFront = $this->fileManagerS3($request->file_name_front, $request->file64_front, $document_type_front, $registerRequest->document_front_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileNameFront->message,
                'status_code' => 500,
            ];
        }



        $documentBackTypeId = '';

        switch ($request->document_type_id) {
            case 1:
                $documentBackTypeId = 2;
                break;
            case 2:
                $documentBackTypeId = 4;
                break;
            case 3:
                $documentBackTypeId = 6;
                break;
            default:
                return (object) [
                    'success' => false,
                    'message' => 'O tipo de documento definido está incorreto para essa ação, por favor tente novamente mais tarde.',
                    'status_code' => 500,
                ];
                break;
        }


        if (!$document_type_back = DocumentType::where('id', '=', $documentBackTypeId)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }


        if (!(($fileNameBack = $this->fileManagerS3($request->file_name_back, $request->file64_back, $document_type_back, $registerRequest->document_verse_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileNameBack->message,
                'status_code' => 500,
            ];
        }



        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->document_front_s3_filename != $fileNameFront->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $updatedByRequester = true;
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_front_s3_filename', $registerRequest->document_front_s3_filename, $fileNameFront->data, $request->id, null, $updatedByRequester);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }

            if ($registerRequest->document_verse_s3_filename != $fileNameBack->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $updatedByRequester = true;
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_verse_s3_filename', $registerRequest->document_verse_s3_filename, $fileNameBack->data, $request->id, null, $updatedByRequester);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->document_type_id = $request->document_type_id;
        $registerRequest->document_front_s3_filename = $fileNameFront->data;
        $registerRequest->document_front_type_id = $documentFrontTypeId;
        $registerRequest->document_verse_s3_filename = $fileNameBack->data;
        $registerRequest->document_verse_type_id = $documentBackTypeId;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Frente e verso do documento enviados com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar a frente e o verso do documento, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setSelfieS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 7)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->selfie_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->selfie_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('selfie_s3_filename', $registerRequest->selfie_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }

        $registerRequest->selfie_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Selfie enviada com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar a selfie, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setAddressProofS3FilenameUpdateDoc(RegisterRequest $registerRequest, Request $request)
    {

        if (!$document_type = DocumentType::where('id', '=', 8)->whereNull('deleted_at')->first()) {
            return (object) [
                'success' => false,
                'message' => 'Tipo do documento não foi localizado, por favor tente novamente mais tarde.',
                'status_code' => 500,
            ];
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequest->address_proof_s3_filename))->success)) {
            return (object) [
                'success' => false,
                'message' => $fileName->message,
                'status_code' => 500,
            ];
        }


        if ($registerRequest->address_proof_s3_filename != $fileName->data) {
            $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
            $updatedByRequester = true;
            $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address_proof_s3_filename', $registerRequest->address_proof_s3_filename, $fileName->data, $request->id, null, $updatedByRequester);

            if (!$response->success) {
                Log::debug([
                    "type"            => "Register Request Log",
                    "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                    "method"          => "POST",
                    "status_code"     => $response->status_code,
                    "response"        => $response->message
                ]);
            }
        }


        $registerRequest->address_proof_s3_filename = $fileName->data;

        if ($registerRequest->save()) {
            return (object) [
                'success' => true,
                'message' => 'Comprovante de residência enviado com sucesso.',
                'status_code' => 200,
            ];
        }

        return (object) [
            'success' => false,
            'message' => 'Ocorreu um erro ao enviar o comprovante de residência, por favor, tente novamente mais tarde ou entre em contato com a equipe de suporte.',
            'status_code' => 500,
        ];
    }

    public function setAddressPlaceBirth(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'address_place_birth'         => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->address_place_birth != $request->address_place_birth) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address_place_birth', $registerRequest->address_place_birth, $request->address_place_birth, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->address_place_birth = $request->address_place_birth;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Naturalidade definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a naturalidade, por favor tente novamente mais tarde."));
    }

    public function setAddressStateBirth(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                          => ['required', 'integer'],
            'uuid'                        => ['required', 'string'],
            'address_state_birth_id'      => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->address_state_birth_id != $request->address_state_birth_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();
                $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address_state_birth_id', $registerRequest->address_state_birth_id, $request->address_state_birth_id, $request->id, null);

                if (!$response->success) {
                    Log::debug([
                        "type"            => "Register Request Log",
                        "date_time"       => (\Carbon\Carbon::parse(\Carbon\Carbon::now()))->format('Y-m-d H:i:s'),
                        "method"          => "POST",
                        "status_code"     => $response->status_code,
                        "response"        => $response->message
                    ]);
                }
            }
        }

        $registerRequest->address_state_birth_id = $request->address_state_birth_id;

        if ($registerRequest->save()) {
            return response()->json(array("success" => "Estado de nascimento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado de nascimento, por favor tente novamente mais tarde."));
    }

    public function changeToSecondPhase(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'              => ['required', 'integer'],
            'uuid'            => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->kyc_s3_filename == null || $registerRequest->kyc_s3_filename == '') {
                return response()->json(array("error" => "É necessário o envio do documento KYC."));
            }

            if ($registerRequest->account_phase_id != 1) {
                return response()->json(array("error" => "Fase atual inválida para troca."));
            }
        }

        $payload = [
            'partner_or_user_uuid' => $registerRequest->pf_acc_uuid_representative_cad
        ];


        //TODO $encrypt not found precisa ajustar essa lógica para retornar esse
        // $encrypt = CryptographyHelper::encrypt(json_encode($payload), 'AES-256-ECB');

        $response = Http::post(config('register_request.url') . 'send-email-validation', $payload);

        Log::debug('Pré aprovação para fase 2 feita com sucesso');
        Log::debug(json_encode($registerRequest));
        Log::debug($response);
        Log::debug($payload);

        if ($response->successful()) {
            $registerRequest->account_phase_id = $this->EnumFaseId('fase2');
            $registerRequest->action_status_id = $this->EnumStatusAcaoId('aguardandoCliente');
            $registerRequest->save();
            return response()->json(array("success" => "Fase 2 definida com sucesso."));
        }

        return response()->json(array("error" => "Não foi possível definir a fase 2, por favor tente novamente mais tarde."));
    }

    public function getExpiredDocInfos(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'string'],
                'deadline_at'     => ['required', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }


            if(isset($request->partner_or_user_pj)){
                if (!$registerRequestRepresentatives = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar o registro do sócio/usuário, por favor tente novamente mais tarde."));
                }

                return response()->json(array("success" => true, "is_document_expired" => $registerRequestRepresentatives[$request['is_expired']], "expired_document_deadline" => $registerRequestRepresentatives[$request['deadline_at']]));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            return response()->json(array("success" => true, "is_document_expired" => $registerRequest[$request['is_expired']], "expired_document_deadline" => $registerRequest[$request['deadline_at']]));

        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }

    }

    public function setLimitDeadlineDocumentFront(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }


            if(isset($request->partner_or_user_pj)){
                if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar o registro do sócio/usuário, por favor tente novamente mais tarde."));
                }

                $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();
                if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                    return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
                }

                $registerRequestRepresentative->is_expired_document_front = $request->is_expired;
                $registerRequestRepresentative->expired_document_front_deadline_at = $request->deadline_at;
                $registerRequestRepresentative->save();

                return response()->json(array("success" => "As informações de documento vencido da frente do documento foram atualizadas com sucesso."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_document_front = $request->is_expired;
            $registerRequest->expired_document_front_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido da frente do documento foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function setLimitDeadlineDocumentVerse(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }


            if(isset($request->partner_or_user_pj)){
                if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar o registro do sócio/usuário, por favor tente novamente mais tarde."));
                }

                $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();
                if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                    return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
                }

                $registerRequestRepresentative->is_expired_document_verse = $request->is_expired;
                $registerRequestRepresentative->expired_document_verse_deadline_at = $request->deadline_at;
                $registerRequestRepresentative->save();

                return response()->json(array("success" => "As informações de documento vencido do verso do documento foram atualizadas com sucesso."));
            }


            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_document_verse = $request->is_expired;
            $registerRequest->expired_document_verse_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido do verso do documento foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function setLimitDeadlineAddressProof(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }


            if(isset($request->partner_or_user_pj)){
                if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar o registro do sócio/usuário, por favor tente novamente mais tarde."));
                }

                $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();
                if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                    return response()->json(array("error" => "Não é possível aprovar uma solicitação que já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
                }

                $registerRequestRepresentative->is_expired_address_proof = $request->is_expired;
                $registerRequestRepresentative->expired_address_proof_deadline_at = $request->deadline_at;
                $registerRequestRepresentative->save();

                return response()->json(array("success" => "As informações de documento vencido do comprovante de residência foram atualizadas com sucesso."));
            }


            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_address_proof = $request->is_expired;
            $registerRequest->expired_address_proof_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido do comprovante de residência foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function setLimitDeadlineSocialContract(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_social_contract = $request->is_expired;
            $registerRequest->expired_social_contract_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido do contrato social foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function setLimitDeadlineBillingStatement(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_billing_statement = $request->is_expired;
            $registerRequest->expired_billing_statement_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido da declaração de faturamento foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function setLimitDeadlineElectionMinutes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_election_minutes = $request->is_expired;
            $registerRequest->expired_election_minutes_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido da ata de eleição foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function setLimitDeadlineLetterOfAttorney(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'              => ['required', 'integer'],
                'uuid'            => ['required', 'string'],
                'is_expired'      => ['required', 'integer'],
                'deadline_at'     => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                return response()->json(["error" => $validator->errors()->first()]);
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
            }

            if ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível modificar esse dado uma vez que a solicitação já foi aprovada ou recusada, por favor verifique os dados e tente novamente."));
            }

            $registerRequest->is_expired_letter_of_attorney = $request->is_expired;
            $registerRequest->expired_letter_of_attorney_deadline_at = $request->deadline_at;
            $registerRequest->save();

            return response()->json(array("success" => "As informações de documento vencido da procuração foram atualizadas com sucesso."));
        } catch (\Exception $e) {
            Log::error('Error', [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

       public function createEmployeeRegisterRequest(Request $request){
        try {
            $validate = Validator::make($request->all(), [
                'cpf' => 'required|string|max:11|exists:payroll_employees,cpf_cnpj|unique:users,cpf_cnpj',
                'phone' => 'required|string|max:22',
                'email' => 'required|string|max:128',
            ], [
                'cpf.required'   => 'O campo cpf é obrigatório.',
                'cpf.string'     => 'O campo cpf deve ser uma string.',
                'cpf.max'        => 'cpf inválido.',
                'cpf.exists'     => 'Não estamos realizando abertura de contas PF neste momento.',
                'cpf.unique'     => 'Não estamos realizando abertura de contas PF neste momento.',
                'phone.required' => 'O campo telefone é obrigatório.',
                'phone.string'   => 'O campo telefone deve ser uma string.',
                'phone.max'      => 'Telefone inválido.',
                'email.required' => 'O campo email é obrigatório.',
                'email.string'   => 'O campo email deve ser uma string.',
                'email.max'      => 'email inválido.',
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'error' => $validate->errors()->first(),
                ], 200);
            }

            $payroll_employee = PayrollEmployee::where('cpf_cnpj', $request['cpf'])->first();
            $payroll_employee_detail = PayrollEmployeeDetail::where('employee_id' , $payroll_employee['id'])->where('accnt_tp_id', 7)->first();

            if(RegisterRequest::where('cpf_cnpj',  $payroll_employee['cpf_cnpj'])->where('register_request_type_id', 3)->whereNull('deleted_at')->latest('created_at')->first()){
                return response()->json(array("error" => "Não estamos realizando abertura de contas PF neste momento."), 200);
            }

            if(!$payroll_employee_detail){
                return response()->json(array("error" => "Não estamos realizando abertura de contas PF neste momento."), 200);
        }

            $masterAccount = Account::select()->where('id', $payroll_employee_detail['account_id'])->first();
            
            $managersRegister = new ManagersRegister;
            $managersRegister->register_master_id = $masterAccount['id'];
            $manager = $managersRegister->getManagersRegister();
            
            // se já tiver um usuário criado com um cpf diferente, mas o mesmo email 
            if (User::where('email', '=', $request['email'])->where('cpf_cnpj', '<>', $request['cpf'])->whereNull('deleted_at')->count() > 0) {
                return response()->json(array("error" => "Não foi possivel realizar a validação para este cpf, entre em contato com o suporte ou tente novamente mais tarde."), 200);
            }
            
            // se já tiver um usuário criado com um cpf diferente, mas o mesmo telefone 
            if (User::where('phone', '=', $request['phone'])->where('cpf_cnpj', '<>', $request['cpf'])->whereNull('deleted_at')->count() > 0) {
                return response()->json(array("error" => "Não foi possivel realizar a validação para este cpf, entre em contato com o suporte ou tente novamente mais tarde."), 200);
            }

            $payload = [
                'name' => $payroll_employee_detail['name'], 
                'email' => $request['email'],
                'phone' => $request['phone'],
                'cpf' => $payroll_employee['cpf_cnpj'],
                'indicate_code' =>  null,
            ];

            $response = Http::post(config('register_request.url') . 'natural-person-employee', $payload);

            Log::debug('Envio para sistema de cadastro de solicitação de cadastro aprovada');
            Log::debug($response->body());
            
            $responseData = $response->json();
            
            Log::debug('response criação de funcionario | sistema de cadastro');
            Log::debug($responseData);
            if(!empty($responseData['error'])){
                return response()->json(array("error" => "Este cpf já está em processo de vallidação pelo nosso compliance.", "next" => $responseData['data']), 200);
            }

            if(!empty($responseData['success'])){
                return response()->json(array("success" => "Sucesso", "next" => $responseData['data']), 200);
            }
            
            return response()->json(['error' => 'Ocorreu um erro inesperado. Entre em contato com o suporte ou tente novamente mais tarde.', 'data' => $responseData]);
        } catch (Exception $e) {
            Log::error('Falha ao enviar Register Request', ["error" => $e]);

            $e = FlattenException::createFromThrowable($e);
            $handler = new HtmlErrorRenderer(true); // boolean, true raises debug flag...
            $css = $handler->getStylesheet();
            $content = 'Request: '.\Request::fullUrl().'<br><br>';
            $content .= $handler->getBody($e);
            \Mail::send('emails.errorEmail', compact('css','content'), function ($message) {
                $message->to('michael@ip4y.com.br')->subject('Erro na Conta Digital');
            });
            return response()->json(['error' => 'Ocorreu um erro ao localizar o cadastro. Por favor tente novamente mais tarde ou entre em contato com o suporte.', 'data' => $e]);
        }

    }
}
