<?php

namespace Silber\PageCache\Console;

use Silber\PageCache\Cache;
use Illuminate\Console\Command;

class ClearCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'page-cache:clear {slug? : URL slug of page to delete} {--force-clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the page cache.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $cache = $this->laravel->make(Cache::class);
        $slug = $this->argument('slug') ?? '';
        $force = $this->option('force-clear');

        if ($slug && !$force) {
            $this->forget($cache, $slug);
        } else {
            $this->clear($cache, $slug);
        }
    }

    /**
     * Remove the cached file for the given slug.
     *
     * @param  \Silber\PageCache\Cache  $cache
     * @param  string  $slug
     * @return void
     */
    public function forget(Cache $cache, $slug)
    {
        if ($cache->forget($slug)) {
            $this->info("Page cache cleared for \"{$slug}\"");
        } else {
            $this->info("No page cache found for \"{$slug}\"");
        }
    }

    /**
     * Clear the full page cache.
     *
     * @param  \Silber\PageCache\Cache  $cache
     * @param  string  $slug
     * @return void
     */
    public function clear(Cache $cache, $slug)
    {
        if ($cache->clear($slug)) {
            $this->info('Page cache cleared at '.$cache->getCachePath($slug));
        } else {
            $this->warn('Page cache not cleared at '.$cache->getCachePath($slug));
        }
    }
}
