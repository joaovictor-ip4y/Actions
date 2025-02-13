<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\UserRelationshipRequest;
use App\Models\UserRelationshipRequestPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\Account\AccountRelationshipCheckService;

class UserRelationshipRequestPermissionController extends Controller
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


        $validator = Validator::make($request->all(), [
            'user_relationship_request_id'               => ['required', 'integer']
        ],[
            'user_relationship_request_id.required'      => 'É obrigatório informar o user_relationship_request_id'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $userRelationshipRequestPermission                               = new UserRelationshipRequestPermission();
        $userRelationshipRequestPermission->id                           = $request->id;
        $userRelationshipRequestPermission->uuid                         = $request->uuid;
        $userRelationshipRequestPermission->user_relationship_request_id = $request->user_relationship_request_id;
        $userRelationshipRequestPermission->only_active                  = $request->only_active;
        return response()->json($userRelationshipRequestPermission->get());
    }

    protected function store(Request $request)
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

        $validator = Validator::make($request->all(), [
            'user_relationship_request_id'                 => ['required', 'integer'],
            'user_relationship_request_uuid'               => ['required', 'string'],
            'permission_id'                                => ['required'],
        ],[
            'user_relationship_request_id.required'        => 'É obrigatório informar o user_relationship_request_id',
            'user_relationship_request_uuid.required'      => 'É obrigatório informar o user_relationship_request_uuid',
            'permission_id.required'                       => 'É obrigatório informar o permission_id',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if( ! UserRelationshipRequest::where('id', '=', $request->user_relationship_request_id)
        ->where('uuid', '=', $request->user_relationship_request_uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('status_id', '=', 4)
        ->first()) {
            return response()->json(["error" => "Solicitação de vínculo de usuário não localizada ou já enviada para aprovação."]);
        }


        $errorOnCreateRequestPermissionList = [];
        $permissionsId = $request->permission_id;
        foreach ($permissionsId as $permissionId) {

            if( ! UserRelationshipRequestPermission::where('user_relationship_request_id', '=', $request->user_relationship_request_id) //se nao existir registro, add
            ->where('permission_id', '=', $permissionId)
            ->whereNull('deleted_at')
            ->first()) {

                if( Permission::where('id', '=', $permissionId) //e se existe esse registro na permissions
                ->where('relationship_id', '=', 3)
                ->whereRaw('id not in (176, 181, 182, 192, 193, 205)') // permissions not allowed to user relationship request
                ->first()) {

                    if( ! UserRelationshipRequestPermission::create([
                        'user_relationship_request_id' => $request->user_relationship_request_id,
                        'permission_id'                => $permissionId,
                        'uuid'                         => Str::orderedUuid(),
                        'deleted_at'                   => null
                    ])) {

                        array_push($errorOnCreateRequestPermissionList, 
                            $permissionId
                        );

                    } 
                                   
                } 

            }  

        }

        if(count($errorOnCreateRequestPermissionList) == 0) {

            return response()->json(["success" => "Permissões definidas com sucesso."]);
            
        } else {
            
            return response()->json(["error" => "Poxa, não foi possível definir algumas das permissões no momento, por favor tente novamente mais tarde.", "data" => $errorOnCreateRequestPermissionList]);

        }

    }

    protected function destroy(Request $request)
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


        $validator = Validator::make($request->all(), [
            'user_relationship_request_id'                 => ['required', 'integer'],
            'user_relationship_request_uuid'               => ['required', 'string'],
            'permission_id'                                => ['required'],
        ],[
            'user_relationship_request_id.required'        => 'É obrigatório informar o user_relationship_request_id',
            'user_relationship_request_uuid.required'      => 'É obrigatório informar o user_relationship_request_uuid',
            'permission_id.required'                       => 'É obrigatório informar o permission_id',
            'uuid.required'                                => 'É obrigatório informar o uuid',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        if( ! UserRelationshipRequest::where('id', '=', $request->user_relationship_request_id)
        ->where('uuid', '=', $request->user_relationship_request_uuid)
        ->where('master_id', '=', $checkAccount->master_id)
        ->where('account_id', '=', $checkAccount->account_id)
        ->where('status_id', '=', 4)
        ->first()) {
            return response()->json(["error" => "Solicitação de vínculo de usuário não localizada ou já enviada para aprovação."]);
        }

        
        $errorOnUpdateRequestPermissionList = [];
        $permissionsId = $request->permission_id;
        foreach ($permissionsId as $permissionId) {

            if( $userRelationshipRequestPermission = UserRelationshipRequestPermission::where('user_relationship_request_id', '=', $request->user_relationship_request_id)
            ->where('id', '=', $permissionId)
            ->first()) {

                $userRelationshipRequestPermission->deleted_at = \Carbon\Carbon::now();

                if( ! $userRelationshipRequestPermission->save()) {
                    
                    array_push($errorOnUpdateRequestPermissionList, 
                        $permissionId
                    );

                } 

            } 
            
        } 

        if (count($errorOnUpdateRequestPermissionList) == 0 ) {

            return response()->json(["success" => "Permissões removidas com sucesso."]);
            
        } else {
            
            return response()->json(["error" => "Poxa, não foi possível remover algumas das permissões no momento, por favor tente novamente mais tarde.", "dataUpdatingRequestPermission" => $errorOnUpdateRequestPermissionList]);

        }
    }
}
