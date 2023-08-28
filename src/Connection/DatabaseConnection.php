<?php

/**
 * DatabaseConnection class.
 */

declare(strict_types=1);

namespace Gebler\Doclite\Connection;

use Gebler\Doclite\Exception\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;

/**
 * DatabaseConnection
 */
class DatabaseConnection
{
    /**
     * SQLite DSN
     * @var string
     */
    protected string $dsn;
    /**
     * @var bool
     */
    protected bool $readOnly;
    /**
     * PDO connection
     * @var ?PDO
     */
    protected ?PDO $conn;
    /**
     * @var array
     */
    protected array $queryCache;
    /**
     * @var int
     */
    private int $timeout;
    /**
     * @var bool
     */
    private bool $ftsEnabled = false;
    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger = null;
    /**
     * @var bool
     */
    private bool $queryLogging = false;
    /**
     * @var float
     */
    private float $queryLoggingThreshold = 0.5;
    /**
     * @var bool
     */
    private bool $slowQueryLogging = false;

    /**
     * Constructor.
     * @param string $dsn Connection DSN
     * @param bool $readOnly Open in read-only mdoe
     * @param int $timeout Max time in seconds to obtain a lock
     * @param bool $fts Flag to enable full text support (requires SQLite FTS5 extension)
     * @param LoggerInterface|null $logger
     * @param PDO|null $conn
     * @throws DatabaseException on connection error
     */
    public function __construct(
        string $dsn,
        bool $readOnly = false,
        int $timeout = 1,
        bool $fts = false,
        ?LoggerInterface $logger = null,
        ?PDO $conn = null
    ) {
        $this->dsn = $dsn;
        $this->readOnly = $readOnly;
        $this->timeout = $timeout;
        $this->conn = $conn;
        $this->ftsEnabled = $fts;
        $this->logger = $logger;
        $this->queryCache = [];
        $this->init();
    }

    /**
     * Prepare a query and bind values by the appropriate type.
     * @param string $query
     * @param array $params
     * @return PDOStatement
     */
    private function prepareQuery(string $query, array $params): PDOStatement
    {
        $paramCount = count($params);
        /**
         * SQLite weirdly converts booleans to 0 or 1 when they are a single
         * value in the results of json_extract, otherwise an array will
         * return a well-formed JSON value.
         * i.e. json_extract('{"active":true}','$.active') = 1 but
         * json_extract('{"active":true,"foo":"bar"}','$.active','$.foo') = [true, 'bar']
         *
         * This annoying inconsistency must be accounted for when forming
         * the query and binding its parameters. If there are multiple parameters,
         * first we need to wrap any placeholders for booleans in a json() call,
         * then we need to expect the string 'true' in the second
         * WHERE operand (so the overall query portion ends up as json('true')).
         * Otherwise, for singular queries, booleans we bind
         * as an int of 0 or 1. Floats also need to be wrapped in json()
         * call and converted to string to be properly matched.
         *
         * This is stupid but it's unfortunately a quirk of SQLite's
         * json_extract() implementation and we can't do anything about it.
         */
        $replaceCount = 0;
        $formedQuery = preg_replace_callback(
            '/(\?)/',
            function ($matches) use ($params, &$replaceCount) {
                $replaceCount += 1;
                $value = $params[$replaceCount - 1];
                if (is_bool($value)) {
                    return 'json(?)';
                }
                if (is_float($value)) {
                    return 'cast(json(?) as real)';
                }
                return '?';
            },
            $query
        );
        $queryHash = hash('sha256', $formedQuery);
        if (isset($this->queryCache[$queryHash])) {
            $stmt = $this->queryCache[$queryHash];
        } else {
            $stmt = $this->conn->prepare($formedQuery);
            $this->queryCache[$queryHash] = $stmt;
        }

        for ($i = 1; $i <= $paramCount; $i++) {
            $paramType = PDO::PARAM_STR;
            $param = $params[$i - 1];
            if (is_bool($param)) {
                /**
                 * See comments above for why this happens.
                 * This would break if there was some other clause alongside
                 * json_extract(json,?) = ? in a query with a single bool, but
                 * that's not a problem in DocLite's implementation, at least
                 * not right now. May need to refactor or redesign in the
                 * future to cope with the json_extract madness on booleans.
                 * Note we should never reach here for singular bools anyway,
                 * since they are converted in the Database::buildFindQuery
                 * method.
                 */
                if ($paramCount === 2) {
                    $paramType = PDO::PARAM_INT;
                } else {
                    $param = $param ? "true" : "false";
                }
            }
            if (is_int($param)) {
                $paramType = PDO::PARAM_INT;
            } elseif (is_null($param)) {
                $paramType = PDO::PARAM_NULL;
            } elseif (is_float($param)) {
                $paramType = PDO::PARAM_STR;
            }
            $stmt->bindValue($i, $param, $paramType);
        }

        return $stmt;
    }

    /**
     * Clear internal query cache.
     * @return void
     */
    public function clearQueryCache(): void
    {
        foreach ($this->queryCache as $key => $query) {
            $query->closeCursor();
            unset($this->queryCache[$key]);
        }
    }

    /**
     * Begin a transaction.
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if (!$this->conn->inTransaction()) {
            return $this->conn->beginTransaction();
        }
        return false;
    }

    /**
     * Rollback a transaction.
     * @return bool
     */
    public function rollback(): bool
    {
        if ($this->conn->inTransaction()) {
            return $this->conn->rollBack();
        }
        return false;
    }

    /**
     * Commit a transaction.
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->conn->inTransaction()) {
            return $this->conn->commit();
        }
        return false;
    }

    /**
     * Execute a prepared statement and return affected rows.
     * @param string $query
     * @param mixed ...$params
     * @return int
     * @throws DatabaseException
     */
    public function executePrepared(string $query, ...$params): int
    {
        try {
            $start = microtime(true);
            $stmt = $this->prepareQuery($query, $params);
            $stmt->execute();
            $end = microtime(true);
            $this->logSlowQuery($end - $start, $query, $params);
            $result = $stmt->rowCount();
            $stmt->closeCursor();
            return $result;
        } catch (PDOException $e) {
            // If the PDOException is that a unique index constraint has failed, extract the field name(s)
            // from the index name, which is in the form idx_[collection]_[field1]_[field2]...
            if ($e->getCode() === '23000') {
                $matches = [];
                if (preg_match('/idx_([a-z0-9_]+)_([a-z0-9_]+)/', $e->getMessage(), $matches)) {
                    $table = $matches[1];
                    $field = $matches[2];
                    $fields = explode('_', $field);
                    $field = implode('.', $fields);
                    $ex = new DatabaseException(
                        'UNIQUE constraint failed: ' . $table . '.' . $field,
                        DatabaseException::ERR_UNIQUE_CONSTRAINT,
                        $e
                    );
                    $this->logException($ex);
                    throw $ex;
                }
            } else {
                $ex = new DatabaseException('Error executing query', DatabaseException::ERR_QUERY, $e, $query, $params);
                $this->logException($ex);
                throw $ex;
            }
        }
        return 0;
    }

    /**
     * Execute a statement and return affected rows.
     * @param string $query
     * @return int
     * @throws DatabaseException
     */
    public function exec(string $query): int
    {
        try {
            $start = microtime(true);
            $affected = $this->conn->exec($query);
            $end = microtime(true);
            $this->logSlowQuery($end - $start, $query, []);
            // Shouldn't really happen in exception mode, but edge-cases...
            if ($affected === false) {
                throw new DatabaseException('Error executing statement', DatabaseException::ERR_QUERY, null, $query);
            }
            return $affected;
        } catch (PDOException $e) {
            $ex = new DatabaseException('Error executing statement', DatabaseException::ERR_QUERY, $e, $query);
            $this->logException($ex);
            throw $ex;
        }
    }

    /**
     * Execute a query and return the first column of first row as single value.
     * @param string $query
     * @param mixed ...$params
     * @return string
     * @throws DatabaseException
     */
    public function valueQuery(string $query, ...$params): string
    {
        try {
            $start = microtime(true);
            $stmt = $this->prepareQuery($query, $params);
            $stmt->execute();
            $end = microtime(true);
            $this->logSlowQuery($end - $start, $query, $params);
            $result = (string)$stmt->fetchColumn();
            $stmt->closeCursor();
            return $result;
        } catch (PDOException $e) {
            $ex = new DatabaseException('Error executing query', DatabaseException::ERR_QUERY, $e, $query, $params);
            $this->logException($ex);
            throw $ex;
        }
    }

    /**
     * Execute a query and return all results as an array.
     * @param string $query
     * @param mixed ...$params Query parameters
     * @return array
     * @throws DatabaseException
     */
    public function queryAll(string $query, ...$params): array
    {
        try {
            $start = microtime(true);
            $stmt = $this->prepareQuery($query, $params);
            $stmt->execute();
            $end = microtime(true);
            $this->logSlowQuery($end - $start, $query, $params);
            $results = $stmt->fetchAll();
            $stmt->closeCursor();
            return $results;
        } catch (PDOException $e) {
            $ex = new DatabaseException('Error executing query', DatabaseException::ERR_QUERY, $e, $query, $params);
            $this->logException($ex);
            throw $ex;
        }
    }

    /**
     * Execute a query and return the results as a generator.
     * @param string $query
     * @param mixed ...$params Query parameters
     * @return iterable
     * @throws DatabaseException
     */
    public function query(string $query, ...$params): iterable
    {
        try {
            $start = microtime(true);
            $stmt = $this->prepareQuery($query, $params);
            $stmt->execute();
            $end = microtime(true);
            $this->logSlowQuery($end - $start, $query, $params);
            foreach ($stmt as $row) {
                yield $row;
            }
            $stmt->closeCursor();
        } catch (PDOException $e) {
            $ex = new DatabaseException('Error executing query', DatabaseException::ERR_QUERY, $e, $query, $params);
            $this->logException($ex);
            throw $ex;
        }
    }

    /**
     * Enable full query logging.
     */
    public function enableQueryLogging(): void
    {
        $this->queryLogging = true;
    }
    /**
     * Disable full query logging.
     */
    public function disableQueryLogging(): void
    {
        $this->queryLogging = false;
    }
    /**
     * Enable slow query logging.
     */
    public function enableSlowQueryLogging(): void
    {
        $this->slowQueryLogging = true;
    }
    /**
     * Disable slow query logging.
     */
    public function disableSlowQueryLogging(): void
    {
        $this->slowQueryLogging = false;
    }
    /**
     * Set the logger.
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function logException(\Throwable $e): void
    {
        if ($this->logger) {
            if ($e instanceof DatabaseException) {
                $this->logger->error($e->getMessage(), [
                    'query' => $e->getQuery(),
                    'params' => $e->getParams(),
                    'exception' => $e->getPrevious(),
                ]);
            } else {
                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    private function logQuery(string $query, array $params): void
    {
        if ($this->queryLogging && $this->logger) {
            $this->logger->debug(sprintf('Query: %s', $query), $params);
        }
    }

    private function logSlowQuery(float $time, string $query, array $params)
    {
        $this->logQuery($query, $params);
        if ($time > $this->queryLoggingThreshold && $this->slowQueryLogging && $this->logger) {
            $this->logger->warning(sprintf('Slow query: %s (%s)', $time, $query), $params);
        }
    }

    /**
     * Create the PDO connection.
     * @throws DatabaseException if error connecting
     */
    public function init(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new DatabaseException('DocLite requires the PDO SQLite extension', DatabaseException::ERR_NO_SQLITE);
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => $this->timeout,
        ];
        if ($this->readOnly) {
            $options[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
        }

        try {
            if (!$this->conn) {
                $this->conn = new PDO($this->dsn, null, null, $options);
            }
            $this->conn->sqliteCreateFunction('REGEXP', function ($p, $v) {
                return preg_match('/' . $p . '/', $v);
            }, 2);
            $this->conn->sqliteCreateFunction('JSON_DOCLITE_UNQUOTE', function ($v) {
                // Unescape any special JSON characters
                $v = str_replace(['\"', '\\\\', '\\/'], ['"', '\\', '/'], $v);
                // Remove surrounding quotes
                return trim($v, '"');
            }, 1);
        } catch (PDOException $e) {
            $ex = new DatabaseException(
                'Unable to initialize database connection',
                DatabaseException::ERR_CONNECTION,
                $e
            );
            $this->logException($ex);
            throw $ex;
        }
        $sqliteVersion = $this->conn->query('SELECT sqlite_version()')->fetchColumn();
        if (version_compare($sqliteVersion, '3.38.0', '<')) {
            $compileOptions = $this->conn->query('PRAGMA compile_options')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('ENABLE_JSON1', $compileOptions)) {
                $ex = new DatabaseException(
                    'DocLite requires SQLite3 to be built with JSON1 extension',
                    DatabaseException::ERR_NO_JSON1
                );
                $this->logException($ex);
                throw $ex;
            }
        }
        if ($this->ftsEnabled && !in_array('ENABLE_FTS5', $compileOptions)) {
            $ex = new DatabaseException(
                'Full text search requires SQLite3 to be built with FTS5 extension',
                DatabaseException::ERR_NO_FTS5
            );
            $this->logException($ex);
            throw $ex;
        }
    }
}
