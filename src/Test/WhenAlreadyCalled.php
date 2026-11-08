<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

final class WhenAlreadyCalled extends TestError
{
    public function __construct()
    {
        parent::__construct('when() was already called. It runs immediately, so call given() and concurrently() before it and call it only once per test.');
    }
}
