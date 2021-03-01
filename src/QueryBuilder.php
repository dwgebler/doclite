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
     * @var array
     */
    private array $queryConditions = [];
    /**
     * @var array
     */
    private array $queryParameters = [];

    /**
     * QueryBuilder constructor.
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Validate field name.
     * @param string $name
     * @throws InvalidArgumentException
     * @return void
     */
    private function validateFieldName(string $name)
    {
        if (!preg_match('/^[\/A-Za-z0-9._\[\]]+$/', $name)) {
            throw new InvalidArgumentException(sprintf('Invalid field name "%s"; may contain only alphanumeric, ' .
                'dot, underscore and square bracket characters', $name));
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
        $field = str_replace('/', '\\/', $field);
        $isPath = substr_compare($field, '[]', -2) === 0;
        if ($isPath) {
            $field = substr($field, 0, -2);
        }

        $this->queryConditions[] = [
            'type' => $type,
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'path' => $isPath,
            'valueModifier' => $valueModifier,
        ];
        return $this;
    }

    /**
     * Join OR
     * @return QueryBuilderInterface
     */
    public function joinOr(): QueryBuilderInterface
    {
        $this->queryConditions[] = [
            'type' => 'JOIN',
            'value' => 'OR',
        ];
        return $this;
    }

    /**
     * Join OR
     * @return QueryBuilderInterface
     */
    public function joinAnd(): QueryBuilderInterface
    {
        $this->queryConditions[] = [
            'type' => 'JOIN',
            'value' => 'AND',
        ];
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
        $this->queryConditions[] = [
            'type' => 'LIMIT',
            'value' => $limit,
        ];
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offset(?int $offset): QueryBuilderInterface
    {
        $this->queryConditions[] = [
            'type' => 'OFFSET',
            'value' => $offset,
        ];
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
        $this->queryConditions[] = [
            'type' => 'ORDERBY',
            'field' => $field,
            'value' => $direction,
        ];
        return $this;
    }

    /**
     * @inheritDoc
     * @throws DatabaseException
     */
    public function fetch(?string $className = null, ?string $idField = null): array
    {
        return $this->collection->executeDqlQuery(
            $this->getSelectQuery(),
            $this->queryParameters,
            $className,
            $idField
        );
    }

    /**
     * @inheritDoc
     * @throws DatabaseException
     */
    public function delete(): int
    {
        return $this->collection->executeDmlQuery(
            $this->getDeleteQuery(),
            $this->queryParameters
        );
    }

    /**
     * Forge an individual WHERE clause of a SELECT statement.
     * @param string $basePart
     * @param string $treePart
     * @param string $joinPart
     * @param array $condition
     * @param array $queryParts
     * @return void
     */
    private function forgeWhereClause(
        string $basePart,
        string $treePart,
        string $joinPart,
        array $condition,
        array &$queryParts
    ): void {
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
                sprintf(
                    $condition['valueModifier'],
                    $condition['value']
                ) : $condition['value'];
        }
        $sqlPart = sprintf(
            "(%s='$.%s' AND json_tree.value %s %s)",
            $treePart,
            $condition['field'],
            $condition['operator'],
            $parameterPart
        );
        if (count($queryParts) === 0) {
            $queryParts[] = $sqlPart;
        } else {
            $queryParts[] = $joinPart . $basePart . $sqlPart;
        }
    }

    /**
     * Get a DELETE query string with placeholders.
     * @return string
     */
    private function getDeleteQuery(): string
    {
        $selectQuery = rtrim($this->getSelectQuery(), ';');
        return sprintf(
            "DELETE FROM \"%s\" WHERE ROWID IN (SELECT ROWID FROM (%s));",
            $this->collection->getName(),
            $selectQuery
        );
    }

    /**
     * Get a SELECT query string with placeholders.
     * @return string
     */
    private function getSelectQuery(): string
    {
        $query = '';
        $queryParts = [];
        $baseSelect = sprintf("SELECT DISTINCT \"%2\$s\".ROWID, json_extract" .
            "(\"%2\$s\".json,'\$.%1\$s'), \"%2\$s\".json " .
            "FROM \"%2\$s\", json_tree(\"%2\$s\".json, '\$') WHERE ", Database::ID_FIELD, $this->collection->getName());
        $currentLimit = -1;
        $currentOffset = 0;
        $limitClause = "LIMIT 0, -1";
        $orderClause = "";
        $hasOrderBy = false;
        $orderByField = '';
        $closingPart = '';
        $hasJoin = false;
        $skipUnionIntersect = false;
        foreach ($this->queryConditions as $condition) {
            $treePart = 'json_tree.fullkey';
            if (!empty($condition['path'])) {
                $treePart = 'json_tree.path';
            }
            switch ($condition['type']) {
                case 'JOIN':
                    if (count($queryParts) === 0) {
                        continue 2;
                    }
                    $joinPart = " INTERSECT ";
                    if ($condition['value'] === 'OR') {
                        $joinPart = " UNION ";
                    }
                    if ($hasJoin) {
                        $joinPart = ') ' . $joinPart;
                    }
                    $sqlPart = $joinPart . "SELECT * FROM (";
                    $closingPart = ')';
                    $hasJoin = true;
                    $queryParts[] = $sqlPart;
                    $skipUnionIntersect = true;

                    break;
                case 'AND':
                    $intersectPart = $skipUnionIntersect ? '' : " INTERSECT ";
                    $skipUnionIntersect = false;
                    $this->forgeWhereClause($baseSelect, $treePart, $intersectPart, $condition, $queryParts);

                    break;
                case 'OR':
                    $unionPart = $skipUnionIntersect ? '' : " UNION ";
                    $skipUnionIntersect = false;
                    $this->forgeWhereClause($baseSelect, $treePart, $unionPart, $condition, $queryParts);

                    break;
                case 'LIMIT':
                    $currentLimit = $condition['value'];
                    $limitClause = sprintf("LIMIT %d,%d", $currentOffset, $currentLimit);
                    break;
                case 'OFFSET':
                    $currentOffset = $condition['value'];
                    $limitClause = sprintf("LIMIT %d,%d", $currentOffset, $currentLimit);
                    break;
                case 'ORDERBY':
                    $orderClause = sprintf(
                        "ORDER BY json_extract(\"%s\".json,'$.%s') %s",
                        $this->collection->getName(),
                        $condition['field'],
                        $condition['value']
                    );
                    $hasOrderBy = true;
                    $orderByField = $condition['field'];

                    break;
            }
        }

        if (empty($queryParts)) {
            $queryParts[] = '1';
        }

        $query = $baseSelect
            . implode('', $queryParts)
            . $closingPart
            . " "
            . $orderClause
            . " "
            . $limitClause
            . ";";
        // Order By clause must be a column in the final result.
        if ($hasOrderBy) {
            $query = str_replace(
                'SELECT DISTINCT ',
                sprintf(
                    'SELECT DISTINCT json_extract("%s".json,\'$.%s\'), ',
                    $this->collection->getName(),
                    $orderByField
                ),
                $query
            );
        }

        return $query;
    }
}
