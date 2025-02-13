<?php

namespace App\Http\Controllers;

use App\Models\StatementMoneyPlusBank;
use App\Models\ApiConfig;
use App\Models\Account;
use Illuminate\Http\Request;
use App\Libraries\ApiMoneyPlus;
use Illuminate\Support\Facades\Crypt;
use App\Services\Account\AccountRelationshipCheckService;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Log;


class StatementMoneyPlusBankController extends Controller
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
        $statementBmpBank = new StatementMoneyPlusBank();
        $statementBmpBank->date_start = $request->date_start ? $request->date_start." 00:00:00.000" : null;
        $statementBmpBank->date_end = $request->date_end ? $request->date_end." 23:59:59.998" : null;
        return response()->json($statementBmpBank->get());
    }


    public function statementReconciliation()
    {

        $apiConfig = new ApiConfig();
        $apiConfig->master_id = 1;
        $apiConfig->api_id = 15;
        $apiConfig->onlyActive = 1;
        $apiData = $apiConfig->getApiConfig()[0];
        $apiMoneyPlus = new ApiMoneyPlus();
        $apiMoneyPlus->client_id = Crypt::decryptString($apiData->api_client_id);
        $apiMoneyPlus->api_address = Crypt::decryptString($apiData->api_address);

        $getMoneyPlusAccounts = Account::where('alias_account_bank_id', '=', 161)
        ->whereIn('alias_account_number',[
            '02875128',
            '02182483',
            '00862987',
            '00795401',
            '02323129',
            '00794701',
            '00842591',
            '00855551',
            '02183903',
            '02205797',
            '02209054',
            '02258721',
            '02271179',
            '02291235',
            '02823169',
            '03383767',
            '03470259',
            '08573156',
            '02243079',
            '00790469'
        ])
        ->get();
        
        foreach($getMoneyPlusAccounts as $aliasAccount){
            $apiMoneyPlus->alias_account_agency = $aliasAccount->alias_account_agency;
            $apiMoneyPlus->alias_account_number = $aliasAccount->alias_account_number;
            
            $baseDate = \Carbon\Carbon::parse('2022-07-01');
            
            //$today = Carbon::today()->startOfDay();
            $today = \Carbon\Carbon::parse('2024-10-01');
    
            
            //while ($baseDate->lessThanOrEqualTo($today)) {    
            while ($baseDate->lessThan($today)) {    
                $apiMoneyPlus->year = (int) \Carbon\Carbon::parse( $baseDate )->format('Y');
                $apiMoneyPlus->month = (int) \Carbon\Carbon::parse( $baseDate )->format('m');
                $apiMoneyPlus->end_day = (int) \Carbon\Carbon::parse( $baseDate->lastOfMonth() )->format('d');
                $apiMoneyPlus->start_day = (int) \Carbon\Carbon::parse( $baseDate->firstOfMonth() )->format('d');
               
                $movements = $apiMoneyPlus->checkExtract();
                
                if(isset($movements->data->movimentos)){
                    foreach($movements->data->movimentos as $movement){  

                        if (isset($movement->tipoLancamento)) {
                            if( $movement->tipoLancamento == "C" or $movement->tipoLancamento == "D"  ){


                                $account_balance = StatementMoneyPlusBank::where('account_id', '=', $aliasAccount->id)->sum('value');

                                if( StatementMoneyPlusBank::where('code', '=', $movement->codigo)->where('account_id', '=', $aliasAccount->id)->count() == 0 ){
                                    $sttmntMoneyPlus = StatementMoneyPlusBank::create([
                                        'uuid' => Str::orderedUuid(),
                                        'account_id' => $aliasAccount->id,
                                        'code' => $movement->codigo,
                                        'transaction_code' => $movement->codigoTransacao,
                                        'transaction_identificator' => $movement->identificadorOperacao,
                                        'nsu' => $movement->nsu,
                                        'type' => $movement->tipoLancamento,
                                        'date' => $movement->dtMovimento,
                                        'value' => ($movement->tipoLancamento == 'C') ? $movement->vlrMovimento : ($movement->vlrMovimento * -1),
                                        'account_balance' => ($movement->tipoLancamento == 'C') ? ($movement->vlrMovimento + $account_balance) : (($movement->vlrMovimento * -1) + $account_balance),
                                        'transaction_description' => $movement->descricaoOperacao,
                                        'client_observation' => $movement->descCliente,
                                        'cpf_cnpj' => $movement->documentoFederal,
                                        'name' => $movement->nome,
                                        'origin_transaction' => $movement->origemTransacao,
                                        'origin_bank' => $movement->bancoOrigem,
                                        'origin_agency' => $movement->agenciaOrigem,
                                        'origin_account' => $movement->contaOrigem,
                                        'created_at' => \Carbon\Carbon::now()
                                    ]);
                                }
                            }
                        }
                    }
                }
                $baseDate->addMonth();
            }
        }
    }
}
