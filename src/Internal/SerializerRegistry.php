<?php

/**
 * SerializerRegistry — central store for lazy-init Serializer and PropertyAccessor instances.
 */

declare(strict_types=1);

namespace Gebler\Doclite\Internal;

use Gebler\Doclite\DocLiteNameConverter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * SerializerRegistry
 *
 * @internal
 */
final class SerializerRegistry
{
    private static ?Serializer $defaultSerializer = null;
    private static ?PropertyAccessor $sensitiveAccessor = null;
    private static ?PropertyAccessorInterface $insensitiveAccessor = null;
    /** @var array<EncoderInterface>|null */
    private static ?array $encoders = null;

    /**
     * Return the shared default Serializer (lazy-init on first call).
     */
    public static function defaultSerializer(): Serializer
    {
        return self::$defaultSerializer ??= self::buildSerializer(null);
    }

    /**
     * Build and return a fresh Serializer configured for the given custom ID property.
     * A new instance is returned on every call so no mutable shared state leaks
     * between interleaved requests with different custom IDs.
     */
    public static function customIdSerializer(string $customId): Serializer
    {
        return self::buildSerializer($customId);
    }

    /**
     * Return the shared case-sensitive PropertyAccessor (lazy-init on first call).
     */
    public static function sensitiveAccessor(): PropertyAccessor
    {
        return self::$sensitiveAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    /**
     * Return the shared case-insensitive PropertyAccessor that does not throw on
     * invalid paths (lazy-init on first call).
     */
    public static function insensitiveAccessor(): PropertyAccessorInterface
    {
        return self::$insensitiveAccessor ??= PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor();
    }

    /**
     * Return the shared encoder list (lazy-init on first call).
     *
     * @return array<EncoderInterface>
     */
    public static function encoders(): array
    {
        return self::$encoders ??= [
            new JsonEncoder(),
            new XmlEncoder(),
            new YamlEncoder(),
            new CsvEncoder(),
        ];
    }

    /**
     * Build a Serializer with a DocLiteNameConverter configured for the given custom ID,
     * or the default (null) ID when none is specified.
     */
    private static function buildSerializer(?string $customId): Serializer
    {
        $converter = new DocLiteNameConverter($customId);
        /** @var array<NormalizerInterface> $normalizers */
        $normalizers = [
            new DateTimeNormalizer(),
            new ObjectNormalizer(null, $converter, null, new ReflectionExtractor()),
            new ArrayDenormalizer(),
        ];
        return new Serializer($normalizers, self::encoders());
    }
}
