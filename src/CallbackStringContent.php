<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

final class CallbackStringContent implements ContentInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param string $name
     * @param callable $callback
     */
    public function __construct(string $name, callable $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return resource
     */
    public function getData()
    {
        $resource = \fopen('php://memory', 'r+');
        if (!$resource) {
            throw new \UnexpectedValueException('Cannot create in-memory resource');
        }

        \fwrite($resource, \call_user_func($this->callback));
        \rewind($resource);
        return $resource;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return ContentInterface::FILE;
    }

    /**
     * @return string
     */
    public function getEncoding(): string
    {
        return 'UTF-8';
    }
}
