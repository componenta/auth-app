<?php

declare(strict_types=1);

namespace Componenta\Auth\App;

use Componenta\Auth\App\Resolver\CurrentSessionResolver;
use Componenta\Config\ConfigProvider as BaseConfigProvider;

/** Registers authentication-aware DI integrations. */
final class ConfigProvider extends BaseConfigProvider
{
    #[\Override]
    protected function getParameterResolvers(): array
    {
        return [
            CurrentSessionResolver::PRIORITY => CurrentSessionResolver::class,
        ];
    }
}
