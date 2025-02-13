<?php

namespace App\Http\Requests\MessageTemplate;

use Illuminate\Foundation\Http\FormRequest;

class MessageTemplateUpdateRequest extends FormRequest
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
            'days' => 'required|integer',
            'typeScheduleMessageId' => 'required|integer',
            'id' => 'required|integer',
            'name' => 'required|string',
            'message' => 'required|string'
        ];
    }
}
