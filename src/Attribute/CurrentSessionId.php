<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Attribute;

use Attribute;
use Componenta\DI\Resolver\Parameter\ParameterSourceAttributeInterface;

/** Injects the identifier of the authenticated server-side session. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentSessionId implements ParameterSourceAttributeInterface {}
