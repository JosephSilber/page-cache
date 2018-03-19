<?php

namespace Silber\PageCache;

use Illuminate\Support\ServiceProvider;
use Silber\PageCache\Console\ClearCache;
use Silber\PageCache\Console\DeleteCache;

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
        $this->commands(DeleteCache::class);

        $this->app->singleton(Cache::class, function () {
            $instance = new Cache($this->app->make('files'));

            return $instance->setContainer($this->app);
        });
    }
}
