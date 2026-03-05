<?php

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Rest;

use NowScrobbling\Container;
use NowScrobbling\Rest\RestController;
use NowScrobbling\Tests\TestCase;
use ReflectionClass;

final class RestControllerTest extends TestCase
{
    public function testValidateShortcodeAllowsKnownTagsOnly(): void
    {
        $controller = new RestController(Container::getInstance());

        self::assertTrue($this->invokePrivate($controller, 'validateShortcode', ['nowscr_trakt_history']));
        self::assertFalse($this->invokePrivate($controller, 'validateShortcode', ['nowscr_unknown']));
    }

    public function testParseAttrsSanitizesAndFiltersByAllowlist(): void
    {
        $controller = new RestController(Container::getInstance());

        $raw = json_encode([
            'MAX_LENGTH' => '<b>120</b>',
            'style' => 'bubble',
            'period' => '7day',
            'limit' => '10',
            'type' => 'movies',
            'not_allowed' => 'drop-me',
            'limit_array' => ['invalid'],
        ]);

        $actual = $this->invokePrivate($controller, 'parseAttrs', [$raw]);

        self::assertSame([
            'max_length' => '120',
            'style' => 'bubble',
            'period' => '7day',
            'limit' => '10',
            'type' => 'movies',
        ], $actual);
    }

    public function testParseAttrsReturnsEmptyForInvalidJson(): void
    {
        $controller = new RestController(Container::getInstance());

        self::assertSame([], $this->invokePrivate($controller, 'parseAttrs', ['not-json']));
        self::assertSame([], $this->invokePrivate($controller, 'parseAttrs', ['"string"']));
        self::assertSame([], $this->invokePrivate($controller, 'parseAttrs', [null]));
    }

    public function testBuildShortcodeEscapesAttributeValues(): void
    {
        $controller = new RestController(Container::getInstance());

        $shortcode = $this->invokePrivate($controller, 'buildShortcode', [
            'nowscr_lastfm_history',
            [
                'max_length' => '45" onclick="alert(1)',
                'style' => 'bubble<script>',
            ],
        ]);

        self::assertStringStartsWith('[nowscr_lastfm_history ', $shortcode);
        self::assertStringContainsString('max_length="45&quot; onclick=&quot;alert(1)"', $shortcode);
        self::assertStringContainsString('style="bubble&lt;script&gt;"', $shortcode);
    }

    private function invokePrivate(object $target, string $method, array $args): mixed
    {
        $reflection = new ReflectionClass($target);
        $instance = $reflection->getMethod($method);
        $instance->setAccessible(true);

        return $instance->invokeArgs($target, $args);
    }
}
