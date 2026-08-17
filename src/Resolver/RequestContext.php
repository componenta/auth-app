<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Resolver;

use Fiber;
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

/** @internal Scoped request stack used only while request DTO mapping is active. */
final class RequestContext
{
    /** @var list<ServerRequestInterface> */
    private array $mainStack = [];

    /** @var WeakMap<object, list<ServerRequestInterface>>|null Fiber identity => request stack. */
    private ?WeakMap $fiberStacks = null;

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(ServerRequestInterface $request, callable $callback): mixed
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            $this->mainStack[] = $request;

            try {
                return $callback();
            } finally {
                array_pop($this->mainStack);
            }
        }

        $stacks = $this->fiberStacks ??= new WeakMap();
        $stack = isset($stacks[$fiber]) ? $stacks[$fiber] : [];
        $stack[] = $request;
        $stacks[$fiber] = $stack;

        try {
            return $callback();
        } finally {
            $stack = $stacks[$fiber];
            array_pop($stack);

            if ($stack === []) {
                unset($stacks[$fiber]);
            } else {
                $stacks[$fiber] = $stack;
            }
        }
    }

    public function current(): ?ServerRequestInterface
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return $this->last($this->mainStack);
        }

        $stacks = $this->fiberStacks;
        if ($stacks === null || !isset($stacks[$fiber])) {
            return null;
        }

        return $this->last($stacks[$fiber]);
    }

    /** @param list<ServerRequestInterface> $stack */
    private function last(array $stack): ?ServerRequestInterface
    {
        return $stack === [] ? null : $stack[count($stack) - 1];
    }
}
