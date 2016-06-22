<?php
namespace Genkgo\ArchiveStream;

/**
 * Class StringContent
 * @package Genkgo\ArchiveStream
 */
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
     * @param $name
     * @param $fileName
     */
    public function __construct($name, $fileName)
    {
        $this->name = $name;
        $this->fileName = $fileName;
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
        return fopen($this->fileName, 'r');
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt()
    {
        return new \DateTimeImmutable('@' . filemtime($this->fileName));
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
