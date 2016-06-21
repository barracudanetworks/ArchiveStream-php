<?php
namespace Genkgo\ArchiveStream;

interface ArchiveStream {

    /**
     * @param $blockSize
     * @return \Generator|\SplTempFileObject[]
     */
    public function read($blockSize);

}