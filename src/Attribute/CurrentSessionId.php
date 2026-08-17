<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Attribute;

use Attribute;

/** Injects the identifier of the authenticated server-side session. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentSessionId {}
