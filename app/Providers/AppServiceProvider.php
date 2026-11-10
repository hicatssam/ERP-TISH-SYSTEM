<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Models\User;
use App\Models\ReportSchedule;
use App\Policies\ReportSchedulePolicy;
use App\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use App\Services\Restaurant\RestaurantContextService;
use App\Services\Notifications\WhatsAppGateway;
use App\Services\Notifications\MetaWhatsAppGateway;
use App\Support\ArabicDisplay;
use App\Support\ArabicDate;
use Illuminate\Support\Facades\Blade;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AppServiceProvider extends ServiceProvider
{
       public function register(): void
{
    // Dedicated queue connection: the AI inference job can legitimately run
    // longer than a normal notification without being picked twice.
    if (config('menu_cutouts.enabled', false)) {
        $databaseQueue = (array) config('queue.connections.database', []);
        config([
            'queue.connections.menu_cutouts' => array_replace([
                'driver' => 'database',
                'connection' => null,
                'table' => 'jobs',
            ], $databaseQueue, [
                'driver' => 'database',
                'queue' => 'menu-cutouts',
                'retry_after' => max(600, (int) config('menu_cutouts.timeout', 360) + 120),
                'after_commit' => true,
            ]),
        ]);
    }

    $this->app->singleton(RestaurantContextService::class, function ($app) {
        return new RestaurantContextService();
    });

    $this->app->singleton(
    WhatsAppGateway::class,
    MetaWhatsAppGateway::class
);
        // Admin gate — bypasses all permission checks
        Gate::before(function (User $user, string $ability) {
            if (\App\Support\ActivityModules::scoped()
                && ($code = \App\Support\ActivityModules::moduleFor($ability))
                && ! app(\App\Services\ModuleService::class)->isEnabled($code)) {
                return false;
            }
            if ($user->isAdmin()) {
                return true;
            }
        });

}

    public function boot(): void
    {

 
    

        /*
         * One date/time policy for the whole ERP.
         * The timezone comes from System Settings and Carbon's human strings
         * use Arabic everywhere (controllers, notifications and Blade).
         */
        $systemTimezone = ArabicDate::timezone();

        config([
            'app.timezone' => $systemTimezone,
        ]);

        date_default_timezone_set(
            $systemTimezone
        );

        Carbon::setLocale('ar');
        CarbonImmutable::setLocale('ar');

        Schema::defaultStringLength(191);

        // Process only new/replaced product images. A dedicated worker keeps
        // rembg CPU work outside the HTTP request and preserves originals.
        if (config('menu_cutouts.enabled', false)) {
            foreach ([
                \App\Models\Product::class,
                \App\Models\RestaurantMenuItem::class,
                \App\Models\ProductVariant::class,
            ] as $imageModel) {
                $imageModel::saved(static function ($model): void {
                    if (! $model->wasChanged('image') || ! filled($model->image)) {
                        return;
                    }

                    \App\Jobs\GenerateMenuCutout::dispatch((string) $model->image)
                        ->onConnection((string) config('menu_cutouts.queue_connection', 'database'))
                        ->onQueue('menu-cutouts')
                        ->afterCommit();
                });
            }
        }

        Gate::policy(ReportSchedule::class, ReportSchedulePolicy::class);
        Paginator::defaultView('pagination.custom');

        Blade::directive('statusArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::status({$expression})); ?>"
        );
        Blade::directive('actionArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::action({$expression})); ?>"
        );
        Blade::directive('currencyArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::currency({$expression})); ?>"
        );
        Blade::directive('roleArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDisplay::role({$expression})); ?>"
        );

        Blade::directive('dateArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDate::date({$expression})); ?>"
        );
        Blade::directive('dateTimeArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDate::compactDateTime({$expression})); ?>"
        );
        Blade::directive('timeArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDate::time({$expression})); ?>"
        );
        Blade::directive('dateTimeFullArabic', static fn (string $expression): string =>
            "<?php echo e(\\App\\Support\\ArabicDate::dateTime({$expression}, false)); ?>"
        );

        

    }


}
