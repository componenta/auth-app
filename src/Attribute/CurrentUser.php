<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Attribute;

use Attribute;
use Componenta\DI\Resolver\Parameter\ParameterSourceAttributeInterface;

/** Injects the authenticated identity from the current PSR-7 request. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentUser implements ParameterSourceAttributeInterface
{
    /** @param class-string|null $type */
    public function __construct(public ?string $type = null) {}
}
