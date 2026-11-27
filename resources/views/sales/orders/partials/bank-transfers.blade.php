@php
    $bankTransfers = $order->payments
        ->filter(function ($payment): bool {
            $methodType = (string) ($payment->paymentMethod?->type ?? '');

            return $payment->location_payment_account_id !== null
                || in_array(
                    $methodType,
                    ['bank_transfer', 'electronic_wallet'],
                    true
                );
        })
        ->sortByDesc(fn ($payment) => $payment->paid_at?->timestamp ?? $payment->id)
        ->values();

    $pendingPaymentAmount = (float) $order->payments
        ->filter(
            fn ($payment): bool =>
                $payment->statusValue() === 'pending_verification'
        )
        ->sum('amount');

    $availableForNewPayment = max(
        0,
        $invoiceRemainingAmount - $pendingPaymentAmount
    );

    $canAddBankTransfer =
        in_array($orderStatus, ['confirmed', 'completed'], true)
        && $availableForNewPayment > 0.004
        && $bankPaymentMethods->isNotEmpty()
        && $bankPaymentAccounts->isNotEmpty();

    $hasBankTransferErrors = !old('order_refund_payment_id') && (
        $errors->has('payment_method_id')
        || $errors->has('location_payment_account_id')
        || $errors->has('amount')
        || $errors->has('reference_number')
        || $errors->has('payment_proof')
        || $errors->has('sender_name')
        || $errors->has('sender_phone')
        || $errors->has('sender_account_number'));
@endphp

<div class="card order-bank-transfers-card">
    <div class="card-header order-bank-transfers-header">
        <div>
            <span class="card-title">التحويلات البنكية والإلكترونية</span>
            <div class="order-transfer-subtitle">
                تسجيل الحوالات المرتبطة بهذا الطلب ومتابعة التحقق منها.
            </div>
        </div>

        <div class="order-transfer-header-actions">
            <a
                href="{{ route('payments.bank-sales', ['search' => $order->order_number]) }}"
                class="btn btn-ghost btn-sm"
            >
                سجل المبيعات البنكية
            </a>

            @can('record', \App\Models\Payment::class)
                @if($canAddBankTransfer)
                    <button
                        type="button"
                        class="btn btn-gold btn-sm"
                        onclick="openBankTransferModal()"
                    >
                        + إضافة حوالة
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <div class="card-body">
        @if(old('order_refund_payment_id') && $errors->any())
            <div class="order-transfer-note is-warning" role="alert">
                تعذر تسجيل الاسترداد:
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if(in_array($orderStatus, ['confirmed', 'completed'], true))
            @if($invoiceRemainingAmount <= 0.004)
                <div class="order-transfer-note is-success">
                    تم تغطية كامل قيمة الطلب، لذلك لا يوجد رصيد متبقٍ لإضافة حوالة جديدة.
                </div>
            @elseif($availableForNewPayment <= 0.004 && $pendingPaymentAmount > 0)
                <div class="order-transfer-note is-warning">
                    الرصيد المتبقي مغطى حاليًا بحوالات قيد التحقق. اعتمد أو ارفض الحوالات المعلّقة قبل إضافة حوالة جديدة.
                </div>
            @elseif($bankPaymentMethods->isEmpty())
                <div class="order-transfer-note is-warning">
                    لا توجد طريقة دفع بنكية أو محفظة إلكترونية مفعلة لهذا الفرع.
                </div>
            @elseif($bankPaymentAccounts->isEmpty())
                <div class="order-transfer-note is-warning">
                    لا يوجد حساب استلام بنكي أو محفظة إلكترونية مفعلة لهذا الفرع.
                </div>
            @endif
        @else
            <div class="order-transfer-note">
                يظهر زر إضافة الحوالة بعد تأكيد الطلب.
            </div>
        @endif

        <div class="order-transfer-summary">
            <div>
                <span>إجمالي الطلب</span>
                <strong>₪{{ number_format((float) $order->total_amount, 2) }}</strong>
            </div>
            <div>
                <span>صافي المدفوع</span>
                <strong>₪{{ number_format($invoicePaidAmount, 2) }}</strong>
            </div>
            <div>
                <span>المتبقي</span>
                <strong>₪{{ number_format($invoiceRemainingAmount, 2) }}</strong>
            </div>
            <div>
                <span>قيد التحقق</span>
                <strong>₪{{ number_format($pendingPaymentAmount, 2) }}</strong>
            </div>
            <div>
                <span>متاح لتحصيل جديد</span>
                <strong>₪{{ number_format($availableForNewPayment, 2) }}</strong>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table order-transfer-table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>المحوّل</th>
                    <th>طريقة الدفع</th>
                    <th>حساب الاستلام</th>
                    <th>المبلغ</th>
                    <th>المرجع</th>
                    <th>الإثبات</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bankTransfers as $payment)
                    @php
                        $paymentStatus = $payment->statusValue();
                        $refundedAmount = round((float) $payment->refunds->sum('amount'), 2);
                        $refundableAmount = round(max(0, (float) $payment->amount - $refundedAmount), 2);
                        $account = $payment->locationPaymentAccount;
                        $accountNumber = $account?->iban
                            ?: ($account?->account_number ?: $account?->phone_number);

                        [$statusClass, $statusText] = match($paymentStatus) {
                            'confirmed' => ['badge-active', 'مؤكدة'],
                            'pending_verification' => ['badge-pending', 'بانتظار التحقق'],
                            'rejected' => ['badge-inactive', 'مرفوضة'],
                            'corrected' => ['badge-pending', 'مصححة'],
                            'refunded' => ['badge-inactive', 'مستردة'],
                            default => ['badge-secondary', $paymentStatus ?: 'غير محددة'],
                        };
                    @endphp

                    <tr>
                        <td>
                            <strong>{{ $payment->paid_at?->format('Y-m-d') ?? '—' }}</strong>
                            <small class="order-transfer-muted">
                                {{ $payment->paid_at?->format('H:i') }}
                            </small>
                        </td>

                        <td>
                            <strong>
                                {{ $payment->sender_name ?: ($order->customer?->name ?? 'غير محدد') }}
                            </strong>
                            <small class="order-transfer-muted" dir="ltr">
                                {{ $payment->sender_phone ?: ($order->customer?->phone ?? '—') }}
                            </small>
                            @if($payment->sender_account_number)
                                <small class="order-transfer-account" dir="ltr">
                                    {{ $payment->sender_account_number }}
                                </small>
                            @endif
                        </td>

                        <td>
                            {{ $payment->paymentMethod?->name_ar
                                ?: ($payment->paymentMethod?->name ?? '—') }}
                        </td>

                        <td>
                            @if($account)
                                <strong>{{ $account->account_holder_name ?: $account->name }}</strong>
                                <small class="order-transfer-muted">
                                    {{ $account->provider_name ?: $account->name }}
                                </small>
                                <small class="order-transfer-account" dir="ltr">
                                    {{ $accountNumber ?: 'بدون رقم' }}
                                </small>
                            @else
                                <span class="order-transfer-warning">غير مربوط</span>
                            @endif
                        </td>

                        <td>
                            <strong>₪{{ number_format((float) $payment->amount, 2) }}</strong>
                            @if($refundedAmount > 0)
                                <small class="order-transfer-muted">مسترد: ₪{{ number_format($refundedAmount, 2) }}</small>
                                <small class="order-transfer-muted">صافي التحصيل: ₪{{ number_format($refundableAmount, 2) }}</small>
                            @endif
                        </td>

                        <td dir="ltr">
                            {{ $payment->reference_number ?: '—' }}
                        </td>

                        <td>
                            @if($payment->payment_proof)
                                <a
                                    href="{{ route('payments.proof', $payment) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-outline btn-xs"
                                >
                                    عرض الإثبات
                                </a>
                            @else
                                <span class="order-transfer-warning">غير مرفق</span>
                            @endif
                        </td>

                        <td>
                            <span class="badge {{ $statusClass }}">
                                {{ $statusText }}
                            </span>

                            @if($payment->verifiedBy)
                                <small class="order-transfer-muted">
                                    بواسطة {{ $payment->verifiedBy->display_name ?? $payment->verifiedBy->name }}
                                </small>
                            @endif

                            @if($paymentStatus === 'rejected' && $payment->rejection_reason)
                                <small class="order-transfer-rejection">
                                    {{ $payment->rejection_reason }}
                                </small>
                            @endif
                        </td>

                        <td>
                            @if($paymentStatus === 'pending_verification')
                                @can('verify', $payment)
                                    <div class="order-transfer-actions">
                                        <form
                                            method="POST"
                                            action="{{ route('payments.verify', $payment) }}"
                                        >
                                            @csrf
                                            <input type="hidden" name="action" value="verify">
                                            <button
                                                type="submit"
                                                class="btn btn-gold btn-xs"
                                            >
                                                اعتماد
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('payments.verify', $payment) }}"
                                            class="order-transfer-reject-form"
                                        >
                                            @csrf
                                            <input type="hidden" name="action" value="reject">
                                            <input
                                                type="text"
                                                name="rejection_reason"
                                                class="form-input"
                                                maxlength="500"
                                                required
                                                placeholder="سبب الرفض"
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-ghost btn-xs"
                                            >
                                                رفض
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    —
                                @endcan
                            @elseif(in_array($paymentStatus, ['confirmed', 'corrected'], true) && $refundableAmount > 0)
                                @can('payments.refund')
                                    @can('refund', $payment)
                                        @if($refundPaymentMethods->isNotEmpty())
                                            <button type="button" class="btn btn-outline btn-xs order-refund-open"
                                                onclick="openOrderRefundDialog('orderRefund{{ $payment->id }}')">
                                                استرداد المبلغ
                                            </button>
                                            <dialog id="orderRefund{{ $payment->id }}" class="order-refund-dialog"
                                                aria-labelledby="orderRefundTitle{{ $payment->id }}" dir="rtl">
                                                <h3 id="orderRefundTitle{{ $payment->id }}">استرداد دفعة #{{ $payment->id }}</h3>
                                                <p>المتاح للاسترداد: <strong>₪{{ number_format($refundableAmount, 2) }}</strong></p>
                                                <p class="order-transfer-note is-warning">سجّل الاسترداد بعد إرجاع المبلغ فعليًا للعميل. النظام لا ينفذ حوالة بنكية خارجية.</p>
                                                <form method="POST" action="{{ route('payments.refund', $payment) }}"
                                                    data-confirm-bypass="1"
                                                    onsubmit="return submitOrderRefund(this)">
                                                    @csrf
                                                    <input type="hidden" name="order_refund_payment_id" value="{{ $payment->id }}">
                                                    <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <div class="order-refund-edit-step">
                                                    <div class="form-group">
                                                        <label class="form-label" for="orderRefundAmount{{ $payment->id }}">المبلغ المسترد</label>
                                                        <input id="orderRefundAmount{{ $payment->id }}" name="amount" type="number"
                                                            class="form-input" step="0.01" min="0.01"
                                                            max="{{ number_format($refundableAmount, 2, '.', '') }}"
                                                            value="{{ number_format($refundableAmount, 2, '.', '') }}" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label" for="orderRefundMethod{{ $payment->id }}">طريقة الاسترداد الفعلية</label>
                                                        <select id="orderRefundMethod{{ $payment->id }}" name="payment_method_id" class="form-select" onchange="syncOrderRefundAccount(this.form)" required>
                                                            <option value="">اختر طريقة الاسترداد</option>
                                                            @foreach($refundPaymentMethods as $method)
                                                                <option value="{{ $method->id }}" data-banking="{{ in_array($method->type, ['bank_transfer', 'electronic_wallet'], true) ? '1' : '0' }}" @selected((int) $method->id === (int) $payment->payment_method_id)>
                                                                    {{ $method->name_ar ?: $method->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group order-refund-account-field" hidden>
                                                        <label class="form-label" for="orderRefundAccount{{ $payment->id }}">الحساب الذي سيخرج منه المبلغ *</label>
                                                        <select id="orderRefundAccount{{ $payment->id }}" name="source_payment_account_id" class="form-select" data-original="{{ $payment->location_payment_account_id }}" disabled>
                                                            <option value="">اختر حساب الصرف</option>
                                                            @foreach($refundPaymentAccounts as $source)
                                                                <option value="{{ $source->id }}" data-method="{{ $source->payment_method_id }}">
                                                                    {{ $source->account_holder_name ?: $source->name }} — {{ $source->provider_name ?: $source->name }} — {{ $source->iban ?: $source->account_number ?: $source->phone_number }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <small class="order-refund-account-hint order-transfer-muted"></small>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label" for="orderRefundReason{{ $payment->id }}">سبب الاسترداد (والمرجع إن وجد)</label>
                                                        <textarea id="orderRefundReason{{ $payment->id }}" name="reason" class="form-input"
                                                            rows="3" minlength="5" maxlength="500" required></textarea>
                                                    </div>
                                                    <div class="order-refund-footer">
                                                        <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">إلغاء</button>
                                                        <button type="submit" class="btn btn-danger" data-refund-next>تسجيل الاسترداد</button>
                                                    </div>
                                                    </div>
                                                    <div class="order-refund-confirm-step" hidden>
                                                        <div class="order-refund-confirm-icon" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg>
                                                        </div>
                                                        <h4 tabindex="-1" data-refund-confirm-title>تأكيد تسجيل الاسترداد</h4>
                                                        <p>هل تم إرجاع المبلغ فعليًا للعميل؟</p>
                                                        <div class="order-refund-confirm-summary">
                                                            <span>المبلغ المسترد</span><strong data-refund-summary-amount></strong>
                                                            <span>طريقة الاسترداد</span><strong data-refund-summary-method></strong>
                                                            <span data-refund-summary-account-label>حساب الصرف</span><strong data-refund-summary-account></strong>
                                                        </div>
                                                        <small class="order-transfer-muted">سيتم تسجيل الحركة المالية وتحديث صافي المدفوع، دون تنفيذ حوالة خارجية.</small>
                                                        <div class="order-refund-footer">
                                                            <button type="button" class="btn btn-outline" data-refund-back onclick="showOrderRefundEdit(this.form)">رجوع</button>
                                                            <button type="submit" class="btn btn-danger" data-refund-confirm>نعم، تأكيد الاسترداد</button>
                                                        </div>
                                                        <div data-refund-delay class="order-transfer-note is-warning" hidden role="status" style="margin-top:.8rem">
                                                            تأخر رد السيرفر. قد تكون العملية قد سُجلت بالفعل؛ لا تعِد إرسالها قبل مراجعة سجل الاستردادات.
                                                            <a href="{{ route('orders.show', $order) }}" class="btn btn-outline btn-xs" style="margin-top:.5rem">التحقق من حالة الطلب</a>
                                                        </div>
                                                    </div>
                                                </form>
                                            </dialog>
                                        @else
                                            <small class="order-transfer-muted">لا توجد طريقة استرداد مفعلة</small>
                                        @endif
                                    @else
                                        —
                                    @endcan
                                @else
                                    —
                                @endcan
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @foreach($payment->refunds as $refund)
                        <tr class="order-refund-history">
                            <td>{{ $refund->processed_at?->format('Y-m-d H:i') }}</td>
                            <td colspan="2">استرداد للدفعة #{{ $payment->id }} — {{ $refund->paymentMethod?->name_ar ?: $refund->paymentMethod?->name }}</td>
                            <td>
                                @if($refund->sourcePaymentAccount)
                                    {{ $refund->sourcePaymentAccount->account_holder_name ?: $refund->sourcePaymentAccount->name }}
                                    <small class="order-transfer-muted">{{ $refund->sourcePaymentAccount->provider_name }}</small>
                                    <small class="order-transfer-account" dir="ltr">{{ $refund->sourcePaymentAccount->iban ?: $refund->sourcePaymentAccount->account_number ?: $refund->sourcePaymentAccount->phone_number }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td><strong>-₪{{ number_format((float) $refund->amount, 2) }}</strong></td>
                            <td colspan="3">{{ $refund->reason }}</td>
                            <td>مسجل</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="9" class="order-transfer-empty">
                            لا توجد حوالات مسجلة على هذا الطلب حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@can('record', \App\Models\Payment::class)
    @if(in_array($orderStatus, ['confirmed', 'completed'], true))
        <div
            id="bankTransferModal"
            class="bank-transfer-modal {{ $hasBankTransferErrors ? 'is-open' : '' }}"
            aria-hidden="{{ $hasBankTransferErrors ? 'false' : 'true' }}"
            onclick="closeBankTransferModalFromBackdrop(event)"
        >
            <div
                class="bank-transfer-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bankTransferModalTitle"
            >
                <div class="bank-transfer-modal-header">
                    <div>
                        <span>تحصيل بنكي / إلكتروني</span>
                        <h3 id="bankTransferModalTitle">إضافة حوالة للطلب {{ $order->order_number }}</h3>
                        <p>
                            المتاح للتحصيل الآن:
                            <strong>₪{{ number_format($availableForNewPayment, 2) }}</strong>
                        </p>
                    </div>

                    <button
                        type="button"
                        class="bank-transfer-close"
                        onclick="closeBankTransferModal()"
                        aria-label="إغلاق"
                    >
                        ×
                    </button>
                </div>

                <form
                    method="POST"
                    action="{{ route('payments.store') }}"
                    enctype="multipart/form-data"
                    id="bankTransferForm"
                >
                    @csrf

                    <input type="hidden" name="entry_context" value="order_bank_transfer">
                    <input type="hidden" name="order_type" value="order">
                    <input type="hidden" name="order_id" value="{{ $order->id }}">

                    <div class="bank-transfer-form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم المحوّل *</label>
                            <input
                                type="text"
                                name="sender_name"
                                class="form-input"
                                maxlength="150"
                                required
                                value="{{ old('sender_name', $order->customer?->name) }}"
                                placeholder="اسم صاحب الحوالة"
                            >
                            @error('sender_name')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">هاتف المحوّل</label>
                            <input
                                type="text"
                                name="sender_phone"
                                class="form-input"
                                maxlength="50"
                                value="{{ old('sender_phone', $order->customer?->phone) }}"
                                placeholder="رقم الهاتف"
                                dir="ltr"
                            >
                            @error('sender_phone')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">رقم حساب / محفظة المحوّل *</label>
                            <input
                                type="text"
                                name="sender_account_number"
                                class="form-input"
                                maxlength="120"
                                required
                                value="{{ old('sender_account_number') }}"
                                placeholder="رقم الحساب أو رقم المحفظة"
                                dir="ltr"
                            >
                            @error('sender_account_number')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">طريقة الدفع *</label>
                            <select
                                name="payment_method_id"
                                id="bankTransferMethod"
                                class="form-select"
                                required
                            >
                                <option value="">اختر طريقة الدفع</option>
                                @foreach($bankPaymentMethods as $method)
                                    <option
                                        value="{{ $method->id }}"
                                        data-requires-reference="{{ $method->requires_reference ? '1' : '0' }}"
                                        data-requires-proof="{{ $method->requires_verification ? '1' : '0' }}"
                                        @selected((string) old('payment_method_id') === (string) $method->id)
                                    >
                                        {{ $method->name_ar ?: $method->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method_id')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">حساب الاستلام *</label>
                            <select
                                name="location_payment_account_id"
                                id="bankTransferAccount"
                                class="form-select"
                                required
                            >
                                <option value="">اختر حساب الاستلام</option>
                                @foreach($bankPaymentAccounts as $account)
                                    @php
                                        $displayAccountNumber = $account->iban
                                            ?: ($account->account_number ?: $account->phone_number);
                                    @endphp
                                    <option
                                        value="{{ $account->id }}"
                                        data-payment-method="{{ $account->payment_method_id }}"
                                        @selected((string) old('location_payment_account_id') === (string) $account->id)
                                    >
                                        {{ $account->name }}
                                        @if($account->provider_name)
                                            — {{ $account->provider_name }}
                                        @endif
                                        @if($displayAccountNumber)
                                            — {{ $displayAccountNumber }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <div id="bankTransferAccountHint" class="order-transfer-field-hint"></div>
                            @error('location_payment_account_id')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">المبلغ *</label>
                            <input
                                type="number"
                                name="amount"
                                class="form-input"
                                min="0.01"
                                max="{{ number_format($availableForNewPayment, 2, '.', '') }}"
                                step="0.01"
                                required
                                value="{{ old('amount', number_format($availableForNewPayment, 2, '.', '')) }}"
                            >
                            @error('amount')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                رقم المرجع / رقم العملية
                                <span id="bankTransferReferenceRequired"></span>
                            </label>
                            <input
                                type="text"
                                name="reference_number"
                                id="bankTransferReference"
                                class="form-input"
                                maxlength="100"
                                value="{{ old('reference_number') }}"
                                placeholder="Transaction ID / Reference"
                                dir="ltr"
                            >
                            @error('reference_number')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                إثبات الدفع
                                <span id="bankTransferProofRequired"></span>
                            </label>
                            <input
                                type="file"
                                name="payment_proof"
                                id="bankTransferProof"
                                class="form-input"
                                accept=".jpg,.jpeg,.png,.pdf"
                            >
                            <div class="order-transfer-field-hint">
                                JPG / PNG / PDF — بحد أقصى 5MB
                            </div>
                            @error('payment_proof')
                                <div class="order-transfer-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="bank-transfer-modal-footer">
                        <button
                            type="button"
                            class="btn btn-ghost"
                            onclick="closeBankTransferModal()"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            class="btn btn-gold"
                            id="bankTransferSubmit"
                        >
                            حفظ الحوالة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan

<style>
    dialog.order-refund-dialog {
        position: fixed !important;
        inset: 0 !important;
        margin: auto !important;
        width: min(520px, calc(100% - 2rem));
        height: fit-content;
        box-sizing: border-box;
        transform: none !important;
        max-height: 90vh;
        overflow: auto;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.25rem;
        background: #fff;
        color: var(--text);
        box-shadow: 0 20px 65px rgba(0,0,0,.25);
        text-align: right;
    }
    .order-refund-account-field[hidden],
    .order-refund-edit-step[hidden],
    .order-refund-confirm-step[hidden],
    .order-refund-confirm-step [data-refund-delay][hidden],
    .order-refund-confirm-summary [hidden] { display:none !important; }
    .order-refund-confirm-step { text-align:center; padding:.7rem 0 .15rem; }
    .order-refund-confirm-icon {
        display:grid; place-items:center; width:64px; height:64px;
        margin:0 auto .85rem; border-radius:50%;
        background:rgba(158,19,59,.08); color:#9e133b;
    }
    .order-refund-confirm-step h4 { margin:.25rem 0 .5rem; font-size:1.1rem; color:var(--text); }
    .order-refund-confirm-step p { margin:.5rem 0 1rem; color:var(--text); }
    .order-refund-confirm-summary {
        display:grid; grid-template-columns:minmax(95px,auto) minmax(0,1fr);
        text-align:right; gap:.7rem 1rem; padding:1rem;
        border:1px solid var(--border); border-radius:12px; margin-bottom:.75rem;
        background:var(--off-white,#faf6f7);
    }
    .order-refund-confirm-summary span { color:var(--text-muted); font-size:.78rem; }
    .order-refund-confirm-summary strong { text-align:left; overflow-wrap:anywhere; font-size:.84rem; }
    .order-refund-confirm-step .order-refund-footer { justify-content:center; flex-wrap:wrap; }
    .order-refund-dialog::backdrop { background: rgba(15,23,42,.6); }
    .order-refund-dialog h3 { margin: 0 0 .7rem; }
    .order-refund-dialog .form-group { margin-top: .8rem; }
    .order-refund-footer { display:flex; justify-content:flex-end; gap:.65rem; margin-top: 1rem; }
    .order-refund-open, .order-refund-history { color:#b42318; }
    .order-refund-history td { background:rgba(180,35,24,.045); }

    .order-bank-transfers-card {
        margin-top: 1.2rem;
    }

    .order-bank-transfers-header,
    .order-transfer-header-actions,
    .order-transfer-actions {
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .order-bank-transfers-header {
        justify-content: space-between;
    }

    .order-transfer-subtitle,
    .order-transfer-muted,
    .order-transfer-field-hint {
        display: block;
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .72rem;
        line-height: 1.55;
    }

    .order-transfer-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .75rem;
    }

    .order-transfer-summary > div {
        padding: .85rem;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--off-white);
    }

    .order-transfer-summary span {
        display: block;
        color: var(--text-muted);
        font-size: .7rem;
        margin-bottom: .3rem;
    }

    .order-transfer-summary strong {
        color: var(--text);
        font-size: .95rem;
    }

    .order-transfer-note {
        margin-bottom: .9rem;
        padding: .75rem .9rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--off-white);
        color: var(--text-muted);
        font-size: .76rem;
    }

    .order-transfer-note.is-success {
        border-color: rgba(25,135,84,.18);
        background: rgba(25,135,84,.06);
        color: #16845b;
    }

    .order-transfer-note.is-warning {
        border-color: rgba(212,160,23,.22);
        background: rgba(212,160,23,.07);
        color: #8a6610;
    }

    .order-transfer-table {
        min-width: 1180px;
    }

    .order-transfer-account {
        display: block;
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .7rem;
    }

    .order-transfer-warning,
    .order-transfer-rejection {
        color: #b42318;
        font-size: .72rem;
    }

    .order-transfer-rejection {
        display: block;
        margin-top: .25rem;
    }

    .order-transfer-actions {
        align-items: flex-start;
        flex-direction: column;
    }

    .order-transfer-reject-form {
        display: grid;
        grid-template-columns: minmax(140px, 1fr) auto;
        gap: .4rem;
        width: 100%;
        min-width: 230px;
    }

    .order-transfer-reject-form .form-input {
        min-height: 30px;
        padding: .35rem .5rem;
        font-size: .7rem;
    }

    .order-transfer-empty {
        padding: 1.25rem !important;
        text-align: center;
        color: var(--text-muted);
    }

    .bank-transfer-modal {
        position: fixed;
        inset: 0;
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15,23,42,.62);
        backdrop-filter: blur(3px);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: .18s ease;
    }

    .bank-transfer-modal.is-open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .bank-transfer-dialog {
        width: min(860px, 100%);
        max-height: 92vh;
        overflow: auto;
        border-radius: 18px;
        border: 1px solid var(--border);
        background: #fff;
        box-shadow: 0 28px 70px rgba(0,0,0,.24);
    }

    .bank-transfer-modal-header {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: #fff;
    }

    .bank-transfer-modal-header span {
        color: var(--gold);
        font-size: .68rem;
        font-weight: 900;
    }

    .bank-transfer-modal-header h3 {
        margin: .2rem 0 0;
        color: var(--text);
        font-size: 1.05rem;
    }

    .bank-transfer-modal-header p {
        margin: .3rem 0 0;
        color: var(--text-muted);
        font-size: .75rem;
    }

    .bank-transfer-close {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        border: 1px solid var(--border);
        border-radius: 50%;
        background: #fff;
        color: var(--text-muted);
        font-size: 1.3rem;
        cursor: pointer;
    }

    .bank-transfer-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        padding: 1.25rem;
    }

    .bank-transfer-modal-footer {
        position: sticky;
        bottom: 0;
        display: flex;
        justify-content: flex-end;
        gap: .65rem;
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
        background: var(--off-white);
    }

    .order-transfer-error {
        margin-top: .25rem;
        color: #b42318;
        font-size: .7rem;
    }

    @media(max-width: 800px) {
        .order-bank-transfers-header,
        .order-transfer-header-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .order-transfer-summary,
        .bank-transfer-form-grid {
            grid-template-columns: 1fr;
        }

        .order-transfer-header-actions .btn {
            width: 100%;
        }
    }
</style>

<script>
    function syncOrderRefundAccount(form) {
        const method = form.elements.payment_method_id;
        const account = form.elements.source_payment_account_id;
        const field = form.querySelector('.order-refund-account-field');
        const hint = form.querySelector('.order-refund-account-hint');
        const banking = method.selectedOptions[0]?.dataset.banking === '1';
        field.hidden = !banking;
        account.disabled = !banking;
        account.required = banking;
        const matches = Array.from(account.options).filter(option => {
            if (!option.value) return false;
            const match = banking && option.dataset.method === method.value;
            option.hidden = !match;
            option.disabled = !match;
            return match;
        });
        if (!matches.some(option => option.value === account.value)) {
            account.value = matches.find(option => option.value === account.dataset.original)?.value
                || (matches.length === 1 ? matches[0].value : '');
        }
        hint.textContent = banking && !matches.length
            ? 'لا يوجد حساب يسمح بالصرف لهذه الطريقة. فعّل حساب الصرف من إعدادات الفرع.'
            : '';
        form.querySelector('[data-refund-next]').disabled = banking && !matches.length;
    }

    function showOrderRefundEdit(form) {
        if (form.dataset.submitting === '1') return;
        form.dataset.refundStep = 'edit';
        form.querySelector('.order-refund-edit-step').hidden = false;
        form.querySelector('.order-refund-confirm-step').hidden = true;
        form.querySelector('[data-refund-confirm]').disabled = false;
        syncOrderRefundAccount(form);
        if (form.closest('dialog').open) form.elements.amount.focus();
    }

    function submitOrderRefund(form) {
        if (form.dataset.submitting === '1') return false;
        if (form.dataset.refundStep !== 'confirm') {
            if (!form.reportValidity()) return false;
            const method = form.elements.payment_method_id;
            const account = form.elements.source_payment_account_id;
            const banking = method.selectedOptions[0]?.dataset.banking === '1';
            form.querySelector('[data-refund-summary-amount]').textContent =
                Number(form.elements.amount.value).toFixed(2) + ' ₪';
            form.querySelector('[data-refund-summary-method]').textContent =
                method.selectedOptions[0]?.textContent.trim() || '—';
            form.querySelector('[data-refund-summary-account-label]').hidden = !banking;
            const accountSummary = form.querySelector('[data-refund-summary-account]');
            accountSummary.hidden = !banking;
            accountSummary.textContent = banking ? (account.selectedOptions[0]?.textContent.trim() || '—') : '';
            form.dataset.refundStep = 'confirm';
            form.querySelector('.order-refund-edit-step').hidden = true;
            form.querySelector('.order-refund-confirm-step').hidden = false;
            form.querySelector('[data-refund-confirm-title]').focus();
            return false;
        }

        form.dataset.submitting = '1';
        form.querySelector('[data-refund-confirm]').disabled = true;
        form.querySelector('[data-refund-back]').disabled = true;
        form.querySelector('[data-refund-confirm]').textContent = 'جاري تسجيل الاسترداد...';
        // The built-in confirmation above is the only confirmation for this form.
        // Do not retry a financial POST automatically, even if navigation stalls.
        window.setTimeout(function () {
            if (form.dataset.submitting === '1' && form.closest('dialog')?.open) {
                form.querySelector('[data-refund-delay]').hidden = false;
            }
        }, 12000);
        return true;
    }

    function openOrderRefundDialog(id) {
        const dialog = document.getElementById(id);
        const form = dialog.querySelector('form');
        if (form.dataset.submitting === '1') return;
        showOrderRefundEdit(form);
        dialog.showModal();
    }

    window.addEventListener('pageshow', function (event) {
        // A restored page may contain a stale balance after a completed POST.
        if (event.persisted && document.querySelector('dialog.order-refund-dialog form[data-submitting="1"]')) {
            window.location.reload();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('dialog.order-refund-dialog').forEach(dialog => {
            dialog.addEventListener('cancel', function (event) {
                const form = dialog.querySelector('form');
                if (form.dataset.submitting === '1') {
                    event.preventDefault();
                } else if (form.dataset.refundStep === 'confirm') {
                    event.preventDefault();
                    showOrderRefundEdit(form);
                }
            });
        });
    });

    function openBankTransferModal() {
        const modal = document.getElementById('bankTransferModal');

        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        syncBankTransferFields();
    }

    function closeBankTransferModal() {
        const modal = document.getElementById('bankTransferModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function closeBankTransferModalFromBackdrop(event) {
        if (event.target.id === 'bankTransferModal') {
            closeBankTransferModal();
        }
    }

    function syncBankTransferFields() {
        const method = document.getElementById('bankTransferMethod');
        const account = document.getElementById('bankTransferAccount');
        const reference = document.getElementById('bankTransferReference');
        const proof = document.getElementById('bankTransferProof');
        const referenceRequired = document.getElementById('bankTransferReferenceRequired');
        const proofRequired = document.getElementById('bankTransferProofRequired');
        const accountHint = document.getElementById('bankTransferAccountHint');

        if (!method || !account) {
            return;
        }

        const selectedMethod = method.options[method.selectedIndex];
        const methodId = method.value;
        const requiresReference = selectedMethod?.dataset.requiresReference === '1';
        const requiresProof = selectedMethod?.dataset.requiresProof === '1';
        let availableAccounts = 0;

        Array.from(account.options).forEach((option, index) => {
            if (index === 0) {
                return;
            }

            const matches = methodId !== ''
                && option.dataset.paymentMethod === methodId;

            option.disabled = !matches;
            option.hidden = !matches;

            if (matches) {
                availableAccounts += 1;
            } else if (option.selected) {
                option.selected = false;
            }
        });

        if (methodId && availableAccounts === 1 && !account.value) {
            const onlyOption = Array.from(account.options).find(
                option => !option.disabled && option.value !== ''
            );

            if (onlyOption) {
                onlyOption.selected = true;
            }
        }

        if (accountHint) {
            accountHint.textContent = methodId && availableAccounts === 0
                ? 'لا يوجد حساب استلام مفعّل لطريقة الدفع المختارة.'
                : '';
        }

        if (reference) {
            reference.required = requiresReference;
        }

        if (proof) {
            proof.required = requiresProof;
        }

        if (referenceRequired) {
            referenceRequired.textContent = requiresReference ? '*' : '';
        }

        if (proofRequired) {
            proofRequired.textContent = requiresProof ? '*' : '';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const method = document.getElementById('bankTransferMethod');
        const form = document.getElementById('bankTransferForm');

        if (method) {
            method.addEventListener('change', syncBankTransferFields);
            syncBankTransferFields();
        }

        if (document.getElementById('bankTransferModal')?.classList.contains('is-open')) {
            document.body.style.overflow = 'hidden';
        }

        if (form) {
            form.addEventListener('submit', function () {
                const button = document.getElementById('bankTransferSubmit');

                if (button) {
                    button.disabled = true;
                    button.textContent = 'جاري حفظ الحوالة...';
                }
            });
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape'
            && document.getElementById('bankTransferModal')?.classList.contains('is-open')
        ) {
            closeBankTransferModal();
        }
    });
</script>
