# componenta/auth-app

Передача текущего пользователя в параметры вызываемой функции через Componenta Auth 4 и Componenta DI 6.

Единственный источник текущего пользователя — запрос PSR-7. Пакет не сохраняет состояние аутентификации в контейнере, глобальных переменных или локальных данных фибры.

Сессии предоставляет отдельный пакет `componenta/auth-session-app` через атрибут `#[CurrentSession]`. Отдельный `#[CurrentSessionId]` не нужен: публичный UUID доступен непосредственно из текущего `AuthSession`.

## Требования

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

`#[CurrentUser]` читает атрибут `IdentityInterface::class` непосредственно из текущего запроса. Приложение может потребовать конкретный тип пользователя:

```php
public function __invoke(
    #[CurrentUser(AppUser::class)] IdentityInterface $user,
): ResponseInterface {
    // ...
}
```

Если запрос PSR-7 отсутствует, разрешение параметра завершается ошибкой. Если запрос есть, но пользователь не установлен, параметр, допускающий `null`, получает `null`; для обязательного параметра выбрасывается исключение. Неверный тип атрибута запроса также вызывает ошибку.

## Ограничение областью вызова

`CurrentUser` регистрируется через `AttributeDefinition` в DI 6 с `AuthoritativeValueProvider` и `InvocationOnlyValueProvider`.

Явно переданные аргументы не могут подменить пользователя из запроса. Применять атрибут к параметрам конструктора запрещено: текущее состояние аутентификации относится к одному вызову и не должно сохраняться в объекте, который может обслуживать следующие запросы.

## Интеграция с сессиями

Текущую сессию предоставляет отдельный пакет `componenta/auth-session-app`:

```php
public function __invoke(
    #[CurrentUser] IdentityInterface $user,
    #[CurrentSession] AuthSession $session,
): ResponseInterface {
    $sessionId = $session->uuid;
}
```

Сам `auth-app` не зависит от контрактов сессии аутентификации.

## Разработка

```bash
composer check
```
