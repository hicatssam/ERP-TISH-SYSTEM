<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $payment = $this->route('payment');
        return [
            'amount'            => ['required', 'numeric', 'min:0.01', 'max:' . ($payment?->amount ?? PHP_INT_MAX)],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'source_payment_account_id' => ['nullable', 'integer', 'exists:location_payment_accounts,id'],
            'request_token' => ['nullable', 'uuid'],
            'reason'            => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'لا يمكن أن يتجاوز مبلغ الاسترداد المبلغ المدفوع الأصلي.',
        ];
    }
}
