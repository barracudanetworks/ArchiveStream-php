<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

final class FileContent implements ContentInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $fileName;

    /**
     * @param string $nameInArchive
     * @param string $sourceFileName
     */
    public function __construct(string $nameInArchive, string $sourceFileName)
    {
        $this->name = $nameInArchive;
        $this->fileName = $sourceFileName;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return resource
     */
    public function getData()
    {
        $resource = \fopen($this->fileName, 'r');
        if (!$resource) {
            throw new \UnexpectedValueException('Cannot create resource, cannot open file ' . $this->fileName);
        }

        return $resource;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . \filemtime($this->fileName));
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return ContentInterface::FILE;
    }

    /**
     * @return string
     */
    public function getEncoding(): string
    {
        return 'UTF-8';
    }
}
