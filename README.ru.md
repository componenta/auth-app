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

Оба значения являются доверенными. Программно переданные параметры не могут их подменить. При `Map*` request DTO mapping поля, совпадающие по имени с параметрами `CurrentSession` или `CurrentSessionId`, отклоняются через `RequestParameterSourceConflictException`, а не рассматриваются как DI override. Resolver читает `SessionInterface::class` только из доверенного `ServerRequestInterface` и никогда не рассматривает cookie, header, token payload или поле запроса как текущую серверную сессию.

`componenta/di` версии 4.0.8 и выше сохраняет provenance mapped request при вложенном factory resolution, прохождении alias и compiled factories и проверяет параметры, атрибуты которых реализуют `ParameterSourceAttributeInterface`, до учёта priority parameter resolvers. `CurrentSession` и `CurrentSessionId` реализуют этот контракт, поэтому те же fail-closed правила действуют внутри DTO, создаваемых через `#[MapRequestPayload]`, `#[MapQueryString]` и остальные request mapper-ы, в том числе когда объявленный mapper type через alias разрешается в конкретную команду. Runtime и compiled production используют одну и ту же границу без request-context state внутри этого пакета.

Nullable-параметры получают `null`, если request существует, но аутентифицированной сессии нет:

```php
function endpoint(#[CurrentSessionId] ?string $sessionId): void {}
```

Отсутствие самого PSR-7 request всегда является ошибкой разрешения, поскольку оба атрибута имеют request-scoped семантику.

`ConfigProvider` регистрируется автоматически через Composer metadata. Runtime-интеграция пакета состоит только из двух атрибутов и `CurrentSessionResolver`; передача request и обнаружение конфликтов mapped sources являются обязанностью `componenta/di`.
