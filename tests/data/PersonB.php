<?php

/**
 * PersonB class.
 */

namespace Gebler\Doclite\Tests\data;

/**
 * PersonB
 */
class PersonB
{
    private string $id_b = '';
    private string $name = '';

    /**
     * @return string
     */
    public function getIdB(): string
    {
        return $this->id_b;
    }

    /**
     * @param string $id_b
     */
    public function setIdB(string $id_b): void
    {
        $this->id_b = $id_b;
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
