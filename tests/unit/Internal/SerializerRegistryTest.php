<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit\Internal;

use Gebler\Doclite\Internal\SerializerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Serializer;

class SerializerRegistryTest extends TestCase
{
    public function testDefaultSerializerIsLazyButStable(): void
    {
        $a = SerializerRegistry::defaultSerializer();
        $b = SerializerRegistry::defaultSerializer();
        $this->assertInstanceOf(Serializer::class, $a);
        $this->assertSame($a, $b);
    }

    public function testCustomIdSerializerIsFreshPerCall(): void
    {
        $a = SerializerRegistry::customIdSerializer('id_a');
        $b = SerializerRegistry::customIdSerializer('id_b');
        $this->assertNotSame($a, $b);
    }

    public function testSensitiveAccessorIsLazyAndStable(): void
    {
        $a = SerializerRegistry::sensitiveAccessor();
        $b = SerializerRegistry::sensitiveAccessor();
        $this->assertSame($a, $b);
    }

    public function testInsensitiveAccessorIsLazyAndStable(): void
    {
        $a = SerializerRegistry::insensitiveAccessor();
        $b = SerializerRegistry::insensitiveAccessor();
        $this->assertSame($a, $b);
    }

    public function testEncodersIsLazyAndStable(): void
    {
        $a = SerializerRegistry::encoders();
        $b = SerializerRegistry::encoders();
        $this->assertSame($a, $b);
        $this->assertCount(4, $a);
    }
}
