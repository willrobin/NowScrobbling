<?php
/**
 * InMemoryLayer Unit Tests
 *
 * @package NowScrobbling\Tests\Unit\Cache
 */

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Cache;

use NowScrobbling\Tests\TestCase;
use NowScrobbling\Cache\Layers\InMemoryLayer;

/**
 * Test cases for the InMemoryLayer cache
 */
final class InMemoryLayerTest extends TestCase
{
    private InMemoryLayer $layer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layer = new InMemoryLayer();
        // Clear the static cache between tests
        $this->layer->clear();
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->layer->get('nonexistent'));
    }

    public function testSetAndGet(): void
    {
        $this->layer->set('test_key', 'test_value', 60);

        $this->assertEquals('test_value', $this->layer->get('test_key'));
    }

    public function testDelete(): void
    {
        $this->layer->set('test_key', 'test_value', 60);
        $this->layer->delete('test_key');

        $this->assertNull($this->layer->get('test_key'));
    }

    public function testClear(): void
    {
        $this->layer->set('key1', 'value1', 60);
        $this->layer->set('key2', 'value2', 60);

        $this->layer->clear();

        $this->assertNull($this->layer->get('key1'));
        $this->assertNull($this->layer->get('key2'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->layer->set('test_key', 'test_value', 60);

        $this->assertTrue($this->layer->has('test_key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->layer->has('nonexistent'));
    }

    public function testOverwriteExistingKey(): void
    {
        $this->layer->set('test_key', 'original', 60);
        $this->layer->set('test_key', 'updated', 60);

        $this->assertEquals('updated', $this->layer->get('test_key'));
    }

    public function testStoreArrayValue(): void
    {
        $data = ['foo' => 'bar', 'count' => 42];
        $this->layer->set('array_key', $data, 60);

        $this->assertEquals($data, $this->layer->get('array_key'));
    }

    public function testStoreObjectValue(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';

        $this->layer->set('object_key', $obj, 60);

        $retrieved = $this->layer->get('object_key');
        $this->assertEquals('test', $retrieved->name);
    }

    public function testGetName(): void
    {
        $this->assertEquals('memory', $this->layer->getName());
    }

    public function testGetStats(): void
    {
        $this->layer->set('key1', 'value1', 60);
        $this->layer->set('key2', 'value2', 60);

        $stats = $this->layer->getStats();

        $this->assertArrayHasKey('size', $stats);
        $this->assertEquals(2, $stats['size']);
    }

    public function testGetSize(): void
    {
        $this->assertEquals(0, $this->layer->getSize());

        $this->layer->set('key1', 'value1', 60);
        $this->assertEquals(1, $this->layer->getSize());

        $this->layer->set('key2', 'value2', 60);
        $this->assertEquals(2, $this->layer->getSize());
    }
}
