# componenta/auth-app

Интеграционный пакет между `componenta/auth` и `componenta/di`.

Пакет предоставляет доверенные атрибуты параметров, источником которых является аутентифицированный `SessionInterface`, уже помещённый в текущий PSR-7 request:

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

`#[CurrentSession]` внедряет текущую серверную сессию, а `#[CurrentSessionId]` — её `SessionInterface::$id`.

Оба значения являются authoritative: явные аргументы вызывающего кода и данные, полученные через request mapping, не могут их подменить. Во время `#[MapRequestPayload]` и другого DTO mapping пакет `auth-app` передаёт уже доверенный PSR-7 request во вложенное создание DTO, поэтому то же правило действует и для атрибутов параметров конструктора mapped DTO. Resolver читает `SessionInterface::class` только из текущего `ServerRequestInterface` и не рассматривает cookie, header, token payload или поле запроса как доверенный идентификатор сессии.

Nullable-параметры получают `null`, если request существует, но аутентифицированной сессии нет:

```php
function endpoint(#[CurrentSessionId] ?string $sessionId): void {}
```

Отсутствие самого PSR-7 request всегда является ошибкой разрешения, поскольку эти атрибуты имеют request-scoped семантику.

`ConfigProvider` регистрируется автоматически через Composer metadata. Изменения в `componenta/auth` и `componenta/di` не требуются.
