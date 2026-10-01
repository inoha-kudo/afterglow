<?php

declare(strict_types=1);

namespace Afterglow;

use Illuminate\Contracts\Cache\Lock;

final readonly class FileLock
{
    public function __construct(
        private Lock $lock,
        private int $waitSeconds = 10,
    ) {}

    public function acquire(): void
    {
        $this->lock->block($this->waitSeconds);
    }

    public function release(): void
    {
        $this->lock->release();
    }
}
