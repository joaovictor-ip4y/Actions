<?php

namespace App\Http\Controllers;

use App\Models\MovementAnalysisDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Account\AccountRelationshipCheckService;

class MovementAnalysisDailyController extends Controller
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
     * @param  \App\Models\MovementAnalysisDaily  $movementAnalysisDaily
     * @return \Illuminate\Http\Response
     */
    public function show(MovementAnalysisDaily $movementAnalysisDaily, Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $movementAnalysisDaily->total_value_red_flag = $request->total_value_red_flag;
        $movementAnalysisDaily->in_value_red_flag = $request->in_value_red_flag;
        $movementAnalysisDaily->out_value_red_flag = $request->out_value_red_flag;        
        $movementAnalysisDaily->total_qtt_red_flag = $request->total_qtt_red_flag;
        $movementAnalysisDaily->in_qtt_red_flag = $request->in_qtt_red_flag;
        $movementAnalysisDaily->out_qtt_red_flag = $request->out_qtt_red_flag;
        $movementAnalysisDaily->only_not_analyzed  = $request->only_not_analyzed == 1 ? 1 : null;
        $movementAnalysisDaily->only_analyzed = $request->only_analyzed == 1 ? 1 : null;
        $movementAnalysisDaily->date_start = $request->date_start;
        $movementAnalysisDaily->date_end = $request->date_end;
        $movementAnalysisDaily->account_id = $request->account_id;

        return response()->json($movementAnalysisDaily->get());
    }

    public function justificationDailyValueTotal(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $movementAnalysisDaily = MovementAnalysisDaily::where('uuid', '=', $request->uuid)->where('account_id', '=', $request->account_id)->first();
        $movementAnalysisDaily->total_value_red_flag_justification = $request->justification;

        if($request->approved == 1){
            $movementAnalysisDaily->total_value_red_flag_justified_by_user_id = (Auth::user())->id;
        } else {
            $movementAnalysisDaily->total_value_red_flag_discarded_by_user_id = (Auth::user())->id;
        }

        $movementAnalysisDaily->total_value_red_flag_analyzed_at = \Carbon\Carbon::now();

        $movementAnalysisDaily->save();

        return response()->json(array("success" => "Justificativa finalizada com sucesso"));
    }

    public function justificationDailyValueIn(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $movementAnalysisDaily = MovementAnalysisDaily::where('uuid', '=', $request->uuid)->where('account_id', '=', $request->account_id)->first();
        $movementAnalysisDaily->in_value_red_flag_justification = $request->justification;

        if($request->approved == 1){
            $movementAnalysisDaily->in_value_red_flag_justified_by_user_id = (Auth::user())->id;
        } else {
            $movementAnalysisDaily->in_value_red_flag_discarded_by_user_id = (Auth::user())->id;
        }

        $movementAnalysisDaily->in_value_red_flag_analyzed_at = \Carbon\Carbon::now();

        $movementAnalysisDaily->save();

        return response()->json(array("success" => "Justificativa finalizada com sucesso"));
    }

    public function justificationDailyValueOut(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $movementAnalysisDaily = MovementAnalysisDaily::where('uuid', '=', $request->uuid)->where('account_id', '=', $request->account_id)->first();
        $movementAnalysisDaily->out_value_red_flag_justification = $request->justification;

        if($request->approved == 1){
            $movementAnalysisDaily->out_value_red_flag_justified_by_user_id = (Auth::user())->id;
        } else {
            $movementAnalysisDaily->out_value_red_flag_discarded_by_user_id = (Auth::user())->id;
        }

        $movementAnalysisDaily->out_value_red_flag_analyzed_at = \Carbon\Carbon::now();

        $movementAnalysisDaily->save();

        return response()->json(array("success" => "Justificativa finalizada com sucesso"));
    }

    public function justificationDailyQttTotal(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $movementAnalysisDaily = MovementAnalysisDaily::where('uuid', '=', $request->uuid)->where('account_id', '=', $request->account_id)->first();
        $movementAnalysisDaily->total_value_qtt_flag_justification = $request->justification;

        if($request->approved == 1){
            $movementAnalysisDaily->total_qtt_red_flag_justified_by_user_id = (Auth::user())->id;
        } else {
            $movementAnalysisDaily->total_qtt_red_flag_discarded_by_user_id = (Auth::user())->id;
        }

        $movementAnalysisDaily->total_qtt_red_flag_analyzed_at = \Carbon\Carbon::now();

        $movementAnalysisDaily->save();

        return response()->json(array("success" => "Justificativa finalizada com sucesso"));
    }

    public function justificationDailyQttIn(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $movementAnalysisDaily = MovementAnalysisDaily::where('uuid', '=', $request->uuid)->where('account_id', '=', $request->account_id)->first();
        $movementAnalysisDaily->in_qtt_red_flag_justification = $request->justification;

        if($request->approved == 1){
            $movementAnalysisDaily->in_qtt_red_flag_justified_by_user_id = (Auth::user())->id;
        } else {
            $movementAnalysisDaily->in_qtt_red_flag_discarded_by_user_id = (Auth::user())->id;
        }

        $movementAnalysisDaily->in_qtt_red_flag_analyzed_at = \Carbon\Carbon::now();

        $movementAnalysisDaily->save();

        return response()->json(array("success" => "Justificativa finalizada com sucesso"));
    }

    public function justificationDailyQttOut(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService           = new AccountRelationshipCheckService();
        $accountCheckService->request  = $request;
        $accountCheckService->permission_id = [2];
        $checkAccount                  = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        
        $movementAnalysisDaily = MovementAnalysisDaily::where('uuid', '=', $request->uuid)->where('account_id', '=', $request->account_id)->first();
        $movementAnalysisDaily->out_qtt_red_flag_justification = $request->justification;

        if($request->approved == 1){
            $movementAnalysisDaily->out_qtt_red_flag_justified_by_user_id = (Auth::user())->id;
        } else {
            $movementAnalysisDaily->out_qtt_red_flag_discarded_by_user_id = (Auth::user())->id;
        }

        $movementAnalysisDaily->out_qtt_red_flag_analyzed_at = \Carbon\Carbon::now();

        $movementAnalysisDaily->save();

        return response()->json(array("success" => "Justificativa finalizada com sucesso"));
    }
    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\MovementAnalysisDaily  $movementAnalysisDaily
     * @return \Illuminate\Http\Response
     */
    public function edit(MovementAnalysisDaily $movementAnalysisDaily)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MovementAnalysisDaily  $movementAnalysisDaily
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MovementAnalysisDaily $movementAnalysisDaily)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MovementAnalysisDaily  $movementAnalysisDaily
     * @return \Illuminate\Http\Response
     */
    public function destroy(MovementAnalysisDaily $movementAnalysisDaily)
    {
        //
    }
}
