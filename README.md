# ArchiveStream 2.0.0

Stream a ZIP file (memory efficient) as a PSR-7 message

## Getting Started

```php
<?php
$archive = new Archive();
$archive->addContent(new FileContent('in_zip_name.txt', 'local/file/name.txt'));
$response = $response->withBody(new Psr7Stream(new ZipStream($zipStream)));
```

## Requirements

  * PHP >=5.6.0
  * gmp extension

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
