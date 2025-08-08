<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(\Modules\Lumasachi\app\Providers\LumasachiServiceProvider::class);
        $this->app['view']->addNamespace('mail', __DIR__ . '/../resources/views/vendor/mail/html');
    }
}
