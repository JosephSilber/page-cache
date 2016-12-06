<?php

use Mockery as m;
use Silber\PageCache\Cache;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheTest extends PHPUnit_Framework_TestCase
{
    /**
     * The filesystem mock instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The cache instance.
     *
     * @var \Silber\PageCache\Cache
     */
    protected $cache;

    public function setUp()
    {
        $this->files = m::mock(Filesystem::class);

        $this->cache = new Cache($this->files);
    }

    public function tearDown()
    {
        m::close();
    }

    public function testCachesGetRequestsWith200Response()
    {
        $this->assertCaches(
            Request::create('foo', 'GET'),
            Response::create('foo-content')
        );
    }

    public function testOnlyCachesGetRequests()
    {
        $response = Response::create('foo-content');

        $this->assertDoesntCache(Request::create('foo', 'HEAD'), $response);
        $this->assertDoesntCache(Request::create('foo', 'OPTIONS'), $response);
        $this->assertDoesntCache(Request::create('foo', 'POST'), $response);
        $this->assertDoesntCache(Request::create('foo', 'PUT'), $response);
        $this->assertDoesntCache(Request::create('foo', 'PATCH'), $response);
        $this->assertDoesntCache(Request::create('foo', 'DELETE'), $response);
    }

    public function testOnlyCaches200Responses()
    {
        $request = Request::create('foo', 'GET');

        $this->assertDoesntCache($request, Response::create('content', 301));
        $this->assertDoesntCache($request, Response::create('content', 302));
        $this->assertDoesntCache($request, Response::create('content', 400));
        $this->assertDoesntCache($request, Response::create('content', 401));
        $this->assertDoesntCache($request, Response::create('content', 403));
        $this->assertDoesntCache($request, Response::create('content', 404));
        $this->assertDoesntCache($request, Response::create('content', 422));
        $this->assertDoesntCache($request, Response::create('content', 500));
    }

    public function testCacheWithoutBasePathThrows()
    {
        $this->setExpectedException(Exception::class);

        $this->cache->cache(Request::create('foo', 'GET'), Response::create('content'));
    }

    public function testCanSetCachePathOnInstance()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('/foo/bar/', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('/foo/bar/baz.html', 'content', true);

        $this->cache->setCachePath('/foo/bar/');
        $this->cache->cache(Request::create('baz', 'GET'), Response::create('content'));

        $this->assertEquals('/foo/bar/', $this->cache->getCachePath());
        $this->assertEquals('/foo/bar/baz/', $this->cache->getCachePath('baz'));
    }

    public function testCanPullCachePathFromContainer()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('/site/public/page-cache/foo/', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('/site/public/page-cache/foo/bar.html', 'content', true);

        $container = new Container;
        $container->instance('path.public', '/site/public');

        $this->cache->setContainer($container);
        $this->cache->cache(Request::create('foo/bar', 'GET'), Response::create('content'));

        $this->assertEquals('/site/public/page-cache/', $this->cache->getCachePath());
        $this->assertEquals('/site/public/page-cache/baz/', $this->cache->getCachePath('baz'));
    }

    public function testCachesRootToSpecialFilename()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('/page-cache/', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('/page-cache/pc__index__pc.html', 'content', true);

        $this->cache->setCachePath('/page-cache');
        $this->cache->cache(Request::create('/', 'GET'), Response::create('content'));
    }

    /**
     * Assert that the given request/response pair are cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function assertCaches($request, $response)
    {
        $this->assertTrue($this->cache->shouldCache($request, $response));
    }

    /**
     * Assert that the given request/response pair are never cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function assertDoesntCache($request, $response)
    {
        $this->assertFalse($this->cache->shouldCache($request, $response));
    }
}
