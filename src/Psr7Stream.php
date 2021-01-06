<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

use Psr\Http\Message\StreamInterface;

final class Psr7Stream implements StreamInterface
{
    /**
     * @var ArchiveReader
     */
    private $delegatedStream;

    /**
     * @var int
     */
    private $blockSize;

    /**
     * @var ?\Generator<int, \SplTempFileObject>
     */
    private $generator;

    /**
     * @var int
     */
    private $position = 0;

    /**
     * @var \SplTempFileObject
     */
    private $resource;

    /**
     * @param ArchiveReader $delegatedStream
     * @param int $blockSize
     */
    public function __construct(ArchiveReader $delegatedStream, int $blockSize = 1048576)
    {
        $this->delegatedStream = $delegatedStream;
        $this->blockSize = $blockSize;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->getContents();
        } catch (\RuntimeException $e) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        return $this->generator !== null && $this->generator->valid() === false;
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Cannot seek archive stream');
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->generator = null;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function write($string): int
    {
        throw new \RuntimeException('Cannot write to archive stream');
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length): string
    {
        $this->initializeBeforeRead();

        if (!$this->generator || !$this->generator->valid()) {
            return '';
        }

        if ($this->resource->eof()) {
            $this->generator->next();
            if (!$this->generator->valid()) {
                return '';
            }

            $this->resource = $this->generator->current();
            $this->resource->rewind();
        }

        $data = $this->resource->fread($length);
        if ($data === false) {
            return '';
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        $data = '';

        while ($this->eof() === false) {
            $data .= $this->read($this->blockSize);
        }

        return $data;
    }

    /**
     * @param null|string $key
     * @return array<string, mixed>
     */
    public function getMetadata($key = null): array
    {
        return [];
    }

    private function initializeBeforeRead(): void
    {
        if ($this->generator === null) {
            $this->generator = $this->delegatedStream->read($this->blockSize);
            $this->resource = $this->generator->current();
            $this->resource->rewind();
        }
    }
}
