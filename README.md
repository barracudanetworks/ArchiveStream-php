# ArchiveStream Message Body (PSR-7)

Stream a ZIP file (memory efficient) as a PSR-7 message

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/genkgo/archive-stream/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/genkgo/archive-stream/)
[![Code Coverage](https://scrutinizer-ci.com/g/genkgo/archive-stream/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/genkgo/archive-stream/)
[![Build Status](https://travis-ci.org/genkgo/archive-stream.png?branch=master)](https://travis-ci.org/genkgo/archive-stream)

## Installation

Use composer to add the package to your dependencies.
```sh
composer require genkgo/archive-stream
```


## Getting Started

```php
<?php
use Genkgo\ArchiveStream\Archive;
use Genkgo\ArchiveStream\CallbackStringContent;
use Genkgo\ArchiveStream\FileContent;
use Genkgo\ArchiveStream\Psr7Stream;
use Genkgo\ArchiveStream\StringContent;
use Genkgo\ArchiveStream\ZipReader;

$archive = (new Archive())
    ->withContent(new CallbackStringContent('callback.txt', function () {
        return 'data';
    }))
    ->withContent(new StringContent('string.txt', 'data'))
    ->withContent(new FileContent('file.txt', 'local/file/name.txt'));
    ->withContent(new EmptyDirectory('directory'));

$response = $response->withBody(
    new Psr7Stream(new ZipReader($archive))
);
```

## Requirements

  * PHP >=5.6.0
  * gmp extension
  * psr/http-message

## Limitations

 * Only the Zip64 (version 4.5 of the Zip specification) format is supported.
 * Files cannot be resumed if a download fails before finishing.

## Contributors
- Paul Duncan - Original author
- Daniel Bergey
- Andy Blyler
- Tony Blyler
- Andrew Borek
- Rafael Corral
- John Maguire
- Frederik Bosch

## License

Original work Copyright 2007-2009 Paul Duncan <pabs@pablotron.org>
Modified work Copyright 2013-2015 Barracuda Networks, Inc.
Modified work Copyright 2016 Genkgo BV.

Licensed under the MIT License
