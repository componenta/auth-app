<?php

declare(strict_types=1);

use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\App\ConfigProvider as AuthAppConfigProvider;
use Componenta\Auth\App\Tests\Fixture\SessionFixture;
use Componenta\Config\Config;
use Componenta\DI\ConfigKey;
use Componenta\DI\Container;
use Componenta\DI\ContainerBuilder;
use Componenta\DI\Exception\AttributeCompositionException;
use Componenta\Identity\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CompiledAuthenticationEndpoint
{
    public function __invoke(
        #[CurrentUser] IdentityInterface $user,
        #[CurrentSessionId] string $sessionId,
    ): array {
        return [$user, $sessionId];
    }
}

final readonly class InvalidCompiledAuthenticationState
{
    public function __construct(
        #[CurrentUser]
        public IdentityInterface $user,
    ) {}
}

/** @return array{0: Container, 1: Container, 2: string} */
function authAppParityContainers(): array
{
    $suffix = bin2hex(random_bytes(5));
    $directory = sys_get_temp_dir() . '/componenta-auth-app-parity-' . $suffix;
    $namespace = 'Componenta\\Auth\\App\\Tests\\Generated\\Parity' . $suffix;
    $configData = (new AuthAppConfigProvider())();
    $development = ContainerBuilder::configure(new Config($configData))->build();

    $compiler = ContainerBuilder::configure(new Config($configData));
    $factories = $compiler->compileFactories([
        CompiledAuthenticationEndpoint::class,
    ], $directory, namespace: $namespace);
    $compiledConfig = $compiler->toArray();
    $dependencies = $compiledConfig[ConfigKey::DEPENDENCIES] ?? [];
    $dependencies[ConfigKey::FACTORIES] = array_replace(
        $dependencies[ConfigKey::FACTORIES] ?? [],
        $factories,
    );

    $production = ContainerBuilder::configureFromCache(
        new Config([]),
        [
            'version' => ContainerBuilder::CACHE_VERSION,
            ConfigKey::DEPENDENCIES => $dependencies,
        ],
        $directory,
    )->build();

    return [$development, $production, $directory];
}

function cleanupAuthAppParityDirectory(string $directory): void
{
    foreach (glob($directory . '/container.factories.*.php') ?: [] as $file) {
        @unlink($file);
    }

    if (is_dir($directory)) {
        @rmdir($directory);
    }
}

it('keeps authentication context invocation identical in development and compiled production', function (): void {
    [$development, $production, $directory] = authAppParityContainers();
    $user = SessionFixture::identity();
    $request = SessionFixture::request(SessionFixture::session('compiled-session'), $user);
    $provided = [ServerRequestInterface::class => $request];

    try {
        $devEndpoint = $development->make(CompiledAuthenticationEndpoint::class);
        $prodEndpoint = $production->make(CompiledAuthenticationEndpoint::class);

        $expected = $development->call($devEndpoint, $provided);
        $actual = $production->call($prodEndpoint, $provided);

        expect($actual)->toBe($expected)
            ->and($actual)->toBe([$user, 'compiled-session']);
    } finally {
        cleanupAuthAppParityDirectory($directory);
    }
});

it('keeps invocation-only constructor rejection identical at runtime and AOT preparation', function (): void {
    $config = new Config((new AuthAppConfigProvider())());
    $builder = ContainerBuilder::configure($config);
    $directory = sys_get_temp_dir() . '/componenta-auth-app-invalid-' . bin2hex(random_bytes(5));

    try {
        expect(fn() => $builder->build()->make(InvalidCompiledAuthenticationState::class))
            ->toThrow(AttributeCompositionException::class, 'is invocation-only and cannot target constructor parameter')
            ->and(fn() => $builder->compileFactories([
                InvalidCompiledAuthenticationState::class,
            ], $directory))->toThrow(
                AttributeCompositionException::class,
                'is invocation-only and cannot target constructor parameter',
            );
    } finally {
        cleanupAuthAppParityDirectory($directory);
    }
});
