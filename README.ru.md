# componenta/auth-app

Интеграция authentication context между `componenta/auth` и `componenta/di` v5.

Единственный источник текущего состояния аутентификации — `AuthenticationMiddleware` из `componenta/auth`. После успешной аутентификации middleware помещает `IdentityInterface` в текущий PSR-7 request по ключу `IdentityInterface::class`, а при наличии серверной сессии — `SessionInterface` по ключу `SessionInterface::class`. Этот пакет читает значения непосредственно из request; отдельного current-user provider, Fiber-local storage, request-global singleton или второго auth-context нет.

## Требования

- PHP 8.4+;
- `componenta/auth` 2.x;
- `componenta/config` 3.x;
- `componenta/di` 5.x.

## Контекстные атрибуты

Пакет предоставляет три атрибута параметров:

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

`#[CurrentUser]` читает `IdentityInterface::class` из текущего request. При необходимости можно потребовать конкретный тип identity приложения:

```php
public function __invoke(
    #[CurrentUser(AppUser::class)] IdentityInterface $user,
): ResponseInterface {
    // ...
}
```

`#[CurrentSession]` читает `SessionInterface::class`, а `#[CurrentSessionId]` возвращает `id` этой сессии.

Если PSR-7 request отсутствует, resolution всегда завершается ошибкой. Если request есть, но пользователь или сессия не аутентифицированы, nullable-параметр получает `null`, а обязательный параметр завершается явной ошибкой. Неверный тип auth-атрибута в request отклоняется fail-closed.

## Invocation-only семантика

Все три атрибута регистрируются через DI v5 `AttributeDefinition` с capabilities `AuthoritativeValueProvider` и `InvocationOnlyValueProvider`.

Они authoritative: generic caller parameters не могут подменить значения, установленные authentication middleware. Они также invocation-only: использование в constructor parameter отклоняется во время композиции attribute plan.

```php
final class Service
{
    public function __construct(
        #[CurrentUser] IdentityInterface $user,
    ) {}
}
```

Такой код невалиден. Текущее состояние аутентификации принадлежит активному callable execution и не должно фиксироваться в состоянии объекта, который способен пережить request.

Команды и DTO, которые несут actor, должны получать его на своей integration/mapping boundary, а не через constructor `#[CurrentUser]`. В частности, CQRS mapping для `ActorAwareInterface` самостоятельно назначает аутентифицированного actor новой message.

## Интеграция с DI v5

`ConfigProvider` регистрирует `CurrentUser`, `CurrentSession` и `CurrentSessionId` как attribute definitions. Отдельного parameter-resolver priority и provider service для authentication context больше нет.

Runtime reflection и AOT preparation используют один composed attribute plan. Тесты проверяют одинаковое callable resolution в development и compiled container, а также одинаковый отказ для invocation-only constructor usage.

## Разработка

```bash
composer check
```
