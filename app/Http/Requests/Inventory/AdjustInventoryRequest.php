<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'exists:locations,id'],
            'product_id'  => ['required', Rule::exists('products', 'id')->where(fn ($q) => $q->where('is_raw_material', true))],
            'quantity'    => ['required', 'numeric', 'not_in:0'],
            'reason'      => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => 'كمية التعديل يجب ألا تكون صفراً.',
        ];
    }
}
