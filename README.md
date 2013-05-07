# ZipStream 0.2.2

A library for dynamically streaming dynamic zip files without the need to have the complete file stored on the server.

## Usage

### Stream whole file at a time

A fast and simple streaming zip file downloader for PHP.  Here's a
simple example:

```php
// Create a new zipstream object
$zip = new ZipStream('example.zip');

// Create a file named 'hello.txt' 
$zip->add_file('some_image.jpg', 'This is the contents of hello.txt');

// Add a file named 'image.jpg' from a local file 'path/to/image.jpg'
$zip->add_file_from_path('some_image.jpg', 'path/to/image.jpg');

// Finish the zip stream
$zip->finish();
```

### Other

You can also add comments, modify file timestamps, and customize (or
disable) the HTTP headers. See the class file for details.

## Requirements

  * PHP version 5.1.2 or newer (specifically, the hash_init and
    hash_file functions).

## Contributors
- Paul Duncan - Original author

## License

Original work Copyright 2007-2009 Paul Duncan <pabs@pablotron.org>
Modified work Copyright 2013 Barracuda Networks, Inc.

Licensed under the MIT License
