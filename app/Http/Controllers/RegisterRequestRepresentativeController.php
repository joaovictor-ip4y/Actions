<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequestRepresentative;
use App\Models\RegisterRequest;
use App\Models\DocumentType;
use App\Models\RegisterRequestRepresentativesPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Libraries\AmazonS3;
use App\Libraries\Facilites;
use Illuminate\Support\Facades\Auth;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\RegisterRequest\RegisterRequestsOrRepresentativeLogsClass;
use App\Models\Gender;
use App\Models\PartnerType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;



class RegisterRequestRepresentativeController extends Controller
{
    protected function get(Request $request)
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
            'register_request_id'   => ['nullable', 'integer'],
            'register_request_uuid' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!Auth::check()) {
                if (!RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->where('status_id', '=', $this->EnumStatusId('emAndamento'))->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique os dados informados ou tente novamente mais tarde."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $registerRepresentative = new RegisterRequestRepresentative();
        $registerRepresentative->register_request_id = $request->register_request_id;
        $registerRepresentative->id = $request->id;
        $registerRepresentative->uuid = $request->uuid;
        $registerRepresentative->date_start = $request->date_start;
        $registerRepresentative->date_end = $request->date_end;
        $registerRepresentative->onlyActive = $request->onlyActive;
        $registerRepresentative->onlyUsers = $request->onlyUsers;
        $registerRepresentative->evaluator_by_user_id = $request->evaluator_by_user_id;

        //se for pra buscar por 1 ou 0
        if ($request->user_partner_status_id != null) {
            $registerRepresentative->user_partner_status_id = [$request->user_partner_status_id];
        }

        //se for pra buscar por null
        if ($request->user_partner_status_id_null != null) {
            $registerRepresentative->user_partner_status_id_null = $request->user_partner_status_id_null;
        }


        return response()->json($registerRepresentative->get());
    }

    protected function getEvaluatorNames(Request $request)
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

        $registerRepresentative = new RegisterRequestRepresentative();

        return response()->json($registerRepresentative->getEvaluatorNames());
    }

    protected function getWithSuccess(Request $request)
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
            'register_request_id'   => ['required', 'integer'],
            'register_request_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!Auth::check()) {
                if (!$registerRequest = RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->where('status_id', '=', $this->EnumStatusId('emAndamento'))->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique os dados informados ou tente novamente mais tarde."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $registerRepresentative = new RegisterRequestRepresentative();
        $registerRepresentative->register_request_id = $request->register_request_id;
        $registerRepresentative->id = $request->id;
        $registerRepresentative->uuid = $request->uuid;
        $registerRepresentative->onlyActive = $request->onlyActive;

        return response()->json(["success" => $registerRepresentative->get()]);
    }

    protected function new(Request $request)
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
            'register_request_id'   => ['required', 'integer'],
            'register_request_uuid' => ['required', 'string'],
            'cpf_cnpj'              => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->where('status_id', '=', $this->EnumStatusId('emAndamento'))->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;
        if (!$facilites->validateCPF($cpf_cnpj)) {
            return response()->json(array("error" => "CPF inválido."));
        }

        if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('register_request_id', '=', $registerRequest->id)->where('cpf_cnpj', '=', $request->cpf_cnpj)->first()) {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::create([
                'uuid' =>  Str::orderedUuid(),
                'register_request_id' => $registerRequest->id,
                'cpf_cnpj' =>  $cpf_cnpj
            ])) {
                return response()->json(array("error" => "Não foi possível realizar o cadastro do representante, por favor tente novamente mais tarde."));
            }
        }
        $registerRequestRepresentative->deleted_at = null;
        $registerRequestRepresentative->save();
        return response()->json(array('success' => 'Representante/Usuário cadastrado com sucesso.', 'data' => $registerRequestRepresentative->get()[0]));
    }

    protected function edit(Request $request)
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
            'register_request_id'   => ['required', 'integer'],
            'register_request_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->where('status_id', '=', $this->EnumStatusId('emAndamento'))->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('register_request_id', '=', $registerRequest->id)->where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (strlen(preg_replace('/[^0-9]/', '', $request->zip_code)) > 8) {
            return response()->json(array("error" => "CEP Inválido"));
        }

        $registerRequestRepresentative->name = mb_strtoupper($request->name);
        $registerRequestRepresentative->pep = $request->pep;
        $registerRequestRepresentative->birth_date = $request->birth_date;
        $registerRequestRepresentative->gender_id = $request->gender_id;
        $registerRequestRepresentative->marital_status_id = $request->marital_status_id;
        $registerRequestRepresentative->phone = $request->phone;
        $registerRequestRepresentative->email = mb_strtolower($request->email);
        $registerRequestRepresentative->public_place = mb_strtoupper($request->public_place);
        $registerRequestRepresentative->address = mb_strtoupper($request->address);
        $registerRequestRepresentative->number = mb_strtoupper($request->number);
        $registerRequestRepresentative->complement = mb_strtoupper($request->complement);
        $registerRequestRepresentative->district = mb_strtoupper($request->district);
        $registerRequestRepresentative->city = mb_strtoupper($request->city);
        $registerRequestRepresentative->state_id = $request->state_id;
        $registerRequestRepresentative->zip_code =  mb_substr(preg_replace('/[^0-9]/', '', $request->zip_code), 0, 8);
        $registerRequestRepresentative->document_type_id = $request->document_type_id;
        $registerRequestRepresentative->document_number = mb_strtoupper($request->document_number);
        $registerRequestRepresentative->income = $request->income;
        $registerRequestRepresentative->mother_name = mb_strtoupper($request->mother_name);
        $registerRequestRepresentative->will_be_user = $request->will_be_user;
        $registerRequestRepresentative->type_id = $request->type_id;

        if (!$registerRequestRepresentative->save()) {
            return response()->json(array("error" => "Poxa, ocorreu uma falha ao atualizar os dados do representante/usuário, por favor tente novamente mais tarde."));
        }

        return response()->json(array("success" => "Dados do representate/usuário atualizados com sucesso.", "data" => $registerRequestRepresentative->get()[0]));
    }

    protected function delete(Request $request)
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
            'register_request_id'   => ['required', 'integer'],
            'register_request_uuid' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->where('status_id', '=', $this->EnumStatusId('emAndamento'))->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('register_request_id', '=', $registerRequest->id)->where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        $registerRequestRepresentative->deleted_at = \Carbon\Carbon::now();

        if (!$registerRequestRepresentative->save()) {
            return response()->json(array("error" => "Poxa, ocorreu uma falha ao excluir o representante/usuário, por favor tente novamente mais tarde."));
        }

        return response()->json(array("success" => "Representante/usuário excluído com sucesso."));
    }

    public function sendWelcomeEmail(Request $request)
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
            return abort(400, "Bad Request | Invalid or missing data");
        }

        if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
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
                            "email" => $registerRequestRepresentative->email,
                            "name" => $registerRequestRepresentative->name
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
                return response()->json(['error' => 'Request failed', 'response' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ocorreu um erro ao enviar este e-mail. Por favor tente novamente mais tarde.']);
        }


        if ($response->successful()) {
            return (object) ["success" => 'E-mail enviado com sucesso.'];
        }
    }

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
                $status_id = 11;
                break;
            case 'emAnalise':
                $status_id = 55;
                break;
            default:
                break;
        }

        return $status_id;
    }

    protected function setSelfieS3Filename(Request $request)
    {
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

            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, por favor verifique ou tente novamente mais tarde."));
            }

            if (Auth::check()) {
                if ($request->replaced_by_agency != null && ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado'))) {
                    return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já analisada, por favor verifique os dados informados ou inicie uma nova solicitação."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


        if (!$document_type = DocumentType::where('id', '=', 7)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequestRepresentative->selfie_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->selfie_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_selfie_s3_filename', $registerRequest->selfie_s3_filename, $fileName->data, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('selfie_s3_filename', $registerRequest->selfie_s3_filename, $fileName->data, null, $request->id);
                }

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

        $registerRequestRepresentative->selfie_s3_filename = $fileName->data;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Selfie enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a selfie, por favor tente novamente mais tarde."));
    }

    protected function setDocumentFrontS3Filename(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'document_type_id'      => ['required', 'integer'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        try {

            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, por favor verifique ou tente novamente mais tarde."));
            }

            if (Auth::check()) {
                if ($request->replaced_by_agency != null && ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado'))) {
                    return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já analisada, por favor verifique os dados informados ou inicie uma nova solicitação."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
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


        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequestRepresentative->document_front_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_front_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_front_s3_filename', $registerRequestRepresentative->document_front_s3_filename, $fileName->data, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_front_s3_filename', $registerRequestRepresentative->document_front_s3_filename, $fileName->data, null, $request->id);
                }

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

        $registerRequestRepresentative->document_front_s3_filename = $fileName->data;

        if ($request->replaced_by_agency == null) {
            $registerRequestRepresentative->document_front_type_id = $document_type->id;
        }


        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Frente do documento enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a frente do documento, por favor tente novamente mais tarde."));
    }

    protected function setDocumentVerseS3Filename(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'document_type_id'      => ['required', 'integer'],
            'file_name'             => ['nullable', 'string'],
            'file64'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        try {

            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, por favor verifique ou tente novamente mais tarde."));
            }

            if (Auth::check()) {
                if ($request->replaced_by_agency != null && ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado'))) {
                    return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já analisada, por favor verifique os dados informados ou inicie uma nova solicitação."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


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


        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequestRepresentative->document_verse_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->document_verse_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_verse_s3_filename', $registerRequestRepresentative->document_verse_s3_filename, $fileName->data, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_verse_s3_filename', $registerRequestRepresentative->document_verse_s3_filename, $fileName->data, null, $request->id);
                }

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

        $registerRequestRepresentative->document_verse_s3_filename = $fileName->data;

        if ($request->replaced_by_agency == null) {
            $registerRequestRepresentative->document_verse_type_id = $document_type->id;
        }

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Verso do documento enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o verso do documento, por favor tente novamente mais tarde."));
    }

    protected function setDocumentTypeFront(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'document_type_id'      => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->document_front_type_id != $document_front_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_front_type_id', $registerRequestRepresentative->document_front_type_id, $document_front_type_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_front_type_id', $registerRequestRepresentative->document_front_type_id, $document_front_type_id, null, $request->id);
                }

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

        $registerRequestRepresentative->document_front_type_id = $document_front_type_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Tipo da frente do documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo da frente do documento, por favor tente novamente mais tarde."));
    }

    protected function setDocumentTypeVerse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'document_type_id'      => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->document_verse_type_id != $document_verse_type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_verse_type_id', $registerRequestRepresentative->document_verse_type_id, $document_verse_type_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_verse_type_id', $registerRequestRepresentative->document_verse_type_id, $document_verse_type_id, null, $request->id);
                }

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

        $registerRequestRepresentative->document_verse_type_id = $document_verse_type_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Tipo do verso do documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o tipo do verso do documento, por favor tente novamente mais tarde."));
    }

    protected function setAddressProofS3Filename(Request $request)
    {
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

            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, por favor verifique ou tente novamente mais tarde."));
            }

            if (Auth::check()) {
                if ($request->replaced_by_agency != null && ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado'))) {
                    return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já analisada, por favor verifique os dados informados ou inicie uma nova solicitação."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


        if (!$document_type = DocumentType::where('id', '=', 8)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequestRepresentative->address_proof_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->address_proof_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_address_proof_s3_filename', $registerRequest->address_proof_s3_filename, $fileName->data, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address_proof_s3_filename', $registerRequest->address_proof_s3_filename, $fileName->data, null, $request->id);
                }

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

        $registerRequestRepresentative->address_proof_s3_filename = $fileName->data;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Comprovante de endereço enviado com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar o comprovante de endereço, por favor tente novamente mais tarde."));
    }

    protected function setWeddingCertificateS3Filename(Request $request)
    {
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

            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }

            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, por favor verifique ou tente novamente mais tarde."));
            }

            if (Auth::check()) {
                if ($request->replaced_by_agency != null && ($registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado'))) {
                    return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já analisada, por favor verifique os dados informados ou inicie uma nova solicitação."));
                }
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


        if (!$document_type = DocumentType::where('id', '=', 10)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Tipo do documento não foi localizado, por favor tente novamente mais tarde."));
        }

        if (!(($fileName = $this->fileManagerS3($request->file_name, $request->file64, $document_type, $registerRequestRepresentative->wedding_certificate_s3_filename))->success)) {
            return response()->json(array("error" => $fileName->message));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequest->wedding_certificate_s3_filename != $fileName->data) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_wedding_certificate_s3_filename', $registerRequest->wedding_certificate_s3_filename, $fileName->data, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('wedding_certificate_s3_filename', $registerRequest->wedding_certificate_s3_filename, $fileName->data, null, $request->id);
                }

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

        $registerRequestRepresentative->wedding_certificate_s3_filename = $fileName->data;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Certidão de casamento enviada com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível enviar a certidão de casamento, por favor tente novamente mais tarde."));
    }

    protected function getSelfieS3(Request $request)
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
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        if ($registerRequestRepresentative->selfie_s3_filename != null and $registerRequestRepresentative->selfie_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 7)->first();
            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->selfie_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Selfie recuperada com sucesso',
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Selfie_' . $registerRequestRepresentative->selfie_s3_filename
                ]);
            }
            return response()->json(array("error" => "Não foi possível realizar o download da selfie, por favor tente mais tarde"));
        } else {
            return response()->json(array("error" => "Selfie não importada", "nao_importado" => true, "document" => "selfie"));
        }
    }

    protected function getDocumentFrontS3(Request $request)
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
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }


        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $documentTypeId = null;
        if ($registerRequestRepresentative->document_front_s3_filename != null and $registerRequestRepresentative->document_front_s3_filename != '') {

            $documentTypeId = $registerRequestRepresentative->document_front_type_id;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->document_front_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Frente do documento recuperada com sucesso',
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Frente_' . $registerRequestRepresentative->document_front_s3_filename,
                    "document" => "front",
                    "id" => $registerRequestRepresentative->document_front_type_id
                ]);
            }
            return response()->json(array("error" => "Não foi possível realizar o download da frente do documento, por favor tente mais tarde"));
        } else {
            return response()->json(array("error" => "Frente do documento não importada", "nao_importado" => true, "document" => "front"));
        }
    }

    protected function getDocumentVerseS3(Request $request)
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
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }

        $documentTypeId = null;
        if ($registerRequestRepresentative->document_front_s3_filename != null and $registerRequestRepresentative->document_front_s3_filename != '') {

            $documentTypeId = $registerRequestRepresentative->document_verse_type_id;
            $documentType = DocumentType::where('id', '=', $documentTypeId)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->document_verse_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Verso do documento recuperado com sucesso',
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Verso_' . $registerRequestRepresentative->document_verse_s3_filename,
                    "document" => "verse",
                    "id" => $registerRequestRepresentative->document_verse_type_id
                ]);
            }
            return response()->json(array("error" => "Não foi possível realizar o download do verso do documento, por favor tente mais tarde"));
        } else {
            return response()->json(array("error" => "Verso do documento não importado", "nao_importado" => true, "document" => "verse"));
        }
    }

    protected function getAddressProofS3(Request $request)
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
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        if ($registerRequestRepresentative->address_proof_s3_filename != null and $registerRequestRepresentative->address_proof_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 8)->first();
            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Comprovante de endereço recuperado com sucesso',
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_de_endereco_' . $registerRequestRepresentative->address_proof_s3_filename
                ]);
            }
            return response()->json(array("error" => "Não foi possível realizar o download do comprovante de endereço, por favor tente mais tarde"));
        } else {
            return response()->json(array("error" => "Comprovante de endereço não importado", "nao_importado" => true, "document" => "addressProof"));
        }
    }

    protected function getWeddingCertificateS3(Request $request)
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
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        try {
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first()) {
                return response()->json(array("error" => "Não foi possível localizar a requisição de cadastro, ou ela já foi enviada para análise, por favor verifique ou tente novamente mais tarde."));
            }
        } catch (\Exception $e) {
            return abort(404, "Not Found");
        }

        if (!Auth::check()) {
            if ($registerRequest->status_id == $this->EnumStatusId('pendente') || $registerRequest->status_id == $this->EnumStatusId('aprovado') || $registerRequest->status_id == $this->EnumStatusId('naoAprovado')) {
                return response()->json(array("error" => "Não é possível alterar uma solicitação de cadastro já enviada para análise, por favor verifique os dados informados ou inicie uma nova solicitação."));
            }
        }


        if ($registerRequestRepresentative->wedding_certificate_s3_filename != null and $registerRequestRepresentative->wedding_certificate_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 10)->first();
            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->wedding_certificate_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                return response()->json([
                    'success' => 'Certidão de casamento recuperada com sucesso',
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Certidao_de_casamento_' . $registerRequestRepresentative->wedding_certificate_s3_filename
                ]);
            }
            return response()->json(array("error" => "Não foi possível realizar o download da certidão de casamento, por favor tente mais tarde"));
        } else {
            return response()->json(array("error" => "Certidão de casamento não importada", "nao_importado" => true, "document" => "weddingCertificate"));
        }
    }

    protected function fileManagerS3($file_name_request, $file64, $document_type, $file_name_data)
    {
        $ext = strtolower(pathinfo($file_name_request, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'pdf'])) {
            return (object) [
                "success" => false,
                "message" => "Formato de arquivo $ext não permitido, formatos permitidos: jpg, jpeg, png e pdf."
            ];
        }


        $fileName = md5($document_type->id . date('Ymd') . time()) . '.' . $ext;

        $amazons3 = new AmazonS3();

        if (empty($file_name_data)) {
            $amazons3->fileName = $file_name_data;
            $amazons3->path     = $document_type->s3_path;
            $amazons3->fileDeleteAmazon();
        }

        $amazons3->fileName = $fileName;
        $amazons3->file64   = base64_encode(file_get_contents($file64));;
        $amazons3->path     = $document_type->s3_path;
        $upfile             = $amazons3->fileUpAmazon();

        if (!$upfile->success) {
            return (object) [
                "success" => false,
                "message" => "Poxa, não foi possível realizar o upload do documento informado, por favor tente novamente mais tarde."
            ];
        }
        return (object) [
            "success" => true,
            "data"    => $fileName
        ];
    }

    public function getRegisterRequestRepresentativeDocuments(Request $request)
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

        if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro do representante, por favor tente novamente mais tarde."));
        }


        $documentFront = [];
        $documentVerse = [];
        $selfie = [];
        $addressProof = [];
        $weddingCertificate = [];


        if ($registerRequestRepresentative->document_front_s3_filename != null and $registerRequestRepresentative->document_front_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequestRepresentative->document_front_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->document_front_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentFront = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Frente_' . $registerRequestRepresentative->document_front_s3_filename,
                    'document_type' => 'front'
                ];
            }
        }

        if ($registerRequestRepresentative->document_verse_s3_filename != null and $registerRequestRepresentative->document_verse_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', $registerRequestRepresentative->document_verse_type_id)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->document_verse_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $documentVerse = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Documento_Verso_' . $registerRequestRepresentative->document_verse_s3_filename,
                    'document_type' => 'verse'
                ];
            }
        }

        if ($registerRequestRepresentative->selfie_s3_filename != null and $registerRequestRepresentative->selfie_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 7)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->selfie_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $selfie = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Selfie_' . $registerRequestRepresentative->selfie_s3_filename,
                    'document_type' => 'selfie'
                ];
            }
        }

        if ($registerRequestRepresentative->address_proof_s3_filename != null and $registerRequestRepresentative->address_proof_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 8)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->address_proof_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $addressProof = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Comprovante_Residencia_' . $registerRequestRepresentative->address_proof_s3_filename,
                    'document_type' => 'addressProof'
                ];
            }
        }

        if ($registerRequestRepresentative->wedding_certificate_s3_filename != null and $registerRequestRepresentative->wedding_certificate_s3_filename != '') {
            $documentType = DocumentType::where('id', '=', 10)->first();

            $fileAmazon = new AmazonS3();
            $fileAmazon->path = $documentType->s3_path;
            $fileAmazon->fileName = $registerRequestRepresentative->wedding_certificate_s3_filename;

            if ($fileAmazon = $fileAmazon->fileDownAmazon()) {
                $weddingCertificate = [
                    'base64' => $fileAmazon->file64,
                    'fileName' => 'Certidao_casamento_' . $registerRequestRepresentative->wedding_certificate_s3_filename,
                    'document_type' => 'weddingCertificate'
                ];
            }
        }


        return response()->json(array(
            'success' => 'Documentos recuperados com sucesso',
            'data' => [
                $documentFront,
                $documentVerse,
                $selfie,
                $addressProof,
                $weddingCertificate
            ]
        ));
    }


    //----------------------------

    public function setCpfCnpj(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'cpf_cnpj'              => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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



        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequestRepresentative->cpf_cnpj != $cpf_cnpj) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_cpf_cnpj', $registerRequestRepresentative->cpf_cnpj, $request->cpf_cnpj, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('cpf_cnpj', $registerRequestRepresentative->cpf_cnpj, $request->cpf_cnpj, null, $request->id);
                }

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

        $registerRequestRepresentative->cpf_cnpj =  $cpf_cnpj;

        if ($registerRequestRepresentative->save()) {
            if (strlen($cpf_cnpj) == 11) {
                return response()->json(array("success" => "CPF definido com sucesso."));
            }
            return response()->json(array("success" => "CNPJ definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CPF/CNPJ, por favor tente novamente mais tarde."));
    }

    public function setName(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'name'                  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->name != $name) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_name', $registerRequestRepresentative->name, $name, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('name', $registerRequestRepresentative->name, $name, null, $request->id);
                }

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

        $registerRequestRepresentative->name = $name;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Nome definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o nome, por favor tente novamente mais tarde."));
    }

    public function setSurname(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'surname'               => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->surname != $surname) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_surname', $registerRequestRepresentative->surname, $surname, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('surname', $registerRequestRepresentative->surname, $surname, null, $request->id);
                }

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

        $registerRequestRepresentative->surname = $surname;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Sobrenome definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o sobrenome, por favor tente novamente mais tarde."));
    }

    public function setPep(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'pep'                   => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->pep != $request->pep) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_pep', $registerRequestRepresentative->pep, $request->pep, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('pep', $registerRequestRepresentative->pep, $request->pep, null, $request->id);
                }

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

        $registerRequestRepresentative->pep = $request->pep;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "PEP definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o PEP, por favor tente novamente mais tarde."));
    }

    public function setBirthDate(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'birth_date'            => ['nullable', 'date']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->birth_date != $request->birth_date) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_birth_date', $registerRequestRepresentative->birth_date, $request->birth_date, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('birth_date', $registerRequestRepresentative->birth_date, $request->birth_date, null, $request->id);
                }

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

        $registerRequestRepresentative->birth_date = $request->birth_date;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Data de nascimento definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a data, por favor tente novamente mais tarde."));
    }

    public function setPhone(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'phone'                 => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->phone != $request->phone) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_phone', $registerRequestRepresentative->phone, $phone, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('phone', $registerRequestRepresentative->phone, $phone, null, $request->id);
                }

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

        $registerRequestRepresentative->phone = $phone;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Telefone definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o telefone, por favor tente novamente mais tarde."));
    }

    public function setEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'email'                 => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->email != $email) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_email', $registerRequestRepresentative->email, $email, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('email', $registerRequestRepresentative->email, $email, null, $request->id);
                }

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

        $registerRequestRepresentative->email = $email;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Email definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o email, por favor tente novamente mais tarde."));
    }

    public function setPublicPlace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'public_place'          => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->public_place != $public_place) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_public_place', $registerRequestRepresentative->public_place, $public_place, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('public_place', $registerRequestRepresentative->public_place, $public_place, null, $request->id);
                }

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

        $registerRequestRepresentative->public_place = $public_place;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Logradouro definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o logradouro, por favor tente novamente mais tarde."));
    }

    public function setAddress(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'address'               => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->address != $address) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_address', $registerRequestRepresentative->address, $address, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('address', $registerRequestRepresentative->address, $address, null, $request->id);
                }

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

        $registerRequestRepresentative->address = $address;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Endereço definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o endereço, por favor tente novamente mais tarde."));
    }

    public function setNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'number'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->number != $number) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_number', $registerRequestRepresentative->number, $number, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('number', $registerRequestRepresentative->number, $number, null, $request->id);
                }

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

        $registerRequestRepresentative->number = $number;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Número definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o número, por favor tente novamente mais tarde."));
    }

    public function setComplement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'complement'            => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->complement != $complement) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_complement', $registerRequestRepresentative->complement, $complement, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('complement', $registerRequestRepresentative->complement, $complement, null, $request->id);
                }

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

        $registerRequestRepresentative->complement = $complement;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Complemento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o complemento, por favor tente novamente mais tarde."));
    }

    public function setDistrict(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'district'              => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->district != $district) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_district', $registerRequestRepresentative->district, $district, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('district', $registerRequestRepresentative->district, $district, null, $request->id);
                }

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

        $registerRequestRepresentative->district = $district;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Bairro definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o bairro, por favor tente novamente mais tarde."));
    }

    public function setCity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'city'                  => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->city != $city) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_city', $registerRequestRepresentative->city, $city, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('city', $registerRequestRepresentative->city, $city, null, $request->id);
                }

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

        $registerRequestRepresentative->city = $city;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Cidade definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a cidade, por favor tente novamente mais tarde."));
    }

    public function setState(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'state_id'              => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->state_id != $request->state_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_state_id', $registerRequestRepresentative->state_id, $request->state_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('state_id', $registerRequestRepresentative->state_id, $request->state_id, null, $request->id);
                }

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

        $registerRequestRepresentative->state_id = $request->state_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Estado definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado, por favor tente novamente mais tarde."));
    }

    public function setZipCode(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'zip_code'              => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->zip_code != $zip_code) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_zip_code', $registerRequestRepresentative->zip_code, $zip_code, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('zip_code', $registerRequestRepresentative->zip_code, $zip_code, null, $request->id);
                }

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

        $registerRequestRepresentative->zip_code = $zip_code;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "CEP definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o CEP, por favor tente novamente mais tarde."));
    }

    public function setGender(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'gender_id'             => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->gender_id != $request->gender_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_gender_id', $registerRequestRepresentative->gender_id, $request->gender_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('gender_id', $registerRequestRepresentative->gender_id, $request->gender_id, null, $request->id);
                }

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

        $registerRequestRepresentative->gender_id = $request->gender_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Gênero definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o gênero, por favor tente novamente mais tarde."));
    }

    public function setDocumentNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'document_number'       => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->document_number != $document_number) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_document_number', $registerRequestRepresentative->document_number, $document_number, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('document_number', $registerRequestRepresentative->document_number, $document_number, null, $request->id);
                }

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

        $registerRequestRepresentative->document_number = $document_number;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Número do documento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o número do documento, por favor tente novamente mais tarde."));
    }

    public function setIncome(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'income'                => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->income != $request->income) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_income', $registerRequestRepresentative->income, $request->income, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('income', $registerRequestRepresentative->income, $request->income, null, $request->id);
                }

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

        $registerRequestRepresentative->income = $request->income;

        if ($registerRequestRepresentative->save()) {
            if ($registerRequest->register_request_type_id == 1) {
                return response()->json(array("success" => "Renda definida com sucesso."));
            }
            return response()->json(array("success" => "Faturamento definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a renda, por favor tente novamente mais tarde."));
    }

    public function setMotherName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'mother_name'           => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->mother_name != $mother_name) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_mother_name', $registerRequestRepresentative->mother_name, $mother_name, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('mother_name', $registerRequestRepresentative->mother_name, $mother_name, null, $request->id);
                }

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

        $registerRequestRepresentative->mother_name =  $mother_name;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Nome da mãe definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o nome da mãe, por favor tente novamente mais tarde."));
    }

    public function setMaritalStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'marital_status_id'     => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->marital_status_id != $request->marital_status_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_marital_status_id', $registerRequestRepresentative->marital_status_id, $request->marital_status_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('marital_status_id', $registerRequestRepresentative->marital_status_id, $request->marital_status_id, null, $request->id);
                }

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

        $registerRequestRepresentative->marital_status_id = $request->marital_status_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Estado civil definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o estado civil, por favor tente novamente mais tarde."));
    }

    public function setNationality(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                            => ['required', 'integer'],
            'uuid'                          => ['required', 'string'],
            'nationality_id'                => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->nationality_id != $request->nationality_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_nationality_id', $registerRequestRepresentative->nationality_id, $request->nationality_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('nationality_id', $registerRequestRepresentative->nationality_id, $request->nationality_id, null, $request->id);
                }

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

        $registerRequestRepresentative->nationality_id = $request->nationality_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Nacionalidade do representante definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a nacionalidade do representante, por favor tente novamente mais tarde."));
    }

    public function setAdministrator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'is_administrator'      => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

            if ($registerRequestRepresentative->is_administrator != $request->is_administrator) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_is_administrator', $registerRequestRepresentative->is_administrator, $request->is_administrator, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('is_administrator', $registerRequestRepresentative->is_administrator, $request->is_administrator, null, $request->id);
                }

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

        $registerRequestRepresentative->is_administrator = $request->is_administrator;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "ADMINISTRADOR definido com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir o ADMINISTRADOR, por favor tente novamente mais tarde."));
    }

    public function setPartnerType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                            => ['required', 'integer'],
            'uuid'                          => ['required', 'string'],
            'type_id'                       => ['nullable', 'integer']
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        try {
            if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
                return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
            }
            if (!$registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereIn('status_id', [$this->EnumStatusId('pendente'), $this->EnumStatusId('emAnalise')])->whereNull('deleted_at')->first()) {
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

        if (!PartnerType::where('id', '=', $request->type_id)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Função do representante não foi localizada, por favor tente novamente mais tarde."));
        }

        if ($registerRequest->status_id == $this->EnumStatusId('emAnalise')) {

            if ($registerRequest->analyzed_by != Auth::user()->id) {
                return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
            }

            if ($registerRequestRepresentative->type_id != $request->type_id) {
                $regReqOrRepLog = new RegisterRequestsOrRepresentativeLogsClass();

                if ($registerRequestRepresentative->is_representative) {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('representative_type_id', $registerRequestRepresentative->type_id, $request->type_id, null, $request->id);
                } else {
                    $response = $regReqOrRepLog->createRegisterRequestOrRepresentativeLog('type_id', $registerRequestRepresentative->type_id, $request->type_id, null, $request->id);
                }

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

        $registerRequestRepresentative->type_id = $request->type_id;

        if ($registerRequestRepresentative->save()) {
            return response()->json(array("success" => "Função do representante definida com sucesso."));
        }
        return response()->json(array("error" => "Não foi possível definir a função do representante, por favor tente novamente mais tarde."));
    }


    protected function getInactiveRepresentativePermissions(Request $request)
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
            'id'  => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        $permission = new Permission();
        $permission->relationship_id = 4;
        $permission->onlyActive = 1;
        $permissions = $permission->getPermission();

        $activePermissions = new RegisterRequestRepresentativesPermissions();
        $activePermissions->id = $request->id; // Corrigindo a variável aqui
        $activePermissions->onlyActive = 1; // Corrigindo a variável aqui
        $activePermissions = $activePermissions->getPermission();

        $filteredPermissions = $permissions->reject(function ($permission) use ($activePermissions) {
            return $activePermissions->contains('permission_id', $permission->id);
        });

        return response()->json(array_values($filteredPermissions->toArray()), 200);
    }

    protected function getRepresentativePermissions(Request $request)
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
            'id'  => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }


        if (!RegisterRequestRepresentative::where('id', '=', $request->id)->first()) {
            return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
        }

        $permissions = new RegisterRequestRepresentativesPermissions();
        $permissions->id = $request->id;
        $permissions->onlyActive = 1;

        return response()->json($permissions->getPermission(), 200);

        // return response()->json(array("error" => "Ocorreu um erro, por favor verifique os dados informados ou tente novamente mais tarde."));


    }

    protected function setRepresentativePermissions(Request $request)
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
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'permissions'           => ['required'],
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
        }

        if ($registerRequestRepresentative->user_relationship_created == 1) {
            return response()->json(array("error" => "O usuário/vínculo já foi criado e as permissões não podem mais ser alteradas."));
        }

        foreach ($request->permissions as $permission) {
            RegisterRequestRepresentativesPermissions::create([
                'representative_id' => $request->id,
                'permission_id'  => $permission,
                'created_at'     => \Carbon\Carbon::now(),
            ]);
        }

        return response()->json(array("success" => "Permissões atribuídas com sucesso."));
    }

    protected function deleteRepresentativePermissions(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [40];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(array("error" => "Representante não localizado, por favor verifique os dados informados ou tente novamente mais tarde."));
        }

        if ($registerRequestRepresentative->user_relationship_created == 1) {
            return response()->json(array("error" => "O usuário/vínculo já foi criado e as permissões não podem mais ser alteradas."));
        }

        $error = [];
        $success = [];

        foreach ($request->permissions as $item_id) {
            if (!$permission_group_item = RegisterRequestRepresentativesPermissions::where('representative_id', '=', $request->id)->where('permission_id', '=', $item_id)->whereNull('deleted_at')->first()) {
                array_push($error, ["error" => "Poxa, não localizamos a permissão com grupo ou ela já foi removida, reveja os dados informados e tente novamente", "id" => $item_id]);
                continue;
            }
            if (RegisterRequestRepresentativesPermissions::where('permission_id', '=', $item_id)->where('representative_id', '=', $request->id)->first()) {
                $permission_group_item->deleted_at = \Carbon\Carbon::now();
                if ($permission_group_item->save()) {
                    array_push($success, ["success" => "Permissão removida com sucesso", "id" => $item_id]);
                    continue;
                } else {
                    array_push($error, ["error" => "Poxa, não foi possível remover a permissão no momento, por favor tente novamente mais tarde", "id" => $item_id]);
                    continue;
                }
            } else {
                array_push($error, ["error" => "Poxa, não foi possível remover a permissão no momento, por favor tente novamente mais tarde", "id" => $item_id]);
                continue;
            }
        }

        if ($error != null) {
            return response()->json(array(
                "error"        => "Atenção, não foi possível remover algumas permissões",
                "error_list"   => $error,
                "success_list" => $success,
            ));
        }

        return response()->json(array(
            "success"       => "Permissões removidas com sucesso",
            "error_list"   => $error,
            "success_list" => $success,
        ));
    }
}
