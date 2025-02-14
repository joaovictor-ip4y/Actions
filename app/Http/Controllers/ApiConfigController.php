<?php

namespace App\Http\Controllers;

use App\Models\ApiConfig;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ApiConfigController extends Controller
{
    protected function get()
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig = new ApiConfig();
        return response()->json($apiConfig->getApiConfig());
    }

    protected function update(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $apiConfig = ApiConfig::where('id','=',$request->id)->where('api_id','=',$request->api_id)->where('master_id','=',$checkAccount->master_id)->first();
        $apiConfig->api_key                 = Crypt::encryptString($request->api_key);
        $apiConfig->api_client_id           = Crypt::encryptString($request->api_client_id);
        $apiConfig->api_authentication      = Crypt::encryptString($request->api_authentication);
        $apiConfig->api_agency              = Crypt::encryptString($request->api_agency);
        $apiConfig->api_account             = Crypt::encryptString($request->api_account);
        $apiConfig->api_address             = Crypt::encryptString($request->api_address);
        $apiConfig->save();
        return response()->json(["success"=>"Atualizado com sucesso"]);
    }

    protected function updateRendimentoBankApiKey(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',1)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_key = Crypt::encryptString($request->api_key);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateRendimentoBankClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',1)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateRendimentoBankAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',1)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->api_authentication);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateRendimentoBankAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',1)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_agency = Crypt::encryptString($request->api_agency);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateRendimentoBankAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',1)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_account = Crypt::encryptString($request->api_account);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateRendimentoBankAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',1)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',8)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinPixAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',9)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_pix_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',8)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinClientSecret(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',8)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->client_secret);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinKey(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',8)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_key = Crypt::encryptString($request->api_key);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',8)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_agency = Crypt::encryptString($request->api_agency);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',8)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_account = Crypt::encryptString($request->api_account);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateCelcoinParticipant(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',9)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_agency = Crypt::encryptString($request->api_participant);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateZenviaAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',3)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->api_authentication);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateZenviaAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',3)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateLemitKey(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',4)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_key = Crypt::encryptString($request->api_key);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateLemitAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',4)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateJustaClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',5)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateJustaAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',5)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->api_authentication);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateJustaAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',5)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateAquiCardClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',6)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateAquiCardAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',6)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->api_authentication);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateAquiCardAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',6)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_agency = Crypt::encryptString($request->api_agency);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateAquiCardAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',6)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_account = Crypt::encryptString($request->api_account);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateAquiCardAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',6)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateFlashCourierClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',7)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateFlashCourierAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',7)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->api_authentication);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateFlashCourierAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',7)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateBrasilBankApiKey(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$apiConfig = ApiConfig::where('api_id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(["error" => "Api não localizada"]);
        }

        $apiConfig->api_key = Crypt::encryptString($request->api_key);

        if ($apiConfig->save()) {
            return response()->json(["success" => "Api atualizada com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível atualizar a Api"]);
    }

    protected function updateBrasilBankClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$apiConfig = ApiConfig::where('api_id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(["error" => "Api não localizada"]);
        }

        $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);

        if ($apiConfig->save()) {
            return response()->json(["success" => "Api atualizada com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível atualizar a Api"]);
    }

    protected function updateBrasilBankAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$apiConfig = ApiConfig::where('api_id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(["error" => "Api não localizada"]);
        }

        $apiConfig->api_authentication = Crypt::encryptString($request->api_authentication);

        if ($apiConfig->save()) {
            return response()->json(["success" => "Api atualizada com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível atualizar a Api"]);
    }

    protected function updateBrasilBankAgency(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$apiConfig = ApiConfig::where('api_id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(["error" => "Api não localizada"]);
        }

        $apiConfig->api_agency = Crypt::encryptString($request->api_agency);

        if ($apiConfig->save()) {
            return response()->json(["success" => "Api atualizada com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível atualizar a Api"]);
    }

    protected function updateBrasilBankAccount(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$apiConfig = ApiConfig::where('api_id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(["error" => "Api não localizada"]);
        }

        $apiConfig->api_account = Crypt::encryptString($request->api_account);

        if ($apiConfig->save()) {
            return response()->json(["success" => "Api atualizada com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível atualizar a Api"]);
    }

    protected function updateBrasilBankAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (!$apiConfig = ApiConfig::where('api_id', '=', 13)->whereNull('deleted_at')->first()) {
            return response()->json(["error" => "Api não localizada"]);
        }

        $apiConfig->api_address = Crypt::encryptString($request->api_address);

        if ($apiConfig->save()) {
            return response()->json(["success" => "Api atualizada com sucesso"]);
        }
        return response()->json(["error" => "Não foi possível atualizar a Api"]);
    }



    protected function updateEdenredAddress(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',17)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_address = Crypt::encryptString($request->api_address);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateEdenredClientId(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',17)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_client_id = Crypt::encryptString($request->api_client_id);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateEdenredAuthentication(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',17)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_authentication = Crypt::encryptString($request->client_secret);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }

    protected function updateEdenredKey(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',17)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_key = Crypt::encryptString($request->api_key);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }
    
    protected function updateEdenredPassword(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [168];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if($apiConfig = ApiConfig::where('api_id','=',17)->whereNull('deleted_at')->where('master_id','=',$checkAccount->master_id)->first()){
            $apiConfig->api_password = Crypt::encryptString($request->api_key);
            if($apiConfig->save()){
                return response()->json(["success"=>"Atualizado com sucesso"]);
            }else{
                return response()->json(["error"=>"Erro ao atualizar"]);
            }
        }else{
            return response()->json(["error"=>"Api não encontrada"]);
        }
    }
}
