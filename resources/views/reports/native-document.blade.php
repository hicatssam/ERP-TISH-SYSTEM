@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')
@section('paper_orientation', 'landscape')
@section('document_title', $title)
@section('document_date', \App\Support\ArabicDate::dateTime(now(), false))
@section('document_subtitle', 'تقرير حسب الفلاتر المختارة')
@section('print_content')
@if($truncated)
    <p class="print-soft-box">تنبيه: بعض الأقسام تعرض أول {{ $cap }} سجل فقط. إجمالي الصفوف قبل الاختصار: {{ $total }}. قلّل نطاق الفلاتر أو استخدم الطباعة الكاملة.</p>
@endif
@foreach($sections as $section)
<div class="print-section">
    <h2>{{ $section['title'] }}</h2>
    <table class="print-table">
        <thead><tr>@foreach($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($section['rows'] as $row)
            <tr>@foreach($row as $value)<td>{{ $value instanceof \BackedEnum ? $value->value : $value }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($section['columns']) }}">لا توجد بيانات ضمن الفلاتر المختارة.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endforeach
@endsection
