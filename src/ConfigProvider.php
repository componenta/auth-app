<?php

declare(strict_types=1);

namespace Componenta\Auth\App;

use Componenta\Auth\App\Factory\RequestMappingResolverFactory;
use Componenta\Auth\App\Resolver\CurrentSessionResolver;
use Componenta\Auth\App\Resolver\RequestContext;
use Componenta\Auth\App\Resolver\RequestMappingResolver;
use Componenta\Config\ConfigProvider as BaseConfigProvider;

/** Registers authentication-aware DI integrations. */
final class ConfigProvider extends BaseConfigProvider
{
    #[\Override]
    protected function getFactories(): array
    {
        return [
            RequestMappingResolver::class => RequestMappingResolverFactory::class,
        ];
    }

    #[\Override]
    protected function getInvokables(): array
    {
        return [
            RequestContext::class,
        ];
    }

    #[\Override]
    protected function getParameterResolvers(): array
    {
        return [
            CurrentSessionResolver::PRIORITY => CurrentSessionResolver::class,
            RequestMappingResolver::PRIORITY => RequestMappingResolver::class,
        ];
    }
}
