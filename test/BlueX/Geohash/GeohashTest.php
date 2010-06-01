<?php

require_once 'utils/geolib.inc.php';

/**
 * Unit tests of the geohash library, utils/geolib.inc.php.
 * 
 * @package utils.geohash;
 */
class utils_GeolibTest extends PHPUnit_Framework_TestCase {
    
    public function setUp()
    {
        if(BLUE_ENVIRONMENT == "dev")
            $this->markTestSkipped("Test does not run in dev, only build");
    }
    /**
     * The digits used by geohash to form the base-32 number system.
     * @return array
     */
    public static function digitProvider() {
        return array(
            array(0, '0'),
            array(1, '1'),
            array(2, '2'),
            array(3, '3'),
            array(4, '4'),
            array(5, '5'),
            array(6, '6'),
            array(7, '7'),
            array(8, '8'),
            array(9, '9'),
            array(10, 'b'),
            array(11, 'c'),
            array(12, 'd'),
            array(13, 'e'),
            array(14, 'f'),
            array(15, 'g'),
            array(16, 'h'),
            array(17, 'j'),
            array(18, 'k'),
            array(19, 'm'),
            array(20, 'n'),
            array(21, 'p'),
            array(22, 'q'),
            array(23, 'r'),
            array(24, 's'),
            array(25, 't'),
            array(26, 'u'),
            array(27, 'v'),
            array(28, 'w'),
            array(29, 'x'),
            array(30, 'y'),
            array(31, 'z'),
        );
    }
    
    /**
     * Tests for geohash::encode_digit() and geohash::decode_digit().
     * These functions provide raw conversion digit by digit.
     * 
     * @dataProvider digitProvider
     * @param int $number
     * @param string $digit
     */
    public function testGeocodeEncodeDigitAndDecodeDigit($number, $digit) {
        $this->assertEquals($digit, geohash::encode_digit($number));
        $this->assertEquals($number, geohash::decode_digit($digit));
    }
    
    /**
     * Tests for testing the encoding.
     * The default precision is 8 characters.
     * @return array of arrays(latitude, longitude, precision, geohash)
     */
    public static function encodingProvider() {
        // test 12 character geohashes, edge cases and internal places
        $encodings = array(
            array(new geopoint(  0,    0), 12, 's00000000000'),
            array(new geopoint( 45,   90), 12, 'y00000000000'),
            array(new geopoint( 45,  -90), 12, 'f00000000000'),
            array(new geopoint(-45,   90), 12, 'q00000000000'),
            array(new geopoint(-45,  -90), 12, '600000000000'),
            array(new geopoint( 90,  180), 12, 'zzzzzzzzzzzz'),
            array(new geopoint( 90, -180), 12, 'bpbpbpbpbpbp'),
            array(new geopoint(-90,  180), 12, 'pbpbpbpbpbpb'),
            array(new geopoint(-90, -180), 12, '000000000000'),
            array(new geopoint( 42.350072, -71.047656), 12, 'drt2zm8ej9eg'),
            array(new geopoint( 38.898632, -77.036541), 12, 'dqcjqcr8yqxd'),
            array(new geopoint(-23.442503, -58.443832), 12, '6ey6wh6t808q'),
            array(new geopoint( 47.516231,  14.550072), 12, 'u26q7454172n'),
            array(new geopoint( 19.856270, 102.495496), 12, 'w78buqdznjj0'),
        );
        foreach ($encodings as $test) {
            $encodings[] = array($test[0], 1, substr($test[2], 0, 1));     // test single character geohashes
            $encodings[] = array($test[0], 2, substr($test[2], 0, 2));     // test two character geohashes
            $encodings[] = array($test[0], 5, substr($test[2], 0, 5));     // test five character geohashes
            $encodings[] = array($test[0], null, substr($test[2], 0, 8));  // test default eight character geohashes
        }
        return $encodings;
    }
    
    /**
     * Tests for geohash::encode(), geohash::decode_box() and geohash::decode(),
     * conversions to and from geohashes.
     * 
     * @dataProvider encodingProvider
     * @param float $latitude
     * @param float $longitude
     * @param int $precision
     * @param string $geohash
     */
    public function testEncodeAndDecodeBox($point, $precision, $geohash) {
        // encode the lat/lon and compare the result to the geohash
        if ($precision === null) {
            $result = geohash::encode($point);
        } else {
            $result = geohash::encode($point, $precision);
        }
        $this->assertEquals($geohash, $result);
        
        // decode the goehash box and make sure it contains the lat/lon
        $box = geohash::decode_box($geohash);
        $this->assertGreaterThanOrEqual($point->latitude, $box->north);
        $this->assertGreaterThanOrEqual($point->longitude, $box->east);
        $this->assertLessThanOrEqual($point->latitude, $box->south);
        $this->assertLessThanOrEqual($point->longitude, $box->west);
        
        // compare the box size to the precision
        // the slices are the number of times we half the map, starting with the prime meridian (0 longitude)
        $slices = ($precision ? $precision : 8) * 5 / 2;
        $dlat = 180 / (1 << floor($slices));
        $dlon = 360 / (1 << ceil($slices));
        $this->assertLessThan(0.0001, abs($box->north - $box->south - $dlat));
        $this->assertLessThan(0.0001, abs($box->east - $box->west - $dlon));
        
        // decode the hash, and make sure it's reasonably close to the original lat/lon
        $decode = geohash::decode($geohash);
        $this->assertLessThan(($dlat/2)+0.0001, abs($point->latitude - $decode->latitude));
        $this->assertLessThan(($dlon/2)+0.0001, abs($point->longitude - $decode->longitude));
    }
    
    /**
     * A bunch of geohashes for testing neighbors, contains and other stuff.
     * @return array
     */
    public static function geohashProvider() {
        $geohashes = array(
            array('s00000000000'),
            array('y00000000000'),
            array('f00000000000'),
            array('q00000000000'),
            array('600000000000'),
            array('zzzzzzzzzzzz'),
            array('bpbpbpbpbpbp'),
            array('pbpbpbpbpbpb'),
            array('000000000000'),
            array('drt2zm8ej9eg'),
            array('dqcjqcr8yqxd'),
            array('6ey6wh6t808q'),
            array('u26q7454172n'),
            array('w78buqdznjj0'),
        );
        // add some random tests
        for ($i = 0; $i < 3; $i++) {
            $geohashes[] = array(geohash::encode(new geopoint(mt_rand(-90000, 90000)/1000, mt_rand(-180000, 180000)/1000, 12)));
        }
        foreach ($geohashes as $test) {
            $geohashes[] = array(substr($test[0], 0, 1));   // test single character geohashes
            $geohashes[] = array(substr($test[0], 0, 2));   // test two character geohashes
            $geohashes[] = array(substr($test[0], 0, 5));   // test five character geohashes
            $geohashes[] = array(substr($test[0], 0, 8));   // test eight character geohashes
        }
        return $geohashes;
    }
    
    /**
     * Tests for geohash::neighbor(), returning adjacent geohashes.
     * After the testEncodeAndDecodeBox, this test relies on those functions.
     * 
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testNeighbors($geohash) {
        $this->assertGreaterThan(0, strlen($geohash));
        
        $point = geohash::decode($geohash);
        $box = geohash::decode_box($geohash);
        
        $north_hash = geohash::neighbor($geohash, geohash::north);
        $south_hash = geohash::neighbor($geohash, geohash::south);
        $east_hash  = geohash::neighbor($geohash, geohash::east);
        $west_hash  = geohash::neighbor($geohash, geohash::west);
        
        // test the north box adjacency
        if (abs($box->north - 90) < 0.0001) {
            // north edge
            $this->assertNull($north_hash);
        } else {
            $north_box = geohash::decode_box($north_hash);
            $this->assertEquals($box->east, $north_box->east);
            $this->assertEquals($box->west, $north_box->west);
            $this->assertLessThan(0.0001, $north_box->south - $box->north);
        }
        
        // test the south box adjacency
        if (abs($box->south + 90) < 0.0001) {
            // north edge
            $this->assertNull($south_hash);
        } else {
            $south_box = geohash::decode_box($south_hash);
            $this->assertEquals($box->east, $south_box->east);
            $this->assertEquals($box->west, $south_box->west);
            $this->assertLessThan(0.0001, $south_box->north - $box->south);
        }
        
        // test the east box adjacency
        $east_box = geohash::decode_box($east_hash);
        $this->assertEquals($box->north, $east_box->north);
        $this->assertEquals($box->south, $east_box->south);
        if (abs($box->east - 180) < 0.0001) {
            // past the international dateline and onto the west edge
            $this->assertLessThan(0.0001, $east_box->west + 180);
        } else {
            $this->assertLessThan(0.0001, $east_box->west - $box->east);
        }
        
        // test the west box adjacency
        $west_box = geohash::decode_box($west_hash);
        $this->assertEquals($box->north, $west_box->north);
        $this->assertEquals($box->south, $west_box->south);
        if (abs($box->west + 180) < 0.0001) {
            // past the international dateline and onto the east edge
            $this->assertLessThan(0.0001, $west_box->east - 180);
        } else {
            $this->assertLessThan(0.0001, $west_box->east - $box->west);
        }
    }
    
    /**
     * Test for the geohash::quadrant() method.
     * 
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testQuadrant($geohash) {
        $len = strlen($geohash);
        $this->assertGreaterThan(0, $len);
        
        $point = geohash::decode($geohash);
        
        // test every precision from invalid to global
        for ($precision = $len+1; $precision >= 0; $precision--) {
            $quadrant = geohash::quadrant($geohash, $precision);
            if ($precision >= $len) {
                // invalid precision, returns null
                $this->assertNull($quadrant);
            } else {
                $box = ($precision > 0) ? geohash::decode_box(substr($geohash, 0, $precision)) : new geobox(new geopoint(90, 180), new geopoint(-90, -180));
                $center = $box->center();
                switch ($quadrant) {
                case geohash::northwest:
                    $this->assertGreaterThanOrEqual($center->latitude, $point->latitude);
                    $this->assertLessThanOrEqual($center->longitude, $point->longitude);
                    break;
                case geohash::northeast:
                    $this->assertGreaterThanOrEqual($center->latitude, $point->latitude);
                    $this->assertGreaterThanOrEqual($center->longitude, $point->longitude);
                    break;
                case geohash::southwest:
                    $this->assertLessThanOrEqual($center->latitude, $point->latitude);
                    $this->assertLessThanOrEqual($center->longitude, $point->longitude);
                    break;
                case geohash::southeast:
                    $this->assertLessThanOrEqual($center->latitude, $point->latitude);
                    $this->assertGreaterThanOrEqual($center->longitude, $point->longitude);
                    break;
                }
            }
        }
    }
    
    /**
     * Test for the geohash::contains() and geohash_set::contains() functions.
     * This test constructs the test points and ranges in and around the geohash.
     * 
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testContains($geohash) {
        $north = geohash::neighbor($geohash, geohash::north);
        $south = geohash::neighbor($geohash, geohash::south);
        $east = geohash::neighbor($geohash, geohash::east);
        $west = geohash::neighbor($geohash, geohash::west);
        
        $outside_tests = array($east, $west);
        if ($north) {
            $outside_tests[] = $north;
        }
        if ($south) {
            $outside_tests[] = $south;
        }
        
        // test points outside the geohash box
        foreach ($outside_tests as $test) {
            for ($i = 0; $i < 32; $i++) {
                $point = geohash::decode($test.geohash::encode_digit($i));
                $this->assertFalse(geohash::contains($geohash, $point));
            }
        }
        
        // test points inside the box
        for ($i = 0; $i < 32; $i++) {
            $point = geohash::decode($geohash.geohash::encode_digit($i));
            $this->assertTrue(geohash::contains($geohash, $point));
        }
        // and the four edge cases (all corners)
        $box = geohash::decode_box($geohash);
        $this->assertTrue(geohash::contains($geohash, new geopoint($box->north, $box->west)));
        $this->assertTrue(geohash::contains($geohash, new geopoint($box->north, $box->east)));
        $this->assertTrue(geohash::contains($geohash, new geopoint($box->south, $box->west)));
        $this->assertTrue(geohash::contains($geohash, new geopoint($box->south, $box->east)));
                
        // make a geohash set and test points inside and out
        $set = new geohash_set();
        $set->add_range($geohash.geohash::encode_digit(8), $geohash.geohash::encode_digit(23));
        
        // test points outside the set
        foreach ($outside_tests as $test) {
            $point = geohash::decode($test);
            $this->assertFalse($set->contains($point));
        }
        // test closer points outside the set
        for ($i = 0; $i < 8; $i++) {
            $point = geohash::decode($geohash.geohash::encode_digit($i));
            $this->assertFalse($set->contains($point));
        }
        for ($i = 24; $i < 32; $i++) {
            $point = geohash::decode($geohash.geohash::encode_digit($i));
            $this->assertFalse($set->contains($point));
        }
        // test points inside the set
        for ($i = 8; $i < 24; $i++) {
            $point = geohash::decode($geohash.geohash::encode_digit($i));
            $this->assertTrue($set->contains($point));
        }
        
        // make a compound geohash set and test points inside and out
        $set = new geohash_set();
        $set->add($geohash);
        $set->add($east);
        
        // test points outside the set
        $outside_tests = array(geohash::neighbor($east, geohash::east), $west);
        if ($north) {
            $outside_tests[] = $north;
        }
        if ($south) {
            $outside_tests[] = $south;
        }
        foreach ($outside_tests as $test) {
            $point = geohash::decode($test);
            $this->assertFalse($set->contains($point));
        }
        // test points inside the set
        $point = geohash::decode($geohash);
        $this->assertTrue($set->contains($point));
        $point = geohash::decode($east);
        $this->assertTrue($set->contains($point));
    }
    
    /**
     * Tests the geohash halving function geohash::halve().
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testHalve($geohash) {
        $north = geohash::neighbor($geohash, geohash::north);
        $south = geohash::neighbor($geohash, geohash::south);
        $east = geohash::neighbor($geohash, geohash::east);
        $west = geohash::neighbor($geohash, geohash::west);
        
        $outside_tests = array(geohash::decode($east), geohash::decode($west));
        if ($north) {
            $outside_tests[] = geohash::decode($north);
        }
        if ($south) {
            $outside_tests[] = geohash::decode($south);
        }
        
        $north_half = geohash::halve($geohash, geohash::north);
        $south_half = geohash::halve($geohash, geohash::south);
        $east_half = geohash::halve($geohash, geohash::east);
        $west_half = geohash::halve($geohash, geohash::west);
        
        // do the obvious outside tests
        foreach ($outside_tests as $test) {
            $this->assertFalse($north_half->contains($test));
            $this->assertFalse($south_half->contains($test));
            $this->assertFalse($east_half->contains($test));
            $this->assertFalse($west_half->contains($test));
        }
        
        // test points from the four quadrants
        $box = geohash::decode_box($geohash);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;
        
        $northwest = new geopoint($box->north - ($dlat/4), $box->west + ($dlon/4));
        $northeast = new geopoint($box->north - ($dlat/4), $box->east - ($dlon/4));
        $southwest = new geopoint($box->south + ($dlat/4), $box->west + ($dlon/4));
        $southeast = new geopoint($box->south + ($dlat/4), $box->east - ($dlon/4));
        
        $this->assertTrue($north_half->contains($northwest));
        $this->assertTrue($north_half->contains($northeast));
        $this->assertFalse($north_half->contains($southwest));
        $this->assertFalse($north_half->contains($southeast));
        
        $this->assertFalse($south_half->contains($northwest));
        $this->assertFalse($south_half->contains($northeast));
        $this->assertTrue($south_half->contains($southwest));
        $this->assertTrue($south_half->contains($southeast));
        
        $this->assertFalse($east_half->contains($northwest));
        $this->assertTrue($east_half->contains($northeast));
        $this->assertFalse($east_half->contains($southwest));
        $this->assertTrue($east_half->contains($southeast));
        
        $this->assertTrue($west_half->contains($northwest));
        $this->assertFalse($west_half->contains($northeast));
        $this->assertTrue($west_half->contains($southwest));
        $this->assertFalse($west_half->contains($southeast));
        
        // these points are from the four corners
        $northwest = new geopoint($box->north, $box->west);
        $northeast = new geopoint($box->north, $box->east);
        $southwest = new geopoint($box->south, $box->west);
        $southeast = new geopoint($box->south, $box->east);
        
        $this->assertTrue($north_half->contains($northwest));
        $this->assertTrue($north_half->contains($northeast));
        $this->assertFalse($north_half->contains($southwest));
        $this->assertFalse($north_half->contains($southeast));
        
        $this->assertFalse($south_half->contains($northwest));
        $this->assertFalse($south_half->contains($northeast));
        $this->assertTrue($south_half->contains($southwest));
        $this->assertTrue($south_half->contains($southeast));
        
        $this->assertFalse($east_half->contains($northwest));
        $this->assertTrue($east_half->contains($northeast));
        $this->assertFalse($east_half->contains($southwest));
        $this->assertTrue($east_half->contains($southeast));
        
        $this->assertTrue($west_half->contains($northwest));
        $this->assertFalse($west_half->contains($northeast));
        $this->assertTrue($west_half->contains($southwest));
        $this->assertFalse($west_half->contains($southeast));
        
        // test the center
        $center_point = new geopoint($box->north - ($dlat/2), $box->west + ($dlon/2));
        $this->assertTrue($north_half->contains($center_point));
        $this->assertTrue($south_half->contains($center_point));
        $this->assertTrue($east_half->contains($center_point));
        $this->assertTrue($west_half->contains($center_point));
    }
    
    /**
     * Tests of the geohash::quarter() function.
     *
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testQuarter($geohash) {
        $north = geohash::neighbor($geohash, geohash::north);
        $south = geohash::neighbor($geohash, geohash::south);
        $east = geohash::neighbor($geohash, geohash::east);
        $west = geohash::neighbor($geohash, geohash::west);
        
        $outside_tests = array(geohash::decode($east), geohash::decode($west));
        if ($north) {
            $outside_tests[] = geohash::decode($north);
        }
        if ($south) {
            $outside_tests[] = geohash::decode($south);
        }
        
        $northwest = geohash::quarter($geohash, geohash::northwest);
        $northeast = geohash::quarter($geohash, geohash::northeast);
        $southwest = geohash::quarter($geohash, geohash::southwest);
        $southeast = geohash::quarter($geohash, geohash::southeast);
        
        // do the obvious outside tests
        foreach ($outside_tests as $test) {
            $this->assertFalse($northwest->contains($test));
            $this->assertFalse($northeast->contains($test));
            $this->assertFalse($southwest->contains($test));
            $this->assertFalse($southeast->contains($test));
        }
        
        // test points from the four quadrants
        $box = geohash::decode_box($geohash);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;
        
        $northwest_point = new geopoint($box->north - ($dlat/4), $box->west + ($dlon/4));
        $northeast_point = new geopoint($box->north - ($dlat/4), $box->east - ($dlon/4));
        $southwest_point = new geopoint($box->south + ($dlat/4), $box->west + ($dlon/4));
        $southeast_point = new geopoint($box->south + ($dlat/4), $box->east - ($dlon/4));
        
        $this->assertTrue($northwest->contains($northwest_point));
        $this->assertFalse($northwest->contains($northeast_point));
        $this->assertFalse($northwest->contains($southwest_point));
        $this->assertFalse($northwest->contains($southeast_point));
        
        $this->assertFalse($northeast->contains($northwest_point));
        $this->assertTrue($northeast->contains($northeast_point));
        $this->assertFalse($northeast->contains($southwest_point));
        $this->assertFalse($northeast->contains($southeast_point));
        
        $this->assertFalse($southwest->contains($northwest_point));
        $this->assertFalse($southwest->contains($northeast_point));
        $this->assertTrue($southwest->contains($southwest_point));
        $this->assertFalse($southwest->contains($southeast_point));
        
        $this->assertFalse($southeast->contains($northwest_point));
        $this->assertFalse($southeast->contains($northeast_point));
        $this->assertFalse($southeast->contains($southwest_point));
        $this->assertTrue($southeast->contains($southeast_point));
        
        // test the corners
        $northwest_point = new geopoint($box->north, $box->west);
        $northeast_point = new geopoint($box->north, $box->east);
        $southwest_point = new geopoint($box->south, $box->west);
        $southeast_point = new geopoint($box->south, $box->east);
        
        $this->assertTrue($northwest->contains($northwest_point));
        $this->assertFalse($northwest->contains($northeast_point));
        $this->assertFalse($northwest->contains($southwest_point));
        $this->assertFalse($northwest->contains($southeast_point));
        
        $this->assertFalse($northeast->contains($northwest_point));
        $this->assertTrue($northeast->contains($northeast_point));
        $this->assertFalse($northeast->contains($southwest_point));
        $this->assertFalse($northeast->contains($southeast_point));
        
        $this->assertFalse($southwest->contains($northwest_point));
        $this->assertFalse($southwest->contains($northeast_point));
        $this->assertTrue($southwest->contains($southwest_point));
        $this->assertFalse($southwest->contains($southeast_point));
        
        $this->assertFalse($southeast->contains($northwest_point));
        $this->assertFalse($southeast->contains($northeast_point));
        $this->assertFalse($southeast->contains($southwest_point));
        $this->assertTrue($southeast->contains($southeast_point));
        
        // test the center
        $center_point = new geopoint($box->north - ($dlat/2), $box->west + ($dlon/2));
        $this->assertTrue($northwest->contains($center_point));
        $this->assertTrue($northeast->contains($center_point));
        $this->assertTrue($southwest->contains($center_point));
        $this->assertTrue($southeast->contains($center_point));
    }
    
    /**
     * Test geolib::expand().
     * This test isn't yet working for edge cases,
     * expanding to the original precision or to the minimum precision.
     * Perhaps those expansions don't make much sense.
     * 
     * @dataProvider geohashProvider
     * @param string $geohash
     */
    public function testCircle($geohash) {
        $circle = new geohash_circle($geohash);
        $center = geohash::decode($geohash);
        $last_radius = 0;
        // test all precisions up to 0
        do {
            $radius = $circle->max_radius() / EARTH_RADIUS;
            
            $circle->expand($geohash, $precision);
            
            // the center point should be in the set
            $this->assertTrue($circle->contains($center));
            
            // the following tests only work for points away from the poles where radiuses expand usefully
            if ($radius > 0) {
                $this->assertGreaterThan($last_radius, $radius);
                
                // all points box size x 1 distance from the center should be in the set
                $this->assertTrue($circle->contains(new geopoint(min($center->latitude + $radius, 90), self::normalize_longitude($center->longitude + $radius))));
                $this->assertTrue($circle->contains(new geopoint(min($center->latitude + $radius, 90), self::normalize_longitude($center->longitude - $radius))));
                $this->assertTrue($circle->contains(new geopoint(max($center->latitude - $radius, -90), self::normalize_longitude($center->longitude + $radius))));
                $this->assertTrue($circle->contains(new geopoint(max($center->latitude - $radius, -90), self::normalize_longitude($center->longitude - $radius))));
                
                // at least one point box size x 2 distance from the center should be outside the set
                /*
                $this->assertFalse($circle->contains(new geopoint(min($center->latitude + $radius*2, 90), $center->longitude + $radius*2)) &&
                                   $circle->contains(new geopoint(min($center->latitude + $radius*2, 90), $center->longitude - $radius*2)) &&
                                   $circle->contains(new geopoint(max($center->latitude - $radius*2, -90), $center->longitude + $radius*2)) &&
                                   $circle->contains(new geopoint(max($center->latitude - $radius*2, -90), $center->longitude - $radius*2)));
                */
                $last_radius = $radius;
            }
        } while ($circle->expand());
    }
    
    /**
     * Returns the longitude as a number between -180 and 180.
     * The longitude entered may be the product of an equation
     * that could put it outside of this range.  The value given
     * will be a place on the earth, just expressed strangely.
     *
     * @param unknown_type $longitude
     */
    public function normalize_longitude($longitude) {
        while ($longitude > 180) {
            $longitude -= 360;
        }
        while ($longitude < -180) {
            $longitude += 360;
        }
        return $longitude;
    }
}
