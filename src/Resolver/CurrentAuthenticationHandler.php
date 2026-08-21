<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Resolver;

use Componenta\Auth\App\Attribute\CurrentSession;
use Componenta\Auth\App\Attribute\CurrentSessionId;
use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\Auth\Session\SessionInterface;
use Componenta\DI\Attribute\Composition\AttributePlan;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\Resolver\Attribute\ParameterAttributeHandlerInterface;
use Componenta\DI\Resolver\Parameter\ParameterAttributeValue;
use Componenta\DI\Resolver\Parameter\ParameterResolutionContext;
use Componenta\DI\Resolver\Target\ParameterTarget;
use Componenta\Identity\IdentityInterface;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/** Resolves authentication context exclusively from the current PSR-7 request. */
final readonly class CurrentAuthenticationHandler implements ParameterAttributeHandlerInterface
{
    public function resolveParameter(
        object $attribute,
        ParameterTarget $target,
        ParameterResolutionContext $context,
        AttributePlan $plan,
        ParameterAttributeValue $value,
    ): ParameterAttributeValue {
        if (!$attribute instanceof CurrentUser
            && !$attribute instanceof CurrentSession
            && !$attribute instanceof CurrentSessionId
        ) {
            throw new LogicException('CurrentAuthenticationHandler received an unsupported parameter attribute.');
        }

        $request = $context->provided[ServerRequestInterface::class] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf('PSR-7 request is required for #[%s]', $attribute::class),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        $resolved = match (true) {
            $attribute instanceof CurrentUser => $this->user($request, $attribute, $target, $context),
            $attribute instanceof CurrentSession => $this->session($request, $target, $context),
            default => $this->sessionId($request, $target, $context),
        };

        if ($resolved !== null && !$target->accepts($resolved)) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'resolved #[%s] value of type %s does not satisfy declared parameter type',
                    $attribute::class,
                    get_debug_type($resolved),
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        return ParameterAttributeValue::resolved($resolved);
    }

    private function user(
        ServerRequestInterface $request,
        CurrentUser $attribute,
        ParameterTarget $target,
        ParameterResolutionContext $context,
    ): ?IdentityInterface {
        $user = $request->getAttribute(IdentityInterface::class);
        if ($user !== null && !$user instanceof IdentityInterface) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'request attribute "%s" must implement %s; got %s',
                    IdentityInterface::class,
                    IdentityInterface::class,
                    get_debug_type($user),
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        if ($user === null) {
            return $this->missing($target, $context, 'current authenticated user is required but unavailable');
        }

        if ($attribute->type !== null && !$user instanceof $attribute->type) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'current authenticated user must be an instance of %s; got %s',
                    $attribute->type,
                    $user::class,
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        return $user;
    }

    private function session(
        ServerRequestInterface $request,
        ParameterTarget $target,
        ParameterResolutionContext $context,
    ): ?SessionInterface {
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
            return $this->missing($target, $context, 'current authenticated session is required but unavailable');
        }

        return $session;
    }

    private function sessionId(
        ServerRequestInterface $request,
        ParameterTarget $target,
        ParameterResolutionContext $context,
    ): ?string {
        return $this->session($request, $target, $context)?->id;
    }

    private function missing(
        ParameterTarget $target,
        ParameterResolutionContext $context,
        string $reason,
    ): null {
        if ($target->allowsNull) {
            return null;
        }

        throw ResolutionException::forParameter(
            $target->reflection,
            reason: $reason,
            providedParameters: $context->provided,
            resolvedParameters: $context->resolved,
        );
    }
}
