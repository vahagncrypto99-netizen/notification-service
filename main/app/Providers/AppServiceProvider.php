<?php

declare(strict_types=1);

namespace App\Providers;

use App\Base\Notification\Channels\ChannelSenderResolver;
use App\Base\Notification\Reports\ReportFormatterResolver;
use App\Base\Notification\Services\ReportFileStorage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register() : void
    {
        $this->app->singleton(ChannelSenderResolver::class);
        $this->app->singleton(ReportFormatterResolver::class);

        $this->app->bind(ReportFileStorage::class, function (Application $app) {
            return new ReportFileStorage(
                $app->make(ReportFormatterResolver::class),
                Storage::disk((string) config('notification.reports.disk'))
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot() : void
    {
        //
    }
}
