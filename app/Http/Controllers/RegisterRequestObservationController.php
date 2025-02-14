<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequest;
use App\Models\RegisterRequestObservation;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class RegisterRequestObservationController extends Controller
{

    public function show(Request $request)
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

        $registerRequestObservation = new RegisterRequestObservation();
        $registerRequestObservation->onlyActive = $request->onlyActive;
        $cpf_cnpj = preg_replace( '/[^0-9]/is', '', $request->cpf_cnpj );
        $registerRequestObservation->cpf_cnpj = $cpf_cnpj;
        $registerRequestObservation->register_request_id = $request->register_request_id;
        return response()->json($registerRequestObservation->get());
    }

    public function store(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'register_request_id'   => ['required', 'integer'],
            'register_request_uuid' => ['required', 'string'],
            'observation'           => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if (! RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar a solicitação, por favor tente novamente mais tarde."));
        }

        if(RegisterRequestObservation::create([
            'uuid'                => Str::orderedUuid(),
            'register_request_id' => $request->register_request_id,
            'user_id'             => Auth::user()->id,
            'observation'         => $request->observation,
            'created_at'          => \Carbon\Carbon::now()
        ])) {
            return response()->json(["success" => "Observação criada com sucesso."]);
        }

        return response()->json(["error" => "Ocorreu um erro ao criar a observação, por favor, verifique os dados informados e tente novamente mais tarde."]);
    }

    public function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'register_request_id'   => ['required', 'integer'],
            'register_request_uuid' => ['required', 'string'],
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string']
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if (! RegisterRequest::where('id', '=', $request->register_request_id)->where('uuid', '=', $request->register_request_uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar a solicitação, por favor tente novamente mais tarde."));
        }

        if (! $registerRequestObservation = RegisterRequestObservation::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->whereNull('deleted_at')->first()) {
            return response()->json(array("error" => "Não foi possível localizar o registro, por favor tente novamente mais tarde."));
        }

        if($registerRequestObservation->user_id != Auth::user()->id) {
            return response()->json(array("error" => "Não foi possível excluir a observação, somente quem a criou pode excluí-la."));
        }

        $registerRequestObservation->deleted_at = \Carbon\Carbon::now();
        if($registerRequestObservation->save()) {
            return response()->json(["success" => "Observação excluída com sucesso."]);
        }
        
        return response()->json(["error" => "Ocorreu um erro ao excluir a observação, por favor, verifique os dados informados e tente novamente mais tarde."]);
    }

}
