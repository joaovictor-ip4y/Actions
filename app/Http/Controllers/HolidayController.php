<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Services\Account\AccountRelationshipCheckService;

class HolidayController extends Controller
{

    public function isBusinessDay($date)
    {
        if((Holiday::isHoliday($date)) or ((date("w", strtotime($date))) == 0) or ((date("w", strtotime($date))) == 6)){
            return false;
        } else {
            return true;
        }
    }

    public function returnBusinessDay($date)
    {
        $businessDay         = $date;
        $businessDayPrevious = $date;
        $businessDayNext     = $date;

        $dayIsBusinessDay = $this->isBusinessDay($date);

        do {
            $businessDayNext = (\Carbon\Carbon::parse( $businessDayNext )->addDays(1) )->format('Y-m-d');
        } while ( !$this->isBusinessDay($businessDayNext) == true );

        do {
            $businessDayPrevious = (\Carbon\Carbon::parse( $businessDayPrevious )->addDays(-1) )->format('Y-m-d');
        } while ( !$this->isBusinessDay($businessDayPrevious) == true );

        return (object) [
            "isBusinessDay"       => $dayIsBusinessDay,
            "day"                 => $businessDay,
            "businessDayNext"     => $businessDayNext,
            "businessDayPrevious" => $businessDayPrevious
        ];
    }

}
