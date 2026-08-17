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

Оба значения являются доверенными: явно переданные параметры и данные request mapping не могут их подменить. Resolver читает `SessionInterface::class` только из доверенного `ServerRequestInterface` и никогда не рассматривает cookie, header, token payload или поле запроса как текущую серверную сессию.

`componenta/di` версии 4.0.3 и выше сохраняет доверенный PSR-7 request, когда request mapper семейства `Map*` создаёт вложенный DTO. Поэтому те же правила `CurrentSession` и `CurrentSessionId` работают внутри команд и других DTO, создаваемых через `#[MapRequestPayload]`, `#[MapQueryString]` и остальные request mapper-ы, без какого-либо request-context state внутри этого пакета.

Nullable-параметры получают `null`, если request существует, но аутентифицированной сессии нет:

```php
function endpoint(#[CurrentSessionId] ?string $sessionId): void {}
```

Отсутствие самого PSR-7 request всегда является ошибкой разрешения, поскольку оба атрибута имеют request-scoped семантику.

`ConfigProvider` регистрируется автоматически через Composer metadata. Runtime-интеграция пакета состоит только из двух атрибутов и `CurrentSessionResolver`; передача request во вложенные DTO является обязанностью `componenta/di`.
