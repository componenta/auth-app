# componenta/auth-app

Application integration between `componenta/auth` and `componenta/di`.

The package exposes authoritative parameter attributes backed by the authenticated `SessionInterface` stored on the current PSR-7 request:

```php
use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\Session\SessionInterface;

final readonly class RevokeSessionCommand
{
    public function __construct(
        #[CurrentSession]
        public SessionInterface $session,
        #[CurrentSessionId]
        public string $sessionId,
    ) {}
}
```

`#[CurrentSession]` injects the authenticated server-side session. `#[CurrentSessionId]` injects its `SessionInterface::$id`.

Both values are authoritative: caller-provided parameters and mapped request data cannot override them. The resolver reads `SessionInterface::class` only from the trusted `ServerRequestInterface`; it never treats a cookie, header, token payload, or request field as the current server-side session.

`componenta/di` 4.0.3 or newer preserves the trusted PSR-7 request when a `Map*` request mapper constructs a nested DTO. As a result, the same `CurrentSession` and `CurrentSessionId` semantics apply inside commands and other DTOs created by `#[MapRequestPayload]`, `#[MapQueryString]`, and the other request mappers without any request-context state in this package.

Nullable targets return `null` when a request exists but has no authenticated session:

```php
function endpoint(#[CurrentSessionId] ?string $sessionId): void {}
```

A missing PSR-7 request is always a resolution error because both attributes are request-scoped.

The package registers its `ConfigProvider` through Composer metadata. Its runtime integration consists only of the two attributes and `CurrentSessionResolver`; request propagation is owned by `componenta/di`.
