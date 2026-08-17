<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Factory;

use Componenta\Auth\App\Resolver\RequestContext;
use Componenta\Auth\App\Resolver\RequestMappingResolver;
use Componenta\Auth\App\Resolver\RequestPropagatingFactory;
use Componenta\DI\Resolver\Parameter\Request\LazyCasterProvider;
use Componenta\DI\Resolver\Parameter\Request\LazyFactory;
use Componenta\DI\Resolver\Parameter\Request\LazyValidationProvider;
use Componenta\DI\Resolver\Parameter\Request\RequestResolver;
use Psr\Container\ContainerInterface;

/** @internal */
final readonly class RequestMappingResolverFactory
{
    public function __invoke(ContainerInterface $container): RequestMappingResolver
    {
        $context = $container->get(RequestContext::class);
        if (!$context instanceof RequestContext) {
            throw new \UnexpectedValueException(sprintf(
                'Container entry "%s" must be an instance of %s; got %s.',
                RequestContext::class,
                RequestContext::class,
                get_debug_type($context),
            ));
        }

        return new RequestMappingResolver(
            new RequestResolver(
                new RequestPropagatingFactory(
                    new LazyFactory($container),
                    $context,
                ),
                new LazyCasterProvider($container),
                new LazyValidationProvider($container),
            ),
            $context,
        );
    }
}
