<?php
/**
 * Container Unit Tests
 *
 * @package NowScrobbling\Tests\Unit
 */

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit;

use NowScrobbling\Tests\TestCase;
use NowScrobbling\Container;

/**
 * Test cases for the DI Container
 */
final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Container::reset();
        $this->container = Container::getInstance();
    }

    public function testSingletonInstance(): void
    {
        $instance1 = Container::getInstance();
        $instance2 = Container::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testBindAndMake(): void
    {
        $this->container->bind('test.service', fn() => new \stdClass());

        $service = $this->container->make('test.service');

        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testBindCreatesNewInstanceEachTime(): void
    {
        $this->container->bind('test.factory', fn() => new \stdClass());

        $instance1 = $this->container->make('test.factory');
        $instance2 = $this->container->make('test.factory');

        $this->assertNotSame($instance1, $instance2);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton('test.singleton', fn() => new \stdClass());

        $instance1 = $this->container->make('test.singleton');
        $instance2 = $this->container->make('test.singleton');

        $this->assertSame($instance1, $instance2);
    }

    public function testHasReturnsTrueForBoundService(): void
    {
        $this->container->bind('existing.service', fn() => 'value');

        $this->assertTrue($this->container->has('existing.service'));
    }

    public function testHasReturnsFalseForUnboundService(): void
    {
        $this->assertFalse($this->container->has('nonexistent.service'));
    }

    public function testContainerPassesItselfToFactory(): void
    {
        $receivedContainer = null;

        $this->container->bind('container.aware', function (Container $c) use (&$receivedContainer) {
            $receivedContainer = $c;
            return new \stdClass();
        });

        $this->container->make('container.aware');

        $this->assertSame($this->container, $receivedContainer);
    }

    public function testResetClearsAllBindings(): void
    {
        $this->container->bind('test.service', fn() => 'value');

        Container::reset();
        $newContainer = Container::getInstance();

        $this->assertFalse($newContainer->has('test.service'));
    }

    public function testMakeThrowsExceptionForUnknownService(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No binding found');

        $this->container->make('unknown.service');
    }

    public function testSingletonWithDependency(): void
    {
        $this->container->singleton('dependency', fn() => new \stdClass());
        $this->container->singleton('service', function (Container $c) {
            $service = new \stdClass();
            $service->dependency = $c->make('dependency');
            return $service;
        });

        $service = $this->container->make('service');

        $this->assertSame(
            $this->container->make('dependency'),
            $service->dependency
        );
    }
}
