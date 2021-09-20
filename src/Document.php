<?php

/**
 * Document class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use ArgumentCountError;
use DateTimeImmutable;
use Error;
use Exception;
use Gebler\Doclite\Exception\DatabaseException;
use InvalidArgumentException;
use Swaggest\JsonSchema\Schema;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use ValueError;

/**
 * Document
 */
class Document
{
    /**
     * @var array
     */
    private array $data;

    /**
     * @var string
     */
    private string $id;

    /**
     * @var Collection
     */
    private Collection $collection;

    /**
     * @var mixed
     */
    private $jsonSchema = null;

    /**
     * @var ?Serializer
     */
    private static ?Serializer $serializer = null;

    /**
     * Document constructor.
     * @param array $data
     * @param Collection $collection
     * @throws DatabaseException
     */
    public function __construct(array $data, Collection $collection)
    {
        if (!isset($data[Database::ID_FIELD])) {
            throw new DatabaseException(
                sprintf(
                    'Document missing identifier field [%s]',
                    Database::ID_FIELD
                ),
                DatabaseException::ERR_MISSING_ID_FIELD
            );
        }
        $this->id = $data[Database::ID_FIELD];
        $this->data = $data;
        $this->collection = $collection;
        if (!self::$serializer) {
            $encoders = [new JsonEncoder()];
            $normalizers = [
                new DateTimeNormalizer(),
                new ObjectNormalizer(null, null, null, new ReflectionExtractor()),
                new ArrayDenormalizer(),
            ];
            self::$serializer = new Serializer($normalizers, $encoders);
        }
    }

    /**
     * Map a document property to an object or class.
     * @param string $property
     * @param string|object $value Class name or object to populate
     * @return Document
     * @throws DatabaseException
     */
    public function map(string $property, $value): Document
    {
        $item = $this->getValue($property);
        $class = $value;
        $context = [];
        if (is_object($value)) {
            $class = get_class($value);
            $context = [AbstractNormalizer::OBJECT_TO_POPULATE => $value];
        }
        try {
            $deserialized = self::$serializer->denormalize(
                $item,
                $class,
                'array',
                $context
            );
        } catch (ExceptionInterface $e) {
            throw new DatabaseException(sprintf(
                'Unable to map [%s] to [%s]',
                $property,
                $class,
                $e
            ));
        }
        return $this->setValue($property, $deserialized);
    }

    /**
     * Remove JSON schema validation.
     * @return bool
     */
    public function removeJsonSchema(): bool
    {
        $this->jsonSchema = null;
        return true;
    }

    /**
     * Add JSON schema to document for validation.
     * @param string $schema
     * @return bool
     * @throws DatabaseException
     */
    public function addJsonSchema(string $schema): bool
    {
        try {
            $this->jsonSchema = Schema::import(json_decode($schema));
        } catch (Exception $e) {
            throw new DatabaseException(
                'Error importing JSON schema',
                DatabaseException::ERR_INVALID_JSON_SCHEMA,
                $e,
                '',
                ['schema' => $schema]
            );
        }
        return true;
    }

    /**
     * Get a document property by depth, e.g. address.postcode
     * @param string $property
     * @return mixed
     */
    public function getValue(string $property)
    {
        $paths = array_filter(
            explode('.', $property),
            fn($path) => $path !== ''
        );
        if (count($paths) === 1) {
            return $this->__get($paths[0]);
        }

        $data = $this->data;

        foreach ($paths as $k => $path) {
            if (substr_compare($path, '[]', -2) === 0) {
                $path = substr($path, 0, -2);
            }
            if (array_key_exists($path, $data)) {
                if (is_array($data[$path])) {
                    if ($k === array_key_last($paths)) {
                        return $data[$path];
                    }
                    $data = $data[$path];
                    continue;
                }
                return $data[$path];
            } else {
                throw new ValueError(sprintf(
                    'No such property [%s]',
                    $property
                ));
            }
        }
    }

    /**
     * Set a document property by depth, e.g. address.postcode
     * Parent values will be created as necessary.
     * @param string $property
     * @param mixed $value
     * @return self
     * @throws DatabaseException
     */
    public function setValue(string $property, $value): self
    {
        try {
            $existingValue = $this->getValue($property);
        } catch (ValueError $e) {
            $existingValue = null;
        }

        $paths = array_filter(
            explode('.', $property),
            fn($path) => $path !== ''
        );
        if (count($paths) === 1) {
            $this->__set($paths[0], $value);
            return $this;
        }

        $data = &$this->data;

        foreach ($paths as $k => $path) {
            if (substr_compare($path, '[]', -2) === 0) {
                $path = substr($path, 0, -2);
            }
            if (array_key_exists($path, $data)) {
                if (is_array($data[$path])) {
                    if ($k === array_key_last($paths)) {
                        $data[$path] = $value;
                        return $this;
                    }
                    $data = &$data[$path];
                    continue;
                }
                $data[$path] = $value;
                return $this;
            } else {
                if ($k === array_key_last($paths)) {
                    $data[$path] = $value;
                } else {
                    $data[$path] = [];
                    $data = &$data[$path];
                }
            }
        }

        try {
            $this->validateJsonSchema();
        } catch (DatabaseException $e) {
            if (isset($existingValue)) {
                $data[$paths[array_key_last($paths)]] = $existingValue;
            }
            throw $e;
        }
        return $this;
    }

    /**
     * Get a document property via property accessor.
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        throw new ValueError(sprintf('No such property [%s]', $name));
    }

    /**
     * Set a document property via property accessor.
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws DatabaseException
     */
    public function __set(string $name, $value)
    {
        if (array_key_exists($name, $this->data)) {
            $oldValue = $this->data[$name];
        }
        $this->data[$name] = $value;
        try {
            $this->validateJsonSchema();
        } catch (DatabaseException $e) {
            if (isset($oldValue)) {
                $this->data[$name] = $oldValue;
            }
            throw $e;
        }
    }

    /**
     * Validate JSON schema.
     * @return bool
     * @throws DatabaseException
     */
    public function validateJsonSchema(): bool
    {
        if ($this->jsonSchema) {
            try {
                $this->jsonSchema->in(json_decode(json_encode($this->data)));
            } catch (Exception $e) {
                throw new DatabaseException(
                    'Document data violates JSON schema',
                    DatabaseException::ERR_INVALID_DATA,
                    $e,
                    '',
                    ['data' => $this->data, 'error' => $e->getMessage()]
                );
            }
        }
        return true;
    }

    /**
     * Get or set a document property.
     * @param string $name
     * @param array $args
     * @return mixed
     * @throws DatabaseException
     */
    public function __call(string $name, array $args)
    {
        $nameLower = strtolower($name);
        $isGet = false;
        if (
            ($isGet = strpos($nameLower, 'set') !== 0) &&
            strpos($nameLower, 'get') !== 0
        ) {
            throw new Error(sprintf(
                'Call to undefined function [%s]',
                $name
            ));
        }

        if (count($args) > 1 || ($isGet && count($args) > 0)) {
            throw new ArgumentCountError('Too many arguments');
        }

        $propertyName = strtolower(preg_replace(
            '/(?<!^)[A-Z]/',
            '_$0',
            substr($name, 3)
        ));

        if ($isGet) {
            if (array_key_exists($propertyName, $this->data)) {
                return $this->data[$propertyName];
            }
            throw new ValueError(sprintf('No such property [%s]', $name));
        }

        if (array_key_exists($propertyName, $this->data)) {
            $oldValue = $this->data[$propertyName];
        }
        $this->data[$propertyName] = $args[0];
        try {
            $this->validateJsonSchema();
        } catch (DatabaseException $e) {
            if (isset($oldValue)) {
                $this->data[$propertyName] = $oldValue;
            }
            throw $e;
        }
        return $this;
    }

    /**
     * Get document time from its Id, if Id is an appropriate UUID
     * (in the context of Doclite, i.e. auto-generated Id)
     * @return DateTimeImmutable
     * @throws DatabaseException
     *
     */
    public function getTime(): DateTimeImmutable
    {
        try {
            $uuid = Uuid::fromString($this->id);
            if (!$uuid instanceof UuidV1) {
                throw new InvalidArgumentException('Invalid UUID, not v1');
            }
            return $uuid->getDateTime();
        } catch (InvalidArgumentException $e) {
            throw new DatabaseException(
                sprintf('Id [%s] is not a v1 UUID', $this->id),
                DatabaseException::ERR_INVALID_UUID,
                $e,
                '',
                [$this->id]
            );
        }
    }

    /**
     * Generate a new unique ID for this document.
     * When the document is saved with this ID, it will be created as
     * a new document copy in the database.
     * @return string
     */
    public function regenerateId(): string
    {
        $this->setId(Uuid::v1()->toRfc4122());
        return $this->getId();
    }

    /**
     * Get document Id
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set document Id
     * @param string $id
     * @return self
     */
    public function setId(string $id): Document
    {
        $this->id = $id;
        $this->data[Database::ID_FIELD] = $id;
        return $this;
    }

    /**
     * Get document Id alias
     * @return string
     */
    public function getDocliteId(): string
    {
        return $this->id;
    }

    /**
     * Set document Id alias
     * @param string $id
     * @return self
     */
    public function setDocliteId(string $id): Document
    {
        return $this->setId($id);
    }

    /**
     * Alias of getData
     */
    public function toArray(): array
    {
        return $this->getData();
    }

    /**
     * Get document data
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Save the document to its collection.
     * @return bool
     * @throws DatabaseException
     */
    public function save(): bool
    {
        $this->validateJsonSchema();
        return $this->collection->save($this);
    }

    /**
     * Delete the document from its collection.
     * @return bool
     * @throws DatabaseException
     */
    public function delete(): bool
    {
        return $this->collection->deleteDocument($this);
    }
}
