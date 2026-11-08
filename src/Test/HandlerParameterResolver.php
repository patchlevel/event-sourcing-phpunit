<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Patchlevel\EventSourcing\CommandBus\Handler\ParameterResolver;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use ReflectionMethod;
use ReflectionNamedType;

use function array_shift;

/**
 * Passes a new decision to every parameter of type Decision and the extra parameters of when() to all the others.
 *
 * @internal
 *
 * @experimental
 */
final class HandlerParameterResolver implements ParameterResolver
{
    /** @param array<mixed> $parameters */
    public function __construct(
        private readonly DecisionFactory $decisionFactory,
        private readonly array $parameters,
    ) {
    }

    /** @return iterable<int, mixed> */
    public function resolve(ReflectionMethod $method, object $command): iterable
    {
        $parameters = $this->parameters;

        yield $command;

        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Decision::class) {
                yield $this->decisionFactory->begin();

                continue;
            }

            if ($parameters === []) {
                return;
            }

            yield array_shift($parameters);
        }
    }
}
