<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'source_location_id'     => ['required', 'integer', 'different:branch_id', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('type', 'branch')->where('is_active', true)->whereNull('deleted_at'))],
            'branch_id'              => [Rule::requiredIf(fn () => $this->user()?->isAdmin() === true), 'nullable', 'integer', Rule::exists('locations', 'id')->where(fn ($q) => $q->where('type', 'branch')->where('is_active', true)->whereNull('deleted_at'))],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'distinct', Rule::exists('products', 'id')->where(fn ($q) => $q->where('is_raw_material', true)->where('is_active', true)->whereNull('deleted_at'))],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
            'notes'                  => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة منتج واحد على الأقل في الطلب.',
            'source_location_id.required' => 'اختر فرع التوريد أولاً.',
            'source_location_id.different' => 'لا يمكن إرسال طلب مخزون من الفرع إلى نفسه.',
            'branch_id.required' => 'اختر الفرع المستلم أولاً.',
        ];
    }
}
