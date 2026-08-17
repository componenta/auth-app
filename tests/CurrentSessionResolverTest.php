<?php

declare(strict_types=1);

use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\ConfigProvider as AuthAppConfigProvider;
use Componenta\Auth\App\Tests\Fixture\SessionFixture;
use Componenta\Auth\Session\SessionInterface;
use Componenta\Caster\ConfigProvider as CasterConfigProvider;
use Componenta\Config\Config;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\DI\Attribute\MapRequestPayload;
use Componenta\DI\ConfigProvider as DiConfigProvider;
use Componenta\DI\Container;
use Componenta\DI\ContainerBuilder;
use Componenta\DI\Exception\RequestParameterSourceConflictException;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\Resolver\Parameter\ParameterSourceAttributeInterface;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthAppMappedCommand
{
    public function __construct(
        #[CurrentSession]
        public SessionInterface $session,
        #[CurrentSessionId]
        public string $sessionId,
        public string $value,
    ) {}
}

function authAppTestContainer(): Container
{
    $provider = new class () extends BaseConfigProvider {
        protected function getProviders(): array
        {
            return [
                new CasterConfigProvider(),
                new DiConfigProvider(),
                new AuthAppConfigProvider(),
            ];
        }
    };

    return ContainerBuilder::configure(new Config($provider()))->build();
}

it('marks current-session attributes as explicit parameter sources', function (): void {
    expect(is_a(CurrentSession::class, ParameterSourceAttributeInterface::class, true))->toBeTrue()
        ->and(is_a(CurrentSessionId::class, ParameterSourceAttributeInterface::class, true))->toBeTrue();
});

it('injects the current authenticated session', function (): void {
    $session = SessionFixture::session();
    $request = SessionFixture::request($session);
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(#[CurrentSession] SessionInterface $current): SessionInterface => $current,
        [ServerRequestInterface::class => $request],
    );

    expect($resolved)->toBe($session);
});

it('injects the current authenticated session id', function (): void {
    $session = SessionFixture::session('session-42');
    $request = SessionFixture::request($session);
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(#[CurrentSessionId] string $sessionId): string => $sessionId,
        [ServerRequestInterface::class => $request],
    );

    expect($resolved)->toBe('session-42');
});

it('returns null for nullable session targets when the request is anonymous', function (): void {
    $request = SessionFixture::request();
    $container = authAppTestContainer();

    $session = $container->call(
        static fn(#[CurrentSession] ?SessionInterface $current): ?SessionInterface => $current,
        [ServerRequestInterface::class => $request],
    );
    $sessionId = $container->call(
        static fn(#[CurrentSessionId] ?string $current): ?string => $current,
        [ServerRequestInterface::class => $request],
    );

    expect($session)->toBeNull()
        ->and($sessionId)->toBeNull();
});

it('fails for required session targets when the request is anonymous', function (): void {
    $request = SessionFixture::request();
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentSessionId] string $sessionId): string => $sessionId,
        [ServerRequestInterface::class => $request],
    ))->toThrow(ResolutionException::class, 'current authenticated session is required but unavailable');
});

it('requires a request even for nullable targets', function (): void {
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentSessionId] ?string $sessionId): ?string => $sessionId,
    ))->toThrow(ResolutionException::class, 'PSR-7 request is required');
});

it('rejects ambiguous session attributes on one parameter', function (): void {
    $request = SessionFixture::request(SessionFixture::session());
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(
            #[CurrentSession]
            #[CurrentSessionId]
            mixed $value,
        ): mixed => $value,
        [ServerRequestInterface::class => $request],
    ))->toThrow(ResolutionException::class, 'cannot be combined on the same parameter');
});

it('does not let explicit parameters shadow trusted session values', function (): void {
    $session = SessionFixture::session('trusted-session');
    $spoofedSession = SessionFixture::session('spoofed-session');
    $request = SessionFixture::request($session);
    $container = authAppTestContainer();

    $sessionIdCallable = static fn(#[CurrentSessionId] string $sessionId): string => $sessionId;
    $sessionCallable = static fn(#[CurrentSession] SessionInterface $current): SessionInterface => $current;

    expect($container->call($sessionIdCallable, [
        ServerRequestInterface::class => $request,
        'sessionId' => 'spoofed-by-name',
    ]))->toBe('trusted-session')
        ->and($container->call($sessionIdCallable, [
            ServerRequestInterface::class => $request,
            0 => 'spoofed-by-position',
        ]))->toBe('trusted-session')
        ->and($container->call($sessionCallable, [
            ServerRequestInterface::class => $request,
            SessionInterface::class => $spoofedSession,
        ]))->toBe($session);
});

it('rejects mapped request data that collides with CurrentSession', function (): void {
    $session = SessionFixture::session('trusted-session');
    $spoofedSession = SessionFixture::session('spoofed-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody([
            'session' => $spoofedSession,
            'value' => 'payload-value',
        ]);
    $container = authAppTestContainer();

    try {
        $container->call(
            static fn(
                #[MapRequestPayload]
                AuthAppMappedCommand $command,
            ): AuthAppMappedCommand => $command,
            [ServerRequestInterface::class => $request],
        );
    } catch (RequestParameterSourceConflictException $exception) {
        expect($exception->dtoClass)->toBe(AuthAppMappedCommand::class)
            ->and($exception->key)->toBe('session')
            ->and($exception->source)->toBe(CurrentSession::class);

        return;
    }

    throw new \RuntimeException('Expected request parameter source conflict.');
});

it('rejects mapped request data that collides with CurrentSessionId', function (): void {
    $session = SessionFixture::session('trusted-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody([
            'sessionId' => 'spoofed-session',
            'value' => 'payload-value',
        ]);
    $container = authAppTestContainer();

    try {
        $container->call(
            static fn(
                #[MapRequestPayload]
                AuthAppMappedCommand $command,
            ): AuthAppMappedCommand => $command,
            [ServerRequestInterface::class => $request],
        );
    } catch (RequestParameterSourceConflictException $exception) {
        expect($exception->dtoClass)->toBe(AuthAppMappedCommand::class)
            ->and($exception->key)->toBe('sessionId')
            ->and($exception->source)->toBe(CurrentSessionId::class);

        return;
    }

    throw new \RuntimeException('Expected request parameter source conflict.');
});

it('fails closed when the session request attribute has an invalid type', function (): void {
    $request = (new ServerRequest('GET', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, 'spoofed-session');
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentSessionId] ?string $sessionId): ?string => $sessionId,
        [ServerRequestInterface::class => $request],
    ))->toThrow(ResolutionException::class, 'must implement');
});

it('rejects declared types that cannot accept the resolved value', function (): void {
    $request = SessionFixture::request(SessionFixture::session());
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentSessionId] int $sessionId): int => $sessionId,
        [ServerRequestInterface::class => $request],
    ))->toThrow(ResolutionException::class, 'does not satisfy declared parameter type');
});

it('reads the session from each request without retaining request state', function (): void {
    $container = authAppTestContainer();
    $callable = static fn(#[CurrentSessionId] string $sessionId): string => $sessionId;

    $first = $container->call($callable, [
        ServerRequestInterface::class => SessionFixture::request(SessionFixture::session('session-before-regeneration')),
    ]);
    $second = $container->call($callable, [
        ServerRequestInterface::class => SessionFixture::request(SessionFixture::session('session-after-regeneration')),
    ]);

    expect($first)->toBe('session-before-regeneration')
        ->and($second)->toBe('session-after-regeneration');
});

it('does not leak mapped request context into later resolutions', function (): void {
    $session = SessionFixture::session('scoped-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody([
            'value' => 'payload-value',
        ]);
    $container = authAppTestContainer();

    $container->call(
        static fn(
            #[MapRequestPayload]
            AuthAppMappedCommand $command,
        ): AuthAppMappedCommand => $command,
        [ServerRequestInterface::class => $request],
    );

    expect(fn() => $container->make(AuthAppMappedCommand::class, [
        'value' => 'later-value',
    ]))->toThrow(ResolutionException::class, 'PSR-7 request is required');
});
