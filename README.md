# ZipStream 0.2.3

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

### Stream each file in parts

This method can be used to serve files of any size (GB, TB).

```php
// Create a new zipstream object
$zip = new ZipStream('example.zip');

// Initiate the stream transfer of some_image.jpg
$zip->init_file_stream_transfer('some_image.jpg');

// Stream part of the contents of som_image.jpg
// This method should be called as many times as needed to send all of it's data
$zip->stream_file_part($data);

// Send data descriptor header for file
$zip->complete_file_stream();

// Other files can be added here, simply run the three commands above for each file that is being sent

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
- Andrew Borek
- Tony Blyler
- Daniel Bergey
- Rafael Corral

## License

Original work Copyright 2007-2009 Paul Duncan <pabs@pablotron.org>
Modified work Copyright 2013 Barracuda Networks, Inc.

Licensed under the MIT License
