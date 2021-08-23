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
    public function handle(Request $request, Closure $next, $pageType = null, $minutesToCache = null) // ...$args = alle arguments from the service provider
    {
        $response = $next($request);

        if ($this->shouldCache($request, $response)) {
            // TODO: Zorg dat er gecached wordt in de juiste folder
            //dd($this->cache->getPageType());
            if (is_null($this->cache->getPageType())) {
                // Set the page type if not set before
                $this->cache->setPageType($pageType ? $pageType : 'page');
            }
            if (is_null($this->cache->getPageType())) {
                // Set the expire time if not set before
                $this->cache->setExpireAt($minutesToCache ? $pageType : config('page-cache.expire_time' . $this->cache->getPageType()));
            }
            $this->cache->cache($request, $response);
        }

        return $response;
    }

    /**
     * Determines whether the given request/response pair should be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldCache(Request $request, Response $response)
    {
        return $request->isMethod('GET') && $response->getStatusCode() == 200;
    }
}
