<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

final class NoWhenProvided extends AggregateTestError
{
    public function __construct()
    {
        parent::__construct('No when was specified. Please provide what should happen otherwise no real testing is done.');
    }
}
