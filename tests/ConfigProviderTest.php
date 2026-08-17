<?php

declare(strict_types=1);

use Componenta\Auth\App\ConfigProvider;
use Componenta\Auth\App\Resolver\CurrentSessionResolver;
use Componenta\Config\ConfigKey;

it('registers only the authoritative current-session resolver', function (): void {
    $config = (new ConfigProvider())();
    $dependencies = $config[ConfigKey::DEPENDENCIES] ?? [];

    expect($dependencies[ConfigKey::PARAMETER_RESOLVERS] ?? null)
        ->toBe([
            CurrentSessionResolver::PRIORITY => CurrentSessionResolver::class,
        ])
        ->and(array_key_exists(ConfigKey::FACTORIES, $dependencies))->toBeFalse()
        ->and(array_key_exists(ConfigKey::INVOKABLES, $dependencies))->toBeFalse()
        ->and(CurrentSessionResolver::PRIORITY)->toBeGreaterThan(1200);
});
