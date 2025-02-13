<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageTemplate\MessageTemplateStoreRequest;
use App\Http\Requests\MessageTemplate\MessageTemplateUpdateRequest;
use App\Http\Requests\MessageTemplate\MessageTemplateUpdateStatusRequest;
use App\Services\MessageTemplate\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageTemplateController extends Controller
{
    public function store(MessageTemplateStoreRequest $request): JsonResponse
    {
        try {
            $service = new MessageTemplateService();
            $service->store(
                days: $request->get('days'),
                typeScheduleMessageId: $request->get('typeScheduleMessageId'),
                name: $request->get('name'),
                message: $request->get('message'),
                status: $request->get('status') ?? 0
            );
            return response()->json(["success" => "Sucesso ao cadastrar mensagem modelo para e-mails!"]);
        } catch (\Throwable $th) {
            Log::error("Erro ao cadastrar mensagem modelo para e-mail!", ['message'=> $th->getMessage()]);
            return response()->json(["error" => "Erro ao cadastrar mensagem modelo para e-mail!"]);
        }
    }

    public function update(MessageTemplateUpdateRequest $request): JsonResponse
    {
        try {
            $service = new MessageTemplateService();
            $message = $service->find($request->get('id'));
            $service->update(
                messageTemplate: $message,
                days: $request->get('days'),
                typeScheduleMessageId: $request->get('typeScheduleMessageId'),
                name: $request->get('name'),
                message: $request->get('message')
            );
            return response()->json(["success" => "Sucesso ao atualizar mensagem modelo para e-mail!"]);
        } catch (\Throwable $th) {
            Log::error("Erro ao atualizar mensagem modelo para e-mail!", ['message'=> $th->getMessage()]);
            return response()->json(["error" => "Erro ao atualizar mensagem modelo para e-mail!"]);
        }
    }

    public function updateStatus(MessageTemplateUpdateStatusRequest $request): JsonResponse
    {
        try {
            $service = new MessageTemplateService();
            $message = $service->find($request->get('id'));
            $service->updateStatus(messageTemplate: $message, status: $request->get('status'));
            return response()->json(["success" => "Sucesso ao atualizar status da mensagem modelo para e-mail!"]);
        } catch (\Throwable $th) {
            Log::error("Erro ao atualizar status da mensagem modelo para e-mail!", ['message'=> $th->getMessage()]);
            return response()->json(["error" => "Erro ao atualizar status da mensagem modelo para e-mail!"]);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        try {
            (new MessageTemplateService())->delete($request->get('id'));
            return response()->json(["success" => "Sucesso ao deletar mensagem modelo para e-mail!"]);
        } catch (\Throwable $th) {
            Log::error("Erro ao deletar mensagem modelo para e-mail!", ['message'=> $th->getMessage()]);
            return response()->json(["error" => "Erro ao deletar mensagem modelo para e-mail!"]);
        }
    }

    public function get(): JsonResponse
    {
        try {
            $data = (new MessageTemplateService())->get();
            return response()->json(["success" => "Sucesso ao recuperar mensagens modelo para e-mail!", "data" => $data]);
        } catch (\Throwable $th) {
            Log::error("Erro ao recuperar mensagens modelo para e-mail!", ['message'=> $th->getMessage()]);
            return response()->json(["error" => "Erro ao recuperar mensagens modelo para e-mail!", "data" => null]);
        }
    }
}
