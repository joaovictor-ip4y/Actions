<?php

namespace App\Http\Controllers;

use App\Models\EscrowBeneficiaries;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Libraries\Facilites;

class EscrowBeneficiariesController extends Controller
{    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'account_id'            => ['required', 'integer'],
            'cpf_cnpj'              => ['required', 'string'],
            'name'                  => ['required', 'string'],
        ],[
            'account_id.required'   => 'É obrigatório informar o account_id',
            'cpf_cnpj.required'     => 'É obrigatório informar o CPF/CNPJ',
            'name.required'         => 'É obrigatório informar o nome/razão social',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;

        if (strlen($cpf_cnpj) == 11) {
            if (!$facilites->validateCPF($cpf_cnpj)) {
                return response()->json(array("error" => "CPF inválido."));
            }
        } else if (strlen($cpf_cnpj) == 14) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido."));
        }

        if( EscrowBeneficiaries::create([
            'uuid'       => Str::orderedUuid(),
            'account_id' => $request->account_id,
            'cpf_cnpj'   => $cpf_cnpj,
            'name'       => $request->name,
            'created_at' => \Carbon\Carbon::now()
        ])) {
            return response()->json(["success" => "Beneficiário escrow cadastrado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível cadastrar o beneficiário escrow no momento, por favor tente novamente mais tarde."]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [4];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $escrowBeneficiaries             = new EscrowBeneficiaries();
        $escrowBeneficiaries->account_id = $checkAccount->account_id;
        $escrowBeneficiaries->onlyActive = $request->onlyActive;
        return response()->json($escrowBeneficiaries->get());
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
            'cpf_cnpj'              => ['required', 'string'],
            'name'                  => ['required', 'string'],
        ],[
            'id.required'           => 'É obrigatório informar o id',
            'uuid.required'         => 'É obrigatório informar o uuid',
            'cpf_cnpj.required'     => 'É obrigatório informar o CPF/CNPJ',
            'name.required'         => 'É obrigatório informar o nome/razão social',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }

        $facilites = new Facilites();
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $request->cpf_cnpj);
        $facilites->cpf_cnpj = $cpf_cnpj;

        if (strlen($cpf_cnpj) == 11) {
            if (!$facilites->validateCPF($cpf_cnpj)) {
                return response()->json(array("error" => "CPF inválido."));
            }
        } else if (strlen($cpf_cnpj) == 14) {
            if (!$facilites->validateCNPJ($cpf_cnpj)) {
                return response()->json(array("error" => "CNPJ inválido."));
            }
        } else {
            return response()->json(array("error" => "CPF ou CNPJ inválido."));
        }


        if( !$escrowBeneficiaries = EscrowBeneficiaries::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->first() 
        ) {
            return response()->json(["error" => "Não foi possível localizar o beneficiário escrow, por favor tente novamente mais tarde."]);
        }

        $escrowBeneficiaries->name = $request->name;
        $escrowBeneficiaries->cpf_cnpj = $cpf_cnpj;

        if($escrowBeneficiaries->save()) {
            return response()->json(["success" => "Beneficiário escrow alterado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível alterar o beneficiário escrow no momento, por favor tente novamente mais tarde"]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        // ----------------- Check Account Verification ----------------- //
        $accountCheckService                = new AccountRelationshipCheckService();
        $accountCheckService->request       = $request;
        $accountCheckService->permission_id = [3];
        $checkAccount                       = $accountCheckService->checkAccount();
        if(!$checkAccount->success){
            return response()->json(array("error" => $checkAccount->message));
        }
        // -------------- Finish Check Account Verification -------------- //

        $validator = Validator::make($request->all(), [
            'id'                    => ['required', 'integer'],
            'uuid'                  => ['required', 'string'],
        ],[
            'id.required'           => 'É obrigatório informar o id',
            'uuid.required'         => 'É obrigatório informar o uuid',
        ]);
        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()]);
        }


        if( !$escrowBeneficiaries = EscrowBeneficiaries::where('id', '=', $request->id)
            ->where('uuid', '=', $request->uuid)
            ->first() 
        ) {
            return response()->json(["error" => "Não foi possível localizar o beneficiário escrow, por favor tente novamente mais tarde."]);
        }

        $escrowBeneficiaries->deleted_at = \Carbon\Carbon::now();

        if($escrowBeneficiaries->save()) {
            return response()->json(["success" => "Beneficiário escrow deletado com sucesso."]);
        }
        return response()->json(["error" => "Poxa, não foi possível deletar o beneficiário escrow no momento, por favor tente novamente mais tarde"]);
    }
}
