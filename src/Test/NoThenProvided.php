<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

final class NoThenProvided extends TestError
{
    public function __construct()
    {
        parent::__construct('No then was specified. Please provide what you expect otherwise no real testing is done.');
    }
}
