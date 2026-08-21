<?php

declare(strict_types=1);

use Attribute;
use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\ConfigProvider as AuthAppConfigProvider;
use Componenta\Auth\App\Tests\Fixture\IdentityFixture;
use Componenta\Auth\App\Tests\Fixture\SessionFixture;
use Componenta\Auth\Session\SessionInterface;
use Componenta\Config\Config;
use Componenta\DI\Container;
use Componenta\DI\ContainerBuilder;
use Componenta\DI\Exception\AttributeCompositionException;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\Resolver\Parameter\ParameterSourceAttributeInterface;
use Componenta\Identity\IdentityInterface;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

function authAppTestContainer(): Container
{
    return ContainerBuilder::configure(
        new Config((new AuthAppConfigProvider())()),
    )->build();
}

it('declares authentication context attributes as parameter sources only', function (): void {
    foreach ([CurrentUser::class, CurrentSession::class, CurrentSessionId::class] as $attribute) {
        $metadata = (new ReflectionClass($attribute))->getAttributes(Attribute::class)[0]->newInstance();

        expect($metadata->flags)->toBe(Attribute::TARGET_PARAMETER)
            ->and(is_a($attribute, ParameterSourceAttributeInterface::class, true))->toBeTrue();
    }
});

it('reads the authenticated user and session exclusively from the current request', function (): void {
    $user = SessionFixture::identity();
    $session = SessionFixture::session('session-42');
    $request = SessionFixture::request($session, $user);
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(
            #[CurrentUser] IdentityInterface $currentUser,
            #[CurrentSession] SessionInterface $currentSession,
            #[CurrentSessionId] string $sessionId,
        ): array => [$currentUser, $currentSession, $sessionId],
        [ServerRequestInterface::class => $request],
    );

    expect($resolved[0])->toBe($user)
        ->and($resolved[1])->toBe($session)
        ->and($resolved[2])->toBe('session-42');
});

it('supports an explicit CurrentUser subtype requirement', function (): void {
    $user = SessionFixture::identity();
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(
            #[CurrentUser(IdentityFixture::class)] IdentityInterface $currentUser,
        ): IdentityInterface => $currentUser,
        [ServerRequestInterface::class => SessionFixture::request(identity: $user)],
    );

    expect($resolved)->toBe($user);
});

it('returns null for nullable authentication context when the request is anonymous', function (): void {
    $container = authAppTestContainer();
    $request = SessionFixture::request();

    $resolved = $container->call(
        static fn(
            #[CurrentUser] ?IdentityInterface $user,
            #[CurrentSession] ?SessionInterface $session,
            #[CurrentSessionId] ?string $sessionId,
        ): array => [$user, $session, $sessionId],
        [ServerRequestInterface::class => $request],
    );

    expect($resolved)->toBe([null, null, null]);
});

it('fails for required authentication context when the request is anonymous', function (): void {
    $container = authAppTestContainer();
    $request = SessionFixture::request();

    expect(fn() => $container->call(
        static fn(#[CurrentUser] IdentityInterface $user): IdentityInterface => $user,
        [ServerRequestInterface::class => $request],
    ))->toThrow(ResolutionException::class, 'current authenticated user is required but unavailable')
        ->and(fn() => $container->call(
            static fn(#[CurrentSessionId] string $sessionId): string => $sessionId,
            [ServerRequestInterface::class => $request],
        ))->toThrow(ResolutionException::class, 'current authenticated session is required but unavailable');
});

it('requires a PSR-7 request even for nullable targets', function (): void {
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentUser] ?IdentityInterface $user): ?IdentityInterface => $user,
    ))->toThrow(ResolutionException::class, 'PSR-7 request is required')
        ->and(fn() => $container->call(
            static fn(#[CurrentSessionId] ?string $sessionId): ?string => $sessionId,
        ))->toThrow(ResolutionException::class, 'PSR-7 request is required');
});

it('does not let caller parameters shadow trusted authentication context', function (): void {
    $trustedUser = SessionFixture::identity();
    $spoofedUser = SessionFixture::identity('0198b914-a800-7000-8000-000000000002');
    $trustedSession = SessionFixture::session('trusted-session');
    $request = SessionFixture::request($trustedSession, $trustedUser);
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(
            #[CurrentUser] IdentityInterface $user,
            #[CurrentSessionId] string $sessionId,
        ): array => [$user, $sessionId],
        [
            ServerRequestInterface::class => $request,
            'user' => $spoofedUser,
            IdentityInterface::class => $spoofedUser,
            'sessionId' => 'spoofed-session',
        ],
    );

    expect($resolved)->toBe([$trustedUser, 'trusted-session']);
});

it('fails closed when authentication request attributes have invalid types', function (): void {
    $container = authAppTestContainer();
    $invalidUserRequest = (new ServerRequest('GET', '/'))
        ->withAttribute(IdentityInterface::class, 'spoofed-user');
    $invalidSessionRequest = (new ServerRequest('GET', '/'))
        ->withAttribute(SessionInterface::class, 'spoofed-session');

    expect(fn() => $container->call(
        static fn(#[CurrentUser] ?IdentityInterface $user): ?IdentityInterface => $user,
        [ServerRequestInterface::class => $invalidUserRequest],
    ))->toThrow(ResolutionException::class, 'must implement')
        ->and(fn() => $container->call(
            static fn(#[CurrentSession] ?SessionInterface $session): ?SessionInterface => $session,
            [ServerRequestInterface::class => $invalidSessionRequest],
        ))->toThrow(ResolutionException::class, 'must implement');
});

it('reads fresh authentication context from every request without a provider or retained state', function (): void {
    $container = authAppTestContainer();
    $callable = static fn(
        #[CurrentUser] IdentityInterface $user,
        #[CurrentSessionId] string $sessionId,
    ): array => [$user, $sessionId];

    $firstUser = SessionFixture::identity();
    $secondUser = SessionFixture::identity('0198b914-a800-7000-8000-000000000002');

    $first = $container->call($callable, [
        ServerRequestInterface::class => SessionFixture::request(
            SessionFixture::session('session-one'),
            $firstUser,
        ),
    ]);
    $second = $container->call($callable, [
        ServerRequestInterface::class => SessionFixture::request(
            SessionFixture::session('session-two'),
            $secondUser,
        ),
    ]);

    expect($first)->toBe([$firstUser, 'session-one'])
        ->and($second)->toBe([$secondUser, 'session-two']);
});

it('rejects authentication context attributes on constructors', function (): void {
    $container = authAppTestContainer();

    expect(fn() => $container->make(AuthUserConstructorTarget::class))
        ->toThrow(AttributeCompositionException::class, 'is invocation-only and cannot target constructor parameter')
        ->and(fn() => $container->make(AuthSessionConstructorTarget::class))
        ->toThrow(AttributeCompositionException::class, 'is invocation-only and cannot target constructor parameter');
});

final readonly class AuthUserConstructorTarget
{
    public function __construct(
        #[CurrentUser]
        public IdentityInterface $user,
    ) {}
}

final readonly class AuthSessionConstructorTarget
{
    public function __construct(
        #[CurrentSession]
        public SessionInterface $session,
    ) {}
}
