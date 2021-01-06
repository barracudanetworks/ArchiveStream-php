<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream;

interface ArchiveReader
{
    /**
     * @param int $blockSize
     * @return \Generator<int, \SplTempFileObject>
     */
    public function read(int $blockSize): \Generator;
}
