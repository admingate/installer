<?php

namespace Admingate\Installer\Providers;

use BaseHelper;
use Admingate\Base\Events\FinishedSeederEvent;
use Admingate\Base\Events\UpdatedEvent;
use Admingate\Base\Traits\LoadAndPublishDataTrait;
use Admingate\Installer\Http\Middleware\CheckIfInstalledMiddleware;
use Admingate\Installer\Http\Middleware\CheckIfInstallingMiddleware;
use Admingate\Installer\Http\Middleware\RedirectIfNotInstalledMiddleware;
use Carbon\Carbon;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\ServiceProvider;
use Throwable;

class InstallerServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this->setNamespace('packages/installer')
            ->loadHelpers()
            ->loadAndPublishConfigurations('installer')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->loadRoutes()
            ->publishAssets();

        $this->app['events']->listen(RouteMatched::class, function () {
            if (defined('INSTALLED_SESSION_NAME')) {
                $router = $this->app->make('router');

                $router->middlewareGroup('install', [CheckIfInstalledMiddleware::class]);
                $router->middlewareGroup('installing', [CheckIfInstallingMiddleware::class]);

                $router->pushMiddlewareToGroup('web', RedirectIfNotInstalledMiddleware::class);
            }
        });

        try {
            $this->app['events']->listen([UpdatedEvent::class, FinishedSeederEvent::class], function () {
                BaseHelper::saveFileData(storage_path(INSTALLED_SESSION_NAME), Carbon::now()->toDateTimeString());
            });
        } catch (Throwable $exception) {
            info($exception->getMessage());
        }
    }
}
