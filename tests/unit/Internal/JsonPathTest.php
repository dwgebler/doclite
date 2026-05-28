<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit\Internal;

use Gebler\Doclite\Internal\JsonPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JsonPathTest extends TestCase
{
    public function testAllowsSimpleIdentifier(): void
    {
        $this->assertSame('foo', JsonPath::validate('foo'));
    }

    public function testAllowsDotSeparatedPath(): void
    {
        $this->assertSame('foo.bar.baz', JsonPath::validate('foo.bar.baz'));
    }

    public function testAllowsTrailingArrayMarker(): void
    {
        $this->assertSame('items[]', JsonPath::validate('items[]'));
    }

    public function testAllowsSpecificArrayIndex(): void
    {
        $this->assertSame('items[0].name', JsonPath::validate('items[0].name'));
    }

    public function testAllowsUnderscoresAndDigitsInSegments(): void
    {
        $this->assertSame('user_email_42', JsonPath::validate('user_email_42'));
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('');
    }

    public function testRejectsLeadingDigit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('1foo');
    }

    public function testRejectsDoubleQuote(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('foo"');
    }

    public function testRejectsBackslash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('foo\\bar');
    }

    public function testRejectsDollarPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('$foo');
    }

    public function testRejectsSingleQuote(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate("foo'");
    }

    public function testRejectsSemicolon(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('foo; DROP TABLE x;--');
    }

    public function testRejectsConsecutiveDots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate('foo..bar');
    }

    public function testRejectsOverlongName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JsonPath::validate(str_repeat('a', 256));
    }
}
