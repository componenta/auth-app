<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Attribute;

use Attribute;
use Componenta\DI\Resolver\Parameter\ParameterSourceAttributeInterface;

/** Injects the authenticated server-side session for the current request. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentSession implements ParameterSourceAttributeInterface {}
