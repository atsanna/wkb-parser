<?php
/**
 * Copyright (C) 2015 Derek J. Lambert
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace CrEOF\WKB;

use CrEOF\WKB\Exception\UnexpectedValueException;

/**
 * Parser for WKB/EWKB spatial object data
 *
 * @author  Derek J. Lambert <dlambert@dereklambert.com>
 * @license http://dlambert.mit-license.org MIT
 */
class Parser
{
    const WKB_POINT               = 1;
    const WKB_LINESTRING          = 2;
    const WKB_POLYGON             = 3;
    const WKB_MULTIPOINT          = 4;
    const WKB_MULTILINESTRING     = 5;
    const WKB_MULTIPOLYGON        = 6;
    const WKB_GEOMETRYCOLLECTION  = 7;

    const WKB_SRID                = 0x20000000;
    const WKB_M                   = 0x40000000;
    const WKB_Z                   = 0x80000000;

    const TYPE_GEOMETRY           = 'Geometry';
    const TYPE_POINT              = 'Point';
    const TYPE_LINESTRING         = 'LineString';
    const TYPE_POLYGON            = 'Polygon';
    const TYPE_MULTIPOINT         = 'MultiPoint';
    const TYPE_MULTILINESTRING    = 'MultiLineString';
    const TYPE_MULTIPOLYGON       = 'MultiPolygon';
    const TYPE_GEOMETRYCOLLECTION = 'GeometryCollection';

    /**
     * @var int
     */
    private $type;

    /**
     * @var int
     */
    private $srid;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @param string $input
     */
    public function __construct($input)
    {
        $this->reader = new Reader($input);
    }

    /**
     * Parse input data
     *
     * @return array
     */
    public function parse()
    {
        $value         = $this->geometry();
        $value['srid'] = $this->srid;

        return $value;
    }

    /**
     * Parse geometry data
     *
     * @return array
     */
    private function geometry()
    {
        $this->byteOrder();
        $this->type();

        if ($this->hasFlag(self::WKB_SRID)) {
            $this->srid();
        }

        $typeName = $this->getTypeName();

        return array(
            'type'  => $typeName,
            'value' => $this->$typeName()
        );
    }

    /**
     * Check presence flags
     *
     * @param int $flag
     *
     * @return bool
     */
    private function hasFlag($flag)
    {
        return ($this->type & $flag) == $flag;
    }

    /**
     * Get name of data type
     *
     * @return string
     * @throws UnexpectedValueException
     */
    private function getTypeName()
    {
        switch ($this->type) {
            case (self::WKB_POINT):
                $type = self::TYPE_POINT;
                break;
            case (self::WKB_LINESTRING):
                $type = self::TYPE_LINESTRING;
                break;
            case (self::WKB_POLYGON):
                $type = self::TYPE_POLYGON;
                break;
            case (self::WKB_MULTIPOINT):
                $type = self::TYPE_MULTIPOINT;
                break;
            case (self::WKB_MULTILINESTRING):
                $type = self::TYPE_MULTILINESTRING;
                break;
            case (self::WKB_MULTIPOLYGON):
                $type = self::TYPE_MULTIPOLYGON;
                break;
            case (self::WKB_GEOMETRYCOLLECTION):
                $type = self::TYPE_GEOMETRYCOLLECTION;
                break;
            default:
                throw new UnexpectedValueException(sprintf('Unsupported WKB type "%s".', $this->type));
                break;
        }

        return strtoupper($type);
    }

    /**
     * Parse data byte order
     */
    private function byteOrder()
    {
        $this->reader->byteOrder();
    }

    /**
     * Parse data type
     */
    private function type()
    {
        $this->type = $this->reader->long();
    }

    /**
     * Parse SRID value
     *
     * @throws UnexpectedValueException
     */
    private function srid()
    {
        $this->type = $this->type ^ self::WKB_SRID;
        $this->srid = $this->reader->long();
    }

    /**
     * Parse POINT values
     *
     * @return float[]
     */
    private function point()
    {
        return array(
            $this->reader->double(),
            $this->reader->double()
        );
    }

    /**
     * Parse LINESTRING value
     *
     * @return array
     */
    private function lineString()
    {
        return $this->valueArray(self::TYPE_POINT);
    }

    /**
     * Parse POLYGON value
     *
     * @return array[]
     */
    private function polygon()
    {
        return $this->valueArray(self::TYPE_LINESTRING);
    }

    /**
     * Parse MULTIPOINT value
     *
     * @return array[]
     */
    private function multiPoint()
    {
        return $this->valueArrayValues(self::TYPE_GEOMETRY);
    }

    /**
     * Parse MULTILINESTRING value
     *
     * @return array[]
     */
    private function multiLineString()
    {
        return $this->valueArrayValues(self::TYPE_GEOMETRY);
    }

    /**
     * Parse MULTIPOLYGON value
     *
     * @return array[]
     */
    private function multiPolygon()
    {
        return $this->valueArrayValues(self::TYPE_GEOMETRY);
    }

    /**
     * @return array[]
     */
    private function geometryCollection()
    {
        return $this->valueArray(self::TYPE_GEOMETRY);
    }

    /**
     * @param string $type
     *
     * @return array[]
     */
    private function valueArray($type)
    {
        $count  = $this->reader->long();
        $values = array();

        for ($i = 0; $i < $count; $i++) {
            $values[] = $this->$type();
        }

        return $values;
    }

    /**
     * @param string $type
     *
     * @return array[]
     */
    private function valueArrayValues($type)
    {
        $values = $this->valueArray($type);

        array_walk($values, create_function('&$a', '$a = $a["value"];'));

        return $values;
    }
}
