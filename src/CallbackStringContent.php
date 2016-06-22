<?php
namespace Genkgo\ArchiveStream;

/**
 * Class CallbackContent
 * @package Genkgo\ArchiveStream
 */
final class CallbackStringContent implements ContentInterface
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var callback
     */
    private $callback;

    /**
     * @param $name
     * @param $callback
     */
    public function __construct($name, callable $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
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
        return fopen('data://text/plain,' . call_user_func($this->callback), 'r');
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
