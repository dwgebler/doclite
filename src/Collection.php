<?php

/**
 * Collection class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use DateTimeImmutable;
use Exception;
use Gebler\Doclite\Exception\DatabaseException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

/**
 * Collection
 */
class Collection implements QueryBuilderInterface
{
    /**
     * @var bool
     */
    private static bool $accessorsInitialized = false;
    /**
     * @var PropertyAccessor
     */
    private static PropertyAccessor $sensitiveAccessor;
    /**
     * @var PropertyAccessor
     */
    private static PropertyAccessor $insensitiveAccessor;
    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;
    /**
     * @var string
     */
    private string $name;
    /**
     * @var bool
     */
    private bool $cachingEnabled = false;
    /**
     * @var int
     */
    private int $cacheLifetime = 60;
    /**
     * @var Serializer
     */
    private Serializer $serializer;
    /**
     * @var DocLiteNameConverter
     */
    private DocLiteNameConverter $nameConverter;
    /**
     * @var array
     */
    private array $ftsIndexes = [];

    /**
     * Constructor.
     * @param string $name
     * @param DatabaseInterface $db
     * @throws DatabaseException
     */
    public function __construct(string $name, DatabaseInterface $db)
    {
        $name = strtolower($name);
        $this->nameConverter = new DocLiteNameConverter();
        $encoders = [
            new JsonEncoder(),
            new XmlEncoder(),
            new YamlEncoder(),
            new CsvEncoder(),
        ];
        $normalizers = [
            new DateTimeNormalizer(),
            new ObjectNormalizer(null, $this->nameConverter, null, new ReflectionExtractor()),
            new ArrayDenormalizer(),
        ];
        $this->serializer = new Serializer($normalizers, $encoders);
        $this->name = $name;
        $this->db = $db;
        if (!self::$accessorsInitialized) {
            self::$sensitiveAccessor = PropertyAccess::createPropertyAccessor();
            self::$insensitiveAccessor =
                PropertyAccess::createPropertyAccessorBuilder()
                    ->disableExceptionOnInvalidPropertyPath()
                    ->getPropertyAccessor();
            self::$accessorsInitialized = true;
        }
        if (!$this->db->tableExists($name)) {
            $this->db->createTable($name);
        }

        $cacheName = $this->getCacheName();
        if (!$this->db->tableExists($cacheName)) {
            $this->db->createCacheTable($cacheName);
        }

        $this->ftsIndexes = $this->db->scanFtsTables($this->name);

        $this->addIndex(Database::ID_FIELD);
    }

    /**
     * Get the collection cache table name.
     * @return string
     */
    private function getCacheName(): string
    {
        return $this->name . '_cache';
    }

    /**
     * Get this collection's serializer service.
     * @return Serializer
     */
    public function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    /**
     * Perform a full text search on a collection. Creates the full text index if necessary.
     * @param string $phrase Search phrase
     * @param string[] $fields Document fields to search
     * ?string $className Optional custom class to decode documents
     * ?string $idField Optional custom class ID field
     * @throws DatabaseException
     */
    public function search(
        string $phrase,
        array $fields,
        ?string $className = null,
        ?string $idField = null
    ): iterable {
        return (new QueryBuilder($this))->search($phrase, $fields, $className, $idField);
        //$hash = $this->getFullTextIndex(...$fields);
        //return $this->fullTextSearch($phrase, $fields, $hash, $className, $idField);
    }

    /**
     * Get the FTS table ID, if any, containing all the fields which are to be searched.
     * @param string ...$fields
     * @return ?string The hashed ID, or null if no matching ID found
     */
    private function getFtsTableIdForFields(string ...$fields): ?string
    {
        foreach ($this->ftsIndexes as $hash => $indexedFields) {
            if (empty(array_diff($fields, $indexedFields))) {
                return $hash;
            }
        }
        return null;
    }

    /**
     * Determine if the specified fields are a superset of fields already held in a FTS index.
     * Return the matching hash ID of the FTS table if so, or null if no match.
     * @param string ...$fields
     * @return ?string
     */
    private function hasPartialFtsIndex(string ...$fields): ?string
    {
        foreach ($this->ftsIndexes as $hash => $indexedFields) {
            if (empty(array_diff($indexedFields, $fields))) {
                return $hash;
            }
        }
        return null;
    }

    /**
     * Check if a full text index exists for the given fields.
     * @param string ...$fields
     * @return bool
     */
    public function hasFullTextIndex(string ...$fields): bool
    {
        return $this->getFtsTableIdForFields(...$fields) !== null;
    }

    /**
     * Retrieve or create a full-text search index on document fields in a collection.
     * Requires SQLite FTS5 extension.
     * @param string ...$fields
     * @return string Hash of the indexed fields
     * @throws DatabaseException
     */
    public function getFullTextIndex(string ...$fields): string
    {
        $fieldsHash = $this->getFtsTableIdForFields(...$fields);
        if ($fieldsHash === null) {
            if ($this->db->isReadOnly()) {
                throw new DatabaseException(
                    "Cannot create full text index in read only mode",
                    DatabaseException::ERR_READ_ONLY_MODE
                );
            }
            if (($partialHash = $this->hasPartialFtsIndex(...$fields)) !== null) {
                /**
                 * To avoid unnecessary duplication, if we have an existing index which is a subset
                 * of the index we need to create, we delete it.
                 */
                $this->db->deleteFullTextIndex($this->name, $partialHash);
            }
            $fieldsHash = hash('sha256', serialize($fields));
            if (!isset($this->ftsIndexes[$fieldsHash])) {
                if (!$this->db->createFullTextIndex($this->name, $fieldsHash, ...$fields)) {
                    throw new DatabaseException(
                        "Error creating full text index.",
                        DatabaseException::ERR_QUERY,
                        null,
                        "",
                        $fields
                    );
                }
                $this->ftsIndexes[$fieldsHash] = $fields;
            }
        }
        return $fieldsHash;
    }

    /**
     * Create an index on a document field in a collection.
     * @param string ...$fields
     * @return bool
     * @throws DatabaseException
     */
    public function addIndex(string ...$fields): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }
        return $this->db->createIndex($this->name, ...$fields);
    }

    /**
     * Get the collection name.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Execute a DML query and return the number of affected rows.
     * @param string $query
     * @param array $parameters
     * @return int
     * @throws DatabaseException
     */
    public function executeDmlQuery(string $query, array $parameters): int
    {
        return $this->db->executeDmlQuery($query, $parameters);
    }

    /**
     * Execute a DQL query and fetch results as documents or raw data.
     * @param string $query
     * @param array $parameters
     * @param bool $fetchRaw Fetch raw array
     * @param ?string $class Custom class name
     * @param ?string $classIdProperty Custom class ID property
     * @return iterable
     * @throws DatabaseException
     */
    public function executeDqlQuery(
        string $query,
        array $parameters,
        bool $fetchRaw = false,
        ?string $class = null,
        ?string $classIdProperty = null
    ): iterable {
        $results = [];
        $haveCache = true;
        $haveResults = false;

        if ($this->cachingEnabled) {
            $results = $this->getCache('dqlQuery', [$query, $parameters]);
            if (!empty($results->current())) {
                $haveResults = true;
            }
        }

        if (!$haveResults) {
            $haveCache = false;
            $results = $this->db->executeDqlQuery($query, $parameters);
        }

        if ($fetchRaw) {
            foreach ($results as $result) {
                yield $result;
            }
        }

        $writeCache = $this->cachingEnabled && !$haveCache;

        if ($writeCache) {
            $this->beginTransaction();
        }

        foreach ($results as $result) {
            $data = $result['json'];
            if ($class) {
                $document = $this->deserializeClass(
                    $data,
                    $class,
                    null,
                    $classIdProperty
                );
            } else {
                $document = $this->createDocument($data);
            }
            if ($writeCache) {
                $this->setCache('dqlQuery', [$query, $parameters], $result);
            }
            yield $document;
        }

        if ($writeCache) {
            $this->commit();
        }
    }

    /**
     * Retrieve an item from cache.
     * @param string $type
     * @param mixed $data
     * @return iterable
     * @throws DatabaseException
     */
    private function getCache(string $type, $data): iterable
    {
        $serialized = $this->serializer->serialize($data, 'json');
        $cacheKey = hash('sha256', $serialized);
        $expiryDate = $this->cacheLifetime > 0 ? new DateTimeImmutable() : null;
        $results = $this->db->getCache($this->getCacheName(), $type, $cacheKey, $expiryDate);
        foreach ($results as $result) {
            if (is_string($result)) {
                yield $this->serializer->decode($result, 'json');
            } else {
                yield $result;
            }
        }
    }

    /**
     * Write an item to the cache.
     * @param string $type
     * @param mixed $queryData
     * @param mixed $resultData
     * @return bool
     * @throws DatabaseException
     */
    private function setCache(string $type, $queryData, $resultData): bool
    {
        if ($this->db->isReadOnly()) {
            throw new DatabaseException('Cannot set cache in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        $expiryDate = null;
        $serializedQuery = $this->serializer->serialize($queryData, 'json');
        $cacheData = $this->serializer->encode($resultData, 'json');
        $cacheKey = hash('sha256', $serializedQuery);
        $dataKey = hash('sha256', $cacheData);
        $hasExpiry = $this->cacheLifetime > 0;
        if ($hasExpiry) {
            $expiryString = sprintf("now +%d seconds", $this->cacheLifetime);
            $expiryDate = new DateTimeImmutable($expiryString);
        }

        return $this->db->setCache(
            $this->getCacheName(),
            $type,
            $cacheKey,
            $dataKey,
            $cacheData,
            $expiryDate
        );
    }

    /**
     * Deserialize JSON to a custom class and set the resultant document's ID.
     * @param string $json
     * @param string $className
     * @param ?string $documentId
     * @param ?string $classIdProperty
     * @return object
     * @throws DatabaseException
     */
    private function deserializeClass(
        string $json,
        string $className,
        ?string $documentId = null,
        ?string $classIdProperty = null
    ): object {
        if ($className === Document::class) {
            return $this->createDocument($json);
        }

        $customIdProperty = true;
        if (!$classIdProperty) {
            $classIdProperty = Database::ID_FIELD;
            $customIdProperty = false;
        }

        if (!$documentId) {
            $decoded = $this->serializer->decode($json, 'json');
            $documentId = $decoded[Database::ID_FIELD] ?? null;
            if ($documentId === null) {
                throw new DatabaseException(
                    'Document missing ID field',
                    DatabaseException::ERR_MISSING_ID_FIELD,
                    null,
                    '',
                    ['data' => $decoded]
                );
            }
        }

        if ($customIdProperty) {
            $this->nameConverter->setCustomId($classIdProperty);
        }

        try {
            $deserialized = $this->serializer->deserialize(
                $json,
                $className,
                'json'
            );
        } catch (Exception $e) {
            throw new DatabaseException(
                sprintf('Unable to map document to class [%s]', $className),
                DatabaseException::ERR_MAPPING_DATA,
                $e,
                '',
                [
                    'error' => $e->getMessage(),
                    'class' => $className,
                    'document_id' => $documentId,
                    'data' => $json,
                ]
            );
        }

        if ($customIdProperty) {
            $this->nameConverter->resetCustomId();
        }

        try {
            self::$sensitiveAccessor->setValue(
                $deserialized,
                $classIdProperty,
                $documentId
            );
        } catch (NoSuchPropertyException $e) {
            try {
                self::$sensitiveAccessor->setValue(
                    $deserialized,
                    'docliteid',
                    $documentId
                );
            } catch (NoSuchPropertyException $ex) {
                throw new DatabaseException(
                    'Unable to resolve ID field for class',
                    DatabaseException::ERR_INVALID_ID_FIELD,
                    $ex,
                    '',
                    ['id' => $documentId, 'class' => $className]
                );
            }
        }
        return $deserialized;
    }

    /**
     * Create a Document belonging to this collection from a JSON string.
     * @param string $json
     * @return Document
     * @throws DatabaseException
     */
    private function createDocument(string $json): Document
    {
        return new Document($this->serializer->decode($json, 'json'), $this);
    }

    /**
     * Delete all documents in a collection.
     * @return bool
     * @throws DatabaseException
     */
    public function deleteAll(): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }
        return $this->db->flushTable($this->name);
    }

    /**
     * Clear the collection's cache table.
     * @return bool
     * @throws DatabaseException
     */
    public function clearCache(): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }
        return $this->db->flushTable($this->getCacheName());
    }

    /**
     * Get the cache lifetime in seconds.
     * @return int
     */
    public function getCacheLifetime(): int
    {
        return $this->cacheLifetime;
    }

    /**
     * Set the cache lifetime in seconds.
     * A lifetime of 0 means the cache never expires and must be manually
     * cleared using clearCache(). To disable caching, call disableCache().
     * @param int $lifetime
     * @return self
     */
    public function setCacheLifetime(int $lifetime): self
    {
        $this->cacheLifetime = $lifetime;
        return $this;
    }

    /**
     * Disable cache. Convenience method.
     * @return self
     * @throws DatabaseException
     */
    public function disableCache(): self
    {
        return $this->enableCache(false);
    }

    /**
     * Enable/disable document caching. When enabled, the results of
     * collection queries will be cached for the cache lifetime.
     * @param bool $enabled
     * @return self
     * @throws DatabaseException
     */
    public function enableCache(bool $enabled = true): self
    {
        if ($enabled && $this->db->isReadOnly()) {
            throw new DatabaseException('Cannot enable cache in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }
        $this->cachingEnabled = $enabled;
        return $this;
    }

    /**
     * Begin a transaction
     * @return bool
     * @throws DatabaseException
     */
    public function beginTransaction(): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }
        return $this->db->beginTransaction($this->name);
    }

    /**
     * Commit a transaction
     * @return bool
     * @throws DatabaseException
     */
    public function commit(): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }
        return $this->db->commit($this->name);
    }

    /**
     * Rollback a transaction
     * @return bool
     * @throws DatabaseException
     */
    public function rollback(): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }
        return $this->db->rollback($this->name);
    }

    /**
     * Find all documents in a collection. Results are returned
     * ordered by internal ID. Returns a generator.
     * @param string|null $class
     * @param string|null $classIdProperty
     * @return iterable
     * @throws DatabaseException
     */
    public function findAll(?string $class = null, ?string $classIdProperty = null): iterable
    {
        $results = null;
        $results = $this->db->findAll($this->name, []);
        foreach ($results as $result) {
            if (empty($result)) {
                yield;
            } else {
                if ($class) {
                    $document = $this->deserializeClass(
                        $result,
                        $class,
                        null,
                        $classIdProperty
                    );
                } else {
                    $document = $this->createDocument($result);
                }
                yield $document;
            }
        }
    }

    /**
     * Find all documents matching the specified criteria.
     * @param array $criteria Key/value map of document fields
     * @param ?string $class
     * @param ?string $classIdProperty
     * @return iterable
     * @throws DatabaseException
     */
    public function findAllBy(array $criteria, ?string $class = null, ?string $classIdProperty = null): iterable
    {
        $results = [];
        $haveResults = false;
        $haveCache = true;
        if ($this->cachingEnabled) {
            $results = $this->getCache('findAllBy', $criteria);
            if (!empty($results->current())) {
                $haveResults = true;
            }
        }
        if (!$haveResults) {
            $haveCache = false;
            $results = $this->db->findAll($this->name, $criteria);
        }

        $writeCache = $this->cachingEnabled && !$haveCache;

        if ($writeCache) {
            $this->beginTransaction();
        }
        foreach ($results as $result) {
            if (empty($result)) {
                yield;
            } else {
                if ($class) {
                    $document = $this->deserializeClass(
                        $result,
                        $class,
                        null,
                        $classIdProperty
                    );
                } else {
                    $document = $this->createDocument($result);
                }
                if ($writeCache) {
                    $this->setCache('findAllBy', $criteria, $result);
                }
                yield $document;
            }
        }
        if ($writeCache) {
            $this->commit();
        }
    }

    /**
     * Find a single document matching the specified criteria.
     * @param array $criteria Key/value map of document fields
     * @param ?string $class
     * @param ?string $classIdProperty
     * @return ?object
     * @throws DatabaseException
     */
    public function findOneBy(array $criteria, ?string $class = null, ?string $classIdProperty = null): ?object
    {
        $result = null;
        $data = null;
        if ($this->cachingEnabled) {
            $result = $this->getCache('findOneBy', $criteria);
        }
        if ($result !== null && $result->valid()) {
            $data = $result->current();
        } else {
            $data = $this->db->find($this->name, $criteria);
            if ($this->cachingEnabled) {
                $this->setCache('findOneBy', $criteria, $data);
            }
        }

        if (!empty($data)) {
            if ($class) {
                return $this->deserializeClass(
                    $data,
                    $class,
                    null,
                    $classIdProperty
                );
            }
            return $this->createDocument($data);
        }
        return null;
    }

    /**
     * Delete a document.
     * @param object $document
     * @param ?string $id
     * @return bool
     * @throws DatabaseException
     */
    public function deleteDocument(object $document, ?string $id = null): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }

        if (empty($id)) {
            $id = $this->getDocumentId($document);
        }
        return $this->db->delete($this->name, $id);
    }

    /**
     * Determine a document's ID.
     * @param object $document
     * @return string
     * @throws DatabaseException
     */
    private function getDocumentId(object $document): string
    {
        $id = (string)(
            self::$insensitiveAccessor->getValue($document, Database::ID_FIELD)
            ?? self::$insensitiveAccessor->getValue($document, 'docliteid')
        );
        if ($id === "") {
            throw new DatabaseException(
                'No ID; document must implement getId(), get_id(), ' .
                'getDocliteId() or have property id or ' . Database::ID_FIELD,
                DatabaseException::ERR_MISSING_ID_FIELD,
                null,
                "",
                [$document]
            );
        }
        return $id;
    }

    /**
     * Save an object as a document.
     * @param object $document
     * @param ?string $id Only really needed if ID is in a custom field on
     * a custom class.
     * @param array $ignoreFields List of field names which should not be saved
     * @return bool
     * @throws DatabaseException
     */
    public function save(object $document, ?string $id = null, array $ignoreFields = []): bool
    {
        if ($this->db->isReadOnly()) {
            return false;
        }

        if (empty($id)) {
            $id = $this->getDocumentId($document);
        }
        if ($document instanceof Document) {
            $json = $this->serializer->serialize(
                $document->getData(),
                'json'
            );
        } else {
            $json = $this->serializer->serialize(
                $document,
                'json',
                [AbstractNormalizer::IGNORED_ATTRIBUTES => $ignoreFields]
            );
            // Set the internal ID field if not present. This is not as hacky as it looks;
            // it's actually more efficient than decoding the whole blob to check for a key.
            if (strpos($json, '{') === 0) {
                if (strpos($json, '"' . Database::ID_FIELD . '":') === false) {
                    $json = '{"' . Database::ID_FIELD . '":"' . $id . '",' . substr($json, 1);
                }
            }
        }
        return $this->db->replace($this->name, $id, $json);
    }

    /**
     * Get a new UUID.
     * @return string
     */
    public function getUuid(): string
    {
        return Uuid::v1()->toRfc4122();
    }

    /**
     * Get or create a document by Id, optionally deserializing
     * to a custom object. If no Id is provided, a v1 UUID will be generated.
     * @param ?string $id
     * @param ?string $class Fully qualified class name
     * @param ?string $classIdProperty Name of the class property which should
     * represent internal DocLite ID if not the default.
     * @return object Document or custom object
     * @throws DatabaseException
     */
    public function get(?string $id = null, ?string $class = null, ?string $classIdProperty = null): object
    {
        $result = null;
        $lookup = true;

        if (empty($id)) {
            $id = $this->getUuid();
            $lookup = false;
        }

        if ($class && !class_exists($class)) {
            throw new DatabaseException(
                sprintf('Collection::get() class [%s] not found', $class),
                DatabaseException::ERR_CLASS_NOT_FOUND
            );
        }

        if ($lookup) {
            $result = $this->db->getById($this->name, $id);
        }

        if (empty($result)) {
            if ($this->db->isReadOnly()) {
                throw new DatabaseException(
                    'Cannot create document in read only mode',
                    DatabaseException::ERR_READ_ONLY_MODE
                );
            }

            $data = [Database::ID_FIELD => $id];
            $json = $this->serializer->encode($data, 'json');
            $this->db->insert($this->name, $json);

            if ($class) {
                return $this->deserializeClass(
                    $json,
                    $class,
                    $id,
                    $classIdProperty
                );
            }
            return new Document($data, $this);
        }

        if ($class) {
            return $this->deserializeClass(
                $result,
                $class,
                $id,
                $classIdProperty
            );
        } else {
            return $this->createDocument($result);
        }
    }

    /**
     * @inheritDoc
     */
    public function union(): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->union();
    }

    /**
     * @inheritDoc
     */
    public function intersect(): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->intersect();
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return (new QueryBuilder($this))->count();
    }

    /**
     * @inheritDoc
     */
    public function where(string $field, string $condition, $value = null): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->where($field, $condition, $value);
    }

    /**
     * @inheritDoc
     */
    public function and(string $field, string $condition, $value = null): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->and($field, $condition, $value);
    }

    /**
     * @inheritDoc
     */
    public function or(string $field, string $condition, $value = null): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->or($field, $condition, $value);
    }

    /**
     * @inheritDoc
     */
    public function limit(?int $limit): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->limit($limit);
    }

    /**
     * @inheritDoc
     */
    public function offset(?int $offset): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->offset($offset);
    }

    /**
     * @inheritDoc
     */
    public function orderBy(string $field, string $direction): QueryBuilderInterface
    {
        return (new QueryBuilder($this))->orderBy($field, $direction);
    }

    /**
     * @inheritDoc
     * @throws DatabaseException
     */
    public function fetch(?string $className = null, ?string $idField = null): array
    {
        return (new QueryBuilder($this))->fetch($className, $idField);
    }

    /**
     * @inheritDoc
     */
    public function fetchArray(?string $className = null, ?string $idField = null): array
    {
        return (new QueryBuilder($this))->fetchArray($className, $idField);
    }

    /**
     * @inheritDoc
     * @throws DatabaseException
     */
    public function delete(): int
    {
        return (new QueryBuilder($this))->delete();
    }

    /**
     * @inheritDoc
     */
    public function fullTextSearch(
        string $term,
        array $fields,
        string $ftsId,
        ?string $className,
        ?string $idField
    ): iterable {
        return (new QueryBuilder($this))->fullTextSearch($term, $fields, $ftsId, $className, $idField);
    }
}
