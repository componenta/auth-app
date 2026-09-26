<?php

declare(strict_types=1);

namespace Componenta\Auth\App;

use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\Resolver\CurrentUserHandler;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\DI\Attribute\Composition\AttributeDefinition;
use Componenta\DI\Attribute\Composition\Capability\AuthoritativeValueProvider;
use Componenta\DI\Attribute\Composition\Capability\InvocationOnlyValueProvider;

/** Registers invocation-only authenticated identity injection for Componenta DI. */
final class ConfigProvider extends BaseConfigProvider
{
    #[\Override]
    protected function getAttributeDefinitions(): array
    {
        return [
            new AttributeDefinition(
                CurrentUser::class,
                new CurrentUserHandler(),
                [
                    AuthoritativeValueProvider::class,
                    InvocationOnlyValueProvider::class,
                ],
            ),
        ];
    }
}
