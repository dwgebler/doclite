<?php

/**
 * DocLiteNameConverter class.
 * When deserializing to a custom class with a custom ID property,
 * ensures the default ID property (currently "__id", which is mapped
 * to any field called "id") is not set where it shouldn't be.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * DocLiteNameConverter
 */
final class DocLiteNameConverter implements NameConverterInterface
{
    public function __construct(private readonly ?string $customId = null)
    {
    }

    public function normalize(string $propertyName): string
    {
        if ($this->customId !== null && $propertyName === $this->customId) {
            return Database::ID_FIELD;
        }
        return $propertyName;
    }

    public function denormalize(string $propertyName): string
    {
        if ($this->customId !== null && $propertyName === Database::ID_FIELD) {
            return $this->customId;
        }
        return $propertyName;
    }
}
