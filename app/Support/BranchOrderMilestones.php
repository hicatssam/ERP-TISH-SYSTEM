<?php

namespace App\Support;

use App\Models\BranchShipment;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\SpecialCakeOrder;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Three auditable operational milestones shared by special cake, showroom
 * cake, and showroom sweets requests. Does not rewrite historical DB statuses.
 */
final class BranchOrderMilestones
{
    private static function workflowStatus(Model $source): string
    {
        $value = $source->getAttribute('status');
        $raw = $value instanceof BackedEnum ? (string) $value->value : (string) $value;

        return match (true) {
            $source instanceof SpecialCakeOrder =>
                SpecialCakeOrder::normalizeLegacyWorkflowStatus($raw),
            $source instanceof ShowroomCakeRequest,
            $source instanceof ShowroomSweetsRequest =>
                $source->status->workflowValue(),
            default => $raw,
        };
    }

    public static function snapshot(Model $source): array
    {
        $source->loadMissing([
            'branchShipment.loadedBy.employee',
            'branchShipment.receivedBy.employee',
            'branchShipment.receivingInvoice',
        ]);

        /** @var BranchShipment|null $shipment */
        $shipment = $source->branchShipment;
        $invoice = $shipment?->receivingInvoice;
        $confirmed = (bool) $invoice?->isConfirmed();

        $loaded = $shipment !== null && (
            $shipment->loaded_at !== null
            || in_array($shipment->status, [
                BranchShipment::LOADED,
                BranchShipment::OUT_FOR_DELIVERY,
                BranchShipment::ARRIVED,
                BranchShipment::RECEIVED,
            ], true)
        );

        $workflowStatus = self::workflowStatus($source);
        $cancelled = $workflowStatus === 'cancelled';
        $executed = ! $cancelled && (
            in_array($workflowStatus, ['ready', 'completed'], true)
            || $shipment !== null
        );

        return [
            'cancelled' => $cancelled,
            'executed' => $executed,
            'loaded' => $loaded,
            'received' => $confirmed,
            'shipment' => $shipment,
            'invoice' => $invoice,
            'executed_at' => $shipment?->created_at
                ?? ($workflowStatus === 'ready' ? $source->updated_at : null),
        ];
    }

    public static function label(Model $source): string
    {
        $data = self::snapshot($source);

        if ($data['cancelled']) {
            return 'ملغى';
        }
        if ($data['received']) {
            return 'تم الاستلام';
        }
        if ($data['loaded']) {
            return 'تم التحميل';
        }
        if ($data['executed']) {
            return 'تم التنفيذ';
        }

        return 'بانتظار التنفيذ';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function steps(Model $source): array
    {
        $data = self::snapshot($source);
        $shipment = $data['shipment'];
        $invoice = $data['invoice'];

        $done = [
            'executed' => $data['executed'],
            'loaded' => $data['loaded'],
            'received' => $data['received'],
        ];

        $active = $data['received']
            ? 'received'
            : ($data['loaded'] ? 'loaded' : ($data['executed'] ? 'executed' : null));

        $description = $invoice?->isConfirmed()
            ? 'فاتورة ' . $invoice->invoice_number
                . ' | المرسل: ' . (float) $invoice->total_items_sent
                . ' | المستلم: ' . (float) $invoice->total_items_received
                . ' | الفرق: ' . (float) $invoice->total_difference
            : null;

        return collect([
            [
                'status' => 'executed',
                'label' => 'تم التنفيذ',
                'icon' => 'factory',
                'role' => 'المصنع / الإنتاج',
                'actor' => $source->handledBy?->display_name ?? null,
                'at' => $data['executed_at'],
                'note' => null,
            ],
            [
                'status' => 'loaded',
                'label' => 'تم التحميل',
                'icon' => 'truck',
                'role' => 'مسؤول تحميل المصنع',
                'actor' => $shipment?->loadedBy?->display_name,
                'at' => $shipment?->loaded_at,
                'note' => $shipment?->notes,
            ],
            [
                'status' => 'received',
                'label' => 'تم الاستلام',
                'icon' => 'store-check',
                'role' => 'الفرع المستلم / فاتورة استلام',
                'actor' => $shipment?->receivedBy?->display_name,
                'at' => $shipment?->received_at,
                'note' => $description,
            ],
        ])->map(function (array $step) use ($done, $active): array {
            $key = $step['status'];
            $step['state'] = ! $done[$key]
                ? 'pending'
                : ($active === $key ? 'current' : 'done');
            $step['kind'] = 'normal';
            $step['is_delivery'] = false;

            return $step;
        })->all();
    }
}
