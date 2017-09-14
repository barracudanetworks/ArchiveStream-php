<?php
namespace Genkgo\ArchiveStream;

/**
 * Class StringContent
 * @package Genkgo\ArchiveStream
 */
final class StringContent implements ContentInterface
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $data;

    /**
     * @param $name
     * @param $data
     */
    public function __construct($name, $data)
    {
        $this->name = $name;
        $this->data = $data;
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
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $this->data);
        rewind($resource);
        return $resource;
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
