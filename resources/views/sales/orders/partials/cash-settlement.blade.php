@php
    $cashSettlementPending = (float) $order->payments
        ->filter(fn ($payment) => $payment->statusValue() === 'pending_verification')
        ->sum('amount');
    $cashSettlementAvailable = round(max(0, $invoiceRemainingAmount - $cashSettlementPending), 2);
    $cashSettlementEligible = in_array($orderStatus, ['confirmed', 'completed'], true)
        && $order->invoice?->isActive()
        && $cashSettlementAvailable > 0.004;
@endphp

@if($order->isRestaurantOrder() && $order->invoice)
<div class="card" id="order-cash-settlement">
    <div class="card-header">
        <div>
            <strong class="card-title">تحصيل الرصيد المتبقي</strong>
            <div style="font-size:.85rem;color:var(--text-muted);margin-top:5px">
                تحصيل نقدي لاحق لنفس الفاتورة، دون إنشاء طلب أو فاتورة جديدة.
            </div>
        </div>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem">
            <span>إجمالي الفاتورة: <strong>₪{{ number_format((float) $order->invoice->total_amount, 2) }}</strong></span>
            <span>المدفوع: <strong>₪{{ number_format($invoicePaidAmount, 2) }}</strong></span>
            <span>المتبقي: <strong>₪{{ number_format($invoiceRemainingAmount, 2) }}</strong></span>
            @if($cashSettlementPending > 0)
                <span>دفعات قيد التحقق: <strong>₪{{ number_format($cashSettlementPending, 2) }}</strong></span>
            @endif
        </div>

        @if($invoiceRemainingAmount <= 0.004)
            <p style="color:var(--success)">تم سداد الفاتورة بالكامل؛ لا يوجد رصيد مستحق.</p>
        @elseif(!in_array($orderStatus, ['confirmed', 'completed'], true) || ! $order->invoice->isActive())
            <p>يجب تأكيد الطلب ووجود فاتورة فعالة قبل التحصيل.</p>
        @elseif($cashSettlementAvailable <= 0.004)
            <p>الرصيد مغطى بدفعات بانتظار التحقق. يجب اعتمادها أو رفضها أولًا.</p>
        @elseif($cashPaymentMethods->isEmpty())
            <p>لا توجد طريقة تحصيل نقدية مفعّلة لهذا الفرع.</p>
        @else
            @can('record', \App\Models\Payment::class)
                @if($cashSettlementEligible)
                    <form action="{{ route('payments.store') }}" method="POST"
                          onsubmit="this.querySelector('button[type=submit]').disabled=true">
                        @csrf
                        <input type="hidden" name="entry_context" value="order_cash_settlement">
                        <input type="hidden" name="order_type" value="order">
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <input type="hidden" name="reference_number" value="POS-CASH-{{ \Illuminate\Support\Str::uuid() }}">
                        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
                            <label style="flex:1;min-width:160px">
                                <span>طريقة التحصيل</span>
                                <select name="payment_method_id" class="form-input" required>
                                    @foreach($cashPaymentMethods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name_ar ?: $method->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label style="flex:1;min-width:160px">
                                <span>المبلغ المستلم (₪)</span>
                                <input type="number" name="amount" class="form-input"
                                       min="0.01" max="{{ number_format($cashSettlementAvailable, 2, '.', '') }}"
                                       step="0.01" value="{{ number_format($cashSettlementAvailable, 2, '.', '') }}" required>
                            </label>
                            <button type="submit" class="btn btn-gold">تسجيل التحصيل النقدي</button>
                        </div>
                    </form>
                @endif
            @endcan
            <p style="font-size:.8rem;color:var(--text-muted);margin-top:12px">
                يتم تسجيل المبلغ في النقدية بتاريخ التحصيل الفعلي. الدفع البنكي يتم من قسم التحويلات أدناه.
            </p>
        @endif
    </div>
</div>
@endif
