<?php

declare(strict_types=1);

use Attribute;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\ConfigProvider as AuthAppConfigProvider;
use Componenta\Auth\App\Tests\Fixture\IdentityFixture;
use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Container;
use Componenta\DI\ContainerFactory;
use Componenta\DI\Exception\AttributeCompositionException;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\Resolver\Parameter\ParameterSourceAttributeInterface;
use Componenta\Identity\IdentityInterface;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

function authAppTestContainer(): Container
{
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new AuthAppConfigProvider(),
    );

    return (new ContainerFactory())->create(
        $composition->config,
        $composition->dependencies,
    )->container;
}

it('declares CurrentUser as an invocation-only parameter source', function (): void {
    $metadata = (new ReflectionClass(CurrentUser::class))
        ->getAttributes(Attribute::class)[0]
        ->newInstance();

    expect($metadata->flags)->toBe(Attribute::TARGET_PARAMETER)
        ->and(is_a(CurrentUser::class, ParameterSourceAttributeInterface::class, true))->toBeTrue();
});

it('reads the authenticated user exclusively from the current request', function (): void {
    $user = IdentityFixture::create();
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(
            #[CurrentUser] IdentityInterface $currentUser,
        ): IdentityInterface => $currentUser,
        [ServerRequestInterface::class => IdentityFixture::request($user)],
    );

    expect($resolved)->toBe($user);
});

it('supports an explicit CurrentUser subtype requirement', function (): void {
    $user = IdentityFixture::create();
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(
            #[CurrentUser(IdentityFixture::class)] IdentityInterface $currentUser,
        ): IdentityInterface => $currentUser,
        [ServerRequestInterface::class => IdentityFixture::request($user)],
    );

    expect($resolved)->toBe($user);
});

it('returns null for a nullable CurrentUser on an anonymous request', function (): void {
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(
            #[CurrentUser] ?IdentityInterface $user,
        ): ?IdentityInterface => $user,
        [ServerRequestInterface::class => IdentityFixture::request()],
    );

    expect($resolved)->toBeNull();
});

it('fails for a required CurrentUser on an anonymous request', function (): void {
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentUser] IdentityInterface $user): IdentityInterface => $user,
        [ServerRequestInterface::class => IdentityFixture::request()],
    ))->toThrow(
        ResolutionException::class,
        'current authenticated user is required but unavailable',
    );
});

it('requires a PSR-7 request even for nullable targets', function (): void {
    $container = authAppTestContainer();

    expect(fn() => $container->call(
        static fn(#[CurrentUser] ?IdentityInterface $user): ?IdentityInterface => $user,
    ))->toThrow(ResolutionException::class, 'PSR-7 request is required');
});

it('does not let caller parameters shadow the trusted authenticated identity', function (): void {
    $trusted = IdentityFixture::create();
    $spoofed = IdentityFixture::create('0198b914-a800-7000-8000-000000000002');
    $container = authAppTestContainer();

    $resolved = $container->call(
        static fn(#[CurrentUser] IdentityInterface $user): IdentityInterface => $user,
        [
            ServerRequestInterface::class => IdentityFixture::request($trusted),
            'user' => $spoofed,
            IdentityInterface::class => $spoofed,
        ],
    );

    expect($resolved)->toBe($trusted);
});

it('fails closed when the identity request attribute has an invalid type', function (): void {
    $container = authAppTestContainer();
    $request = (new ServerRequest('GET', '/'))
        ->withAttribute(IdentityInterface::class, 'spoofed-user');

    expect(fn() => $container->call(
        static fn(#[CurrentUser] ?IdentityInterface $user): ?IdentityInterface => $user,
        [ServerRequestInterface::class => $request],
    ))->toThrow(ResolutionException::class, 'must implement');
});

it('reads fresh identity state from every request without retained state', function (): void {
    $container = authAppTestContainer();
    $callable = static fn(
        #[CurrentUser] IdentityInterface $user,
    ): IdentityInterface => $user;

    $firstUser = IdentityFixture::create();
    $secondUser = IdentityFixture::create('0198b914-a800-7000-8000-000000000002');

    $first = $container->call($callable, [
        ServerRequestInterface::class => IdentityFixture::request($firstUser),
    ]);
    $second = $container->call($callable, [
        ServerRequestInterface::class => IdentityFixture::request($secondUser),
    ]);

    expect($first)->toBe($firstUser)
        ->and($second)->toBe($secondUser);
});

it('rejects CurrentUser on constructors', function (): void {
    $container = authAppTestContainer();

    expect(fn() => $container->make(AuthUserConstructorTarget::class))
        ->toThrow(
            AttributeCompositionException::class,
            'is invocation-only and cannot target constructor parameter',
        );
});

final readonly class AuthUserConstructorTarget
{
    public function __construct(
        #[CurrentUser]
        public IdentityInterface $user,
    ) {}
}
