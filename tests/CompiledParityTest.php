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
use Componenta\DI\ConfigKey;
use Componenta\DI\ConfigProvider as DiConfigProvider;
use Componenta\DI\Container;
use Componenta\DI\ContainerBuilder;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CompiledCurrentSessionConsumer
{
    public function __construct(
        #[CurrentSession]
        public SessionInterface $session,
        #[CurrentSessionId]
        public string $sessionId,
    ) {}
}

final readonly class CompiledMappedSessionCommand
{
    public function __construct(
        #[CurrentSession]
        public SessionInterface $session,
        #[CurrentSessionId]
        public string $sessionId,
        public string $value,
    ) {}
}

final readonly class CompiledMappedSessionConsumer
{
    public function __construct(
        #[MapRequestPayload]
        public CompiledMappedSessionCommand $command,
    ) {}
}

function authAppParityProvider(): BaseConfigProvider
{
    return new class () extends BaseConfigProvider {
        protected function getProviders(): array
        {
            return [
                new CasterConfigProvider(),
                new DiConfigProvider(),
                new AuthAppConfigProvider(),
            ];
        }
    };
}

/**
 * @param list<class-string> $entries
 * @return array{0: Container, 1: Container, 2: string}
 */
function authAppParityContainers(array $entries): array
{
    $directory = sys_get_temp_dir() . '/componenta-auth-app-parity-' . bin2hex(random_bytes(5));
    $configData = authAppParityProvider()();
    $development = ContainerBuilder::configure(new Config($configData))->build();

    $compiler = ContainerBuilder::configure(new Config($configData));
    $factories = $compiler->compileFactories($entries, $directory);
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

it('keeps direct current-session resolution identical in development and compiled production', function (): void {
    [$development, $production, $directory] = authAppParityContainers([
        CompiledCurrentSessionConsumer::class,
    ]);
    $session = SessionFixture::session('compiled-session');
    $request = SessionFixture::request($session);
    $provided = [ServerRequestInterface::class => $request];

    try {
        $expected = $development->make(CompiledCurrentSessionConsumer::class, $provided);
        $actual = $production->make(CompiledCurrentSessionConsumer::class, $provided);

        expect($actual->session)->toBe($expected->session)
            ->and($actual->session)->toBe($session)
            ->and($actual->sessionId)->toBe($expected->sessionId)
            ->and($actual->sessionId)->toBe('compiled-session');
    } finally {
        cleanupAuthAppParityDirectory($directory);
    }
});

it('keeps mapped DTO session propagation identical in development and compiled production', function (): void {
    [$development, $production, $directory] = authAppParityContainers([
        CompiledMappedSessionConsumer::class,
    ]);
    $session = SessionFixture::session('trusted-compiled-session');
    $spoofed = SessionFixture::session('spoofed-compiled-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody([
            'session' => $spoofed,
            'sessionId' => 'spoofed-compiled-session',
            'value' => 'payload-value',
        ]);
    $provided = [ServerRequestInterface::class => $request];

    try {
        $expected = $development->make(CompiledMappedSessionConsumer::class, $provided);
        $actual = $production->make(CompiledMappedSessionConsumer::class, $provided);

        expect($actual->command->session)->toBe($expected->command->session)
            ->and($actual->command->session)->toBe($session)
            ->and($actual->command->sessionId)->toBe($expected->command->sessionId)
            ->and($actual->command->sessionId)->toBe('trusted-compiled-session')
            ->and($actual->command->value)->toBe('payload-value');
    } finally {
        cleanupAuthAppParityDirectory($directory);
    }
});
