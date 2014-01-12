<?php
/**
 * Unit Test Example
 *
 * @author Sven Anders Robbestad <robbestad@gmail.com>
 */
namespace SarMetadata\Tests;

require_once __DIR__ . '/../../src/SarMetadata/SarMetadata.php';
require_once __DIR__ . '/../../src/SarMetadata/FastImage.php';

use SarMetadata\SarMetadata;

    /**
     * ParserTest class test case
     *
     * @author Sven Anders Robbestad <robbestad@gmail.com>
     */
class SarMetadataTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Can initalize class 
     */
    public function testInitializeClassSarMetadata()
    {
            $meta = new SarMetadata();
            $this->assertNotNull($meta);
    }

    public function testFetchUrl()
    {
            $meta = new SarMetadata();
            $url="http://www.imdb.com/title/tt1170358/?ref_=nv_sr_1";
            $response=$meta->getMeta($url);
            $this->assertNotNull($response->title,$meta->error_response);

    }




}
