<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Resolver;

use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\Session\SessionInterface;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\Resolver\Parameter\ParameterResolutionContext;
use Componenta\DI\Resolver\Parameter\ParameterResolverInterface;
use Componenta\DI\Resolver\Parameter\Request\RequestParameter;
use Componenta\DI\Resolver\Target\ParameterTarget;

/** Resolves trusted session values established by the authentication middleware. */
final class CurrentSessionResolver implements ParameterResolverInterface
{
    /**
     * Must run before generic caller-provided and castable resolvers so request
     * input cannot shadow an authenticated session value.
     */
    public const int PRIORITY = 1300;

    #[\Override]
    public function supports(ParameterTarget $target): bool
    {
        return $target->hasAttribute(CurrentSession::class)
            || $target->hasAttribute(CurrentSessionId::class);
    }

    #[\Override]
    public function resolveParameter(
        ParameterTarget $target,
        ParameterResolutionContext $context,
    ): ?array {
        $currentSession = $target->hasAttribute(CurrentSession::class);
        $currentSessionId = $target->hasAttribute(CurrentSessionId::class);

        if (!$currentSession && !$currentSessionId) {
            return null;
        }

        if ($currentSession && $currentSessionId) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'attributes #[%s] and #[%s] cannot be combined on the same parameter',
                    CurrentSession::class,
                    CurrentSessionId::class,
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        $request = RequestParameter::get($context->provided);
        if ($request === null) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'PSR-7 request is required for #[%s]',
                    $currentSession ? CurrentSession::class : CurrentSessionId::class,
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        $session = $request->getAttribute(SessionInterface::class);

        if ($session !== null && !$session instanceof SessionInterface) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'request attribute "%s" must implement %s; got %s',
                    SessionInterface::class,
                    SessionInterface::class,
                    get_debug_type($session),
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        if ($session === null) {
            if ($target->allowsNull) {
                return [$target->position, null];
            }

            throw ResolutionException::forParameter(
                $target->reflection,
                reason: 'current authenticated session is required but unavailable',
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        $value = $currentSession ? $session : $session->id;

        if (!$target->accepts($value)) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'resolved current session value of type %s does not satisfy declared parameter type',
                    get_debug_type($value),
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        return [$target->position, $value];
    }
}
