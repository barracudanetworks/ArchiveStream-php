<?php
namespace Genkgo\ArchiveStream;

use Psr\Http\Message\StreamInterface;

/**
 * Class Psr7Stream
 * @package Genkgo\ArchiveStream
 */
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
     * @var \Generator
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
    public function __construct(ArchiveReader $delegatedStream, $blockSize = 1048576)
    {
        $this->delegatedStream = $delegatedStream;
        $this->blockSize = $blockSize;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
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
    public function close()
    {
        return;
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
    public function getSize()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return $this->generator !== null && $this->generator->valid() === false;
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException('Cannot seek archive stream');
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->generator = null;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function write($string)
    {
        throw new \RuntimeException('Cannot write to archive stream');
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        $this->initializeBeforeRead();

        if (!$this->generator->valid()) {
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

        return $this->resource->fread($length);
    }

    /**
     * {@inheritdoc}
     */
    public function getContents()
    {
        $data = '';

        while ($this->eof() === false) {
            $data .= $this->read($this->blockSize);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        return [];
    }

    /**
     *
     */
    private function initializeBeforeRead()
    {
        if ($this->generator === null) {
            $this->generator = $this->delegatedStream->read($this->blockSize);
            $this->resource = $this->generator->current();
            $this->resource->rewind();
        }
    }
}
