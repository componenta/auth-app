<?php

declare(strict_types=1);

use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\ConfigProvider;
use Componenta\Config\ConfigKey;
use Componenta\DI\Attribute\Composition\AttributeDefinition;
use Componenta\DI\Attribute\Composition\Capability\AuthoritativeValueProvider;
use Componenta\DI\Attribute\Composition\Capability\InvocationOnlyValueProvider;

it('registers only the current authenticated user attribute', function (): void {
    $config = (new ConfigProvider())();
    $dependencies = $config[ConfigKey::DEPENDENCIES] ?? [];
    $definitions = $dependencies[ConfigKey::ATTRIBUTE_DEFINITIONS] ?? [];

    expect($definitions)->toHaveCount(1)
        ->and(array_key_exists(ConfigKey::PARAMETER_RESOLVERS, $dependencies))->toBeFalse();

    $definition = $definitions[0] ?? null;

    expect($definition)->toBeInstanceOf(AttributeDefinition::class)
        ->and($definition->attribute)->toBe(CurrentUser::class)
        ->and($definition->capabilities)->toContain(AuthoritativeValueProvider::class)
        ->and($definition->capabilities)->toContain(InvocationOnlyValueProvider::class);
});
