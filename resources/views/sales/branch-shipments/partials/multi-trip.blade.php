@php
    $progress = $tripProgress;
    $complete = $invoice?->isConfirmed() ?? false;
    $closedShort = $shipment->status === \App\Models\BranchShipment::CLOSED_SHORT;
@endphp
<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <div>
            <span class="card-title">مراجعة الكميات ونقلات الفروع</span>
            <p class="form-help">يُسجل التحميل لكل نقلة مع الموصّل. تصدر فاتورة نهائية بعد اكتمال الاستلام، أو عند إغلاق الطلب بنقص مع سبب موثق.</p>
        </div>
        <span class="badge {{ $complete ? 'badge-active' : 'badge-warning' }}">{{ $closedShort ? 'أغلقت بنقص — فاتورة معتمدة' : ($complete ? 'مكتمل — الفاتورة صادرة' : 'قيد التوريد') }}</span>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:1rem">
            <div class="stat-card"><div class="stat-info"><div class="stat-label">إجمالي المطلوب</div><div class="stat-value">{{ number_format($progress['required'], 3) }}</div></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-label">المستلم فعليًا</div><div class="stat-value">{{ number_format($progress['received'], 3) }}</div></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-label">في النقلات المفتوحة</div><div class="stat-value">{{ number_format($progress['pending'], 3) }}</div></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-label">المتبقي للاستلام</div><div class="stat-value">{{ number_format($progress['remaining'], 3) }}</div></div></div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>الصنف</th><th>الوحدة</th><th>المطلوب</th><th>المستلم</th><th>في النقل</th><th>متاح للتحميل</th></tr></thead>
                <tbody>
                    @foreach($progress['lines'] as $line)
                        <tr>
                            <td><strong>{{ $line['item']->displayDescription() }}</strong></td>
                            <td>{{ $line['item']->displayUnit() ?: '—' }}</td>
                            <td>{{ number_format($line['required'], 3) }}</td>
                            <td>{{ number_format($line['received'], 3) }}</td>
                            <td>{{ number_format($line['pending'], 3) }}</td>
                            <td>{{ number_format($line['available'], 3) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canFactoryOperate && ! $complete && collect($progress['lines'])->contains(fn ($line) => $line['available'] > 0.0005))
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">تسجيل نقلة جديدة</span></div>
        <form method="POST" action="{{ route('branch-shipments.trips.store', $shipment) }}">
            @csrf
            <div class="card-body">
                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">الموصّل *</label>
                    <select name="driver_id" class="form-select" required>
                        <option value="">اختر الموصّل</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected(old('driver_id') == $driver->id)>{{ $driver->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>الصنف</th><th>المتبقي غير المخصّص لنقلة</th><th>كمية التحميل الفعلية</th></tr></thead>
                        <tbody>
                            @foreach($progress['lines'] as $id => $line)
                                <tr>
                                    <td>{{ $line['item']->displayDescription() }}</td>
                                    <td>{{ number_format($line['available'], 3) }} {{ $line['item']->displayUnit() }}</td>
                                    <td>
                                        <input type="number" name="items[{{ $id }}][quantity]" step="0.001"
                                            min="0" max="{{ $line['available'] }}"
                                            value="{{ old("items.{$id}.quantity", 0) }}"
                                            class="form-input" aria-label="كمية تحميل {{ $line['item']->displayDescription() }}" required>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-group" style="margin-top:1rem">
                    <label class="form-label">ملاحظات التحميل</label>
                    <textarea name="notes" class="form-textarea" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="card-footer" style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-gold">اعتماد تحميل النقلة</button>
            </div>
        </form>
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">سجل النقلات ({{ $shipment->trips->count() }})</span></div>
    <div class="card-body" style="display:grid;gap:1rem">
        @forelse($shipment->trips as $trip)
            <section style="border:1px solid var(--border);border-radius:12px;padding:1rem">
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:space-between">
                    <strong>النقلة #{{ $trip->trip_number }} — {{ $trip->driver?->display_name ?? '—' }}</strong>
                    <span class="badge {{ $trip->status === 'received' ? 'badge-active' : 'badge-warning' }}">{{ $trip->status === 'received' ? 'استلمها الفرع' : 'بانتظار استلام الفرع' }}</span>
                </div>
                <p class="form-help" style="margin:.3rem 0 .8rem">
                    حملها: {{ $trip->loadedBy?->display_name ?? '—' }} · {{ $trip->loaded_at?->format('Y-m-d H:i') }}
                    @if($trip->received_at) · استلمها: {{ $trip->receivedBy?->display_name ?? '—' }} · {{ $trip->received_at->format('Y-m-d H:i') }} @endif
                </p>
                @if($trip->notes)<p class="form-help">ملاحظات التحميل: {{ $trip->notes }}</p>@endif
                @if($trip->status === 'loaded' && $canReceive)
                    <form method="POST" action="{{ route('branch-shipments.trips.receive', [$shipment, $trip]) }}">
                        @csrf
                        <p class="form-help">ادخل الكمية المستلمة فعليًا. أي نقص يعود إلى المتبقي المطلوب في النقلات القادمة.</p>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>الصنف</th><th>المحمّل</th><th>المستلم فعليًا</th><th>ملاحظة</th></tr></thead>
                                <tbody>
                                    @foreach($trip->items as $tripItem)
                                        <tr>
                                            <td>{{ $tripItem->invoiceItem?->displayDescription() }}</td>
                                            <td>{{ number_format((float)$tripItem->loaded_quantity, 3) }}</td>
                                            <td>
                                                <input type="number" name="items[{{ $tripItem->id }}][received_quantity]"
                                                    value="{{ old("items.{$tripItem->id}.received_quantity", $tripItem->loaded_quantity) }}"
                                                    min="0" max="{{ $tripItem->loaded_quantity }}" step="0.001" required
                                                    class="form-input" aria-label="المستلم {{ $tripItem->invoiceItem?->displayDescription() }}">
                                            </td>
                                            <td><input name="items[{{ $tripItem->id }}][note]" type="text" maxlength="1000" class="form-input"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <label class="form-label" style="margin-top:.75rem">ملاحظات الاستلام</label>
                        <textarea name="notes" class="form-textarea" rows="2" maxlength="2000"></textarea>
                        <button type="submit" class="btn btn-success" style="margin-top:.75rem">تأكيد استلام هذه النقلة</button>
                    </form>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>الصنف</th><th>المحمّل</th><th>المستلم</th><th>الفرق</th></tr></thead>
                            <tbody>
                                @foreach($trip->items as $tripItem)
                                    <tr>
                                        <td>{{ $tripItem->invoiceItem?->displayDescription() }}</td>
                                        <td>{{ number_format((float)$tripItem->loaded_quantity, 3) }}</td>
                                        <td>{{ $tripItem->received_quantity === null ? '—' : number_format((float)$tripItem->received_quantity, 3) }}</td>
                                        <td>{{ $tripItem->received_quantity === null ? '—' : number_format((float)$tripItem->loaded_quantity - (float)$tripItem->received_quantity, 3) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($trip->receiving_notes)<p class="form-help">ملاحظات الاستلام: {{ $trip->receiving_notes }}</p>@endif
                @endif
            </section>
        @empty
            <p class="form-help">لا توجد نقلات مسجلة حتى الآن. تبدأ العملية بتسجيل النقلة الأولى من مسؤول النقلات.</p>
        @endforelse
    </div>
</div>
@if($canCloseShort && ! $complete && ! $progress['complete'] && $progress['received'] > 0.0005 && $progress['pending'] < 0.0005)
<div class="card" style="margin-bottom:1rem;border:1px solid #f5baba">
    <div class="card-header"><span class="card-title">إغلاق الطلبية مع وجود نقص</span></div>
    <div class="card-body">
        <p class="form-help">يبقى الفرق غير مستلم، وتصدر فاتورة بالكميات المستلمة فعليًا فقط. لا يمكن إضافة نقلات بعد الإغلاق. تأكد من مراجعة جميع النقلات أولًا.</p>
        <form method="POST" action="{{ route('branch-shipments.close-short', $shipment) }}">
            @csrf
            <label class="form-label" for="dispatcherClosureReason">سبب إغلاق الطلبية قبل اكتمال الكميات *</label>
            <textarea name="closure_reason" id="dispatcherClosureReason" class="form-textarea" rows="3" minlength="10" maxlength="2000" required placeholder="مثال: عدم توفر الصنف في المصنع وتعذر إرسال باقي الكمية...">{{ old('closure_reason') }}</textarea>
            @error('closure_reason')<small class="form-error">{{ $message }}</small>@enderror
            <div style="margin-top:.65rem"><button class="btn btn-danger" type="submit" data-confirm="سيتم إغلاق الطلبية نهائيًا بالنقص مع حفظ السبب وإصدار فاتورة المستلم فقط، هل أنت متأكد؟">تأكيد الإغلاق مع نقص</button></div>
        </form>
    </div>
</div>
@endif

@if($closedShort)
<div class="alert alert-warning" style="margin-bottom:1rem">
    <strong>أُغلقت الطلبية مع نقص</strong> — {{ $shipment->closure_reason }}
    <div>أغلقها: {{ $shipment->closedBy?->display_name ?? '—' }} · {{ $shipment->closed_at?->format('Y-m-d H:i') ?? '—' }}</div>
    <div>المتبقي غير المسلم: {{ number_format($progress['remaining'], 3) }}</div>
</div>
@elseif($complete)
<div class="alert alert-success">
    اكتملت الكميات المطلوبة لكل صنف. فاتورة الاستلام النهائية {{ $invoice->invoice_number }} معتمدة.
    <a class="btn btn-outline btn-sm" href="{{ route('branch-receiving-invoices.show', $invoice) }}">فتح الفاتورة</a>
</div>
@endif
