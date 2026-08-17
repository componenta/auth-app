<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Tests\Fixture;

use Componenta\Auth\Session\Session;
use Componenta\Auth\Session\SessionInterface;
use Componenta\Identity\Uuid;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final class SessionFixture
{
    public static function session(string $id = 'session-current'): SessionInterface
    {
        $createdAt = new \DateTimeImmutable('2026-08-17T12:00:00+00:00');

        return new Session(
            id: $id,
            subjectId: Uuid::fromString('0198b914-a800-7000-8000-000000000001'),
            expiresAt: $createdAt->modify('+30 minutes'),
            absoluteExpiresAt: $createdAt->modify('+8 hours'),
            regenerateAt: $createdAt->modify('+5 minutes'),
            replacedBy: null,
            createdAt: $createdAt,
            lastActiveAt: $createdAt,
        );
    }

    public static function request(?SessionInterface $session = null): ServerRequestInterface
    {
        $request = new ServerRequest('GET', 'https://example.test/');

        return $session === null
            ? $request
            : $request->withAttribute(SessionInterface::class, $session);
    }

    private function __construct() {}
}
