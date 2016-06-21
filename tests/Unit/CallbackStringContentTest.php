<?php
namespace Genkgo\ArchiveStream\Unit;

use Genkgo\ArchiveStream\AbstractTestCase;
use Genkgo\ArchiveStream\CallbackStringContent;

final class CallbackStringContentTest extends AbstractTestCase {

    public function test () {
        $content = new CallbackStringContent('name', function () {
           return 'data';
        });

        $this->assertEquals('name', $content->getName());
        $this->assertEquals('data', stream_get_contents($content->getData()));
    }
}