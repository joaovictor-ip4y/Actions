<?php

namespace App\Http\Controllers;

use App\Models\PixPaymentUndone;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;
use App\Classes\User\UserClass;
use App\Classes\Token\TokenClass;
use Illuminate\Support\Str;
use App\Models\AccountMovement;
use App\Classes\Account\AccountMovementClass;
use App\Classes\Banking\PixClass;
use Illuminate\Support\Facades\Validator;

class PixPaymentUndoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PixPaymentUndone  $pixPaymentUndone
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [404];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //
        
        $pixPaymentUndone = new PixPaymentUndone();
        $pixPaymentUndone->id = $request->id;
        $pixPaymentUndone->uuid = $request->uuid;
        $pixPaymentUndone->status_id = $request->status_id;
        $pixPaymentUndone->end_to_end = $request->end_to_end;
        $pixPaymentUndone->transaction_id = $request->transaction_id;
        $pixPaymentUndone->account_id = $checkAccount->account_id;
        $pixPaymentUndone->master_id = $checkAccount->master_id;
        
        $pixPaymentUndone->created_at_start = $request->created_at_start;
        $pixPaymentUndone->created_at_end = $request->created_at_end;
        $pixPaymentUndone->payment_date_start = $request->payment_date_start;
        $pixPaymentUndone->payment_date_end = $request->payment_date_end;
        return response()->json($pixPaymentUndone->get());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PixPaymentUndone  $pixPaymentUndone
     * @return \Illuminate\Http\Response
     */
    public function edit(PixPaymentUndone $pixPaymentUndone)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PixPaymentUndone  $pixPaymentUndone
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PixPaymentUndone $pixPaymentUndone)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PixPaymentUndone  $pixPaymentUndone
     * @return \Illuminate\Http\Response
     */
    public function destroy(PixPaymentUndone $pixPaymentUndone)
    {
        //
    }

    public function setUndone(Request $request)
    {

        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [407];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $pixUndone = PixPaymentUndone::whereIn('id', $request->id)->whereNotIn('status_id', [36, 58, 59]);

        if($pixUndone->count() > 0 ) {
            $pixUndone->update([
                'status_id' => 59,
                'discard_at' => \Carbon\Carbon::now(),
                'discard_by_user_id' => $checkAccount->user_id
            ]);
            return response()->json(array("success" => "PIX desfeito(s) descartado(s) com sucesso."));
        }

        return response()->json(array("error" => "PIX desfeito(s) selecionado(s) sem status disponível para descartar."));
    }

    public function reversePixUndone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [406];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        //validate user password
        $user = new UserClass();
        $user->password = $request->password;

        $validateUserPassword = $user->validateUserPassword();
        if(!$validateUserPassword->success){
            return response()->json(array("error" => $validateUserPassword->message_pt_br));
        }

        // validate user to send token
        $validateUserToSendToken = $user->getAuthenticatedUser();
        if(!$validateUserToSendToken->success){
            return response()->json(array("error" => $validateUserToSendToken->message_pt_br));
        }

        //get pix undone to reverse
        $pixUndone = PixPaymentUndone::whereIn('id', $request->id)->whereNotIn('status_id', [36, 58]);

        if($pixUndone->count() == 0 ) {
            return response()->json(array("error" => "PIX desfeito(s) selecionado(s) sem status disponível para estornar."));
        }

        // set approval token
        $batchId = (string) Str::orderedUuid();
        $token = new TokenClass();
        $token->data = (object) [
            'type_id' => 16,
            'origin_id' => $batchId,
            'minutes_to_expiration' => 15
        ];
        $tokenData = $token->createToken();

        // set approval token on pix undones
        foreach($pixUndone->get() as $pixToReverse) {
            $updatePixUndone = PixPaymentUndone::where('id', '=', $pixToReverse->id)->first();
            $updatePixUndone->approval_token = $tokenData->data->token_phone;
            $updatePixUndone->approval_token_expiration = $tokenData->data->token_expiration;
            $updatePixUndone->batch_id = $batchId;
            $updatePixUndone->save();
        }

        // send token
        

        $message = "Token ".substr($tokenData->data->token_phone, 0, 4)."-".substr($tokenData->data->token_phone, 4, 4).". Gerado para ESTORNAR ".$pixUndone->count()." PIX desfeito(s) no valor de R$ ".number_format($pixUndone->sum('value'), 2, ',', '.');

        $token->data = (object) [
            'master_id' => $checkAccount->master_id,
            'phone' => $validateUserToSendToken->data->phone,
            'message' => $message,
            'token' => $tokenData->data->token_phone,
            'type_id' => 16,
            'origin_id' => $batchId,
            'minutes_to_expiration' => 15,
        ];

        $sendToken = $token->sendTokenBySms();

        if( ! $sendToken->success ){
            return response()->json(array("error" =>  $sendToken->message_pt_br));
        }

        return response()->json(array("success" => "Token enviado com sucesso", "data" => ["id" => $request->id, "batch_id" => $batchId]));
    }

    public function approveReversePixUndone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [406];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'array'],
            'batch_id' => ['required', 'string'],
            'token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }

        //get pix undone to reverse
        $pixUndone = PixPaymentUndone::whereIn('id', $request->id)->whereNotIn('status_id', [36, 58])->where('batch_id', '=', $request->batch_id);

        if($pixUndone->count() == 0 ) {
            return response()->json(array("error" => "PIX desfeito(s) selecionado(s) sem status disponível para estornar."));
        }

        // validate token
        $pixToReverse = $pixUndone->first();

        if($pixToReverse->approval_token != $request->token) {
            return response()->json(array("error" => "Token inválido"));
        }


        // get day and hour to validate token expiration
        $now = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s');
        if( $now > (\Carbon\Carbon::parse($pixToReverse->approval_token_expiration)->format('Y-m-d H:i:s')) ) {
            return response()->json(array("error" => "Token expirado, por favor gere um novo token e tente novamente"));
        }
       

        foreach($pixUndone->get() as $pixToReverse) {

            if( $accountMovement = AccountMovement::where('origin_id', '=', $pixToReverse->original_id)->where('mvmnt_type_id', '=', 40)->where('value', '<', 0)->first() ) {
                $accountMovement->reversed = 1;
                $accountMovement->reversed_at = \Carbon\Carbon::now();
                $accountMovement->save();
            }

            $accountMovement = new AccountMovementClass;
            $accountMovement->payload = (object) [
                'account_id' => $pixToReverse->account_id,
                'master_id' => $pixToReverse->master_id,
                'origin_id' => $pixToReverse->original_id,
                'mvmnt_type_id' => 40,
                'value' => $pixToReverse->value,
                'description' => mb_substr('Estorno | PIX | '.$pixToReverse->favored_name.' | '.$pixToReverse->description.' | Transação '.$pixToReverse->original_end_to_end, 0, 255)
            ];

            $createReverseMovement = $accountMovement->createMovement();

            if($createReverseMovement->success) {
                $updatePixUndone = PixPaymentUndone::where('id', '=', $pixToReverse->id)->first();
                $updatePixUndone->reversed_by_user_id = $checkAccount->user_id;
                $updatePixUndone->reversed_at = \Carbon\Carbon::now();
                $updatePixUndone->status_id = 36;
                $updatePixUndone->save();
            }

        }

        return response()->json(array('success' => 'PIX desfeito(s) estornado(s) com sucesso'));

    }

    public function redoPixUndone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [405];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        //validate user password
        $user = new UserClass();
        $user->password = $request->password;

        $validateUserPassword = $user->validateUserPassword();
        if(!$validateUserPassword->success){
            return response()->json(array("error" => $validateUserPassword->message_pt_br));
        }

        //validate user to send token
        $validateUserToSendToken = $user->getAuthenticatedUser();
        if(!$validateUserToSendToken->success){
            return response()->json(array("error" => $validateUserToSendToken->message_pt_br));
        }

        //get pix undone to reverse
        $pixUndone = PixPaymentUndone::whereIn('id', $request->id)->whereNotIn('status_id', [36, 58]);

        if($pixUndone->count() == 0 ) {
            return response()->json(array("error" => "PIX desfeito(s) selecionado(s) sem status disponível para refazer."));
        }

        // set approval token
        $batchId = (string) Str::orderedUuid();
        $token = new TokenClass();
        $token->data = (object) [
            'type_id' => 17,
            'origin_id' => $batchId,
            'minutes_to_expiration' => 15
        ];
        $tokenData = $token->createToken();

        // set approval token on pix undones
        foreach($pixUndone->get() as $pixToReverse) {
            $updatePixUndone = PixPaymentUndone::where('id', '=', $pixToReverse->id)->first();
            $updatePixUndone->approval_token = $tokenData->data->token_phone;
            $updatePixUndone->approval_token_expiration = $tokenData->data->token_expiration;
            $updatePixUndone->batch_id = $batchId;
            $updatePixUndone->save();
        }

        // send token
        $message = "Token ".substr($tokenData->data->token_phone, 0, 4)."-".substr($tokenData->data->token_phone, 4, 4).". Gerado para REFAZER ".$pixUndone->count()." PIX desfeito(s) no valor de R$ ".number_format($pixUndone->sum('value'), 2, ',', '.');

        $token->data = (object) [
            'master_id' => $checkAccount->master_id,
            'phone' => $validateUserToSendToken->data->phone,
            'message' => $message,
            'token' => $tokenData->data->token_phone,
            'type_id' => 17,
            'origin_id' => $batchId,
            'minutes_to_expiration' => 15,
        ];

        $sendToken = $token->sendTokenBySms();

        if( ! $sendToken->success ){
            return response()->json(array("error" =>  $sendToken->message_pt_br));
        }

        return response()->json(array("success" => "Token enviado com sucesso", "data" => ["id" => $request->id, "batch_id" => $batchId]));
    }

    public function approveRedoPixUndone(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [405];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'array'],
            'batch_id' => ['required', 'string'],
            'token' => ['required', 'string', 'size:8']
        ]);

        if ($validator->fails()) {
            return response()->json(array("error" => $validator->errors()->first()));
        }


        //get pix undone to reverse
        $pixUndone = PixPaymentUndone::whereIn('id', $request->id)->whereNotIn('status_id', [36, 58])->where('batch_id', '=', $request->batch_id);

        if($pixUndone->count() == 0 ) {
            return response()->json(array("error" => "PIX desfeito(s) selecionado(s) sem status disponível para refazer."));
        }

        // validate token
        $pixToReverse = $pixUndone->first();

        if($pixToReverse->approval_token != $request->token) {
            return response()->json(array("error" => "Token inválido"));
        }


        // get day and hour to validate token expiration
        $now = (\Carbon\Carbon::parse( \Carbon\Carbon::now() ))->format('Y-m-d H:i:s');
        if( $now > (\Carbon\Carbon::parse($pixToReverse->approval_token_expiration)->format('Y-m-d H:i:s')) ) {
            return response()->json(array("error" => "Token expirado, por favor gere um novo token e tente novamente"));
        }
       
        $pixClass = new PixClass;
        $errorList = [];
        foreach($pixUndone->get() as $pixToRedo) {
            $pixClass->payload = (object) [
                'id' => $pixToRedo->id,
                'batch_id' => $pixToRedo->batch_id,
                'user_id' => $checkAccount->user_id
            ];


            $redoPix = $pixClass->redoPixUndone();
            if( ! $redoPix->success ) {
                array_push($errorList, $redoPix->message_pt_br);
            }
        }

        if( sizeof($errorList) > 0) {
            return response()->json(array("error" => "Não foi possível refazer todo(s) o(s) PIX desfeito(s): ".implode(', ', $errorList)));
        }

        return response()->json(array("success" => "PIX desfeito(s) refeito(s) com sucesso"));


    }
}
