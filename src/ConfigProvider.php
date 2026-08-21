<?php

declare(strict_types=1);

namespace Componenta\Auth\App;

use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\Resolver\CurrentAuthenticationHandler;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\DI\Attribute\Composition\AttributeDefinition;
use Componenta\DI\Attribute\Composition\Capability\AuthoritativeValueProvider;
use Componenta\DI\Attribute\Composition\Capability\InvocationOnlyValueProvider;

/** Registers authentication context attributes for Componenta DI. */
final class ConfigProvider extends BaseConfigProvider
{
    #[\Override]
    protected function getAttributeDefinitions(): array
    {
        $handler = new CurrentAuthenticationHandler();
        $capabilities = [
            AuthoritativeValueProvider::class,
            InvocationOnlyValueProvider::class,
        ];

        return [
            new AttributeDefinition(CurrentUser::class, $handler, $capabilities),
            new AttributeDefinition(CurrentSession::class, $handler, $capabilities),
            new AttributeDefinition(CurrentSessionId::class, $handler, $capabilities),
        ];
    }
}
