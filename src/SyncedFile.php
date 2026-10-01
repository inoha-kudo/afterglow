<?php

declare(strict_types=1);

namespace Afterglow;

final class SyncedFile
{
    private readonly string $path;

    private readonly ?string $originalHash;

    private bool $closed = false;

    private function __construct(
        private readonly FileSynchronizer $synchronizer,
        private readonly ?FileLock $lock,
        bool $readOnly,
    ) {
        $this->path = $this->synchronizer->pull();
        $this->originalHash = $readOnly ? null : $this->hash();
    }

    public static function open(FileSynchronizer $synchronizer, ?FileLock $lock = null, bool $readOnly = false): self
    {
        $lock?->acquire();

        try {
            return new self($synchronizer, $lock, $readOnly);
        } catch (\Throwable $e) {
            $lock?->release();

            throw $e;
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        try {
            if (! file_exists($this->path)) {
                throw new \RuntimeException('Temporary file no longer exists.');
            }

            if ($this->originalHash !== null && $this->hash() !== $this->originalHash) {
                $this->synchronizer->push($this->path);
            }
        } finally {
            $this->discard();
        }
    }

    public function discard(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        try {
            if (is_dir($directory = dirname($this->path))) {
                foreach (new \FilesystemIterator($directory) as $file) {
                    assert($file instanceof \SplFileInfo);

                    unlink($file->getPathname());
                }

                rmdir($directory);
            }
        } finally {
            $this->lock?->release();
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    private function hash(): string
    {
        $hash = hash_file('xxh128', $this->path);

        if ($hash === false) {
            throw new \RuntimeException('Could not calculate XXH128 hash.');
        }

        return $hash;
    }
}
