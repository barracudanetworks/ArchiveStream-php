<?php
namespace Genkgo\ArchiveStream;

/**
 * Class Archive
 * @package Genkgo\ArchiveStream
 */
final class Archive
{
    /**
     * @var ContentInterface[]
     */
    private $content = [];

    /**
     * @var string
     */
    private $comment;

    /**
     * @param ContentInterface $content
     * @return Archive
     */
    public function withContent(ContentInterface $content)
    {
        $clone = clone $this;
        $clone->content[] = $content;
        return $clone;
    }

    /**
     * @param string $comment
     * @return Archive
     */
    public function withComment($comment)
    {
        $clone = clone $this;
        $clone->comment = $comment;
        return $clone;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContents()
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }
}
