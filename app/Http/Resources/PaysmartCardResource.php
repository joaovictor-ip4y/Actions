<?php

namespace App\Http\Resources;

use App\Modules\Card\Domain\Classes\HelperEventoIdClass;
use Illuminate\Http\Resources\Json\JsonResource;

class PaysmartCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'card_status_id' => $this->card_status_id,
            'requestCard' => $this->requestCard,
            'tracking' => $this->whenLoaded('tracking', function () {
                return [
                    'id' => $this->tracking->id,
                    'uuid' => $this->tracking->uuid,
                    'histories' => $this->tracking->histories->map(function ($history) {
                        return [
                            'id' => $history->id,
                            'uuid' => $history->uuid,
                            'history_occurrence' => $history->history_occurrence,
                            'history_event_id' => HelperEventoIdClass::get($history->history_event_id),
                            'history_event' => $history->history_event,
                            'history_ar_mail' => $history->history_ar_mail,
                            'history_frq' => $history->history_frq,
                            'history_location' => $history->history_location,
                        ];
                    }),
                ];
            }),
        ];
    }
}
