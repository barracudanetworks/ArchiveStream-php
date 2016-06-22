<?php
namespace Genkgo\ArchiveStream;

use Genkgo\ArchiveStream\Exception\ContentWithoutDataException;

interface ContentInterface
{
    const FILE = 0;
    const DIRECTORY = 1;

    /**
     * @return string
     */
    public function getName();

    /**
     * @return resource
     * @throws ContentWithoutDataException
     */
    public function getData();

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt();

    /**
     * @return int
     */
    public function getType();

    /**
     * @return string
     */
    public function getEncoding();
}
