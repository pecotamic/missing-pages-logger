<?php

namespace Pecotamic\MissingPagesLogger;

use Pecotamic\MissingPagesLogger\Http\Middleware\MissingPagesLogger;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Statamic::booted(function () {
            app('router')->prependMiddlewareToGroup('statamic.web', MissingPagesLogger::class);
            app('router')->prependMiddlewareToGroup('web', MissingPagesLogger::class);
        });
    }

    protected function bootConfig(): self
    {
        $this->mergeConfigFrom(__DIR__.'/../config/missing-pages-logger.php', 'pecotamic.missing-pages-logger');

        $this->publishes([
            __DIR__.'/../config/missing-pages-logger.php' => config_path('pecotamic/missing-pages-logger.php'),
        ], 'config');

        return $this;
    }
}
