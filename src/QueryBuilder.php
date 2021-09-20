<?php

/**
 * QueryBuilder class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use Gebler\Doclite\Exception\DatabaseException;
use InvalidArgumentException;

/**
 * QueryBuilder
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * @var Collection
     */
    private Collection $collection;
    /**
     * @var array
     */
    private array $expressions = [
        '=' => ['op' => '=', 'val' => null],
        '!=' => ['op' => '!=', 'val' => null],
        '<' =>  ['op' => '<', 'val' => null],
        '<=' =>  ['op' => '<=', 'val' => null],
        '>'  => ['op' => '>', 'val' => null],
        '>='  => ['op' => '>=', 'val' => null],
        'STARTS'  => ['op' => 'LIKE', 'val' => '%s%%'],
        'NOT STARTS'  => ['op' => 'NOT LIKE', 'val' => '%s%%'],
        'CONTAINS'  => ['op' => 'LIKE', 'val' => '%%%s%%'],
        'NOT CONTAINS' => ['op' => 'NOT LIKE', 'val' => '%%%s%%'],
        'ENDS'  => ['op' => 'LIKE', 'val' => '%%%s'],
        'NOT ENDS' => ['op' => 'NOT LIKE', 'val' => '%%%s'],
        'MATCHES' => ['op' => 'REGEXP', 'val' => null],
        'NOT MATCHES' => ['op' => 'NOT REGEXP', 'val' => null],
        'EMPTY' => ['op' => 'IS NULL', 'val' => ''],
        'NOT EMPTY' => ['op' => 'IS NOT NULL', 'val' => ''],
    ];
    /**
     * @var string
     */
    private string $currentQueryKey;
    /**
     * @var array
     */
    private array $queryConditions = [
        'where' => [],
        'and' => [],
        'or' => [],
        'order' => [],
        'offset' => 0,
        'limit' => -1,
    ];
    /**
     * @var array
     */
    private array $queryParameters = [];
    /**
     * @var array
     */
    private array $joins = [];

    /**
     * QueryBuilder constructor.
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
        $this->currentQueryKey = 'where';
    }

    /**
     * Validate field name.
     * @param string $name
     * @throws InvalidArgumentException
     * @return void
     */
    private function validateFieldName(string $name)
    {
        if (!preg_match('/^["\/A-Za-z0-9._\[\]]+$/', $name)) {
            throw new InvalidArgumentException(sprintf('Invalid field name "%s"; may contain only alphanumeric, ' .
                'dot, slash, quote, underscore and square bracket characters', $name));
        }
    }

    /**
     * Validate condition and return its equivalent operator,
     * with any modifier this operator must make to the value.
     * @param string $condition
     * @throws InvalidArgumentException
     * @return array
     */
    private function validateCondition(string $condition): array
    {
        if (!in_array($condition, array_keys($this->expressions))) {
            throw new InvalidArgumentException(sprintf('Condition "%s" is not valid', $condition));
        }
        $operator = $this->expressions[$condition]['op'];
        $modifier = $this->expressions[$condition]['val'];
        return [$operator, $modifier];
    }

    /**
     * Conditional clause (i.e. AND or OR)
     * @param string $type
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return QueryBuilderInterface
     */
    private function conditionalClause(string $type, string $field, string $condition, $value): QueryBuilderInterface
    {
        $this->validateFieldName($field);
        list($operator, $valueModifier) = $this->validateCondition(strtoupper($condition));
        $isPath = substr_compare($field, '[]', -2) === 0;
        if ($isPath) {
            $field = substr($field, 0, -2);
        }

        $queryCondition = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'path' => $isPath,
            'valueModifier' => $valueModifier,
            'condition' => $type,
        ];

        if (empty($this->queryConditions[$this->currentQueryKey])) {
            $queryCondition['condition'] = '';
        }

        $this->queryConditions[$this->currentQueryKey][] = $queryCondition;


        return $this;
    }

    /**
     * Join OR
     * @return QueryBuilderInterface
     */
    public function union(): QueryBuilderInterface
    {
        if (!empty($this->queryConditions['where'])) {
            $this->currentQueryKey = 'or';
        }
        return $this;
    }

    /**
     * Join OR
     * @return QueryBuilderInterface
     */
    public function intersect(): QueryBuilderInterface
    {
        if (!empty($this->queryConditions['where'])) {
            $this->currentQueryKey = 'and';
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function where(string $field, string $condition, $value = null): QueryBuilderInterface
    {
        return $this->conditionalClause('AND', $field, $condition, $value);
    }

    /**
     * @inheritDoc
     */
    public function and(string $field, string $condition, $value = null): QueryBuilderInterface
    {
        return $this->conditionalClause('AND', $field, $condition, $value);
    }

    /**
     * @inheritDoc
     */
    public function or(string $field, string $condition, $value = null): QueryBuilderInterface
    {
        return $this->conditionalClause('OR', $field, $condition, $value);
    }

    /**
     * @inheritDoc
     */
    public function limit(?int $limit): QueryBuilderInterface
    {
        $this->queryConditions['limit'] = $limit;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offset(?int $offset): QueryBuilderInterface
    {
        $this->queryConditions['offset'] = $offset;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderBy(string $field, string $direction = 'ASC'): QueryBuilderInterface
    {
        $this->validateFieldName($field);
        $direction = strtoupper($direction);
        if ($direction !== "ASC" && $direction !== "DESC") {
            throw new InvalidArgumentException('Sort order must be ASC or DESC');
        }
        $this->queryConditions['order'][] = [
            'field' => $field,
            'value' => $direction,
        ];
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        $result = $this->collection->executeDqlQuery($this->getCountQuery(), $this->queryParameters, true);
        $first = $result->current();
        if (isset($first['c'])) {
            return (int)$result->current()['c'];
        }
        return -1;
    }

    /**
     * @inheritDoc
     */
    public function fetch(?string $className = null, ?string $idField = null): iterable
    {
        return $this->collection->executeDqlQuery(
            $this->getSelectQuery(),
            $this->queryParameters,
            false,
            $className,
            $idField
        );
    }

    /**
     * @inheritdoc
     */
    public function search(
        string $phrase,
        array $fields,
        ?string $className = null,
        ?string $idField = null
    ): iterable {
        $hash = $this->collection->getFullTextIndex(...$fields);
        $query = $this->getFullTextSearchQuery($phrase, $fields, $hash);
        return $this->collection->executeDqlQuery(
            $query,
            $this->queryParameters,
            false,
            $className,
            $idField
        );
    }

    /**
     * @inheritdoc
     */
    public function join(
        Collection $collection,
        string $field,
        string $key,
        bool $excludeField = false
    ): QueryBuilderInterface {
        $this->validateFieldName($field);
        $this->validateFieldName($key);
        $this->joins[] = [
            'collection' => $collection->getName(),
            'field' => $field,
            'key' => $key,
            'exclude' => $excludeField
        ];
        return $this;
    }

    /**
     * Get search query
     */
    private function getFullTextSearchQuery(string $phrase, array $fields, string $hash): string
    {
        $phrases = [];
        foreach ($fields as $field) {
            if (!empty($phrase)) {
                $ftsTerm = '"' . str_replace('"', '""', $phrase) . '"';
                $phrases[] = strtolower($this->collection->getName()) . '_' . $field . ':' . $ftsTerm;
            }
        }
        $phrase = implode(' OR ', $phrases);
        $table = 'fts_' . strtolower($this->collection->getName()) . '_' . $hash;
        $queryTemplate = "SELECT s.rowid, s.rank, {$this->collection->getName()}.json FROM {$table} s INNER JOIN " .
            $this->collection->getName() . " ON {$this->collection->getName()}.rowid = s.rowid %s WHERE {$table} " .
            "MATCH ? AND %s ORDER BY s.rank LIMIT %d OFFSET %d;";

        $this->queryParameters = [$phrase];
        $treePart = '';
        $wherePart = $this->getWhereCondition($treePart);
        $limitPart = $this->queryConditions['limit'];
        $offsetPart = $this->queryConditions['offset'];

        return sprintf($queryTemplate, $treePart, $wherePart, $limitPart, $offsetPart);
    }

    /**
     * @inheritDoc
     */
    public function fetchArray(?string $className = null, ?string $idField = null): array
    {
        return iterator_to_array($this->fetch($className, $idField));
    }

    /**
     * @inheritDoc
     */
    public function delete(): int
    {
        return $this->collection->executeDmlQuery(
            $this->getDeleteQuery(),
            $this->queryParameters
        );
    }

    /**
     * Get the WHERE portion of a query.
     * @param string|null $treePart
     * @return string
     */
    private function getWhereCondition(?string &$treePart = null): string
    {
        $wherePart = '';
        $treePart = $treePart ?? '';
        $treeCount = 0;
        $treeFields = [];

        $whereGroups = ['where' => '', 'and' => ' AND ', 'or' => ' OR '];
        foreach ($whereGroups as $group => $sqlWord) {
            if (!empty($this->queryConditions[$group])) {
                $wherePart .= $sqlWord . '(';
                foreach ($this->queryConditions[$group] as $condition) {
                    $this->forgeWhereClause($condition, $wherePart, $treePart, $treeCount, $treeFields);
                }
                $wherePart .= ')';
            }
        }

        if (empty($wherePart)) {
            $wherePart = '1';
        }
        return $wherePart;
    }

    /**
     * Forge the ORDER BY clause of a SELECT statement.
     *
     * @return string The order by conditions.
     */
    private function forgeOrderClause(): string
    {
        $orderParts = [];
        foreach ($this->queryConditions['order'] as $order) {
            $orderParts[] = sprintf(
                'json_extract("%s".json, \'$.%s\') %s',
                $this->collection->getName(),
                $order['field'],
                $order['value']
            );
        }
        return implode(', ', $orderParts);
    }

    /**
     * Forge the WHERE clause of a statement.
     * @param array $condition
     * @param string $wherePart
     * @param string $treePart
     * @param int $treeCount
     * @param array $treeFields
     * @return void
     */
    private function forgeWhereClause(
        array $condition,
        string &$wherePart,
        string &$treePart,
        int &$treeCount,
        array &$treeFields
    ): void {
        $treeTemplate = sprintf(", json_tree(\"%s\".json, '$.%%s') AS t%%d", $this->collection->getName());
        $rootTemplate = sprintf("%%s (json_extract(\"%s\".json, '$.%%s') %%s %%s) ", $this->collection->getName());
        $pathTemplate = "%1\$s (%2\$s.value %3\$s %4\$s) ";

        if (is_array($condition['value'])) {
            $condition['value'] = json_encode($condition['value']);
        }
        // Again, SQLite's json_extract() on a single boolean
        // value gets converted to 1 or 0, not true or false.
        if (is_bool($condition['value'])) {
            $condition['value'] = (int)$condition['value'];
        }
        $parameterPart = '?';
        if ($condition['valueModifier'] === '') {
            $parameterPart = '';
        } else {
            $this->queryParameters[] = $condition['valueModifier'] ?
                sprintf($condition['valueModifier'], $condition['value']) : $condition['value'];
        }

        if (!$condition['path']) {
            $wherePart .= sprintf(
                $rootTemplate,
                $condition['condition'],
                $condition['field'],
                $condition['operator'],
                $parameterPart
            );
        } else {
            if (!in_array($condition['field'], $treeFields)) {
                $treeCount += 1;
                $treePart .= sprintf($treeTemplate, $condition['field'], $treeCount);
                $treeFields[] = $condition['field'];
            }
            $wherePart .= sprintf(
                $pathTemplate,
                $condition['condition'],
                't' . $treeCount,
                $condition['operator'],
                $parameterPart
            );
        }
    }

    /**
     * Get a DELETE query string with placeholders.
     * @return string
     */
    private function getDeleteQuery(): string
    {
        $this->queryParameters = [];
        $baseSelect = sprintf(
            'DELETE FROM "%1$s"',
            $this->collection->getName()
        );
        $queryTemplate = $baseSelect . '%s WHERE %s LIMIT %d;';
        $treePart = '';
        $wherePart = $this->getWhereCondition($treePart);
        $limitPart = $this->queryConditions['limit'];

        return sprintf($queryTemplate, $treePart, $wherePart, $limitPart);
    }

    /**
     * Get a COUNT query string with placeholders.
     * @return string
     */
    private function getCountQuery(): string
    {
        $this->queryParameters = [];
        $baseSelect = sprintf(
            'SELECT COUNT (DISTINCT "%1$s".ROWID) AS c FROM "%1$s"',
            $this->collection->getName()
        );
        $queryTemplate = $baseSelect . '%s WHERE %s;';
        $treePart = '';
        $wherePart = $this->getWhereCondition($treePart);

        return sprintf($queryTemplate, $treePart, $wherePart);
    }

    /**
     * Get the column to select as primary query result, including any joins as necessary.
     * @param ?string $treePart
     * @param ?string $wherePart
     * @return string
     */
    private function getSelectColumn(?string &$treePart = null, ?string &$wherePart = null): string
    {
        if (is_null($treePart)) {
            $treePart = '';
        }
        if (is_null($wherePart)) {
            $wherePart = '';
        }
        $default = sprintf('"%1$s".json', $this->collection->getName());
        $select = null;
        if (!empty($this->joins)) {
            $select = "json_set({$default}%s) AS json";
            $queryPart = '';
            $joinCounter = 1;
            foreach ($this->joins as $join) {
                $jsonPart = sprintf('json(j%d.json)', $joinCounter);
                if ($join['exclude']) {
                    $jsonPart = sprintf("json_remove(%s, '$.%s')", $jsonPart, $join['field']);
                }
                $queryPart .= sprintf(
                    ", '$.%s', json(json_group_array(DISTINCT %s))",
                    $join['collection'],
                    $jsonPart
                );
                $treePart .= sprintf(', "%s" AS j%d', $join['collection'], $joinCounter);
                $wherePart .= sprintf(
                    " AND (json_extract(j%d.json,'$.%s') = json_extract(%s.json, '$.%s'))",
                    $joinCounter,
                    $join['field'],
                    $this->collection->getName(),
                    $join['key']
                );
                $joinCounter += 1;
            }
            $select = sprintf($select, $queryPart);
        }
        return $select ?? $default;
    }

    /**
     * Get a SELECT query string with placeholders.
     * @return string
     */
    private function getSelectQuery(): string
    {
        $this->queryParameters = [];
        $baseSelect = sprintf('SELECT DISTINCT "%1$s".ROWID, %%s FROM "%1$s"', $this->collection->getName());
        $queryTemplate = $baseSelect . '%s WHERE %s ORDER BY %s LIMIT %d OFFSET %d;';
        $treePart = '';
        $wherePart = $this->getWhereCondition($treePart);
        $limitPart = $this->queryConditions['limit'];
        $offsetPart = $this->queryConditions['offset'];
        $selectColumn = $this->getSelectColumn($treePart, $wherePart);

        $orderPart = $this->forgeOrderClause();
        if (empty($orderPart)) {
            $orderPart = sprintf('"%s".ROWID', $this->collection->getName());
        }

        return sprintf($queryTemplate, $selectColumn, $treePart, $wherePart, $orderPart, $limitPart, $offsetPart);
    }
}
