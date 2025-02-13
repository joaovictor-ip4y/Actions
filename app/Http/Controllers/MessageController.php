<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
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

        $message = new Message();
        $message->id         = $request->id;
        $message->master_id  = $checkAccount->master_id;
        $message->show_from  = $request->show_from;
        $message->show_until = $request->show_until;
        $message->onlyActive = $request->onlyActive;

        return response()->json($message->getMessage());
    }

    protected function create(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if(Message::where('message_type_id','=',$request->message_type_id)->where('master_id','=',$checkAccount->master_id)->whereNull('deleted_at')->first()){
            return response()->json(array("error" => "Mensagem já inserida"));
        }else{
            if(Message::create([
                'master_id'       => $checkAccount->master_id,
                'message_type_id' => $request->message_type_id,
                'message'         => $request->message,
                'show_from'       => $request->show_from,
                'show_until'      => $request->show_until
            ])){
                return response()->json(array("success" => "Mensagem inserida com sucesso"));
            }else{
                return response()->json(array("error" => "Erro ao criar a Mensagem"));

            }
        }
    }

    protected function delete(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($message = Message::where('id','=',$request->id)->first()){
            $message->deleted_at = \Carbon\Carbon::now();
            if($message->save()){
                return response()->json(array("success" => "Mensagem excluída com sucesso"));
            }else{
                return response()->json(array("error" => "Erro ao excluir a mensagem"));
            }
        }else{
            return response()->json(array("error" => "Mensagem não encontrada"));
        }
    }
}
