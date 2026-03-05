<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = parent::createApplication();

        // Safety hard-stop: test suite must never run against local MySQL data.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');

        return $app;
    }

    /**
     * @return array<class-string, int>
     */
    protected function setUpTraits(): array
    {
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'Test safety check failed: tests must use sqlite to protect local data.'
            );
        }

        return parent::setUpTraits();
    }
}
