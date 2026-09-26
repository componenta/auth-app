# componenta/auth-app

Invocation-only интеграция аутентифицированной identity между Componenta Auth 3 и Componenta DI v5.

Единственный источник текущего пользователя — текущий PSR-7 request. Пакет не хранит Fiber-local, request-global или удерживаемое контейнером состояние аутентификации.

Сессии намеренно вынесены из этого пакета. `#[CurrentSession]` принадлежит будущему `componenta/auth-session-app`; отдельного `#[CurrentSessionId]` в Auth 3 нет, потому что публичный UUID доступен непосредственно из текущего `AuthSession`.

## Требования

- PHP 8.4+;
- `componenta/auth` 3.x;
- `componenta/config` 3.x;
- `componenta/di` 5.x;
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

`#[CurrentUser]` читает `IdentityInterface::class` непосредственно из текущего request. При необходимости приложение может потребовать свой subtype identity:

```php
public function __invoke(
    #[CurrentUser(AppUser::class)] IdentityInterface $user,
): ResponseInterface {
    // ...
}
```

Если PSR-7 request отсутствует, resolution всегда завершается ошибкой. Если request есть, но authenticated identity отсутствует, nullable-параметр получает `null`, обязательный параметр — явную ошибку. Неверный тип request attribute отклоняется fail-closed.

## Invocation-only семантика

`CurrentUser` регистрируется через DI v5 `AttributeDefinition` с `AuthoritativeValueProvider` и `InvocationOnlyValueProvider`.

Параметры caller не могут подменить authenticated identity, а constructor injection запрещён. Текущее состояние аутентификации относится только к одному callable invocation и не должно сохраняться в объекте, переживающем request.

## Интеграция с сессиями

Session-specific состояние предоставляет отдельный пакет `componenta/auth-session-app`:

```php
public function __invoke(
    #[CurrentUser] IdentityInterface $user,
    #[CurrentSession] AuthSession $session,
): ResponseInterface {
    $sessionId = $session->uuid;
}
```

Сам `auth-app` не зависит от authentication-session contracts.

## Разработка

```bash
composer check
```
