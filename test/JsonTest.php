<?php

declare(strict_types=1);

namespace Danipa\Sdk\Tests;

use Danipa\Sdk\Internal\Json;
use Danipa\Sdk\Internal\MalformedResponse;
use Danipa\Sdk\Internal\Money;
use PHPUnit\Framework\TestCase;

final class JsonTest extends TestCase
{
    public function testDecimalNumbersArriveAsExactStrings(): void
    {
        $d = Json::decodeObject('{"a":125.00,"b":1234567890123.45,"c":-0.10,"d":1.5e3,"n":7}');

        self::assertSame('125.00', $d['a'], 'trailing zeros kept');
        self::assertSame('1234567890123.45', $d['b'], 'no float rounding');
        self::assertSame('-0.10', $d['c']);
        self::assertSame('1.5e3', $d['d']);
        self::assertSame(7, $d['n'], 'integers stay integers');
    }

    public function testNumbersInsideStringsAreUntouched(): void
    {
        $d = Json::decodeObject('{"s":"price 1.50 \"quoted 2.25\" \\\\","arr":[1.10,{"x":0.5}]}');

        self::assertSame('price 1.50 "quoted 2.25" \\', $d['s']);
        self::assertSame(['1.10', ['x' => '0.5']], $d['arr']);
    }

    public function testDecimalAccessorAcceptsNumberOrString(): void
    {
        $d = Json::decodeObject('{"n":10,"f":10.5,"s":"10.50","bad":"ten"}');
        self::assertSame('10', Json::decimal($d, 'n'));
        self::assertSame('10.5', Json::decimal($d, 'f'));
        self::assertSame('10.50', Json::decimal($d, 's'));

        $this->expectException(MalformedResponse::class);
        Json::decimal($d, 'bad');
    }

    public function testInvalidJsonIsMalformedNotAWarning(): void
    {
        $this->expectException(MalformedResponse::class);
        Json::decodeObject('{"a":');
    }

    public function testMoneyNormalisation(): void
    {
        self::assertSame('10.50', Money::normalize('10.50'));
        self::assertSame('10', Money::normalize(10));
        self::assertSame('10.5', Money::normalize(10.5));
        self::assertSame('0.1', Money::normalize(0.1));
        $this->expectException(\InvalidArgumentException::class);
        Money::normalize('abc');
    }
}
