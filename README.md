# componenta/auth-app

Invocation-only authenticated-identity integration between Componenta Auth 4 and Componenta DI 6.

The current PSR-7 request is the only source of the authenticated identity. This package does not keep Fiber-local, request-global or container-retained authentication state.

Authentication sessions are intentionally not part of this package. `#[CurrentSession]` belongs to `componenta/auth-session-app`; There is no separate `#[CurrentSessionId]` attribute because the public session UUID is available from the current `AuthSession` itself.

## Requirements

- PHP 8.5.13+;
- `componenta/auth` 4.x;
- `componenta/config` 4.x;
- `componenta/di` 6.x;
- PSR-7 2.x.

## CurrentUser

```php
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Identity\IdentityInterface;

public function __invoke(
    #[CurrentUser] IdentityInterface $user,
): ResponseInterface {
    // ...
}
```

`#[CurrentUser]` reads `IdentityInterface::class` directly from the current request. An application-specific identity subtype may be required explicitly:

```php
public function __invoke(
    #[CurrentUser(AppUser::class)] IdentityInterface $user,
): ResponseInterface {
    // ...
}
```

A missing PSR-7 request is always a resolution error. If the request exists but has no authenticated identity, nullable targets receive `null`; required targets fail explicitly. An invalid request-attribute type fails closed.

## Invocation-only semantics

`CurrentUser` is registered through the DI 6 `AttributeDefinition` pipeline with `AuthoritativeValueProvider` and `InvocationOnlyValueProvider`.

Generic caller parameters cannot shadow the authenticated identity, and constructor injection is rejected. Current authentication state belongs to one callable invocation and must not be retained in an object that may outlive the request.

## Session integration

Session-specific invocation state is provided by the separate `componenta/auth-session-app` package:

```php
public function __invoke(
    #[CurrentUser] IdentityInterface $user,
    #[CurrentSession] AuthSession $session,
): ResponseInterface {
    $sessionId = $session->uuid;
}
```

`auth-app` itself has no dependency on authentication-session contracts.

## Development

```bash
composer check
```
