<?php

declare(strict_types=1);

namespace Afterglow;

use Doltlite\Doltlite3;
use Doltlite\Doltlite3Result;
use Doltlite\Doltlite3Stmt;

final class AfterglowStmt extends Doltlite3Stmt
{
    /** @var \WeakMap<Doltlite3Result, bool> */
    private readonly \WeakMap $results;

    public function __construct(Doltlite3 $db, string $query)
    {
        $this->results = new \WeakMap;

        parent::__construct($db, $query);
    }

    private function __clone(): void {}

    #[\Override]
    public function execute(): Doltlite3Result
    {
        $result = parent::execute();

        $this->results[$result] = true;

        return $result;
    }

    #[\Override]
    public function close(): bool
    {
        foreach ($this->results as $result => $_) {
            $result->finalize();
        }

        return parent::close();
    }
}
