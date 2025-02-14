<?php

namespace App\Http\Controllers;

use App\Models\SecurityQuestionAnswers;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityQuestionAnswersController extends Controller
{
    protected function newAnswer(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $checkAccount                  = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (SecurityQuestionAnswers::where('user_id','=',$checkAccount->user_id)->count() == 2) {
            return response()->json(array("error" => "Poxa, o máximo permitido de perguntas cadastradas foi excedido"));
        }

        if (SecurityQuestionAnswers::where('security_question_id','=', $request->security_question_id)->where('user_id','=',$checkAccount->user_id)->first()) {
            return response()->json(array("error" => "Poxa, não é permitido repetir a pergunta, por favor, tente outra"));
        }

        if (SecurityQuestionAnswers::create([
            'uuid'                  => Str::orderedUuid(),
            'user_id'               => $checkAccount->user_id,
            'security_question_id'  => $request->security_question_id,
            'answer'                => $request->answer,
            'created_at'            => \Carbon\Carbon::now(),
            ])){
            return response()->json(array("success" => "Pergunta cadastrada com sucesso"));
        }
        return response()->json(array("error" => "Poxa, não foi possível cadastrar a pergunta informada, por favor, tente mais tarde"));
    }
}
