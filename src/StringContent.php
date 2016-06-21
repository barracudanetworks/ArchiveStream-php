<?php
namespace Genkgo\ArchiveStream;

/**
 * Class StringContent
 * @package Genkgo\ArchiveStream
 */
final class StringContent implements ContentInterface {

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
        return fopen('data://text/plain,' . $this->data, 'r');
    }
}