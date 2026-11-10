@push('print_styles')
<style>
@media screen {
 html,body {background:#f3eee6 url("{{ asset('assets/tish-paper/paper/background.webp') }}") center/540px auto repeat!important}
 .print-paper {background:#f3eee6 url("{{ asset('assets/tish-paper/paper/background.webp') }}") center/540px auto repeat!important;box-shadow:0 12px 28px #33211530!important;border:0!important}
 .print-document-title {color:#a91f17!important;font-weight:900!important}
 .print-document-subtitle {color:#62584f!important}
 .invoice-party-box {background:#faf0e3!important}
 .print-screen-toolbar {background:#252525!important}
 .print-screen-btn {border-radius:0!important}
 .invoice-status-paid {background:#277936!important;color:white!important}
 .invoice-items th {color:#a91f17!important}
}
</style>
@endpush

{{-- Legacy view name retained for integrations; the document has one design. --}}
@include('finance.invoices.print')
