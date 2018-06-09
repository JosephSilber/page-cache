<?php

namespace Silber\PageCache\Middleware;

use Closure;
use Silber\PageCache\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * The cache instance.
     *
     * @var \Silber\PageCache\Cache
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @var \Silber\PageCache\Cache  $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->cache->shouldCache($request, $response)) {
            $this->cache->cache($request, $response);
        }

        return $response;
    }
}
