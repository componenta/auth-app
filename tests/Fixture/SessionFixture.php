<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Tests\Fixture;

use Componenta\Auth\Session\Session;
use Componenta\Auth\Session\SessionInterface;
use Componenta\Identity\IdentityInterface;
use Componenta\Identity\Uuid;
use Componenta\Identity\UuidInterface;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final readonly class IdentityFixture implements IdentityInterface
{
    public function __construct(public UuidInterface $uuid) {}
}

final class SessionFixture
{
    public static function identity(
        string $uuid = '0198b914-a800-7000-8000-000000000001',
    ): IdentityInterface {
        return new IdentityFixture(Uuid::fromString($uuid));
    }

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

    public static function request(
        ?SessionInterface $session = null,
        ?IdentityInterface $identity = null,
    ): ServerRequestInterface {
        $request = new ServerRequest('GET', 'https://example.test/');

        if ($identity !== null) {
            $request = $request->withAttribute(IdentityInterface::class, $identity);
        }
        if ($session !== null) {
            $request = $request->withAttribute(SessionInterface::class, $session);
        }

        return $request;
    }

    private function __construct() {}
}
