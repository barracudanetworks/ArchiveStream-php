<?php declare(strict_types=1);
namespace Genkgo\ArchiveStream;

/**
 * Class StringContent
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
     * @param string $name
     * @param string $data
     */
    public function __construct(string $name, string $data)
    {
        $this->name = $name;
        $this->data = $data;
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

        \fwrite($resource, $this->data);
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
