<?php
namespace Genkgo\ArchiveStream;

/**
 * Class Archive
 * @package Genkgo\ArchiveStream
 */
final class Archive {

    /**
     * @var ContentInterface[]
     */
    private $content = [];

    /**
     * @var array
     */
    private $directories = [];

    /**
     * @param ContentInterface $content
     */
    public function addContent(ContentInterface $content) {
        $this->content[] = $content;
    }

    /**
     * @param $name
     */
    public function addDirectory($name) {
        $this->directories[] = $name;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContents()
    {
        return $this->content;
    }

    /**
     * @return array
     */
    public function getDirectories()
    {
        return $this->directories;
    }
}