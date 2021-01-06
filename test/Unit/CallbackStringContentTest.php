<?php

declare(strict_types=1);

namespace Genkgo\TestArchiveStream\Unit;

use Genkgo\ArchiveStream\CallbackStringContent;
use Genkgo\TestArchiveStream\AbstractTestCase;

final class CallbackStringContentTest extends AbstractTestCase
{
    public function test()
    {
        $content = new CallbackStringContent('name', function () {
            return 'data';
        });

        $this->assertEquals('name', $content->getName());
        $this->assertEquals('data', \stream_get_contents($content->getData()));
    }
}
