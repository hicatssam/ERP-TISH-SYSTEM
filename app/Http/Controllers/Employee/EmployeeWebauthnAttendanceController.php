<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeWebauthnAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeWebauthnAttendanceController extends Controller
{
    public function __construct(private readonly EmployeeWebauthnAttendanceService $service) {}

    public function registrationOptions(Request $request): JsonResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:100']]);

        return response()->json($this->service->options($request, 'register', $data['label']));
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $this->service->register($request, $data);

        return response()->json(['ok' => true, 'message' => 'تم تسجيل المفتاح. اطلب من مسؤول الحضور اعتماده.']);
    }

    public function punchOptions(Request $request): JsonResponse
    {
        return response()->json($this->service->options($request, 'punch'));
    }

    public function punch(Request $request): JsonResponse
    {
        return response()->json($this->service->punch($request, $request->validate($this->rules(false))));
    }

    private function rules(bool $registration): array
    {
        return [
            'token' => ['required', 'uuid'], 'id' => ['required', 'string', 'max:2048'],
            'type' => ['required', 'in:public-key'], 'response' => ['required', 'array'],
            'response.clientDataJSON' => ['required', 'string', 'max:12000'],
            ...($registration ? ['response.attestationObject' => ['required', 'string', 'max:64000']] : [
                'response.authenticatorData' => ['required', 'string', 'max:16000'],
                'response.signature' => ['required', 'string', 'max:4096'],
                'response.userHandle' => ['nullable', 'string', 'max:128'],
            ]),
        ];
    }
}
