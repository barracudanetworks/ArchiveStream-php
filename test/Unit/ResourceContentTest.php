<?php

declare(strict_types=1);

namespace Genkgo\TestArchiveStream\Unit;

use Genkgo\ArchiveStream\ResourceContent;
use Genkgo\TestArchiveStream\AbstractTestCase;

final class ResourceContentTest extends AbstractTestCase
{
    public function test()
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'str');
        \file_put_contents($tempFile, 'data');

        $content = new ResourceContent('file.txt', \fopen($tempFile, 'r'));

        $this->assertEquals('file.txt', $content->getName());
        $this->assertEquals('data', \stream_get_contents($content->getData()));
    }
}
