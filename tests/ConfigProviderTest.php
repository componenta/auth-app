<?php

declare(strict_types=1);

use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\ConfigProvider;
use Componenta\Config\ConfigKey;
use Componenta\DI\Attribute\Composition\AttributeDefinition;
use Componenta\DI\Attribute\Composition\Capability\AuthoritativeValueProvider;
use Componenta\DI\Attribute\Composition\Capability\InvocationOnlyValueProvider;

it('registers authentication context through DI v5 attribute definitions only', function (): void {
    $config = (new ConfigProvider())();
    $dependencies = $config[ConfigKey::DEPENDENCIES] ?? [];
    $definitions = $dependencies[ConfigKey::ATTRIBUTE_DEFINITIONS] ?? [];

    expect($definitions)->toHaveCount(3)
        ->and(array_key_exists(ConfigKey::PARAMETER_RESOLVERS, $dependencies))->toBeFalse();

    $byAttribute = [];
    foreach ($definitions as $definition) {
        expect($definition)->toBeInstanceOf(AttributeDefinition::class);
        $byAttribute[$definition->attribute] = $definition;
    }

    expect(array_keys($byAttribute))->toBe([
        CurrentUser::class,
        CurrentSession::class,
        CurrentSessionId::class,
    ]);

    foreach ($byAttribute as $definition) {
        expect($definition->capabilities)->toContain(AuthoritativeValueProvider::class)
            ->and($definition->capabilities)->toContain(InvocationOnlyValueProvider::class);
    }
});
