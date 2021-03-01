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
class DocLiteNameConverter implements NameConverterInterface
{
    private string $customId = '';
    public function normalize(string $propertyName): string
    {
        return $propertyName;
    }

    public function denormalize(string $propertyName): string
    {
        return $propertyName === Database::ID_FIELD ?
            ($this->customId ? $this->customId : $propertyName)
            : $propertyName;
    }

    public function setCustomId(string $id)
    {
        $this->customId = $id;
    }

    public function resetCustomId(): void
    {
        $this->customId = '';
    }
}
