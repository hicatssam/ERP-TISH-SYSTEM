<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PosCustomerScreenController extends Controller
{
    public function __construct(private readonly RestaurantContextService $context) {}

    private function location(Request $request)
    {
        return $this->context->resolveLocation($request->user(), $request->integer('location_id') ?: null);
    }

    private function key(int $locationId): string
    {
        return 'pos_customer_screen_v1_'.$locationId;
    }

    private function config(int $locationId): array
    {
        $data = json_decode((string) SystemSetting::get($this->key($locationId), '{}'), true);
        $data = is_array($data) ? $data : [];
        return [
            'media' => array_values(array_filter(is_array($data['media'] ?? null) ? $data['media'] : [], fn ($m) =>
                is_array($m) && isset($m['id'], $m['path'], $m['type']) &&
                str_starts_with($m['path'], 'pos-display/'.$locationId.'/') &&
                in_array($m['type'], ['image', 'video'], true))),
            'image_seconds' => max(3, min(60, (int) ($data['image_seconds'] ?? 8))),
            'thanks_seconds' => max(4, min(30, (int) ($data['thanks_seconds'] ?? 12))),
            'ad_percent' => max(30, min(70, (int) ($data['ad_percent'] ?? 60))),
        ];
    }

    private function publicConfig(int $locationId): array
    {
        $config = $this->config($locationId);
        $config['media'] = array_map(fn ($media) => [
            'id' => $media['id'], 'type' => $media['type'],
            'url' => Storage::disk('public')->url($media['path']),
        ], $config['media']);
        return $config;
    }

    public function show(Request $request)
    {
        $location = $this->location($request);
        return response()->view('restaurant.pos.customer-screen', [
            'location' => $location,
            'scope' => $request->user()->id.':'.$location->id,
            'config' => $this->publicConfig((int) $location->id),
            'brand' => SystemSetting::get('system_name_en', 'TISH') ?: 'TISH',
        ])->header('Cache-Control', 'no-store');
    }

    public function playlist(Request $request)
    {
        return response()->json($this->publicConfig((int) $this->location($request)->id))
            ->header('Cache-Control', 'no-store');
    }

    public function settings(Request $request)
    {
        $location = $this->location($request);
        return view('restaurant.pos.customer-screen-settings', [
            'location' => $location,
            'config' => $this->publicConfig((int) $location->id),
        ]);
    }

    public function update(Request $request)
    {
        $location = $this->location($request);
        $id = (int) $location->id;
        $data = $request->validate([
            'action' => ['required', 'in:save,add,remove,up,down'],
            'media_id' => ['nullable', 'uuid'],
            'media' => ['required_if:action,add', 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4', 'max:51200'],
            'image_seconds' => ['required_if:action,save', 'nullable', 'integer', 'between:3,60'],
            'thanks_seconds' => ['required_if:action,save', 'nullable', 'integer', 'between:4,30'],
            'ad_percent' => ['required_if:action,save', 'nullable', 'integer', 'between:30,70'],
        ]);

        Cache::lock('pos-customer-screen-config-'.$id, 60)->block(5, function () use ($id, $request, $data) {
            $config = $this->config($id);
            $removePath = null;
            $newPath = null;
            switch ($data['action']) {
                case 'save':
                    foreach (['image_seconds', 'thanks_seconds', 'ad_percent'] as $key) {
                        $config[$key] = (int) $data[$key];
                    }
                    break;
                case 'add':
                    if (count($config['media']) >= 10) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['media' => 'الحد الأقصى 10 إعلانات لكل فرع.']);
                    }
                    $file = $request->file('media');
                    $type = $file->getMimeType() === 'video/mp4' ? 'video' : 'image';
                    $newPath = $file->store('pos-display/'.$id, 'public');
                    if (! $newPath) throw \Illuminate\Validation\ValidationException::withMessages(['media' => 'تعذر حفظ الملف.']);
                    $config['media'][] = ['id' => (string) Str::uuid(), 'path' => $newPath, 'type' => $type];
                    break;
                default:
                    $index = array_search($data['media_id'] ?? '', array_column($config['media'], 'id'), true);
                    if ($index === false) abort(404);
                    if ($data['action'] === 'remove') {
                        $removePath = $config['media'][$index]['path'];
                        array_splice($config['media'], $index, 1);
                    } else {
                        $other = $index + ($data['action'] === 'up' ? -1 : 1);
                        if (isset($config['media'][$other])) {
                            [$config['media'][$index], $config['media'][$other]] = [$config['media'][$other], $config['media'][$index]];
                        }
                    }
            }
            try {
                SystemSetting::set($this->key($id), json_encode($config, JSON_THROW_ON_ERROR));
            } catch (\Throwable $error) {
                if ($newPath) Storage::disk('public')->delete($newPath);
                throw $error;
            }
            if ($removePath) Storage::disk('public')->delete($removePath);
        });

        return redirect()->route('restaurant.pos.customer-screen.settings', ['location_id' => $id])
            ->with('success', 'تم تحديث إعلانات شاشة الزبون.');
    }
}
