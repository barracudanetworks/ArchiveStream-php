<?php
namespace Genkgo\ArchiveStream;

/**
 * Class ResourceContent
 * @package Genkgo\ArchiveStream
 */
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
     * @param $name
     * @param $resource
     */
    public function __construct($name, $resource)
    {
        $this->name = $name;
        $this->resource = $resource;
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
     */
    public function getData()
    {
        return $this->resource;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt()
    {
        return new \DateTimeImmutable();
    }

    /**
     * @return int
     */
    public function getType()
    {
        return ContentInterface::FILE;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return 'UTF-8';
    }
}
