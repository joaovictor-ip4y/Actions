<?php

namespace App\Http\Controllers;

use App\Models\DocumentCnh;
use App\Models\RegisterDataPf;
use App\Libraries\AmazonS3;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;

class DocumentCnhController extends Controller
{
    protected function get()
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [38, 39];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //


        $documentCnh = new DocumentCnh();
        return response()->json($documentCnh->getDocumentCnh());
    }

    protected function uploadCNHFront(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [38, 39];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $dataPF = RegisterDataPf::where('register_master_id','=', $request->register_master_id)->first();
        $cnh = DocumentCnh::where('rgstr_data_pf_id','=', $dataPF->id)->first();
        $ext = pathinfo($request->file_name, PATHINFO_EXTENSION);
        $fileName = md5('front'.$dataPF->id.$cnh->id.date('Ymd').time()).'.'.$ext;

        $amazons3 = new AmazonS3();
        $amazons3->fileName = $fileName;
        $amazons3->file64   = $request->file64;
        $amazons3->path     = 'dinari_bank/register/pf/cnh/';

        $upfile =  $amazons3->fileUpAmazon();
        if($upfile->success){
            $cnh->cnh_front_s3_file_name = $fileName;
            $cnh->cnh_front_s3_at  = \Carbon\Carbon::now();
            $cnh->save();
            return response()->json(array("success"=>"Frente da CNH enviada com sucesso"));
        }else{
            return response()->json(array("error"=>"Falha ao enviar a frente da CNH, por favor tente novamente mais tarde"));
        }
    }

    protected function uploadCNHVerse(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [38, 39];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $dataPF = RegisterDataPf::where('register_master_id','=', $request->register_master_id)->first();
        $cnh = DocumentCnh::where('rgstr_data_pf_id','=', $dataPF->id)->first();
        $ext = pathinfo($request->file_name, PATHINFO_EXTENSION);
        $fileName = md5('front'.$dataPF->id.$cnh->id.date('Ymd').time()).'.'.$ext;
        $amazons3 = new AmazonS3();
        $amazons3->fileName = $fileName;
        $amazons3->file64   = $request->file64;
        $amazons3->path     = 'dinari_bank/register/pf/cnh/';

        $upfile =  $amazons3->fileUpAmazon();
        if($upfile->success){
            $cnh->cnh_verse_s3_file_name = $fileName;
            $cnh->cnh_verse_s3_at   = \Carbon\Carbon::now();
            $cnh->save();
            return response()->json(array("success"=>"Verso da CNH enviada com sucesso"));
        }else{
            return response()->json(array("error"=>"Falha ao enviar o verso da CNH, por favor tente novamente mais tarde"));
        }
    }
    
    protected function downloadCNHFront(Request $request)
    {
       // ----------------- Check Account Verification ----------------- //
       $accountCheckService                = new AccountRelationshipCheckService();
       $accountCheckService->request       = $request;
       $accountCheckService->permission_id = [38, 39];
       $checkAccount                       = $accountCheckService->checkAccount();
       if(!$checkAccount->success){
           return response()->json(array("error" => $checkAccount->message));
       }
       // -------------- Finish Check Account Verification -------------- //
        
        $dataPF = RegisterDataPf::where('register_master_id','=', $request->register_master_id)->first();
        $cnh = DocumentCnh::where('rgstr_data_pf_id','=', $dataPF->id)->first();
        $amazons3 = new AmazonS3();
        $amazons3->fileName = $cnh->cnh_verse_s3_file_name;
        $amazons3->path     = 'dinari_bank/register/pf/cnh/';
        $downfile =  $amazons3->fileDownAmazon();
        if($downfile->success){
            return response()->json(array("filename"=>$amazons3->fileName,"File"=>$downfile));
        }else{
            return response()->json(array("error"=>"Falha ao enviar o verso da CNH, por favor tente novamente mais tarde"));
        }
    }
}
