<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\BranchReceivingInvoice;
use App\Models\BranchShipment;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Services\Delivery\BranchDeliveryService;
use App\Services\PrintThemeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mpdf\Mpdf;

class BranchShipmentController extends Controller
{
    public function __construct(
        private readonly BranchDeliveryService $delivery,
    ) {
    }

    public function create(
        Request $request,
        string $sourceType,
        int $sourceId
    ): RedirectResponse {
        $source = $this->delivery->resolveSource($sourceType, $sourceId);

        abort_unless(
            $this->canFactoryOperate($request->user(), $source),
            403,
            'هذه العملية متاحة للمصنع المرتبط بالطلب فقط.'
        );

        $shipment = $this->delivery->createShipment(
            $source,
            $request->user()
        );

        return redirect()
            ->route('branch-shipments.show', $shipment)
            ->with('success', 'تم فتح شحنة الطلبية وتجهيز مسودة فاتورة الاستلام.');
    }

    public function show(
        Request $request,
        BranchShipment $branchShipment
    ): View {
        $this->assertShipmentAccess(
            $request->user(),
            $branchShipment
        );

        $branchShipment->load([
            'source',
            'fromLocation',
            'toLocation',
            'driver.employee',
            'loadedBy.employee',
            'dispatchedBy.employee',
            'arrivedBy.employee',
            'receivedBy.employee',
            'receivingInvoice.items',
        ]);

        $drivers = User::query()
            ->where('is_active', true)
            ->with('employee')
            ->orderBy('username')
            ->get();

        return view('sales.branch-shipments.show', [
            'shipment' => $branchShipment,
            'invoice' => $branchShipment->receivingInvoice,
            'sourceNumber' => $this->delivery->sourceNumber($branchShipment->source),
            'sourceLabel' => $this->delivery->sourceLabel($branchShipment->source),
            'sourceUrl' => $this->delivery->sourceRoute($branchShipment->source),
            'drivers' => $drivers,
            'canFactoryOperate' => $this->canFactoryOperate(
                $request->user(),
                $branchShipment->source
            ),
            'canDriverOperate' => $this->canDriverOperate(
                $request->user(),
                $branchShipment
            ),
            'canReceive' => $this->canReceive(
                $request->user(),
                $branchShipment
            ),
        ]);
    }

    public function markLoaded(
        Request $request,
        BranchShipment $branchShipment
    ): RedirectResponse {
        $branchShipment->loadMissing('source');

        abort_unless(
            $this->canFactoryOperate($request->user(), $branchShipment->source),
            403
        );

        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $driver = User::query()
            ->where('is_active', true)
            ->findOrFail((int) $data['driver_id']);

        $this->delivery->markLoaded(
            $branchShipment,
            $request->user(),
            $driver,
            $data['notes'] ?? null
        );

        return back()->with('success', 'تم تأكيد تحميل الطلبية وتحديد الموصّل.');
    }

    public function dispatch(
        Request $request,
        BranchShipment $branchShipment
    ): RedirectResponse {
        $branchShipment->loadMissing('source');

        abort_unless(
            $this->canFactoryOperate($request->user(), $branchShipment->source)
            || $this->canDriverOperate($request->user(), $branchShipment),
            403
        );

        $this->delivery->dispatch(
            $branchShipment,
            $request->user()
        );

        return back()->with('success', 'تم تسجيل خروج الطلبية للتوصيل.');
    }

    public function arrive(
        Request $request,
        BranchShipment $branchShipment
    ): RedirectResponse {
        abort_unless(
            $this->canDriverOperate($request->user(), $branchShipment),
            403,
            'تسجيل الوصول متاح للموصّل المعيّن أو الأدمن.'
        );

        $this->delivery->markArrived(
            $branchShipment,
            $request->user()
        );

        return back()->with('success', 'تم تسجيل وصول الطلبية إلى الفرع.');
    }

    public function receive(
        Request $request,
        BranchShipment $branchShipment
    ): RedirectResponse {
        abort_unless(
            $this->canReceive($request->user(), $branchShipment),
            403,
            'تأكيد الاستلام متاح للفرع المستلم فقط.'
        );

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = $this->delivery->receive(
            $branchShipment,
            $data['items'],
            $request->user(),
            $data['notes'] ?? null
        );

        return redirect()
            ->route('branch-receiving-invoices.show', $invoice)
            ->with('success', 'تم اعتماد استلام الطلبية وإصدار فاتورة الاستلام.');
    }

    public function invoiceShow(
        Request $request,
        BranchReceivingInvoice $branchReceivingInvoice
    ): View {
        $branchReceivingInvoice->load([
            'shipment.source',
            'shipment.driver.employee',
            'sendingLocation',
            'receivingLocation',
            'driver.employee',
            'receivedBy.employee',
            'items',
        ]);

        $this->assertShipmentAccess(
            $request->user(),
            $branchReceivingInvoice->shipment
        );

        return view('sales.branch-receiving-invoices.show', [
            'invoice' => $branchReceivingInvoice,
            'sourceNumber' => $this->delivery->sourceNumber($branchReceivingInvoice->source),
            'sourceLabel' => $this->delivery->sourceLabel($branchReceivingInvoice->source),
            'sourceUrl' => $this->delivery->sourceRoute($branchReceivingInvoice->source),
        ]);
    }

    public function invoicePrint(
        Request $request,
        BranchReceivingInvoice $branchReceivingInvoice
    ): View {
        $this->loadInvoiceForOutput($branchReceivingInvoice);
        $this->assertShipmentAccess(
            $request->user(),
            $branchReceivingInvoice->shipment
        );

        return view('sales.branch-receiving-invoices.print', [
            'invoice' => $branchReceivingInvoice,
            'sourceNumber' => $this->delivery->sourceNumber($branchReceivingInvoice->source),
            'sourceLabel' => $this->delivery->sourceLabel($branchReceivingInvoice->source),
            'pdfMode' => false,
        ]);
    }

    public function invoicePdf(
        Request $request,
        BranchReceivingInvoice $branchReceivingInvoice
    ) {
        $this->loadInvoiceForOutput($branchReceivingInvoice);
        $this->assertShipmentAccess(
            $request->user(),
            $branchReceivingInvoice->shipment
        );

        $html = view('sales.branch-receiving-invoices.print', [
            'invoice' => $branchReceivingInvoice,
            'sourceNumber' => $this->delivery->sourceNumber($branchReceivingInvoice->source),
            'sourceLabel' => $this->delivery->sourceLabel($branchReceivingInvoice->source),
            'pdfMode' => true,
        ])->render();

        $printService = app(PrintThemeService::class);
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $printService->pdfFormat($printService->settings()),
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
            'margin_right' => 0,
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle('فاتورة استلام ' . $branchReceivingInvoice->invoice_number);
        $mpdf->WriteHTML($html);

        $filename = $branchReceivingInvoice->invoice_number . '.pdf';

        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function loadInvoiceForOutput(
        BranchReceivingInvoice $invoice
    ): void {
        $invoice->load([
            'shipment.source',
            'shipment.driver.employee',
            'source',
            'sendingLocation',
            'receivingLocation',
            'driver.employee',
            'receivedBy.employee',
            'items',
        ]);
    }

    private function assertShipmentAccess(
        User $user,
        BranchShipment $shipment
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        if ((int) $shipment->driver_id === (int) $user->id) {
            return;
        }

        $locations = $this->userLocationIds($user);

        abort_unless(
            in_array((int) $shipment->from_location_id, $locations, true)
            || in_array((int) $shipment->to_location_id, $locations, true),
            403,
            'لا تملك صلاحية الوصول إلى هذه الشحنة.'
        );
    }

    private function canFactoryOperate(
        User $user,
        Model $source
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        $factoryId = match (true) {
            $source instanceof SpecialCakeOrder => (int) $source->factory_location_id,
            $source instanceof ShowroomCakeRequest,
            $source instanceof ShowroomSweetsRequest => (int) $source->factory_location_id,
            default => 0,
        };

        if (! in_array($factoryId, $this->userLocationIds($user), true)) {
            return false;
        }

        return match (true) {
            $source instanceof SpecialCakeOrder =>
                $user->can('cake_orders.dispatch'),

            $source instanceof ShowroomCakeRequest =>
                $user->can('showroom_cake_requests.update_status'),

            $source instanceof ShowroomSweetsRequest =>
                $user->can('showroom_sweets_requests.dispatch')
                || $user->can('showroom_sweets_requests.update_status'),

            default => false,
        };
    }

    private function canDriverOperate(
        User $user,
        BranchShipment $shipment
    ): bool {
        return $user->isAdmin()
            || (
                $shipment->driver_id
                && (int) $shipment->driver_id === (int) $user->id
            );
    }

    private function canReceive(
        User $user,
        BranchShipment $shipment
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        if (! in_array(
            (int) $shipment->to_location_id,
            $this->userLocationIds($user),
            true
        )) {
            return false;
        }

        $shipment->loadMissing('source');
        $source = $shipment->source;

        return match (true) {
            $source instanceof SpecialCakeOrder =>
                $user->can('cake_orders.receive'),

            $source instanceof ShowroomCakeRequest =>
                $user->can('showroom_cake_requests.create')
                || $user->can('showroom_cake_requests.update_status'),

            $source instanceof ShowroomSweetsRequest =>
                $user->can('showroom_sweets_requests.receive')
                || $user->can('showroom_sweets_requests.update_status'),

            default => false,
        };
    }

    /**
     * @return array<int,int>
     */
    private function userLocationIds(User $user): array
    {
        return $user->employee
            ?->locations()
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];
    }
}
