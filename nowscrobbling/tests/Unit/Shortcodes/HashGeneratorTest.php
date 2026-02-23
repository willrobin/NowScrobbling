<?php
/**
 * HashGenerator Unit Tests
 *
 * @package NowScrobbling\Tests\Unit\Shortcodes
 */

declare(strict_types=1);

namespace NowScrobbling\Tests\Unit\Shortcodes;

use NowScrobbling\Shortcodes\Renderer\HashGenerator;
use NowScrobbling\Tests\TestCase;

/**
 * Test cases for HashGenerator
 */
final class HashGeneratorTest extends TestCase
{
    public function testGeneratesStableHashForSameInput(): void
    {
        $generator = new HashGenerator();
        $data = [
            'recenttracks' => [
                'track' => [
                    [
                        'name' => 'Song A',
                        'artist' => ['#text' => 'Artist A'],
                    ],
                ],
            ],
        ];

        $hash1 = $generator->generate($data);
        $hash2 = $generator->generate($data);

        $this->assertSame($hash1, $hash2);
    }

    public function testNowPlayingStateAffectsHash(): void
    {
        $generator = new HashGenerator();

        $base = [
            'recenttracks' => [
                'track' => [
                    [
                        'name' => 'Song A',
                        'artist' => ['#text' => 'Artist A'],
                        '@attr' => ['nowplaying' => 'false'],
                    ],
                ],
            ],
        ];

        $nowPlaying = $base;
        $nowPlaying['recenttracks']['track'][0]['@attr']['nowplaying'] = 'true';

        $this->assertNotSame(
            $generator->generate($base),
            $generator->generate($nowPlaying)
        );
    }

    public function testIgnoresVolatileTimestampFields(): void
    {
        $generator = new HashGenerator();

        $first = [
            'name' => 'Song A',
            'artist' => ['#text' => 'Artist A'],
            'timestamp' => 1000,
            'cached_at' => 1000,
            '__ns_cached' => true,
        ];

        $second = $first;
        $second['timestamp'] = 2000;
        $second['cached_at'] = 2000;
        $second['__ns_cached'] = false;

        $this->assertSame(
            $generator->generate($first),
            $generator->generate($second)
        );
    }
}

