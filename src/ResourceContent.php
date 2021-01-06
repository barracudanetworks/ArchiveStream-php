<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

final class ResourceContent implements ContentInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var resource
     */
    private $resource;

    /**
     * @param string $name
     * @param resource $resource
     */
    public function __construct(string $name, $resource)
    {
        $this->name = $name;
        $this->resource = $resource;
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
        return $this->resource;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
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
