<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Seed the three roles and admin permissions whenever the test database is rebuilt. */
    protected $seed = true;

    /**
     * The auth manager caches the resolved user between requests made in one test.
     * Requests that carry a bearer token must authenticate from scratch every time,
     * otherwise a revoked or expired token would still look logged in.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (isset($server['HTTP_AUTHORIZATION'])) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
