<?php

namespace App\Http\Controllers;

use App\Models\UsrRltnshpPrmssn;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\PermissionGroupItem;
use App\Models\UserRelationship;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class UsrRltnshpPrmssnController extends Controller
{

    public function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [140];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error   = [];
        $success = [];
        $permission = '';

        foreach ($request->permission_id as $req) {

            if ($usr_rltnshp_prmssn = UsrRltnshpPrmssn::where('permission_id','=',$req)->where('usr_rltnshp_id','=',$request->usr_rltnshp_id)->whereNull('deleted_at')->first()) {

                array_push($error,[
                    "error"  => "Permissão já concedida para o vínculo",
                    "id"     => $usr_rltnshp_prmssn->permission_id]
                );

            } else {

                if (!$permission = Permission::where('id','=',$req)->whereNull('deleted_at')->first()) {
                    array_push($error,["error" => "Poxa, não localizamos a permissão, reveja os dados informados e tente novamente", "id"=>$req]);
                    continue;
                }

                if (!UserRelationship::where('id','=',$request->usr_rltnshp_id)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Poxa, não localizamos o relacionamento da permissão com usuario, reveja os dados informados e tente novamente"));
                }

                if($request->prmssn_grp_id != null){
                    if (!PermissionGroup::where('id','=',$request->prmssn_grp_id)->whereNull('deleted_at')->first()) {
                        return response()->json(array("error" => "Poxa, não localizamos o grupo de permissão, reveja os dados informados e tente novamente"));
                    }
                }


                if (UsrRltnshpPrmssn::create([
                    'usr_rltnshp_id' => $request->usr_rltnshp_id,
                    'prmssn_grp_id'  => $request->prmssn_grp_id,
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

        return response()->json(array(
            "success"       => "Permissões concedidas com sucesso",
            "error_list"   => $error,
            "success_list" => $success,
        ));
    }

    public function newToPjAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                 = new AccountRelationshipCheckService();
        $accountCheckService->request        = $request;
        $accountCheckService->permission_id  = [392];
        $checkAccount                        = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error   = [];
        $success = [];
        $permission = '';
        $permissionNotAllowed = [];

        //check if relationship is from account
        if( ! $userRelationship = UserRelationship::where('account_id', '=', $checkAccount->account_id)->where('id', '=', $request->usr_rltnshp_id)->where('relationship_id', '=', 3)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Vínculo não pertence a conta"));
        }

        foreach ($request->permission_id as $req) {

            if ($usr_rltnshp_prmssn = UsrRltnshpPrmssn::where('permission_id','=',$req)->where('usr_rltnshp_id','=',$userRelationship->id)->whereNull('deleted_at')->first()) {

                array_push($error,[
                    "error"  => "Permissão já concedida para o vínculo",
                    "id"     => $usr_rltnshp_prmssn->permission_id]
                );

            } else {

                if (!$permission = Permission::where('id','=',$req)->whereNull('deleted_at')->first()) {
                    array_push($error,["error" => "Poxa, não localizamos a permissão, reveja os dados informados e tente novamente", "id"=>$req]);
                    continue;
                }

                if (!UserRelationship::where('id','=',$userRelationship->id)->whereNull('deleted_at')->first()) {
                    return response()->json(array("error" => "Poxa, não localizamos o relacionamento da permissão com usuario, reveja os dados informados e tente novamente"));
                }

                if( Permission::where('id', '=', $permission->id)
                ->where('relationship_id', '=', 3)
                ->whereRaw('id not in (176, 181, 182, 192, 193, 205)') // permissions not allowed to user in pj account
                ->first()) {
                    
                    if (UsrRltnshpPrmssn::create([
                        'usr_rltnshp_id' => $userRelationship->id,
                        'prmssn_grp_id'  => $userRelationship->permission_group_id,
                        'permission_id'  => $permission->id,
                        'created_at'     => \Carbon\Carbon::now(),
                    ])) {
                        array_push($success, ["success" => "Permissão concedida ao vínculo com sucesso", "id"=>$permission->id]);
                    } else {
                        array_push($error, ["error" => "Poxa, não foi possível conceder a permissão para o vínculo no momento, por favor tente novamente mais tarde", "id"=>$permission->id]);
                    }

                } else {
                    array_push($permissionNotAllowed, [$req]);
                }

            }
        }

        if( !empty($permissionNotAllowed) ) {
            return response()->json(array(
                "permission_not_allowed" => $permissionNotAllowed,
                "error"                  => "Atenção, não foi possível conceder uma ou mais permissões",
                "error_list"             => $error,
                "success_list"           => $success,
            ));
        }

        if ($error != null) {
            return response()->json(array(
                "error"        => "Atenção, não foi possível conceder uma ou mais permissões",
                "error_list"   => $error,
                "success_list" => $success,
            ));
        }

        return response()->json(array(
            "success"       => "Permissões concedidas com sucesso",
            "error_list"   => $error,
            "success_list" => $success,
        ));
    }

    public function updateGroup(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id  = [137];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $permission_group_id                = new PermissionGroupItem();
        $permission_group_id->master_id     = $checkAccount->master_id;
        $permission_group_id->prmsn_grp_id  = $request->prmsn_grp_id;
        $permission_group_id->onlyActive    = 1;
        $permission_group_data              = $permission_group_id->getPermissionGroupItem();

        if (!$permission_group  = PermissionGroup::where('id','=',$request->prmsn_grp_id)->first()) {
            return response()->json(array("error" => "Poxa, tivemos um problema ao atualizar o grupo de permissões, por favor tente novamente mais tarde"));
        }

        if (!$user_relationship = UserRelationship::where('id','=',$request->usr_rltnshp_id)->first()) {
            return response()->json(array("error" => "Poxa, tivemos um problema ao atualizar o grupo de permissões, por favor tente novamente mais tarde"));
        }

        if ($permission_group->relationship_id == $user_relationship->relationship_id) {
            UsrRltnshpPrmssn::removePermissions($request->usr_rltnshp_id);
            foreach ($permission_group_data as $prmssn_grp_dt ) {
                UsrRltnshpPrmssn::create([
                    'usr_rltnshp_id' => $request->usr_rltnshp_id,
                    'prmssn_grp_id'  => $prmssn_grp_dt->permission_group_id,
                    'permission_id'  => $prmssn_grp_dt->permission_id,
                    'created_at'     => \Carbon\Carbon::now(),
                ]);
            }
            $user_relationship->permission_group_id = $request->prmsn_grp_id;
            if ($user_relationship->save()) {
                return response()->json(array("success" => "Grupo de permissões atualizado com sucesso para o vínculo"));
            } else {
                return response()->json(array("error" => "Poxa, tivemos um problema ao atualizar o grupo de permissões, por favor tente novamente mais tarde"));
            }
        } else {
            return response()->json(array("error" =>  "O grupo de permissão informado não pode ser utilizado no tipo de vínculo selecionado"));
        }
    }

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [138, 393];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $usr_rltnshp_prmssn                         = new UsrRltnshpPrmssn();
        $usr_rltnshp_prmssn->master_id              = $checkAccount->master_id;
        $usr_rltnshp_prmssn->usr_rltnshp_id         = $request->usr_rltnshp_id;
        $usr_rltnshp_prmssn->relationship_id        = $request->relationship_id;
        $usr_rltnshp_prmssn->permission_group_id    = $request->permission_group_id;
        $usr_rltnshp_prmssn->permission_id          = $request->permission_id;
        $usr_rltnshp_prmssn->onlyActive             = $request->onlyActive;
        $usr_rltnshp_prmssn->id                     = $request->id;

        return response()->json($usr_rltnshp_prmssn->get());

    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [140];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error   = [];
        $success = [];

        foreach ($request->id as $req) {
            if ($usr_rltnshp_prmssn = UsrRltnshpPrmssn::where('id','=',$req)->where('usr_rltnshp_id','=',$request->usr_rltnshp_id)->whereNull('deleted_at')->first()) {
                $usr_rltnshp_prmssn->deleted_at = \Carbon\Carbon::now();
                if ($usr_rltnshp_prmssn->save()) {
                    array_push($success,["success" => "Permissão excluída com sucesso", "id" => $req]);
                } else {
                    array_push($error,["error" => "Poxa, não foi possível remover a permissão no momento, por favor tente mais tarde", "id" => $req]);
                }

            } else {
                array_push($error,["error" => "Poxa, não localizamos o relacionamento da permissão com usuario ou ela já foi removida, reveja os dados informados e tente novamente", "id" => $req]);
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
            "success"      => "Permissões removidas com sucesso",
            "error_list"   => $error,
            "success_list" => $success,
        ));
    }

    protected function deleteToPjAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [392];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error   = [];
        $success = [];

        //check if relationship is from account
        if( ! $userRelationship = UserRelationship::where('account_id', '=', $checkAccount->account_id)->where('id', '=', $request->usr_rltnshp_id)->where('relationship_id', '=', 3)->whereNull('deleted_at')->first() ) {
            return response()->json(array("error" => "Vínculo não pertence a conta"));
        }

        foreach ($request->id as $req) {
            if ($usr_rltnshp_prmssn = UsrRltnshpPrmssn::where('id','=',$req)->where('usr_rltnshp_id','=',$userRelationship->id)->whereNull('deleted_at')->first()) {
                $usr_rltnshp_prmssn->deleted_at = \Carbon\Carbon::now();
                if ($usr_rltnshp_prmssn->save()) {
                    array_push($success,["success" => "Permissão excluída com sucesso", "id" => $req]);
                } else {
                    array_push($error,["error" => "Poxa, não foi possível remover a permissão no momento, por favor tente mais tarde", "id" => $req]);
                }

            } else {
                array_push($error,["error" => "Poxa, não localizamos o relacionamento da permissão com usuario ou ela já foi removida, reveja os dados informados e tente novamente", "id" => $req]);
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
            "success"      => "Permissões removidas com sucesso",
            "error_list"   => $error,
            "success_list" => $success,
        ));
    }


    public function setNewPermission()
    {
        $userRelationships4 = UserRelationship::where('relationship_id', '=', 4)->get();
        foreach($userRelationships4 as $userRelationship){
            UsrRltnshpPrmssn::create([
                'usr_rltnshp_id' => $userRelationship->id,
                'prmssn_grp_id'  => $userRelationship->permission_group_id,
                'permission_id'  => 358,
                'created_at'     => \Carbon\Carbon::now(),
            ]);
        }


        $userRelationships3 = UserRelationship::where('relationship_id', '=', 3)->get();
        foreach($userRelationships3 as $userRelationship){
            UsrRltnshpPrmssn::create([
                'usr_rltnshp_id' => $userRelationship->id,
                'prmssn_grp_id'  => $userRelationship->permission_group_id,
                'permission_id'  => 359,
                'created_at'     => \Carbon\Carbon::now(),
            ]);
        }
    }
}
