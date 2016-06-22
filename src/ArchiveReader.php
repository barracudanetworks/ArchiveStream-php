<?php
namespace Genkgo\ArchiveStream;

interface ArchiveReader
{
    /**
     * @param $blockSize
     * @return \Generator|\SplTempFileObject[]
     */
    public function read($blockSize);
}
