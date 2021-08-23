<?php

namespace Silber\PageCache;

use Illuminate\Support\ServiceProvider;
use Silber\PageCache\Console\ClearCache;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(ClearCache::class);
        $locale = $this->app->getLocale();
        $this->app->singleton(Cache::class, function () use ($locale) {
            $instance = new Cache($this->app->make('files'));

            return $instance->setLocale($locale)->setContainer($this->app);
        });
    }
}
