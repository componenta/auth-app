<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Resolver;

use Componenta\DI\FactoryInterface;
use Componenta\DI\Resolver\Parameter\Request\RequestParameter;

/** @internal Propagates the trusted request into DTO construction parameters. */
final readonly class RequestPropagatingFactory implements FactoryInterface
{
    public function __construct(
        private FactoryInterface $factory,
        private RequestContext $context,
    ) {}

    #[\Override]
    public function make(string $entry, array $params = []): object
    {
        $request = $this->context->current();

        if ($request !== null) {
            $params[RequestParameter::KEY] = $request;
        }

        return $this->factory->make($entry, $params);
    }
}
