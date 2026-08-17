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

Both values are authoritative: explicit caller arguments and mapped request data cannot override them. During `#[MapRequestPayload]` and other request DTO mapping, `auth-app` propagates the already trusted PSR-7 request into nested DTO construction, so the same rule also holds for constructor attributes inside mapped DTOs. The resolver reads `SessionInterface::class` only from the current `ServerRequestInterface`; it never trusts a raw cookie, header, token payload, or request field as a session identity.

Nullable targets return `null` when a request exists but has no authenticated session:

```php
function endpoint(#[CurrentSessionId] ?string $sessionId): void {}
```

A missing PSR-7 request is always a resolution error because the attributes are request-scoped.

The package registers its `ConfigProvider` through Composer metadata. No changes to `componenta/auth` or `componenta/di` are required.
