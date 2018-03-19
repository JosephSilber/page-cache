<?php

namespace Silber\PageCache\Console;

use Silber\PageCache\Cache;
use Illuminate\Console\Command;

class DeleteCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'page-cache:delete {filename : File to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a single file of cache';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $cache = $this->laravel->make(Cache::class);

        $filename = $this->argument('filename') . '.html';

        if($cache->delete($filename)) {
            $this->info('File deleted from ' . $cache->getCachePath() . $filename);
        }
        else {
            $this->warn('File not found at ' . $cache->getCachePath() . $filename);
        }
    }
}
