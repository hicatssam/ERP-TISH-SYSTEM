<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use App\Support\ActivityModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivityModules
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ActivityModules::scoped()) {
            $modules = app(ModuleService::class);
            $code = ActivityModules::moduleFor((string) $request->route()?->getName());
            if ($code) {
                $modules->require($code);
            }
            if (str_starts_with((string) $request->route()?->getName(), 'reports.')) {
                $type = $request->route('type') ?? $request->route('report');
                if (is_string($type) && ($reportModule = ActivityModules::reportModule($type))) {
                    $modules->require($reportModule);
                }
            }
        }
        return $next($request);
    }
}
