<?php

declare(strict_types=1);

use Componenta\Auth\App\ConfigProvider;
use Componenta\Auth\App\Factory\RequestMappingResolverFactory;
use Componenta\Auth\App\Resolver\CurrentSessionResolver;
use Componenta\Auth\App\Resolver\RequestContext;
use Componenta\Auth\App\Resolver\RequestMappingResolver;
use Componenta\Config\ConfigKey;

it('registers authoritative session and request-mapping integration', function (): void {
    $config = (new ConfigProvider())();
    $dependencies = $config[ConfigKey::DEPENDENCIES] ?? [];

    expect($dependencies[ConfigKey::PARAMETER_RESOLVERS] ?? null)
        ->toBe([
            CurrentSessionResolver::PRIORITY => CurrentSessionResolver::class,
            RequestMappingResolver::PRIORITY => RequestMappingResolver::class,
        ])
        ->and($dependencies[ConfigKey::FACTORIES] ?? null)
        ->toBe([
            RequestMappingResolver::class => RequestMappingResolverFactory::class,
        ])
        ->and($dependencies[ConfigKey::INVOKABLES] ?? null)
        ->toBe([RequestContext::class])
        ->and(CurrentSessionResolver::PRIORITY)->toBeGreaterThan(1200)
        ->and(RequestMappingResolver::PRIORITY)->toBeGreaterThan(800)
        ->and(RequestMappingResolver::PRIORITY)->toBeLessThan(900);
});
