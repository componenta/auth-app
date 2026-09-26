<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Tests\Fixture;

use Componenta\Identity\IdentityInterface;
use Componenta\Identity\Uuid;
use Componenta\Identity\UuidInterface;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final readonly class IdentityFixture implements IdentityInterface
{
    public function __construct(public UuidInterface $uuid) {}

    public static function create(
        string $uuid = '0198b914-a800-7000-8000-000000000001',
    ): self {
        return new self(Uuid::fromString($uuid));
    }

    public static function request(
        ?IdentityInterface $identity = null,
    ): ServerRequestInterface {
        $request = new ServerRequest('GET', 'https://example.test/');

        return $identity === null
            ? $request
            : $request->withAttribute(IdentityInterface::class, $identity);
    }
}
