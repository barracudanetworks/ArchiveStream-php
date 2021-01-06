<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

final class Archive
{
    /**
     * @var ContentInterface[]
     */
    private $content = [];

    /**
     * @var string
     */
    private $comment = '';

    /**
     * @param ContentInterface $content
     * @return Archive
     */
    public function withContent(ContentInterface $content): Archive
    {
        $clone = clone $this;
        $clone->content[] = $content;
        return $clone;
    }

    /**
     * @param string $comment
     * @return Archive
     */
    public function withComment(string $comment): Archive
    {
        $clone = clone $this;
        $clone->comment = $comment;
        return $clone;
    }

    /**
     * @return array<int, ContentInterface>
     */
    public function getContents(): array
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }
}
