<?php

namespace App\Http\Controllers;

use App\Libraries\SimpleOFX;
use App\Models\StatementBrasilBank;
use App\Models\Master;
use App\Services\Account\MovementService;
use App\Services\Failures\sendFailureAlert;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;


class StatementBrasilBankController extends Controller
{

    protected function get(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [85];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $statementBrasilBank = new StatementBrasilBank();
        $statementBrasilBank->id          = $request->id;
        $statementBrasilBank->master_id   = $request->master_id;
        $statementBrasilBank->bank        = $request->bank;
        $statementBrasilBank->account     = $request->account;
        $statementBrasilBank->type        = $request->type;
        $statementBrasilBank->date        = $request->date;
        $statementBrasilBank->description = $request->description;
        $statementBrasilBank->fit_id      = $request->fit_id;
        $statementBrasilBank->check_num   = $request->check_num;
        $statementBrasilBank->balance     = $request->balance;
        $statementBrasilBank->onlyActive  = $request->onlyActive;
        $statementBrasilBank->date_start  = $request->date_start ? $request->date_start." 00:00:00.000" : null;
        $statementBrasilBank->date_end    = $request->date_end ? $request->date_end." 23:59:59.998" : null;
        $statementBrasilBank->value_start = $request->value_start;
        $statementBrasilBank->value_end   = $request->value_end;
        return response()->json($statementBrasilBank->get());
    }

    protected function new(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [85];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        if (pathinfo($request->file_name, PATHINFO_EXTENSION) != 'ofx' and pathinfo($request->file_name, PATHINFO_EXTENSION) != 'OFX') {
            return response()->json(array("error" => "Para essa operação, é permitido apenas importação de arquivos no formato OFX"));
        }

        $fileName = Str::orderedUuid().'_'.$request->file_name;

        if (!Storage::disk('ofx_upload')->put($fileName, base64_decode( (explode(',',$request->file64))[1] ))) {
            return response()->json(array("error" => "Não foi possível realizar o upload do documento, por favor tente novamente mais tarde"));
        }

        $simpleOfx = new SimpleOFX();
        $simpleOfx->pathFile = Storage::disk('ofx_upload')->path($fileName);
        $importOfxFile = $simpleOfx->readOFX();

        if (!$importOfxFile->success) {
            Storage::disk('ofx_upload')->delete($fileName);
            return response()->json(array("error" => $importOfxFile->error));
        }

        if ($importOfxFile->data->bank_number != '001') {
            Storage::disk('ofx_upload')->delete($fileName);
            return response()->json(array("error" => "É permitida a importação apenas de OFX do Banco do Brasil"));
        }

        $errorList = [];

        $statementBrasilBank            = new StatementBrasilBank();
        $statementBrasilBank->master_id = $checkAccount->master_id;
        $statementBrasilBank->bank      = $importOfxFile->data->bank_number;
        $statementBrasilBank->account   = str_replace('-', '', $importOfxFile->data->account_number);
        
        foreach ($importOfxFile->data->movimentation as $field) {
            if($field->date != null){
                if (!$statementBrasilBank
                    ->where('bank'       , '=', $statementBrasilBank->bank )
                    ->where('account'    , '=', $statementBrasilBank->account)
                    ->where('type'       , '=', $field->type)
                    ->where('date'       , '=', $field->date.' 00:00:00.000')
                    ->where('value'      , '=', $field->value)
                    ->where('description', '=', $field->description)
                    ->where('fit_id'     , '=', $field->id)
                // ->where('check_num'  , '=', $field->checknum)
                    ->first()
                ) {
                    if (!StatementBrasilBank::create([
                        'uuid'        => Str::orderedUuid(),
                        'master_id'   => $statementBrasilBank->master_id,
                        'bank'        => $statementBrasilBank->bank,
                        'account'     => $statementBrasilBank->account,
                        'type'        => $field->type,
                        'date'        => $field->date,
                        'value'       => $field->value,
                        'description' => $field->description,
                        'fit_id'      => $field->id,
                        'check_num'   => $field->checknum,
                        'balance'     => ($statementBrasilBank->getBalance())->balance + $field->value,
                        'created_at'  => \Carbon\Carbon::now(),
                    ])) {
                        array_push($errorList, [
                            "error"       => "Não foi possível realizar a imortação deste movimento",
                            "check_num"   => $field->checknum,
                            "description" => $field->description,
                            "account"     => $importOfxFile->data->account_number,
                            "bank"        => $importOfxFile->data->bank_number,
                        ]);
                    } else {
                        if(
                            $field->checknum  == '73017019' 
                            or $field->checknum == '05355820' 
                            or $field->checknum == '00978391' 
                            or $field->checknum == '00188506' 
                            or $field->checknum == '00059919'
                            or $field->description == 'TARIFA PACOTE DE SERVICOS'
                            or $field->description == 'TAR DOC'
                            or $field->description == 'TARIFA PIX ENVIADO'
                        ){

                            $master = Master::where('id','=',$checkAccount->master_id)->first();

                            $movementService = new MovementService();
                            $movementService->movementData = (object)[
                                'account_id'    => $master->margin_accnt_id,
                                'master_id'     => $checkAccount->master_id,
                                'origin_id'     => null,
                                'mvmnt_type_id' => 45,
                                'value'         => round($field->value,2),
                                'description'   => 'Banco do Brasil | '.$field->description,
                            ];

                            if(!$movementService->create()){
                                $sendFailureAlert               = new sendFailureAlert();
                                $sendFailureAlert->title        = 'Falha lançamento Tarifa Banco do Brasil';
                                $sendFailureAlert->errorMessage = 'Ocorreu uma falha ao lançar '.$field->description.' no valor de '.round($field->value,2).' no extrato do Banco do Brasil';
                                $sendFailureAlert->sendFailures();                                                
                            }
                        }
                    }
                } else {
                    array_push($errorList, [
                        "error"     => "Movimento já foi importado anteriormente",
                        'bank'      => $importOfxFile->data->bank_number,
                        'account'   => $importOfxFile->data->account_number,
                        'type'      => $field->type,
                        'check_num' => $field->checknum,
                        'date'      => $field->date,
                        'value'     => $field->value,
                    ]);
                }
            }
        }

        Storage::disk('ofx_upload')->delete($fileName);

        return response()->json(array("success" => "Extrato do Banco do Brasil importado com sucesso", "error_list" => $errorList, "movimentation" => $importOfxFile->data));
    }

    protected function getBalance(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [1];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $statementBrasilBank             = new StatementBrasilBank();
        $statementBrasilBank->master_id  = $checkAccount->master_id;
        $statementBrasilBank->bank       = $request->bank;
        $statementBrasilBank->account    = $request->account;
        $statementBrasilBank->onlyActive = $request->onlyActive;
        return response()->json($statementBrasilBank->getBalance());
    }

    public function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [85];
        $checkAccount                       = $accountCheckService->checkAccount();
        if (!$checkAccount->success) {
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                   => ['required', 'integer'],
            'uuid'                 => ['required', 'string']
        ],[
            'id.required'          => 'É obrigatório informar o id',
            'uuid.required'        => 'É obrigatório informar o uuid'
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        if ( !$statementBrasilBank = StatementBrasilBank::where('id', '=', $request->id)
        ->where('uuid', '=', $request->uuid)
        ->first()) {
            return response()->json(["error" => "Registro não localizado, por favor verifique e tente novamente."]);
        }

        $statementBrasilBank->deleted_at = \Carbon\Carbon::now();

        if ($statementBrasilBank->save()) {
            return response()->json(["success" => "Registro excluído com sucesso."]);
        }
        return response()->json(["error" => "Ocorreu um erro ao excluir o registro."]);
    }
}
