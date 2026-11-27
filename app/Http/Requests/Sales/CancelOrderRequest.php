<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
            'stock_disposition' => ['nullable', 'in:restore,retain,waste,split'],
            'line_dispositions' => ['nullable', 'array'],
            'line_dispositions.*' => ['required', 'in:restore,retain,waste'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'سبب الإلغاء مطلوب.',
            'cancellation_reason.min'      => 'سبب الإلغاء يجب ألا يقل عن 5 أحرف.',
        ];
    }
}
