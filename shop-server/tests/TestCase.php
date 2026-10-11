<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        if (file_exists(dirname(__DIR__).'/.env.testing')) {
            $app->loadEnvironmentFrom('.env.testing');
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Sanctum guards cache the authenticated user on the shared app instance
     * between in-process HTTP calls; clear them so bearer-token checks are real.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
