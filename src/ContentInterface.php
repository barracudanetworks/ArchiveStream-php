<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

use Genkgo\ArchiveStream\Exception\ContentWithoutDataException;

interface ContentInterface
{
    const FILE = 0;

    const DIRECTORY = 1;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return resource
     * @throws ContentWithoutDataException
     */
    public function getData();

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt(): \DateTimeImmutable;

    /**
     * @return int
     */
    public function getType(): int;

    /**
     * @return string
     */
    public function getEncoding(): string;
}
