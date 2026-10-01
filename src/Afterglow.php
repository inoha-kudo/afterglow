<?php

declare(strict_types=1);

namespace Afterglow;

use Doltlite\Doltlite3;
use Doltlite\Doltlite3Stmt;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class Afterglow extends Doltlite3
{
    /** @var \WeakMap<Doltlite3Stmt, bool> */
    private readonly \WeakMap $statements;

    private readonly SyncedFile $file;

    /**
     * @param  array<mixed>  $overrides
     */
    public function __construct(
        int $flags = DOLTLITE3_OPEN_READWRITE | DOLTLITE3_OPEN_CREATE,
        array $overrides = [],
    ) {
        $this->statements = new \WeakMap;

        $config = array_replace_recursive(Config::array('afterglow.connection'), $overrides);

        if ($readOnly = ($flags & DOLTLITE3_OPEN_READONLY) !== 0) {
            $config = array_replace_recursive($config, [
                'lock' => ['store' => null],
            ]);
        }

        $this->file = $this->openSyncedFile($config, $readOnly);

        try {
            parent::__construct($this->file->path(), $flags);
        } catch (\Throwable $e) {
            $this->file->discard();

            throw $e;
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable) {
            try {
                $this->file->discard();
            } catch (\Throwable) {
                return;
            }
        }
    }

    private function __clone(): void {}

    #[\Override]
    public function close(): bool
    {
        foreach ($this->statements as $statement => $_) {
            $statement->close();
        }

        parent::close();

        $this->file->close();

        return true;
    }

    #[\Override]
    public function prepare(string $query): Doltlite3Stmt
    {
        $statement = new AfterglowStmt($this, $query);

        $this->statements[$statement] = true;

        return $statement;
    }

    /**
     * @param  array<mixed>  $config
     */
    private function openSyncedFile(array $config, bool $readOnly): SyncedFile
    {
        [$disk, $path] = [
            Arr::string($config, 'disk'), Arr::string($config, 'path'),
        ];

        return SyncedFile::open(
            new FileSynchronizer(Storage::disk($disk), $path),
            $this->createLock($config, 'afterglow:'.sha1($disk.':'.$path)),
            $readOnly,
        );
    }

    /**
     * @param  array<mixed>  $config
     */
    private function createLock(array $config, string $name): ?FileLock
    {
        if (Arr::get($config, 'lock.store') === null) {
            return null;
        }

        $provider = Cache::store(Arr::string($config, 'lock.store'))->getStore();

        assert($provider instanceof LockProvider);

        return new FileLock(
            $provider->lock($name, Arr::integer($config, 'lock.seconds', 0)),
            Arr::integer($config, 'lock.wait_seconds', 10),
        );
    }
}
