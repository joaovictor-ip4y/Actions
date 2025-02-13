<?php

namespace App\Http\Controllers;

use App\Models\RegisterRequest;
use App\Models\RegisterRequestCheck;
use App\Models\RegisterRequestRepresentative;
use App\Models\RegisterRequestsData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Services\Account\AccountRelationshipCheckService;

class RegisterRequestCheckController extends Controller
{
    
    public function verifyCheck(Request $request) 
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
            'description_default_id'                => ['required', 'integer'],
            'description_representative_id'         => ['nullable', 'integer'],
            'register_request_id'                   => ['nullable', 'integer'],
            'register_request_representative_id'    => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }
        
        
        $registerRequest = RegisterRequest::where('id', '=', $request->register_request_id)->whereNull('deleted_at')->first();
        $registerRequestRepresentative = RegisterRequestRepresentative::where('id', '=', $request->register_request_representative_id)->whereNull('deleted_at')->first();

        $same_analist = $this->checkSameAnalyst($registerRequest, $registerRequestRepresentative);

        if(!$same_analist) {
            return response()->json(array("error" => "Não foi possível realizar a alteração pois a análise da solicitação de cadastro pertence a outro usuário. Realize a alteração de análise primeiramente."));
        }
        
        $field_id = $request->description_default_id; 

        // caso seja referente a sócio/usuário
        if (isset($request->register_request_representative_id)) {
           

            //caso seja o solicitante, muda a nomenclatura para "representative_name" por exemplo, ao invés de apenas "name"
            if($registerRequestRepresentative->is_representative == 1) {
                $field_id = $request->description_representative_id;
            } 
            

            if( RegisterRequestCheck::where('register_request_representative_id', '=', $request->register_request_representative_id)->where('field_id', '=', $field_id)->whereNull('deleted_at')->count() > 0 ) {   

                return $this->editCheck('register_request_representative_id', $request->register_request_representative_id, $field_id);

            } 

         // caso seja referente à empresa
        } else if(isset($request->register_request_id)) {

            if( RegisterRequestCheck::where('register_request_id', '=', $request->register_request_id)->where('field_id', '=', $field_id)->whereNull('deleted_at')->count() > 0 ) {

                return $this->editCheck('register_request_id', $request->register_request_id, $field_id);

            } 
        
        }         
   
        return $this->createCheckRegister($request, $field_id);

    }

    public function editCheck(string $key, int $id, string $field_id) {
        

        $rgstRqstCheck = RegisterRequestCheck::where($key, '=', $id)->where('field_id', '=', $field_id)->whereNull('deleted_at')->first();

        if($rgstRqstCheck->is_checked == 1) {

            $rgstRqstCheck->is_checked = 0;

        } else {

            $rgstRqstCheck->is_checked = 1;

        }

        if($rgstRqstCheck->save()) {
            return response()->json(array("success" => "Confirmação do campo alterada com sucesso.", "is_checked" => $rgstRqstCheck->is_checked));
        }

        return response()->json(array("error" => "Não foi possível alterar a confirmação do campo, por favor tente novamente mais tarde."));

    }

    public function createCheckRegister(Request $request, string $field_id) {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [349];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (isset($request->register_request_representative_id)) {

            $register_request_id = null;
            $register_request_representative_id = $request->register_request_representative_id;

        } else {

            $register_request_id = $request->register_request_id;
            $register_request_representative_id = null;

        }

        if ( RegisterRequestCheck::create([
            'uuid'                                 => Str::orderedUuid(),
            'register_request_id'                  => $register_request_id,
            'register_request_representative_id'   => $register_request_representative_id,
            'field_id'                             => $field_id,
            'user_id'                              => Auth::user()->id,
            'is_checked'                           => 1,
            'created_at'                           => \Carbon\Carbon::now()
        ])) {
            return response()->json(array("success" => "Confirmação do campo criada com sucesso.", "is_checked" => 1));
        }

        return response()->json(array("error" => "Não foi possível criar a confirmação do campo, por favor tente novamente mais tarde."));

    }

    public function getCheckIds(Request $request) {

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
            'register_request_id'                  => ['nullable', 'integer'],
            'register_request_representative_id'   => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }
        

        $data = [];

        if (isset($request->register_request_id)) {
            
            $rgstReqChecks = RegisterRequestCheck::where('register_request_id', '=', $request->register_request_id)->where('is_checked', '=', 1)->whereNull('deleted_at')->get();
        
        } else if (isset($request->register_request_representative_id)) {
        
            $rgstReqChecks = RegisterRequestCheck::where('register_request_representative_id', '=', $request->register_request_representative_id)->where('is_checked', '=', 1)->whereNull('deleted_at')->get();
        
        }


        foreach($rgstReqChecks as $rgstReqCheck) {
            $regReqData = RegisterRequestsData::where('id', '=', $rgstReqCheck->field_id)->first();
            array_push($data, $regReqData->id);
        }


        return response()->json(["success" => $data]);

    }

    public function checkSameAnalyst($registerRequest, $registerRequestRepresentative) {

        if(isset($registerRequestRepresentative)) {
            $registerRequest = RegisterRequest::where('id', '=', $registerRequestRepresentative->register_request_id)->whereNull('deleted_at')->first();
        }

        if ($registerRequest->analyzed_by != Auth::user()->id) {
            return false;
        } else {
            return true;
        }
    }
}
