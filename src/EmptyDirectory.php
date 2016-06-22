<?php
namespace Genkgo\ArchiveStream;

use Genkgo\ArchiveStream\Exception\ContentWithoutDataException;

/**
 * Class EmptyDirectory
 * @package Genkgo\ArchiveStream
 */
final class EmptyDirectory implements ContentInterface
{
    /**
     * @var \DateTimeImmutable
     */
    private $modifiedAt;
    /**
     * @var string
     */
    private $name;

    /**
     * @param $name
     * @param \DateTimeImmutable $modifiedAt
     */
    public function __construct($name, \DateTimeImmutable $modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
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
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return ContentInterface::DIRECTORY;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return 'ASCII';
    }
}
