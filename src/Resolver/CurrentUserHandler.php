<?php

declare(strict_types=1);

namespace Componenta\Auth\App\Resolver;

use Componenta\Auth\App\Attribute\CurrentUser;
use Componenta\DI\Attribute\Composition\AttributePlan;
use Componenta\DI\Exception\ResolutionException;
use Componenta\DI\Resolver\Attribute\ParameterAttributeHandlerInterface;
use Componenta\DI\Resolver\Parameter\ParameterAttributeValue;
use Componenta\DI\Resolver\Parameter\ParameterResolutionContext;
use Componenta\DI\Resolver\Target\ParameterTarget;
use Componenta\Identity\IdentityInterface;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/** Resolves the authenticated identity exclusively from the current PSR-7 request. */
final readonly class CurrentUserHandler implements ParameterAttributeHandlerInterface
{
    public function resolveParameter(
        object $attribute,
        ParameterTarget $target,
        ParameterResolutionContext $context,
        AttributePlan $plan,
        ParameterAttributeValue $value,
    ): ParameterAttributeValue {
        if (!$attribute instanceof CurrentUser) {
            throw new LogicException(
                'CurrentUserHandler received an unsupported parameter attribute.',
            );
        }

        $request = $context->provided[ServerRequestInterface::class] ?? null;

        if (!$request instanceof ServerRequestInterface) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'PSR-7 request is required for #[%s]',
                    $attribute::class,
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

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
            if ($target->allowsNull) {
                return ParameterAttributeValue::resolved(null);
            }

            throw ResolutionException::forParameter(
                $target->reflection,
                reason: 'current authenticated user is required but unavailable',
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
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

        if (!$target->accepts($user)) {
            throw ResolutionException::forParameter(
                $target->reflection,
                reason: sprintf(
                    'resolved #[%s] value of type %s does not satisfy declared parameter type',
                    $attribute::class,
                    $user::class,
                ),
                providedParameters: $context->provided,
                resolvedParameters: $context->resolved,
            );
        }

        return ParameterAttributeValue::resolved($user);
    }
}
