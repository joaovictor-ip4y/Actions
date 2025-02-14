<?php

namespace App\Http\Controllers;

use App\Models\ZipCodeAddress;
use App\Models\ZipCodeCity;
use App\Models\ZipCodeDistrict;
use App\Libraries\ViaCep;
use App\Libraries\Facilites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ZipCodeAddressController extends Controller
{
    protected function getZipCode(Request $request )
    {
        $validator = Validator::make($request->all(), [
            'zip_code' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return abort(404, "Not Found | Invalid Data");
        }

        if( strlen(preg_replace( '/[^0-9]/', '', $request->zip_code)) < 8 or strlen(preg_replace( '/[^0-9]/', '', $request->zip_code)) > 8 ){
            return response()->json(array("error" => "CEP inválido"));
        }

        $zipCode = ZipCodeAddress::returnZipCodeData(preg_replace( '/[^0-9]/', '', $request->zip_code)  );
        return response()->json($zipCode);
        if($zipCode->zip_code == ''){
            $viaCepAPI      = new ViaCep();
            $viaCepAPI->cep = preg_replace( '/[^0-9]/', '', $request->zip_code);
            $cep            = $viaCepAPI->checkCep();
            if($cep != null){ 
                if(isset($cep->cep)){
                    $cepData = (object) [
                        'address'     => $cep->logradouro,
                        'district'    => $cep->bairro,
                        'city'        => $cep->localidade,
                        'state_id'    => Facilites::convertStateToInt($cep->uf),
                        'short_state' => $cep->uf,
                        'ibge_code'   => $cep->ibge,
                        'gia_code'    => $cep->gia,
                        'cep'         => preg_replace("/[^0-9]/",'',$cep->cep)
                    ];
                    //check to register new city in zip code cities table
                    if(ZipCodeCity::where('city','=',$cepData->city)->where('state_id','=',$cepData->state_id)->count() == 0){
                        $zipCodeCity = ZipCodeCity::create([
                            'code'       => (ZipCodeCity::getNextCityCode())->code,
                            'city'       => $cepData->city,
                            'state_id'   => $cepData->state_id,
                            'zip_code'   => $cepData->cep,
                            'ibge_code'  => $cepData->ibge_code,
                            'created_at' => \Carbon\Carbon::now()
                        ]);
                    } else {
                        $zipCodeCity = ZipCodeCity::where('city','=',$cepData->city)->where('state_id','=',$cepData->state_id)->first();
                    }
                    //check to register new district in zip code district table
                    if(ZipCodeDistrict::where('city_code','=',$zipCodeCity->code)->where('district','=',$cepData->district)->count() == 0){
                        $zipCodeDistrict = ZipCodeDistrict::create([
                            'city_code'     => $zipCodeCity->code,
                            'district_code' => (ZipCodeDistrict::getNextDistrictCode())->district_code,
                            'district'      => $cepData->district,
                            'created_at'    => \Carbon\Carbon::now()
                        ]);
                    } else {
                        $zipCodeDistrict = ZipCodeDistrict::where('city_code','=',$zipCodeCity->code)->where('district','=',$cepData->district)->first();
                    }
                    //register new address
                    ZipCodeAddress::create([
                        'city_code'     => $zipCodeCity->code,
                        'district_code' => $zipCodeDistrict->district_code,
                        'address'       => $cepData->address,
                        'zip_code'      => $cepData->cep,
                        'ibge_code'     => $cepData->ibge_code,
                        'gia_code'      => $cepData->gia_code,
                        'created_at'    => \Carbon\Carbon::now()
                    ]);
                }
            }
            $zipCode = ZipCodeAddress::returnZipCodeData(  preg_replace( '/[^0-9]/', '', $request->zip_code)  );
        }

        if($zipCode->zip_code == ''){
            return response()->json(array("error" => "CEP não localizado", "zip_code_data" => $zipCode));
        } else {
            return response()->json(array("success" => "CEP localizado com sucesso", "zip_code_data" => $zipCode));
        }
    }
}
