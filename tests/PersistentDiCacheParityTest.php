<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Tests;

use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\ConfigProvider;
use Componenta\Auth\App\Tests\Fixture\IdentityFixture;
use Componenta\Config\Config;
use Componenta\Config\ConfigKey;
use Componenta\DI\Cache\DiCacheGenerator;
use Componenta\DI\ContainerBuilder;
use Componenta\Identity\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;

it('preserves CurrentUser semantics through the persistent DI cache', function (): void {
    $provider = new ConfigProvider();
    $provided = $provider();
    $dependencies = $provided[ConfigKey::DEPENDENCIES] ?? [];

    expect($dependencies)->toBeArray();

    $path = sys_get_temp_dir()
        . '/componenta-auth-app-di-'
        . bin2hex(random_bytes(6))
        . '.php';

    try {
        (new DiCacheGenerator())->generate($dependencies, $path);

        $cache = require $path;
        expect($cache)->toBeArray();

        $container = ContainerBuilder::configureFromCache(
            new Config([]),
            $cache,
            dirname($path),
        )->build();

        $user = IdentityFixture::create();
        $request = IdentityFixture::request($user);

        $resolved = $container->call(
            static fn(
                #[CurrentUser] IdentityInterface $currentUser,
            ): IdentityInterface => $currentUser,
            [ServerRequestInterface::class => $request],
        );

        expect($resolved)->toBe($user);
    } finally {
        @unlink($path);
    }
});
