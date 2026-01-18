<?php
/**
 * Sanitizer Unit Tests
 *
 * @package NowScrobbling\Tests\Unit\Security
 */

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Security;

use NowScrobbling\Tests\TestCase;
use NowScrobbling\Security\Sanitizer;

/**
 * Test cases for the Sanitizer class
 *
 * Note: These tests use WordPress function stubs which have simplified implementations.
 * The stub's strip_tags() keeps text content within tags, so tests are adjusted accordingly.
 */
final class SanitizerTest extends TestCase
{
    public function testSanitizeTextRemovesHtmlTags(): void
    {
        // strip_tags keeps the text content, removes only the tags
        $input = '  <b>Hello</b> <i>World</i>  ';
        $result = Sanitizer::sanitize($input, 'text');

        $this->assertEquals('Hello World', $result);
    }

    public function testSanitizeTextTrims(): void
    {
        $input = '   Hello World   ';
        $result = Sanitizer::sanitize($input, 'text');

        $this->assertEquals('Hello World', $result);
    }

    public function testSanitizeInt(): void
    {
        $this->assertEquals(42, Sanitizer::sanitize('42', 'int'));
        $this->assertEquals(42, Sanitizer::sanitize(42.7, 'int'));
        $this->assertEquals(0, Sanitizer::sanitize('not a number', 'int'));
        $this->assertEquals(123, Sanitizer::sanitize(-123, 'int')); // absint
    }

    public function testSanitizeFloat(): void
    {
        $this->assertEquals(3.14, Sanitizer::sanitize('3.14', 'float'));
        $this->assertEquals(0.0, Sanitizer::sanitize('not a float', 'float'));
    }

    public function testSanitizeBool(): void
    {
        $this->assertTrue(Sanitizer::sanitize('1', 'bool'));
        $this->assertTrue(Sanitizer::sanitize('true', 'bool'));
        $this->assertTrue(Sanitizer::sanitize('yes', 'bool'));
        $this->assertFalse(Sanitizer::sanitize('0', 'bool'));
        $this->assertFalse(Sanitizer::sanitize('false', 'bool'));
        $this->assertFalse(Sanitizer::sanitize('no', 'bool'));
    }

    public function testSanitizeKey(): void
    {
        $result = Sanitizer::sanitize('My_Key-123!@#', 'key');

        $this->assertEquals('my_key-123', $result);
    }

    public function testSanitizeValidEmail(): void
    {
        $this->assertEquals('test@example.com', Sanitizer::sanitize('test@example.com', 'email'));
    }

    public function testSanitizeEmailRemovesInvalidChars(): void
    {
        // The stub removes invalid characters but doesn't validate email format
        $result = Sanitizer::sanitize('test<>@example.com', 'email');
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }

    public function testSanitizeUrl(): void
    {
        $result = Sanitizer::sanitize('https://example.com/path?query=1', 'url');

        $this->assertStringContainsString('example.com', $result);
    }

    public function testSanitizeArrayWithSimpleValues(): void
    {
        $input = [
            'name' => '  Test  ',
            'count' => '42',
        ];

        $result = Sanitizer::sanitizeArray($input);

        $this->assertEquals('Test', $result['name']);
        $this->assertEquals('42', $result['count']);
    }

    public function testSanitizeArrayWithNested(): void
    {
        $input = [
            'nested' => [
                'value' => '  ok  ',
            ],
        ];

        $result = Sanitizer::sanitizeArray($input);

        $this->assertEquals('ok', $result['nested']['value']);
    }

    public function testSanitizeJsonReturnsArray(): void
    {
        $json = '{"name": "Test", "count": 5}';
        $result = Sanitizer::sanitizeJson($json);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(5, $result['count']);
    }

    public function testSanitizeJsonInvalidString(): void
    {
        $result = Sanitizer::sanitizeJson('not json');

        $this->assertEquals([], $result);
    }

    public function testSanitizeNullReturnsNull(): void
    {
        $this->assertNull(Sanitizer::sanitize(null, 'text'));
    }

    public function testShortcodeAttsWithDefaults(): void
    {
        $defaults = [
            'count' => 5,
            'enabled' => true,
            'label' => 'Default',
        ];

        $atts = [
            'count' => '10',
            'enabled' => 'false',
        ];

        $result = Sanitizer::shortcodeAtts($atts, $defaults);

        $this->assertEquals(10, $result['count']);
        $this->assertFalse($result['enabled']);
        $this->assertEquals('Default', $result['label']);
    }

    public function testOptionSanitization(): void
    {
        $result = Sanitizer::option('ns_test', '', 'text', 'default');

        $this->assertEquals('default', $result);
    }
}
