<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ShowroomSweetsRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Services\PrintThemeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowroomSweetsRequestsReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $canViewAll = $this->canViewAll($request->user());

        $query = $this->reportQuery(
            $request->user(),
            $filters
        );

        $summary = $this->summary(
            clone $query
        );

        $requests = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view(
            'reports.showroom-sweets-requests.index',
            [
                'requests' => $requests,
                'locations' => $this->locations(
                    $request->user(),
                    $canViewAll
                ),
                'statusEnum' =>
                    ShowroomSweetsRequestStatus::workflowCases(),
                'products' => Product::query()
                    ->active()
                    ->orderByRaw("COALESCE(NULLIF(name_ar, ''), name)")
                    ->get([
                        'id',
                        'name',
                        'name_ar',
                        'sku',
                    ]),
                'filters' => $filters,
                'summary' => $summary,
                'productTotals' => $this->productTotals(
                    (clone $query)->get()
                ),
                'canViewAll' => $canViewAll,
            ]
        );
    }

    public function print(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $requests = $this->reportQuery(
            $request->user(),
            $filters
        )
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return view(
            'reports.showroom-sweets-requests.document',
            [
                'requests' => $requests,
                'summary' => $this->summaryFromCollection(
                    $requests
                ),
                'productTotals' => $this->productTotals(
                    $requests,
                    $filters
                ),
                'filters' => $filters,
                'selectedProduct' => filled($filters['product_id'] ?? null)
                    ? Product::find((int) $filters['product_id'])
                    : null,
                'pdfMode' => false,
            ]
        );
    }

    public function pdf(
        Request $request
    ): StreamedResponse {
        $filters = $this->validatedFilters($request);

        $requests = $this->reportQuery(
            $request->user(),
            $filters
        )
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        $filename = 'showroom-sweets-requests-'
            . now()->format('Ymd-His')
            . '.pdf';

        $summary = $this->summaryFromCollection(
            $requests
        );

        $html = view(
            'reports.showroom-sweets-requests.pdf',
            [
                'requests' => $requests,
                'summary' => $summary,
                'productTotals' => $this->productTotals(
                    $requests,
                    $filters
                ),
                'filters' => $filters,
                'selectedProduct' => filled($filters['product_id'] ?? null)
                    ? Product::find((int) $filters['product_id'])
                    : null,
                'branding' => $this->pdfBranding(),
            ]
        )->render();

        $tempDir = storage_path(
            'app/mpdf/showroom-sweets-requests'
        );

        File::ensureDirectoryExists($tempDir);

        $defaultConfig = (
            new ConfigVariables()
        )->getDefaults();

        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (
            new FontVariables()
        )->getDefaults();

        $fontData = $defaultFontConfig['fontdata'];

        $cairoFontDir = public_path(
            'assets/fonts'
        );

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
            'directionality' => 'rtl',
            'fontDir' => array_merge(
                $fontDirs,
                [$cairoFontDir]
            ),
            'fontdata' => $fontData + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'cairo',
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetTitle(
            'تقرير طلبات حلويات الفروع'
        );

        // Keep report tables inside one landscape page width.
        $mpdf->simpleTables = true;
        $mpdf->shrink_tables_to_fit = 1;

        $mpdf->SetDisplayMode('fullwidth');
        $mpdf->WriteHTML($html);

        $pdfBinary = $mpdf->Output(
            '',
            \Mpdf\Output\Destination::STRING_RETURN
        );

        if (
            ! str_starts_with($pdfBinary, '%PDF')
            || strlen($pdfBinary) < 1000
        ) {
            throw new \RuntimeException(
                'تعذر إنشاء ملف PDF لتقرير طلبات حلويات الفروع.'
            );
        }

        return response()->streamDownload(
            static function () use ($pdfBinary): void {
                echo $pdfBinary;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' =>
                    (string) strlen($pdfBinary),
                'Cache-Control' =>
                    'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function validatedFilters(
        Request $request
    ): array {
        $validated = validator(
            $request->only([
                'location_id',
                'product_id',
                'product_sort',
                'status',
                'date_from',
                'date_to',
                'request_number',
            ]),
            [
                'location_id' => [
                    'nullable',
                    'integer',
                    'exists:locations,id',
                ],
                'product_id' => [
                    'nullable',
                    'integer',
                    'exists:products,id',
                ],
                'product_sort' => [
                    'nullable',
                    'string',
                    'in:created_asc,created_desc,name_asc,quantity_desc,quantity_asc',
                ],
                'status' => [
                    'nullable',
                    'string',
                ],
                'date_from' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'date_to' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:date_from',
                ],
                'request_number' => [
                    'nullable',
                    'string',
                    'max:60',
                ],
            ]
        )->validate();

        if (
            filled($validated['status'] ?? null)
            && ! collect(
                ShowroomSweetsRequestStatus::workflowCases()
            )->contains(
                fn (ShowroomSweetsRequestStatus $status) =>
                    $status->value === $validated['status']
            )
        ) {
            abort(422, 'حالة الطلب المحددة غير صالحة.');
        }

        return $validated;
    }

    private function reportQuery(
        User $user,
        array $filters
    ): Builder {
        $productId = filled(
            $filters['product_id'] ?? null
        )
            ? (int) $filters['product_id']
            : null;

        $query = ShowroomSweetsRequest::query()
            ->with([
                'requestingLocation:id,name',
                'factoryLocation:id,name',
                'creator.employee',
                'handledBy.employee',
                'receivedBy.employee',
                'items' => function ($items) use ($productId): void {
                    if ($productId) {
                        $items->where(
                            'product_id',
                            $productId
                        );
                    }

                    $items->with('product');
                },
            ]);

        if ($productId) {
            $query
                ->whereHas(
                    'items',
                    fn ($items) => $items->where(
                        'product_id',
                        $productId
                    )
                )
                ->withCount([
                    'items as items_count' =>
                        fn ($items) => $items->where(
                            'product_id',
                            $productId
                        ),
                ])
                ->withSum([
                    'items as requested_quantity_total' =>
                        fn ($items) => $items->where(
                            'product_id',
                            $productId
                        ),
                ], 'quantity')
                ->withSum([
                    'items as reserved_quantity_total' =>
                        fn ($items) => $items->where(
                            'product_id',
                            $productId
                        ),
                ], 'reserved_quantity');
        } else {
            $query
                ->withCount('items')
                ->withSum(
                    'items as requested_quantity_total',
                    'quantity'
                )
                ->withSum(
                    'items as reserved_quantity_total',
                    'reserved_quantity'
                );
        }

        $canViewAll = $this->canViewAll($user);

        if (! $canViewAll) {
            $locationIds = $this->userLocationIds($user);

            if (empty($locationIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn(
                    'requesting_location_id',
                    $locationIds
                );
            }
        }

        if (filled($filters['location_id'] ?? null)) {
            $locationId = (int) $filters['location_id'];

            if (! $canViewAll) {
                abort_unless(
                    in_array(
                        $locationId,
                        $this->userLocationIds($user),
                        true
                    ),
                    403,
                    'لا تملك صلاحية عرض تقارير هذا الفرع.'
                );
            }

            $query->where(
                'requesting_location_id',
                $locationId
            );
        }

        if (filled($filters['status'] ?? null)) {
            $workflow = ShowroomSweetsRequestStatus::from(
                $filters['status']
            )->workflowValue();

            $statusValues = collect(
                ShowroomSweetsRequestStatus::cases()
            )
                ->filter(
                    fn (ShowroomSweetsRequestStatus $status) =>
                        $status->workflowValue() === $workflow
                )
                ->map(
                    fn (ShowroomSweetsRequestStatus $status) =>
                        $status->value
                )
                ->values()
                ->all();

            $query->whereIn(
                'status',
                $statusValues
            );
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse(
                    $filters['date_from']
                )->startOfDay()
            );
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where(
                'created_at',
                '<',
                CarbonImmutable::parse(
                    $filters['date_to']
                )->addDay()
                ->startOfDay()
            );
        }

        if (filled($filters['request_number'] ?? null)) {
            $query->where(
                'request_number',
                'like',
                '%'
                . addcslashes(
                    trim($filters['request_number']),
                    '%_\\'
                )
                . '%'
            );
        }

        return $query;
    }

    private function summary(
        Builder $query
    ): array {
        $requests = $query->get();

        return $this->summaryFromCollection(
            $requests
        );
    }

    private function productTotals(
        Collection $requests,
        array $filters = []
    ): Collection {
        $rows = $requests
            ->flatMap(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->map(
                        fn ($item) => [
                            'product_id' =>
                                (int) $item->product_id,
                            'product_name' =>
                                $item->displayProductName(),
                            'unit' =>
                                (string) $item->requested_unit,
                            'quantity' =>
                                (float) $item->quantity,
                            'reserved_quantity' =>
                                (float) $item->reserved_quantity,
                            'request_id' =>
                                (int) $request->id,
                            'branch_id' =>
                                (int) $request->requesting_location_id,
                            'branch_name' =>
                                $request->requestingLocation?->name
                                ?? '—',
                            'product_created_at' =>
                                $item->product?->created_at,
                        ]
                    )
            )
            ->groupBy(
                fn (array $row) =>
                    $row['product_id']
                    . '|'
                    . $row['unit']
            )
            ->map(function (
                Collection $productRows
            ): array {
                $first = $productRows->first();

                $branches = $productRows
                    ->groupBy('branch_id')
                    ->map(function (
                        Collection $branchRows
                    ): array {
                        $branch = $branchRows->first();

                        return [
                            'branch_id' =>
                                $branch['branch_id'],
                            'branch_name' =>
                                $branch['branch_name'],
                            'quantity' =>
                                $branchRows->sum('quantity'),
                            'reserved_quantity' =>
                                $branchRows->sum(
                                    'reserved_quantity'
                                ),
                            'requests_count' =>
                                $branchRows
                                    ->pluck('request_id')
                                    ->unique()
                                    ->count(),
                        ];
                    })
                    ->sortBy('branch_name')
                    ->values();

                return [
                    'product_id' =>
                        $first['product_id'],
                    'product_name' =>
                        $first['product_name'],
                    'unit' =>
                        $first['unit'],
                    'quantity' =>
                        $productRows->sum('quantity'),
                    'reserved_quantity' =>
                        $productRows->sum(
                            'reserved_quantity'
                        ),
                    'requests_count' =>
                        $productRows
                            ->pluck('request_id')
                            ->unique()
                            ->count(),
                    'branches' => $branches,
                    'product_created_at' =>
                        $productRows
                            ->pluck('product_created_at')
                            ->filter()
                            ->min(),
                ];
            })
            ->values();

        return match (
            $filters['product_sort'] ?? 'created_asc'
        ) {
            'created_desc' =>
                $rows
                    ->sortByDesc('product_created_at')
                    ->values(),

            'name_asc' =>
                $rows
                    ->sortBy(
                        fn (array $row) =>
                            mb_strtolower(
                                $row['product_name']
                            )
                    )
                    ->values(),

            'quantity_desc' =>
                $rows
                    ->sortByDesc('quantity')
                    ->values(),

            'quantity_asc' =>
                $rows
                    ->sortBy('quantity')
                    ->values(),

            default =>
                $rows
                    ->sortBy('product_created_at')
                    ->values(),
        };
    }

    private function summaryFromCollection(
        Collection $requests
    ): array {
        return [
            'requests_count' => $requests->count(),
            'completed_count' => $requests->filter(
                fn (ShowroomSweetsRequest $request) =>
                    $request->status->workflowValue()
                        === 'completed'
            )->count(),
            'cancelled_count' => $requests->filter(
                fn (ShowroomSweetsRequest $request) =>
                    $request->status->workflowValue()
                        === 'cancelled'
            )->count(),
            'items_count' => $requests->sum(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->count()
            ),
            'requested_quantity' => $requests->sum(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->sum(
                        fn ($item) =>
                            (float) $item->quantity
                    )
            ),
            'reserved_quantity' => $requests->sum(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->sum(
                        fn ($item) =>
                            (float) $item->reserved_quantity
                    )
            ),
        ];
    }

    private function canViewAll(
        User $user
    ): bool {
        return $user->isAdmin()
            || $user->can(
                'showroom_sweets_requests.view_all'
            );
    }

    private function locations(
        User $user,
        bool $canViewAll
    ): Collection {
        $query = Location::query()
            ->branches()
            ->active()
            ->orderBy('name');

        if (! $canViewAll) {
            $query->whereIn(
                'id',
                $this->userLocationIds($user)
            );
        }

        return $query->get([
            'id',
            'name',
        ]);
    }

    private function pdfBranding(): array
    {
        $theme = app(
            PrintThemeService::class
        )->settings();

        return [
            'primary_color' =>
                $theme['primary_color'] ?? '#d6a925',
            'secondary_color' =>
                $theme['secondary_color'] ?? '#0b2f4f',
            'text_color' =>
                $theme['text_color'] ?? '#1f2937',
            'business_name' =>
                $theme['business_name'] ?? config('app.name'),
            'business_name_en' =>
                $theme['business_name_en'] ?? '',
            'business_tagline' =>
                $theme['business_tagline'] ?? '',
            'business_phone' =>
                $theme['business_phone'] ?? '',
            'business_email' =>
                $theme['business_email'] ?? '',
            'business_address' =>
                $theme['business_address'] ?? '',
            'footer_text' =>
                $theme['footer_text'] ?? '',
            'show_logo' =>
                (bool) ($theme['show_logo'] ?? true),
            'logo_path' => $this->pdfLogoPath(),
        ];
    }

    /**
     * mPDF is given a local logo path rather than a Base64 data URI.
     * This keeps branding while avoiding the blank-page issue caused
     * by embedding a large image payload in the report HTML.
     */
    private function pdfLogoPath(): ?string
    {
        foreach ([
            SystemSetting::get('print_logo'),
            SystemSetting::get('brand_report_logo'),
            SystemSetting::get('brand_logo'),
        ] as $value) {
            $value = trim((string) $value);

            if (
                $value === ''
                || str_starts_with($value, 'data:image/')
                || preg_match('#^https?://#i', $value)
                || str_starts_with($value, '//')
            ) {
                continue;
            }

            $normalized = ltrim(
                str_replace('\\', '/', $value),
                '/'
            );

            $relative = preg_replace(
                '#^storage/#',
                '',
                $normalized
            );

            if (
                ! str_contains($relative, '..')
                && Storage::disk('public')->exists($relative)
            ) {
                return Storage::disk('public')->path(
                    $relative
                );
            }

            foreach ([
                public_path($normalized),
                public_path('storage/' . $relative),
                storage_path('app/public/' . $relative),
            ] as $candidate) {
                if (
                    is_string($candidate)
                    && is_file($candidate)
                ) {
                    return $candidate;
                }
            }
        }

        $fallback = public_path(
            'assets/images/logo.png'
        );

        return is_file($fallback)
            ? $fallback
            : null;
    }

    private function userLocationIds(
        User $user
    ): array {
        return $user
            ->employee
            ?->locations()
            ->pluck('locations.id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all()
            ?? [];
    }
}
