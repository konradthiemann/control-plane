<?php

namespace App\Tests\Config;

use App\Config\AppRegistry;
use PHPUnit\Framework\TestCase;

class AppRegistryTest extends TestCase
{
    public function testFindBySlugReturnsMatchingApp(): void
    {
        $app = AppRegistry::findBySlug('knips');

        self::assertNotNull($app);
        self::assertSame('Knips', $app->displayName);
        self::assertSame('Foto-Challenge', $app->githubRepo);
        self::assertTrue($app->hasKnipsAnalytics);
    }

    public function testFindBySlugReturnsNullForUnknownSlug(): void
    {
        self::assertNull(AppRegistry::findBySlug('does-not-exist'));
    }

    public function testAllReturnsUniqueSlugs(): void
    {
        $slugs = array_map(static fn ($app) => $app->slug, AppRegistry::all());

        self::assertSame($slugs, array_unique($slugs));
        self::assertNotEmpty($slugs);
    }
}
