<?php
/**
 * geolib.inc.php
 * 
 * A library of where in the world things are.
 * Mostly this library is concerned with geohashes,
 * which encode latitude/longitude pairs into scalar,
 * and therefore index-friendly, values.
 * 
 * @author Chris Johnson, Sept 2008
 * @package utils
 */

define('EARTH_RADIUS', 6371);  // mean radius in km

/**
 * A geohash is a Z-order, or Morton curve, filling the earth's longitudes and latitudes
 * starting at -90 -180 to the left then up and finally ending up at 90 180.  It uses a
 * base-32 character encoding, and has the characteristic of getting less precise as you
 * remove characters from the end of the hash.
 * 
 * http://en.wikipedia.org/wiki/Geohash
 * http://en.wikipedia.org/wiki/Z-order_(curve)
 * 
 * @package utils
 */
class geohash {
    const encoding = '0123456789bcdefghjkmnpqrstuvwxyz';
    
    /**
     * Returns the geohash encoded 'digit' of a number between 0 and 31.
     * @param int $number
     * @return string
     */
    public static function encode_digit($number) {
        $encoding = geohash::encoding;
        return $encoding[$number];
    }
    
    /**
     * Returns the int between 0 and 31 corresponding to a geohash digit.
     * @param string $digit
     * @return int
     */
    public static function decode_digit($digit) {
        return strpos(geohash::encoding, $digit);
    }
    
    /**
     * Calculate the geohash for a geopoint to a given precision.
     * The precision is measured in geohash characters.
     * @param geopoint $point
     * @param int $precision
     * @param string
     */
    public static function encode($point, $precision = 8) {
        $max_longitude =  180;
        $min_longitude = -180;
        $max_latitude  =   90;
        $min_latitude  =  -90;
        
        $hash = '';
        $longitude_bit = true;
        for ($i = 0; $i < $precision; $i++) {
            $byte = 0;
            for ($j = 0; $j < 5; $j++) {
                if ($longitude_bit) {
                    $mid = ($max_longitude + $min_longitude) / 2;
                    if ($point->longitude >= $mid) {
                        $bit = 1;
                        $min_longitude = $mid;
                    } else {
                        $bit = 0;
                        $max_longitude = $mid;
                    }
                } else {
                    $mid = ($max_latitude + $min_latitude) / 2;
                    if ($point->latitude >= $mid) {
                        $bit = 1;
                        $min_latitude = $mid;
                    } else {
                        $bit = 0;
                        $max_latitude = $mid;
                    }
                }
                $byte = ($byte << 1) | $bit;
                $longitude_bit = !$longitude_bit;
            }
            $hash .= geohash::encode_digit($byte);
        }
        return $hash;
    }
    
    /**
     * Calculate the bounding box for a geohash
     * @param string $hash
     * @return geobox
     */
    public static function decode_box($hash) {
        $northeast = new geopoint(90, 180);
        $southwest = new geopoint(-90, -180);
        
        $count = strlen($hash);
        $longitude_bit = true;
        for ($i = 0; $i < $count; $i++) {
            $byte = geohash::decode_digit($hash[$i]);
            for ($j = 0; $j < 5; $j++) {
                $bit = ($byte & 0x10) ? 1 : 0;
                $byte = $byte << 1;
                if ($longitude_bit) {
                    $mid = ($northeast->longitude + $southwest->longitude) / 2;
                    if ($bit) {
                        $southwest->longitude = $mid;
                    } else {
                        $northeast->longitude = $mid;
                    }
                } else {
                    $mid = ($northeast->latitude + $southwest->latitude) / 2;
                    if ($bit) {
                        $southwest->latitude = $mid;
                    } else {
                        $northeast->latitude = $mid;
                    }
                }
                $longitude_bit = !$longitude_bit;
            }
        }
        return new geobox($northeast, $southwest);
    }
    
    /**
     * Returns the geopoint at the center of the geohash box.
     * @param string $hash
     * @return geopoint
     */
    public static function decode($hash) {
        return self::decode_box($hash)->center();
    }
    
    /**
     * Returns the geohash immediately following this one.
     * If it's the last geohash, returns null.
     * 
     * Examples:
     *      geohash::increment('38z') returns '390'
     *      geohash::increment('z') returns null
     * 
     * @param string $hash
     * @return string|null
     */
    public static function increment($hash) {
        $len = strlen($hash);
        $char = $hash[$len-1];
        $code = geohash::decode_digit($char);
        $base = ($len > 1) ? substr($hash, 0, $len-1) : '';
        if ($code < 31) {
            return $base.geohash::encode_digit($code+1);
        } elseif ($len > 1) {
            $base = self::increment($base);
            return $base ? $base.'0' : null;
        } else {
            return null;
        }
    }
    
    // each index maps to the neighboring encoded digit, border digits wrap
    const odd_north_neighbor = '238967debc01fg45kmstqrwxuvhjyznp';
    const odd_south_neighbor = 'bc01fg45238967deuvhjyznpkmstqrwx';
    const odd_east_neighbor  = '14365h7k9dcfesgujnmqp0r2twvyx8zb';
    const odd_west_neighbor  = 'p0r21436x8zb9dcf5h7kjnmqesgutwvy';
    
    const odd_north_border = 'bcfguvyz';
    const odd_south_border = '0145hjnp';
    const odd_east_border  = 'prxz';
    const odd_west_border  = '028b';
    
    const north = 'n';
    const south = 's';
    const east  = 'e';
    const west  = 'w';
    const northeast = 'ne';
    const northwest = 'nw';
    const southeast = 'se';
    const southwest = 'sw';
    
    /**
     * Returns a neighboring geohash in a cardinal direction.
     * This function returns null for goehashes north of the north pole or south of the south.
     * @param string $hash
     * @param string $direction one of geohash::north, geohash::south, geohash::east or geohash::west
     * @return string|null the neighboring geohash
     */
    public static function neighbor($hash, $direction) {
        $precision = strlen($hash);
        $odd = $precision % 2;
        
        // odd and even geohash characters map differently, but they are symmetric as per the following mapping
        switch ($direction) {
        case geohash::north:
            $neighbor = $odd ? geohash::odd_north_neighbor : geohash::odd_east_neighbor;
            $border   = $odd ? geohash::odd_north_border   : geohash::odd_east_border;
            break;
        case geohash::south:
            $neighbor = $odd ? geohash::odd_south_neighbor : geohash::odd_west_neighbor;
            $border   = $odd ? geohash::odd_south_border   : geohash::odd_west_border;
            break;
        case geohash::east:
            $neighbor = $odd ? geohash::odd_east_neighbor : geohash::odd_north_neighbor;
            $border   = $odd ? geohash::odd_east_border   : geohash::odd_north_border;
            break;
        case geohash::west:
            $neighbor = $odd ? geohash::odd_west_neighbor : geohash::odd_south_neighbor;
            $border   = $odd ? geohash::odd_west_border   : geohash::odd_south_border;
            break;
        default:
            trigger_error('Unsupported geohash direction, "'.$direction.'"', E_USER_ERROR);
            return;
        }
        
        $char = $hash[$precision-1];
        $base = substr($hash, 0, $precision-1);
        if (strpos($border, $char) !== false) {
            // border char
            if ($precision > 1) {
                $base = self::neighbor($base, $direction);
                if ($base == null) {
                    return null;
                }
            } elseif ($direction == geohash::north || $direction == geohash::south) {
                // unable to go north of the north pole or south of the south
                return null;
            }
        }
        $code = geohash::decode_digit($char);
        $neighbor_char = $neighbor[$code];
        return $base.$neighbor_char;
    }
    
    /**
     * Returns true if the geopoint lies within the hash region.
     *
     * @param string $hash
     * @param geopoint $point
     * @return boolean
     */
    public static function contains($hash, $point) {
        $box = geohash::decode_box($hash);
        return ($box->south <= $point->latitude && $point->latitude <= $box->north &&
                $box->west <= $point->longitude && $point->longitude <= $box->east);
    }
    
    /**
     * Return the quadrant of an ancestor containing this geohash.
     * If no precision is given, the immediant parent will be assumed.
     * We can find out which quadrant of any ancestor up to and including the first character.
     * To determine the global quadrant, choose precision 0.
     * 
     * Examples:
     *      '00' is in the southwest quadrant of '0'
     *      'drt2zm8h1t3v' is in the northeast quadrant of 'drt2zm8h1t3'
     *      '9345' is northwest on the globe
     * 
     * @param string $hash geohash
     * @param int $precision
     * @return string geohash::northwest, geohash::northeast, geohash::southwest or geohash::southeast
     */
    public static function quadrant($hash, $precision = null) {
        if ($precision >= strlen($hash)) {
            return null;
        }
        $odd = $precision % 2;
        $quadrant = (int)(geohash::decode_digit($hash[$precision]) / 8);
        switch ($quadrant) {
        case 0:
            return geohash::southwest;
        case 1:
            return $odd ? geohash::southeast : geohash::northwest;
        case 2:
            return $odd ? geohash::northwest : geohash::southeast;
        case 3:
            return geohash::northeast;
        }
    }
    
    /**
     * Cut this geohash into a region half its size.
     * @param string $hash
     * @param string $direction one of geohash::north, geohash::south, geohash::east, geohash::west
     * @return geohash_set
     */
    public static function halve($hash, $direction) {
        $odd = strlen($hash) % 2;
        switch ($direction) {
        case (geohash::north):
            $set = $odd ? array(array(16,31)) : array(array(8,15), array(24,31));
            break;
        case (geohash::south):
            $set = $odd ? array(array(0,15)) : array(array(0,7), array(16,23));
            break;
        case (geohash::east):
            $set = $odd ? array(array(8,15), array(24,31)) : array(array(16,31));
            break;
        case (geohash::west):
            $set = $odd ? array(array(0,7), array(16,23)) : array(array(0,15));
            break;
        default:
            trigger_error('Illegal halve direction, "'.$direction.'"', E_USER_ERROR);
        }
        $region = new geohash_set();
        foreach ($set as $range) {
            $region->add_range($hash.geohash::encode_digit($range[0]),
                               $hash.geohash::encode_digit($range[1]));
        }
        return $region;
    }
    
    /**
     * Reduce this geohash to a region of one quadrant.
     * @param string $hash
     * @param string $direction one of geohash::northeast, geohash::northwest, geohash::southeast, geohash::southwest
     * @return geohash_set
     */
    public static function quarter($hash, $direction) {
        $odd = strlen($hash) % 2;
        switch ($direction) {
        case (geohash::northeast):
            $range = array(24, 31);
            break;
        case (geohash::northwest):
            $range = $odd ? array(16, 23) : array(8, 15);
            break;
        case (geohash::southeast):
            $range = $odd ? array(8, 15) : array(16, 23);
            break;
        case (geohash::southwest):
            $range = array(0, 7);
            break;
        default:
            trigger_error('Illegal quarter direction, "'.$direction.'"', E_USER_ERROR);
            break;
        }
        $set = new geohash_set();
        $set->add_range($hash.geohash::encode_digit($range[0]), $hash.geohash::encode_digit($range[1]));
        return $set;
    }
}

/**
 * A location on the earth denoted by latitude and longitude.
 * The latitude indicates the distance north or south of the equator,
 * -90 being the south pole, 0 being the equator and +90 being the north pole.
 * The longitude indicates an east west distance from the prime meridian,
 * the Grenich Meridian pasing through the Royal Observatory in Greenwich
 * or for our purposes 0.   The longitudes proceed west meeting the international
 * date line at -180, and east meeting the same from the other direction at +180.
 * @package utils
 */
class geopoint {
    public $latitude;
    public $longitude;
    
    public function __construct($latitude, $longitude) {
        $this->latitude  = $latitude;
        $this->longitude = $longitude;
    }
    
    /**
     * Convenience method to convert this geopoint to a geohash.
     * @see geohash::encode
     * @param int $precision
     * @return geohash
     */
    public function geohash($precision = 8) {
        return geohash::encode($this, $precision);
    }
    
    /**
     * Returns the distance between two geopoints in kilometers.
     * @param geopoint $point
     * @return float distance in kilometers
     */
    public function distance_to_point($point) {
        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        
        $lat2 = deg2rad($point->latitude);
        $lon2 = deg2rad($point->longitude);
        
        // spherical law of cosines (accurate to ~1m w/64 bit floats)
        return acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lon1 - $lon2)) * EARTH_RADIUS;
    }
    
    /**
     * Returns the distance from this point to a latitudinal line.
     * @param float $latitude -90 to 90
     * @return float distance in kilometers
     */
    public function distance_to_latitude($latitude) {
        return $this->distance_to_point(new geopoint($latitude, $this->longitude));
    }
    
    /**
     * Returns the distance from this point to a longitudinal line.
     * http://williams.best.vwh.net/avform.htm#Int
     * @param float $longitude -180 and 180
     * @param float distance in kilometers
     */
    public function distance_to_longitude($longitude) {
        $lon3 = deg2rad($longitude);
        
        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        
        // equatorial point perpendicular to the longitudal great circle
        $lat2 = 0;
        $lon2 = $lon3 + M_PI / 2;
        
        if (abs(sin($lon1 - $lon2)) < 1E-12) {
            // the meridian case: sin($lon1 - $lon2) = 0, produces divide by zero error
            $lat3 = ($lat1 >= 0 ? 1 : -1) * EARTH_RADIUS * M_PI/2;
        } elseif (M_PI/2 - abs($lat1) < 1E-12) {
            // the distance from a pole to any meridian will be 0
            return 0;
        } else {
            $lat3 = atan((sin($lat1) * cos($lat2) * sin($lon3 - $lon2) -
                          sin($lat2) * cos($lat1) * sin($lon3 - $lon1)) /
                         (cos($lat1) * cos($lat2) * sin($lon1 - $lon2)));
        }
        $point3 = new geopoint(rad2deg($lat3), rad2deg($lon3));
        
        return $this->distance_to_point($point3);
    }
    
    /**
     * Human readable version of the latitude/longitude to 6 decimal places.
     * @return string
     */
    public function __toString() {
        return number_format($this->latitude, 6).' '.number_format($this->longitude, 6);
    }
}

/**
 * Circumscribes an area of the earth.  The edges are marked by a single
 * lat/long pair marking the north, south, east and west boundaries.
 * @package utils
 */
class geobox {
    public $north;
    public $south;
    public $east;
    public $west;
    
    public function __construct($p1, $p2) {
        $this->north = max($p1->latitude, $p2->latitude);
        $this->south = min($p1->latitude, $p2->latitude);
        $this->east = max($p1->longitude, $p2->longitude);
        $this->west = min($p1->longitude, $p2->longitude);
    }
    
    /**
     * Returns the center point of this box.
     * @return geopoint
     */
    public function center() {
        return new geopoint(($this->north + $this->south) / 2, ($this->east + $this->west) / 2);
    }
    
    /**
     * Returns the northeast corner of the box.
     * @return geopoint
     */
    public function northeast() {
        return new geopoint($this->north, $this->east);
    }
    
    /**
     * Returns the northwest corner of the box.
     * @return geopoint
     */
    public function northwest() {
        return new geopoint($this->north, $this->west);
    }
    
    /**
     * Returns the southeast corner of the box.
     * @return geopoint
     */
    public function southeast() {
        return new geopoint($this->south, $this->east);
    }
    
    /**
     * Returns the southwest corner of the box.
     * @return geopoint
     */
    public function southwest() {
        return new geopoint($this->south, $this->west);
    }
    
    /**
     * Human readable string representation of the box.
     * @return string
     */
    public function __toString() {
        return '[('.number_format($this->north, 4).' '.number_format($this->east, 4).') ('.number_format($this->south, 4).' '.number_format($this->west, 4).')]';
    }
}

/**
 * A collection of geohashes or geohash ranges.
 * @package utils
 */
class geohash_set {
    private $geohashes = array();
    
    /**
     * @param $geohashes geohash|array
     */
    public function add($geohash) {
        $this->geohashes[] = $geohash;
    }
    
    public function add_range($first, $last) {
        $this->geohashes[] = array($first, $last);
    }
    
    /**
     * Enter description here...
     *
     * @param geohash_set $set
     */
    public function add_set($set) {
        foreach ($set->geohashes as $geohash) {
            $this->geohashes[] = $geohash;
        }
    }
    
    /**
     * Returns true if the point falls within one of the hash boxes.
     *
     * @param float $latitude
     * @param float $longitude
     * @return boolean
     */
    public function contains($point) {
        foreach ($this->geohashes as $geohash) {
            if (is_array($geohash)) {
                $first = $geohash[0];
                $last = $geohash[1];
                $test = $first;
                while ($test <= $last && $test) {
                    if (geohash::contains($test, $point)) {
                        return true;
                    }
                    $test = geohash::increment($test);
                }
            } else {
                if (geohash::contains($geohash, $point)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function optimize() {
        trigger_error('TODO', E_USER_ERROR);
    }
    
    /**
     * Returns an array of geohashes.
     * Some of the array elements may be arrays containing the start and endpoints of a range of geohashes.
     * 
     * @return array
     */
    public function export() {
        return $this->geohashes;
    }
    
    public function build_sql($column = 'geohash') {
        $clauses = array();
        foreach ($this->geohashes as $item) {
            if (is_array($item)) {
                $start = $item[0];
                $end = geohash::increment($item[1]);
            } else {
                $start = $item;
                $end = geohash::increment($start);
            }
            $range = "$column >= '$start'";
            if ($end) {
                $range .= " AND $column < '$end'";
            }
            $clauses[] = $range;
        }
        $result = '('.implode(') OR (', $clauses).')';
        return $result;
    }
    
    public function __toString() {
        foreach ($this->geohashes as $item) {
            $result .= isset($result) ? ', ' : '{';
            if (is_array($item)) {
                $result .= '['.$item[0].'-'.$item[1].']';
            } else {
                $result .= $item;
            }
        }
        $result .= '}';
        return $result;
    }
}

/**
 * Computes geohash sets in widening circles around a center point.
 * 
 * 
 * 
 * NOTE: This does not work very well near the poles, and this library should not be relied on for those edge cases.
 *       Some serious dancing around the math has to be done for us to effectively use geohashes near the poles.
 *       Perhaps another location paradigm should be used?  I.e. It may be better to just use a latitude, longitude sort.
 * 
 * @package utils
 */
class geohash_circle {
    
    private $center_geohash;
    private $precision;
    private $geobox;
    private $center;
    private $geohash_set;
    private $max_radius;
    
    /**
     * Creates a new geohash circle, centered around a geohash center.
     *
     * @param string $center_geohash
     */
    public function __construct($center_geohash) {
        $this->center_geohash = $center_geohash;
        $this->precision = strlen($center_geohash);
        
        $this->geobox = geohash::decode_box($center_geohash);
        $this->center = $this->geobox->center();
        
        // Begin with a set including only the box itself
        $this->geohash_set = new geohash_set();
        $this->geohash_set->add($center_geohash);
        
        // TODO: Should this include the 8 surrounding boxes?  (see code below)
/*      $south = geohash::neighbor($hash, geohash::south);
        if ($south) {
            $set->add(geohash::neighbor($south, geohash::west));
            $set->add($south);
            $set->add(geohash::neighbor($south, geohash::east));
        }
        $set->add(geohash::neighbor($hash, geohash::west));
        $set->add($hash);
        $set->add(geohash::neighbor($hash, geohash::east));
        $north = geohash::neighbor($hash, geohash::north);
        if ($north) {
            $set->add(geohash::neighbor($hash, geohash::west));
            $set->add($north);
            $set->add(geohash::neighbor($hash, geohash::east));
        } */
        
        // The distance to the longitude will always be less than the distance to the latitude (I think)
        $this->max_radius = $this->center->distance_to_longitude($this->geobox->east);
    }
    
    /**
     * Returns the distance from the center point of this circle to a geopoint.
     * @param geopoint $point
     * @return float distance in kilometers
     */
    public function distance_to_point($point) {
        return $this->center->distance_to_point($point);
    }
    
    /**
     * Returns the maximum reliable radius for a geohash query generated from this circle.
     * Some points may be in the queried set, but outside the circle (i.e. the corner points).
     * @return float distance in kilometers
     */
    public function max_radius() {
        return $this->max_radius;
    }
    
    /**
     * TODO:  Should this return true if the point is inside the geohash set or the circle?
     * @param geopoint $point
     * @return boolean
     */
    public function contains($point) {
        return $this->geohash_set->contains($point);
    }
    
    /**
     * Returns a sql where clause containing all of the relevant geohash ranges.
     * @param string $column
     * @return string
     */
    public function geohash_sql($column = 'geohash') {
        return $this->geohash_set->build_sql($column);
    }
    
    /**
     * Increase the size the of the circle.
     * @param int $amount
     * @return boolean success|failure
     */
    public function expand($amount = 1) {
        if ($this->precision == 1) {
            // currently unwilling to expand to cover the entire globe
            return false;
        }
        $this->precision = max($this->precision - $amount, 1);
        
        // shrink the geohash, grow the box
        $center = substr($this->center_geohash, 0, $this->precision);
        $box = geohash::decode_box($center);
        $dlat = $box->north - $box->south;
        $dlon = $box->east - $box->west;
        
        $set = new geohash_set();
        $set->add($center);
        
        $quadrant = geohash::quadrant($this->center_geohash, $this->precision);
        if ($quadrant == geohash::northeast || $quadrant == geohash::northwest) {
            // north side
            $north = geohash::neighbor($center, geohash::north);
            if ($north) {
                $set->add_set(geohash::halve($north, geohash::south));
                if ($quadrant == geohash::northeast) {
                    $set->add_set(geohash::quarter(geohash::neighbor($north, geohash::east), geohash::southwest));
                } else {  // northwest
                    $set->add_set(geohash::quarter(geohash::neighbor($north, geohash::west), geohash::southeast));
                }
                $box->north += $dlat / 2;
            }
        } else {
            // south side
            $south = geohash::neighbor($center, geohash::south);
            if ($south) {
                $set->add_set(geohash::halve($south, geohash::north));
                if ($quadrant == geohash::southeast) {
                    $set->add_set(geohash::quarter(geohash::neighbor($south, geohash::east), geohash::northwest));
                } else {  // southwest
                    $set->add_set(geohash::quarter(geohash::neighbor($south, geohash::west), geohash::northeast));
                }
                $box->south -= $dlat / 2;
            }
        }
        
        if ($quadrant == geohash::northeast || $quadrant == geohash::southeast) {
            // east side
            $set->add_set(geohash::halve(geohash::neighbor($center, geohash::east), geohash::west));
            $box->east += $dlon / 2;
        } else {
            // west side
            $set->add_set(geohash::halve(geohash::neighbor($center, geohash::west), geohash::east));
            $box->west -= $dlon / 2;
        }
        
        $this->geohash_set = $set;
        
        $this->max_radius = min($this->center->distance_to_longitude($box->east),
                                $this->center->distance_to_longitude($box->west));
        if ($box->north < 90) {
            $this->max_radius = min($this->center->distance_to_latitude(min($box->north, 90)), $this->max_radius);
        }
        if ($box->south > -90) {
            $this->max_radius = min($this->center->distance_to_latitude(max($box->south, -90)), $this->max_radius);
        }
        return true;
    }
}

/**
 * Converts kilometers to miles.
 * @param float $kilometers
 * @return float miles
 */
function kmtomi($kilometers) {
    return $kilometers * 0.6214;
}

/**
 * Converts miles to kilometers.
 * @param float $miles
 * @return float kilometers
 */
function mitokm($miles) {
    return $miles * 1.609344;
}
