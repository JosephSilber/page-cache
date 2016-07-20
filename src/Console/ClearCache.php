<?php

namespace Silber\PageCache\Console;

use Silber\PageCache\Cache;
use Illuminate\Console\Command;

class ClearCache extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'page-cache:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears the page cache.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cache = $this->laravel->make(Cache::class);

        if ($cache->clear()) {
            $this->info('Page cache cleared at '.$cache->getCachePath());
        } else {
            $this->warn('Page cache not cleared at '.$cache->getCachePath());
        }
    }
}
