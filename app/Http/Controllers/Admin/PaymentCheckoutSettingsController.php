<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentArrangement;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentCheckoutSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentCheckoutSettingsController extends Controller
{
    public function index(PaymentCheckoutSettings $checkout): View
    {
        return view('admin.settings.payment-checkout', [
            'arrangements' => PaymentArrangement::cases(),
            'enabledPos' => collect($checkout->posOptions())->map(fn ($a) => $a->value)->all(),
            'customerCash' => $checkout->customerCashEnabled(),
            'customerNonCash' => $checkout->customerNonCashEnabled(),
        ]);
    }

    public function update(Request $request, PaymentCheckoutSettings $checkout): RedirectResponse
    {
        $validated = $request->validate([
            'pos_arrangements' => ['required', 'array', 'min:1'],
            'pos_arrangements.*' => ['required', 'string', 'distinct',
                Rule::in(array_map(fn (PaymentArrangement $a) => $a->value, PaymentArrangement::cases()))],
            'customer_cash' => ['required', 'boolean'],
            'customer_non_cash' => ['required', 'boolean'],
        ]);

        if (! $validated['customer_cash'] && ! $validated['customer_non_cash']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_cash' => 'يجب إبقاء خيار دفع واحد على الأقل متاحًا للعميل.',
            ]);
        }

        $checkout->save(
            $validated['pos_arrangements'],
            (bool) $validated['customer_cash'],
            (bool) $validated['customer_non_cash'],
        );

        return redirect()->route('settings.payment-checkout.index')
            ->with('success', 'تم حفظ إعدادات الدفع والطلبات.');
    }
}
