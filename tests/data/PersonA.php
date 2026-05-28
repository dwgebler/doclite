<?php

/**
 * PersonA class.
 */

namespace Gebler\Doclite\Tests\data;

/**
 * PersonA
 */
class PersonA
{
    private string $id_a = '';
    private string $name = '';

    /**
     * @return string
     */
    public function getIdA(): string
    {
        return $this->id_a;
    }

    /**
     * @param string $id_a
     */
    public function setIdA(string $id_a): void
    {
        $this->id_a = $id_a;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
