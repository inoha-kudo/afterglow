<?php

declare(strict_types=1);

namespace Afterglow;

use Doltlite\Doltlite3;
use Doltlite\Doltlite3Stmt;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class Afterglow extends Doltlite3
{
    /** @var \WeakMap<Doltlite3Stmt, bool> */
    private readonly \WeakMap $statements;

    private readonly SyncedFile $file;

    public function __construct(int $flags = DOLTLITE3_OPEN_READWRITE | DOLTLITE3_OPEN_CREATE)
    {
        $this->statements = new \WeakMap;

        $config = Config::array('afterglow.connection');

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
            $config['disk'] ?? null, $config['path'] ?? null,
        ];

        assert(is_string($disk));
        assert(is_string($path));

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
        $lock = $config['lock'] ?? [];

        assert(is_array($lock));

        if (is_null($store = $lock['store'] ?? null)) {
            return null;
        }

        [$seconds, $waitSeconds] = [
            $lock['seconds'] ?? 0, $lock['wait_seconds'] ?? 10,
        ];

        assert(is_string($store));
        assert(is_int($seconds));
        assert(is_int($waitSeconds));

        $provider = Cache::store($store)->getStore();

        assert($provider instanceof LockProvider);

        return new FileLock($provider->lock($name, $seconds), $waitSeconds);
    }
}
