<?php

namespace Silber\PageCache;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Container\Container;
use Statamic\Support\Arr;
use Statamic\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class Cache
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container|null
     */
    protected $container = null;

    /**
     * The directory in which to store the cached pages.
     *
     * @var string|null
     */
    protected $cachePath = null;


    /**
     * The locale of the site cache.
     *
     * @var string|null
     */
    protected $locale = null;


    /**
     * The type of page to cache (used for cache index). Use page | plp | pdp
     *
     * @var string|null
     */
    protected $pageType = null;

    /**
     * Time to cache in minutes
     *
     * @var int|null
     */
    protected $expireAt = null;

    /**
     * Constructor.
     *
     * @var \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Sets the container instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Sets the directory in which to store the cached pages.
     *
     * @param  string  $path
     * @return void
     */
    public function setCachePath($path)
    {
        $this->cachePath = rtrim($path, '\/');
    }

    /**
     * Gets the path to the cache directory.
     *
     * @param  string  ...$paths
     * @return string
     *
     * @throws \Exception
     */
    public function getCachePath()
    {
        $base = $this->cachePath ? $this->cachePath : $this->getDefaultCachePath();

        if (is_null($base)) {
            throw new Exception('Cache path not set.');
        }

        return $this->join(array_merge([$base], func_get_args()));
    }

    /**
     * Join the given paths together by the system's separator.
     *
     * @param  string[] $paths
     * @return string
     */
    protected function join(array $paths)
    {
        $trimmed = array_map(function ($path) {
            return trim($path, '/');
        }, $paths);

        return $this->matchRelativity(
            $paths[0], implode('/', array_filter($trimmed))
        );
    }

    /**
     * Makes the target path absolute if the source path is also absolute.
     *
     * @param  string  $source
     * @param  string  $target
     * @return string
     */
    protected function matchRelativity($source, $target)
    {
        return $source[0] == '/' ? '/'.$target : $target;
    }

    /**
     * Caches the given response if we determine that it should be cache.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return $this
     */
    public function cacheIfNeeded(Request $request, Response $response)
    {
        if ($this->shouldCache($request, $response)) {
            $this->cache($request, $response);
        }

        return $this;
    }

    /**
     * Determines whether the given request/response pair should be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    public function shouldCache(Request $request, Response $response)
    {
        return $request->isMethod('GET') && $response->getStatusCode() == 200;
    }

    /**
     * Cache the response to a file.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function cache(Request $request, Response $response)
    {
        list($path, $file) = $this->getDirectoryAndFileNames($request, $response);

        $this->files->makeDirectory($path, 0775, true, true);

        $this->files->put(
            $this->join([$path, $file]),
            $response->getContent(),
            true
        );

        \App\Models\CacheIndex::create([
            'path' => $this->join([$path, $file]),
            'page_type' => $this->pageType,
            'expire_at' => \Carbon\Carbon::now()->addMinutes($this->expireAt),
        ]);
    }

    /**
     * Remove the cached file for the given slug.
     *
     * @param  string  $slug
     * @return bool
     */
    public function forget($slug)
    {
        $deletedHtml = $this->files->delete($this->getCachePath($slug.'.html'));
        $deletedJson = $this->files->delete($this->getCachePath($slug.'.json'));

        return $deletedHtml || $deletedJson;
    }

    /**
     * Clear the full cache directory, or a subdirectory.
     *
     * @param  string|null
     * @return bool
     */
    public function clear($path = null)
    {
        return $this->files->deleteDirectory($this->getCachePath($path), true);
    }

    /**
     * Get the names of the directory and file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response $response
     * @return array
     */
    protected function getDirectoryAndFileNames($request, $response)
    {
        $filename = $this->getFilePath($this->getUrl($request), $response);
        $pathInfo = pathinfo($filename);
        //dd($filename);
        return [$pathInfo['dirname'], $pathInfo['basename']];
    }

    /**
     * Get the URL from a request.
     *
     * @param  Request  $request
     * @return string
     */
    public function getUrl(Request $request)
    {
        $url = $request->getUri();

        if (config('page-cache.ignore_query_strings')) {
            $url = explode('?', $url)[0];
        }

        return $url;
    }

    /**
     * Get the path to the cached file.
     *
     * @param $url
     * @return string
     */
    public function getFilePath($url, $response)
    {
        $urlParts = parse_url($url);
        $pathParts = pathinfo($urlParts['path']);
        $slug = $pathParts['basename'];
        $query = $this->calculateQuery($urlParts);
        $extension = $this->guessFileExtension($response);
        if ($this->isBasenameTooLong($basename = $slug.'_'.$query.'.'.$extension)) {
            $basename = $slug.'_lqs_'.md5($query).'.'.$extension;
        }

        return $this->getCachePath().$pathParts['dirname'].'/'.$basename;
    }

    private function calculateQuery(array $urlParts)
    {
        if (config('page-cache.ignore_query_strings')) {
            return '';
        }
        $query = Arr::get($urlParts, 'query', '');
        if (config('page-cache.query_strings_params_whitelist')) {
            $query = $this->filterQStringByWhitelist($query);
        }

        return $query;

    }

    private function filterQStringByWhitelist($query) : string
    {
        $whitelist = config('page-cache.query_strings_params_whitelist');
        $approvedParts = [];
        $parts = explode('&', $query);
        foreach ($parts as $part) {
            $partParts = explode('=', $part);
            if(in_array($partParts[0], $whitelist)) {
                $approvedParts[] = $part;
            }
        }
        // TODO: Order van de parameters. Lijkt al goed te gaan. Maar hoe kan dat?
        return implode('&', $approvedParts);

    }

    private function isBasenameTooLong($basename)
    {
        return strlen($basename) > config('page-cache.max_filename_length', 255);
    }

    private function isLongQueryStringPath($path)
    {
        return Str::contains($path, '_lqs_');
    }

    /**
     * Get the default path to the cache directory.
     *
     * @return string|null
     */
    protected function getDefaultCachePath()
    {
        if ($this->container && $this->container->bound('path.public')) {
            $cachePath = $this->container->make('path.public').'/'.config('page-cache.cache-path').'/';
            if ($this->locale && config('page-cache.multisite')) {
                $sites = config('statamic.sites.sites');
                $subFolder = '';
                foreach ($sites as $site) {
                    if ($site['locale'] === $this->locale) {
                        $subFolder = parse_url($site['url'])['host'] . '/';
                    }
                }
                $cachePath = $cachePath . $subFolder . '/';
            }

            return $cachePath;
        }
    }

    /**
     * Guess the correct file extension for the given response.
     *
     * Currently, only JSON and HTML are supported.
     *
     * @return string
     */
    protected function guessFileExtension($response)
    {
        $contentType = $response->headers->get('Content-Type');

        if ($response instanceof JsonResponse ||
            $contentType == 'application/json'
        ) {
            return 'json';
        }

        if (in_array($contentType, ['text/xml', 'application/xml'])) {
            return 'xml';
        }

        return 'html';
    }

    /**
     * @param string|null $locale
     * @return Cache
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @param string|null $pageType
     * @return Cache
     */
    public function setPageType($pageType)
    {
        $this->pageType = $pageType;
        return $this;
    }

    /**
     * @param int|null $expireAt
     * @return Cache
     */
    public function setExpireAt($expireAt)
    {
        $this->expireAt = $expireAt;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return int|null
     */
    public function getExpireAt()
    {
        return $this->expireAt;
    }

    /**
     * @return string|null
     */
    public function getPageType()
    {
        return $this->pageType;
    }

}
