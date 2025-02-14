<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Services\Account\AccountRelationshipCheckService;

class NotificationController extends Controller
{
    protected function get()
    {
        $notification = new Notification();
        return response()->json($notification->getNotification());
    }

    public function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $validator = Validator::make($request->all(), [
            'subject'         => ['required', 'string'],
            'description'     => ['required', 'string'],
            'active'          => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if($request->active) {
            $this->deactivateAllNotifications();
        }
       
        if ( Notification::create([
            'uuid'                  => Str::orderedUuid(),
            'subject'               => $request->subject,
            'description'           => $request->description,
            'active_until_at'       => $request->active_until_at,
            'active'                => $request->active,
            'created_by_user_id'    => Auth::user()->id,
            'activated_at'          => $request->active ? \Carbon\Carbon::now() : null,
            'activated_by_user_id'  => $request->active ? Auth::user()->id : null,
        ])) {
            return response()->json(["success" => "Aviso cadastrado com sucesso"]);
        }
        
        return response()->json(["error" => "Não foi possível realizar o cadastro do aviso"]);
    }

    public function edit(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [3];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
             return response()->json(array("error" => $checkAccount->message));
         }
         // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'              => ['required', 'integer'],
            'uuid'            => ['required', 'string'],
            'subject'         => ['required', 'string'],
            'description'     => ['required', 'string'],
            'active'          => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if (!$notification = Notification::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(["error" => "Ocorreu um erro ao localizar o aviso"]);
        }

        if (!is_null($notification->deleted_at)) {
            return response()->json(["error" => "Não é possível alterar uma notificação já excluída"]);
        }
       

        if($request->active) {
            $this->deactivateAllNotifications();
            $notification->activated_at             = \Carbon\Carbon::now();
            $notification->activated_by_user_id     = Auth::user()->id;
            $notification->deactivated_at           = null;
            $notification->deactivated_by_user_id   = null;
        } else {
            $notification->deactivated_at           = \Carbon\Carbon::now();
            $notification->deactivated_by_user_id   = Auth::user()->id;
        }

        $notification->subject                  = $request->subject;
        $notification->description              = $request->description;
        $notification->active_until_at          = $request->active_until_at;
        $notification->active                   = $request->active;
        

        if ($notification->save()) {
            return response()->json(["success" => "Aviso atualizado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao atualizar o aviso"]);
    }

    public function deactivateAllNotifications() 
    {

        $notifications = Notification::whereNull('deleted_at')->get();
        
        foreach($notifications as $ntf) {
            $ntf->active                   = false;
            $ntf->active_until_at          = null;
            $ntf->activated_at             = null;
            $ntf->activated_by_user_id     = null;
            $ntf->deactivated_at           = \Carbon\Carbon::now();
            $ntf->deactivated_by_user_id   = Auth::user()->id;
            $ntf->save();
        }

    }

    public function deactivate(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [3];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
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

        if (!$notification = Notification::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(["error" => "Ocorreu um erro ao localizar o aviso"]);
        }

        if (!is_null($notification->deleted_at)) {
            return response()->json(["error" => "Não é possível desativar uma notificação já excluída"]);
        }

        $notification->active                 = false;
        $notification->active_until_at        = null;
        $notification->activated_at           = null;
        $notification->activated_by_user_id   = null;
        $notification->deactivated_at         = \Carbon\Carbon::now();
        $notification->deactivated_by_user_id = Auth::user()->id;

        if ($notification->save()) {
            return response()->json(["success" => "Aviso desativado com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao desativar o aviso"]);
    }

    public function delete(Request $request)
    {
         // ----------------- Check Account Verification ----------------- //
         $accountCheckService           = new AccountRelationshipCheckService();
         $accountCheckService->request  = $request;
         $accountCheckService->permission_id = [3];
         $checkAccount                  = $accountCheckService->checkAccount();
         if(!$checkAccount->success){
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

        if (!$notification = Notification::where('id', '=', $request->id)->where('uuid', '=', $request->uuid)->first()) {
            return response()->json(["error" => "Ocorreu um erro ao localizar o aviso"]);
        }

        if (!is_null($notification->deleted_at)) {
            return response()->json(["error" => "A notificação já foi excluída"]);
        }

        $notification->active                 = false;
        $notification->active_until_at        = null;
        $notification->activated_at           = null;
        $notification->activated_by_user_id   = null;
        $notification->deactivated_at         = null;
        $notification->deactivated_by_user_id = null;
        $notification->deleted_at             = \Carbon\Carbon::now();
        $notification->deleted_by_user_id     = Auth::user()->id;

        if ($notification->save()) {
            return response()->json(["success" => "Aviso excluído com sucesso"]);
        }
        return response()->json(["error" => "Ocorreu um erro ao excluir o aviso"]);
    }

    public function checkIfNotificationActive()
    {
        $notifications = Notification::whereNull('deleted_at')->get();


        foreach($notifications as $ntf) {

            if($ntf->active) {

                $is_valid_date_range = $this->checkRangeDateUntilAtIsValid($ntf);

                if($is_valid_date_range->success) {

                    return response()->json(["success" => "Encontrado aviso ativo", "data" => $ntf]);

                }
            }
        }

        return response()->json(["error" => "Não foi encontrado nenhum aviso ativo"]);
    }

    public function checkRangeDateUntilAtIsValid($ntf) 
    {
        // Sua variável contendo a data
        $dataVariavel = $ntf->active_until_at; // Substitua isso pela sua variável
    
        // Converta a string para um objeto Carbon
        $dataObjeto = \Carbon\Carbon::parse($dataVariavel);
    
        // Verifique se a data é futura ou igual ao dia atual
        if ($dataObjeto->isFuture() || $dataObjeto->isToday()) {
            return (object) [
                "success"  => true,
                "message"  => "A data é futura ou igual ao dia atual",
            ];
        } 

        return (object) [
            "success"  => false,
            "message"  => "A data é posterior ao dia atual",
        ];
        
    }

}

