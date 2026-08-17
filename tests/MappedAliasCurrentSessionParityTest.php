<?php

declare(strict_types=1);

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
use Componenta\DI\Exception\RequestParameterSourceConflictException;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

interface MappedAliasSessionCommandContract {}

final readonly class MappedAliasSessionCommand implements MappedAliasSessionCommandContract
{
    public function __construct(
        #[CurrentSessionId]
        public string $sessionId,
        public string $value,
    ) {}
}

final readonly class MappedAliasSessionEntry
{
    public function __construct(
        #[MapRequestPayload]
        public MappedAliasSessionCommandContract $command,
    ) {}
}

function mappedAliasSessionProvider(): BaseConfigProvider
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

function mappedAliasSessionBuilder(): ContainerBuilder
{
    return ContainerBuilder::configure(
        new Config(mappedAliasSessionProvider()()),
    )->addAlias(
        MappedAliasSessionCommandContract::class,
        MappedAliasSessionCommand::class,
    );
}

/** @return array{0: Container, 1: Container, 2: string} */
function mappedAliasSessionContainers(): array
{
    $suffix = bin2hex(random_bytes(5));
    $directory = sys_get_temp_dir() . '/componenta-auth-app-mapped-alias-' . $suffix;
    $namespace = 'Componenta\\Auth\\App\\Tests\\Generated\\MappedAlias' . $suffix;
    $configData = mappedAliasSessionProvider()();
    $configData[ConfigKey::DEPENDENCIES][ConfigKey::ALIASES][MappedAliasSessionCommandContract::class]
        = MappedAliasSessionCommand::class;

    $development = ContainerBuilder::configure(new Config($configData))->build();
    $compiler = ContainerBuilder::configure(new Config($configData));
    $factories = $compiler->compileFactories(
        [MappedAliasSessionEntry::class, MappedAliasSessionCommand::class],
        $directory,
        namespace: $namespace,
    );
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

function cleanupMappedAliasSessionDirectory(string $directory): void
{
    foreach (glob($directory . '/container.factories.*.php') ?: [] as $file) {
        @unlink($file);
    }

    if (is_dir($directory)) {
        @rmdir($directory);
    }
}

/** @return array{class-string, string, class-string, string} */
function mappedAliasSessionConflictSnapshot(
    Container $container,
    ServerRequestInterface $request,
): array {
    try {
        $container->make(MappedAliasSessionEntry::class, [
            ServerRequestInterface::class => $request,
        ]);
    } catch (RequestParameterSourceConflictException $exception) {
        return [
            $exception::class,
            $exception->key,
            $exception->source,
            $exception->parameter ?? '',
        ];
    }

    throw new RuntimeException('Expected mapped current-session source conflict.');
}

it('rejects mapped session id spoofing after the mapper type resolves through an alias', function (): void {
    $session = SessionFixture::session('trusted-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody([
            'sessionId' => 'spoofed-session',
            'value' => 'payload-value',
        ]);

    $snapshot = mappedAliasSessionConflictSnapshot(
        mappedAliasSessionBuilder()->build(),
        $request,
    );

    expect($snapshot[1])->toBe('sessionId')
        ->and($snapshot[2])->toBe(CurrentSessionId::class)
        ->and($snapshot[3])->toBe('sessionId');
});

it('injects the trusted session id into an aliased mapped command without a collision', function (): void {
    $session = SessionFixture::session('trusted-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody(['value' => 'payload-value']);
    $entry = mappedAliasSessionBuilder()->build()->make(MappedAliasSessionEntry::class, [
        ServerRequestInterface::class => $request,
    ]);

    expect($entry->command)->toBeInstanceOf(MappedAliasSessionCommand::class)
        ->and($entry->command->sessionId)->toBe('trusted-session')
        ->and($entry->command->value)->toBe('payload-value');
});

it('keeps aliased current-session source conflicts identical in development and compiled production', function (): void {
    [$development, $production, $directory] = mappedAliasSessionContainers();
    $session = SessionFixture::session('trusted-session');
    $request = (new ServerRequest('POST', 'https://example.test/'))
        ->withAttribute(SessionInterface::class, $session)
        ->withParsedBody([
            'sessionId' => 'spoofed-session',
            'value' => 'payload-value',
        ]);

    try {
        expect(mappedAliasSessionConflictSnapshot($production, $request))
            ->toBe(mappedAliasSessionConflictSnapshot($development, $request));
    } finally {
        cleanupMappedAliasSessionDirectory($directory);
    }
});
