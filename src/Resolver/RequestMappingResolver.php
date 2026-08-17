<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Resolver;

use Componenta\DI\Resolver\Parameter\ParameterResolutionContext;
use Componenta\DI\Resolver\Parameter\ParameterResolverInterface;
use Componenta\DI\Resolver\Parameter\Request\MapperInterface;
use Componenta\DI\Resolver\Parameter\Request\RequestDataExtractorInterface;
use Componenta\DI\Resolver\Parameter\Request\RequestParameter;
use Componenta\DI\Resolver\Parameter\Request\RequestResolver;
use Componenta\DI\Resolver\Target\ParameterTarget;

/**
 * Preserves the current request while RequestResolver constructs mapped DTOs.
 *
 * This is deliberately limited to mapping attributes. Other request extraction
 * remains owned by the stock DI RequestResolver.
 *
 * @internal
 */
final readonly class RequestMappingResolver implements ParameterResolverInterface
{
    public const int PRIORITY = 850;

    public function __construct(
        private RequestResolver $resolver,
        private RequestContext $context,
    ) {}

    #[\Override]
    public function supports(ParameterTarget $target): bool
    {
        foreach ($target->attributeClasses as $attributeClass) {
            if (is_a($attributeClass, RequestDataExtractorInterface::class, true)
                && is_a($attributeClass, MapperInterface::class, true)
            ) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function resolveParameter(
        ParameterTarget $target,
        ParameterResolutionContext $context,
    ): ?array {
        $request = RequestParameter::get($context->provided);

        if ($request === null) {
            return $this->resolver->resolveParameter($target, $context);
        }

        return $this->context->run(
            $request,
            fn(): ?array => $this->resolver->resolveParameter($target, $context),
        );
    }
}
