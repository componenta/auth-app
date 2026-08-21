# componenta/auth-app

Authentication-context integration for `componenta/auth` and `componenta/di` v5.

`AuthenticationMiddleware` in `componenta/auth` is the source of truth. After successful authentication it stores the authenticated `IdentityInterface` on the current PSR-7 request under `IdentityInterface::class` and, when available, the authenticated `SessionInterface` under `SessionInterface::class`. This package reads those request attributes directly; it has no current-user provider, Fiber-local store, request-global singleton or other parallel authentication context.

## Requirements

- PHP 8.4+;
- `componenta/auth` 2.0.3+;
- `componenta/config` 3.x;
- `componenta/di` 5.x.

## Context attributes

The package provides three parameter attributes:

```php
use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\Session\SessionInterface;
use Componenta\Identity\IdentityInterface;

public function __invoke(
    #[CurrentUser] IdentityInterface $user,
    #[CurrentSession] ?SessionInterface $session,
    #[CurrentSessionId] ?string $sessionId,
): ResponseInterface {
    // ...
}
```

`#[CurrentUser]` reads `IdentityInterface::class` from the current request. It can additionally require an application-specific identity subtype:

```php
public function __invoke(
    #[CurrentUser(AppUser::class)] IdentityInterface $user,
): ResponseInterface {
    // ...
}
```

`#[CurrentSession]` reads `SessionInterface::class`; `#[CurrentSessionId]` returns that session's `id`.

A missing PSR-7 request is always a resolution error. If a request exists but has no authenticated user/session, nullable targets receive `null`; required targets fail explicitly. Request attributes with invalid types fail closed.

## Invocation-only semantics

All three attributes are registered through the DI v5 `AttributeDefinition` pipeline with `AuthoritativeValueProvider` and `InvocationOnlyValueProvider` capabilities.

They are therefore authoritative: generic caller parameters cannot shadow values established by authentication middleware. They are also invocation-only: using them on constructor parameters is rejected during attribute-plan composition.

```php
final class Service
{
    public function __construct(
        #[CurrentUser] IdentityInterface $user,
    ) {}
}
```

The example above is invalid. Current authentication state belongs to the active callable execution and must not be captured in object state that can outlive the request.

Commands or DTOs that carry an actor should receive it through the integration/mapping boundary that constructs the fresh message rather than through constructor `#[CurrentUser]` injection. `auth-app` itself does not populate `ActorAwareInterface`; a CQRS/request integration that owns actor mapping should use the same authenticated `IdentityInterface::class` request attribute as its source.

## DI v5 integration

`ConfigProvider` registers `CurrentUser`, `CurrentSession` and `CurrentSessionId` as attribute definitions. There is no custom parameter-resolver priority and no authentication-context provider service.

The same composed attribute plan is used by runtime reflection and AOT preparation. Tests cover callable resolution in development and compiled containers, invocation-only constructor rejection, and a real persistent DI-cache round trip for the authentication attribute definitions.

## Development

```bash
composer check
```
