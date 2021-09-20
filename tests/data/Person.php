<?php

/**
 * Person class.
 */

namespace Gebler\Doclite\Tests\data;

/**
 * Person
 */
class Person
{
    private string $id = '';
    private string $name = '';
    private string $customIdField = '';

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
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

    /**
     * @return string
     */
    public function getCustomIdField(): string
    {
        return $this->customIdField;
    }

    /**
     * @param string $customIdField
     */
    public function setCustomIdField(string $customIdField): void
    {
        $this->customIdField = $customIdField;
    }

}