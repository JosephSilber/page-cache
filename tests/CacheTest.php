<?php

namespace Silber\PageCache\Tests;

use Exception;
use Mockery as m;
use Silber\PageCache\Cache;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class CacheTest extends TestCase
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

    public function setUp() : void
    {
        $this->files = m::mock(Filesystem::class);

        $this->cache = new Cache($this->files);
    }

    public function tearDown() : void
    {
        m::close();
    }

    public function testCachesGetRequestsWith200Response()
    {
        $this->assertCaches(
            Request::create('foo', 'GET'),
            new Response('foo-content')
        );
    }

    public function testOnlyCachesGetRequests()
    {
        $response = new Response('foo-content');

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

        $this->assertDoesntCache($request, new Response('content', 301));
        $this->assertDoesntCache($request, new Response('content', 302));
        $this->assertDoesntCache($request, new Response('content', 400));
        $this->assertDoesntCache($request, new Response('content', 401));
        $this->assertDoesntCache($request, new Response('content', 403));
        $this->assertDoesntCache($request, new Response('content', 404));
        $this->assertDoesntCache($request, new Response('content', 422));
        $this->assertDoesntCache($request, new Response('content', 500));
    }

    public function testCacheWithoutBasePathThrows()
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException(Exception::class);
        } else {
            $this->setExpectedException(Exception::class);
        }

        $this->cache->cache(Request::create('foo', 'GET'), new Response('content'));
    }

    public function testCanSetCachePathOnInstance()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('foo/bar', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('foo/bar/baz.html', 'content', true);

        $this->cache->setCachePath('foo/bar');
        $this->cache->cache(Request::create('baz', 'GET'), new Response('content'));

        $this->assertEquals('foo/bar', $this->cache->getCachePath());
        $this->assertEquals('foo/bar/baz', $this->cache->getCachePath('baz'));
    }

    public function testUsesAbsolutePathWhenBasePathIsAbsolute()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('/site/public/page-cache/foo', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('/site/public/page-cache/foo/bar.html', 'content', true);

        $this->cache->setCachePath('/site/public/page-cache');
        $this->cache->cache(Request::create('foo/bar', 'GET'), new Response('content'));

        $this->assertEquals('/site/public/page-cache', $this->cache->getCachePath());
        $this->assertEquals('/site/public/page-cache/baz', $this->cache->getCachePath('baz'));
    }

    public function testCanPullCachePathFromContainer()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('site/public/page-cache/foo', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('site/public/page-cache/foo/bar.html', 'content', true);

        $container = new Container;
        $container->instance('path.public', 'site/public');

        $this->cache->setContainer($container);
        $this->cache->cache(Request::create('foo/bar', 'GET'), new Response('content'));

        $this->assertEquals('site/public/page-cache', $this->cache->getCachePath());
        $this->assertEquals('site/public/page-cache/baz', $this->cache->getCachePath('baz'));
    }

    public function testCachesRootToSpecialFilename()
    {
        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('page-cache', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('page-cache/pc__index__pc.html', 'content', true);

        $this->cache->setCachePath('page-cache');
        $this->cache->cache(Request::create('/', 'GET'), new Response('content'));
    }

    public function testCachesJsonResponsesWithJsonExtension()
    {
        $content = ['this' => 'is', 'json' => [1, 2, 3]];

        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('page-cache', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('page-cache/get-json.json', json_encode($content), true);

        $this->cache->setCachePath('page-cache');
        $this->cache->cache(
            Request::create('get-json', 'GET'),
            new JsonResponse($content)
        );
    }

    public function testCachesResponsesWithJsonHeaderWithJsonExtension()
    {
        $json = json_encode(['this' => 'is', 'json' => [1, 2, 3]]);

        $this->files->shouldReceive('makeDirectory')->once()
                    ->with('page-cache', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('page-cache/get-json.json', $json, true);

        $this->cache->setCachePath('page-cache');
        $this->cache->cache(
            Request::create('get-json', 'GET'),
            new Response($json, 200, ['Content-Type' => 'application/json'])
        );
    }

    public function testCachesResponsesWithXmlHeaderWithXmlExtension()
    {
        $xml = 'This is XML. Prove me wrong.';

        $this->files->shouldReceive('makeDirectory')->twice()
                    ->with('page-cache', 0775, true, true);

        $this->files->shouldReceive('put')->once()
                    ->with('page-cache/get-xml-text.xml', $xml, true);

        $this->files->shouldReceive('put')->once()
                    ->with('page-cache/get-xml-application.xml', $xml, true);

        $this->cache->setCachePath('page-cache');
        $this->cache->cache(
            Request::create('get-xml-text', 'GET'),
            new Response($xml, 200, ['Content-Type' => 'text/xml'])
        );
        $this->cache->cache(
            Request::create('get-xml-application', 'GET'),
            new Response($xml, 200, ['Content-Type' => 'application/xml'])
        );
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
