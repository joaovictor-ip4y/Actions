<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\PermissionGroupItem;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class PermissionGroupItemController extends Controller
{
    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [144];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $permissionGroupItem = new PermissionGroupItem();
        $permissionGroupItem->permission_id = $request->permission_id;
        $permissionGroupItem->prmsn_grp_id  = $request->prmsn_grp_id ;
        $permissionGroupItem->onlyActive    = $request->onlyActive ;
        $permissionGroupItem->master_id     = $checkAccount->master_id;

        return response()->json($permissionGroupItem->getPermissionGroupItem());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [143];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error   = [];
        $success = [];

        foreach ($request->id as $req) {

            if ( (!$prmsn_grp = PermissionGroup::where('id','=',$request->prmssn_grp_id)->where('master_id','=',$checkAccount->master_id)->first()) ) {
                return response()->json(array("error" => "Ocorreu uma falha ao conceder a permissão, por favor tente novamente mais tarde"));
            }

            if (PermissionGroupItem::where('permission_id','=', $req)->where('prmsn_grp_id','=', $prmsn_grp->id)->whereNull('deleted_at')->first()) {
                array_push($error,["error" => "Permissão já concedida para o grupo", "id" => $req]);
            } else {

                if (!$permission = Permission::where('id','=', $req)->whereNull('deleted_at')->first()) {
                    array_push($error,["error" => "Ocorreu uma falha ao conceder a permissão, por favor tente novamente mais tarde","id" => $req]);
                    continue;
                } else {

                    if (PermissionGroupItem::create([
                        'permission_id' => $permission->id,
                        'prmsn_grp_id'  => $prmsn_grp->id,
                    ])) {
                        array_push($success,["success" => "Permissão concedida com sucesso", "id" => $permission->id]);
                    } else {
                        array_push($error,["error" => "Poxa, não foi possível conceder a permissão para o grupo no momento, por favor tente novamente mais tarde", "id"=>$permission->id]);
                    }
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

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [146];
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $error = [];
        $success = [];

        foreach ($request->id as $item_id) {
            if(!$permission_group_item = PermissionGroupItem::where('id','=',$item_id)->where('prmsn_grp_id','=',$request->prmsn_grp_id)->whereNull('deleted_at')->first()) {
                array_push($error, ["error" => "Poxa, não localizamos a permissão com grupo ou ela já foi removida, reveja os dados informados e tente novamente", "id" => $item_id]);
                continue;
            }
            if (PermissionGroup::where('id','=',$permission_group_item->prmsn_grp_id)->where('master_id','=',$checkAccount->master_id)->first()){
                $permission_group_item->deleted_at = \Carbon\Carbon::now();
                if ($permission_group_item->save()) {
                    array_push($success, ["success" => "Permissão removida com sucesso", "id" => $item_id]);
                    continue;
                } else {
                    array_push($error, ["error" => "Poxa, não foi possível remover a permissão no momento, por favor tente novamente mais tarde", "id" => $item_id]);
                    continue;
                }
            } else {
                array_push($error, ["error" => "Poxa, a permissão não pertence ao grupo, por favor reveja os dados informados e tente novamente", "id" => $item_id]);
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
