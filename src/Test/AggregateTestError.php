<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use RuntimeException;

abstract class AggregateTestError extends RuntimeException
{
}
