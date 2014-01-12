#Metadata extractor module for ZF2

A ZF2 module to fetch metadata from any given URI

###More info

http://blog.robbestad.com

### How to use

In your composer.json, add the following line

    "svenanders/sarmetadata": "dev-master"

Add the following line to your **application.config.php**:

    'modules' => array(
        'SarMetadata',
    ),
  
In your code, include the class:

    use SarMetadata\SarMetadata;

and then in your functions, use it like this:

    $meta = new SarMetadata();
    $url="http://www.imdb.com/title/tt1170358/?ref_=nv_sr_1";
    $response=$meta->getMeta($url);

Provides:

    ->title
    ->author
    ->image
    ->keywords
    ->description
    ->twitter tweetcount
    ->facebook shares and likes

Tests: 

execute **phpunit vendor/svenanders/sarmetadata/tests/** from the root of your project to run the tests

#####License:

Sven Anders Robbestad (C) 2014

<img src="http://i.creativecommons.org/l/by/3.0/88x31.png" alt="CC BY">

