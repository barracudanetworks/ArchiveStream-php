<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

use Genkgo\ArchiveStream\Exception\ContentWithoutDataException;

final class EmptyDirectory implements ContentInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
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
     * @throws ContentWithoutDataException
     */
    public function getData()
    {
        throw new ContentWithoutDataException('Empty directory has no data');
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
        return ContentInterface::DIRECTORY;
    }

    /**
     * @return string
     */
    public function getEncoding(): string
    {
        return 'ASCII';
    }
}
