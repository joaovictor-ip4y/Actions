<?php

namespace App\Http\Requests\ScheduleMessage;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleMessageUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => 'required|integer',
            'type_schedule_message_id' => 'required|integer',
            'observation' => 'sometimes|string',
            'days' => 'required|integer|min:0',
            'subject' => 'sometimes|string',
            'message' => 'required|string',
            'cco' => 'sometimes|array',
            'cco.*' => 'sometimes|email',
            'type_delivery' => 'required|array',
            'type_delivery.*' => 'sometimes|in:email,whatsapp,sms',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'cco' => array_unique($this->cco ?? []),
            'type_delivery' => array_unique($this->type_delivery),
        ]);
    }
}
