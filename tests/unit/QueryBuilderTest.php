<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Collection;
use Gebler\Doclite\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private $collection;
    private $builder;
    private $queries = [];

    public function stubDql($query, $params, $class, $idProperty)
    {
        $this->queries['select'][] = [
            'query' => $query,
            'params' => $params,
            'class' => $class,
            'idProperty' => $idProperty
        ];
        return [];
    }

    public function stubDml($query, $params)
    {
        $this->queries['delete'][] = [
            'query' => $query,
            'params' => $params,
        ];
        return 1;
    }

    public function setUp(): void
    {
        $this->queries = [];
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('executeDqlQuery')->willReturnCallback([$this, 'stubDql']);
        $this->collection->method('executeDmlQuery')->willReturnCallback([$this, 'stubDml']);
        $this->collection->method('getName')->wilLReturn('test');
        $this->builder = new QueryBuilder($this->collection);
    }

    public function assertSelectQueryContains($expected, $params = null)
    {
        if (!is_array($expected)) {
            $expected = [$expected];
        }
        $query = array_shift($this->queries['select']);
        foreach ($expected as $item) {
            $this->assertStringContainsString($item, $query['query']);
        }
        if ($params) {
            foreach ($params as $param) {
                $this->assertContains($param, $query['params']);
            }
        }
    }

    public function testBasicSelect()
    {
        $this->builder->where('foo', '=', 'bar')->fetch();
        $expected = 'SELECT DISTINCT "test".ROWID, "test".json FROM "test" WHERE ( (json_extract("test".json, \'$.foo\') = ?) ) ORDER BY "test".ROWID LIMIT -1 OFFSET 0';
        $query = array_shift($this->queries['select']);
        $this->assertStringStartsWith($expected, $query['query']);
    }

    public function testDefaultWhereAll()
    {
        $this->builder->fetch();
        $expected = 'SELECT DISTINCT "test".ROWID, "test".json FROM "test" WHERE 1';
        $query = array_shift($this->queries['select']);
        $this->assertStringStartsWith($expected, $query['query']);
    }

    public function testOrderByAsc()
    {
        $this->builder->where('foo', '=', 'bar')->orderBy('baz')->fetch();
        $expected = 'ORDER BY json_extract("test".json, \'$.baz\') ASC';
        $this->assertSelectQueryContains($expected);
    }

    public function testOrderByDesc()
    {
        $this->builder->where('foo', '=', 'bar')->orderBy('baz', 'desc')->fetch();
        $expected = 'ORDER BY json_extract("test".json, \'$.baz\') DESC';
        $this->assertSelectQueryContains($expected);
    }

    public function testDefaultOffSetLimit()
    {
        $this->builder->where('foo', '=', 'bar')->fetch();
        $expected = "LIMIT -1 OFFSET 0";
        $this->assertSelectQueryContains($expected);
    }

    public function testCustomLimitDefaultOffset()
    {
        $this->builder->where('foo', '=', 'bar')->limit(50)->fetch();
        $expected = "LIMIT 50 OFFSET 0";
        $this->assertSelectQueryContains($expected);
    }

    public function testCustomOffsetDefaultLimit()
    {
        $this->builder->where('foo', '=', 'bar')->offset(50)->fetch();
        $expected = "LIMIT -1 OFFSET 50";
        $this->assertSelectQueryContains($expected);
    }

    public function testCustomLimitCustomOffset()
    {
        $this->builder->where('foo', '=', 'bar')->offset(40)->limit(50)->fetch();
        $expected = "LIMIT 50 OFFSET 40";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereContains()
    {
        $this->builder->where('foo', 'CONTAINS', 'bar')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar%']);
    }

    public function testWhereStartsWith()
    {
        $this->builder->where('foo', 'STARTS', 'bar')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') LIKE ?)";
        $this->assertSelectQueryContains($expected, ['bar%']);
    }

    public function testWhereEndsWith()
    {
        $this->builder->where('foo', 'ENDS', 'bar')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar']);
    }

    public function testWhereNotContains()
    {
        $this->builder->where('foo', 'NOT CONTAINS', 'bar')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar%']);
    }

    public function testWhereNotStartsWith()
    {
        $this->builder->where('foo', 'NOT STARTS', 'bar')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT LIKE ?)";
        $this->assertSelectQueryContains($expected, ['bar%']);
    }

    public function testWhereNotEndsWith()
    {
        $this->builder->where('foo', 'NOT ENDS', 'bar')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar']);
    }

    public function testWhereRegExp()
    {
        $this->builder->where('foo', 'MATCHES', '^[A-Za-z]*$')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') REGEXP ?)";
        $this->assertSelectQueryContains($expected, ['^[A-Za-z]*$']);
    }

    public function testWhereNotRegExp()
    {
        $this->builder->where('foo', 'NOT MATCHES', '^[A-Za-z]*$')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT REGEXP ?)";
        $this->assertSelectQueryContains($expected, ['^[A-Za-z]*$']);
    }

    public function testWhereIsNull()
    {
        $this->builder->where('foo', 'EMPTY')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') IS NULL )";
        $this->assertEmpty($this->queries['select'][0]['params']);
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereIsNotNull()
    {
        $this->builder->where('foo', 'NOT EMPTY')->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') IS NOT NULL )";
        $this->assertEmpty($this->queries['select'][0]['params']);
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereAnd()
    {
        $this->builder->where('foo', '=', 'bar')
            ->and('bar', '<', 100)->fetch();
        $expected ="(json_extract(\"test\".json, '$.foo') = ?) AND (json_extract(\"test\".json, '$.bar') < ?)";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereOr()
    {
        $this->builder->where('foo', '=', 'bar')
            ->or('bar', '<', 100)->fetch();
        $expected = "(json_extract(\"test\".json, '$.foo') = ?) OR (json_extract(\"test\".json, '$.bar') < ?)";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereJoinAnd()
    {
        $this->builder->where('foo', '=', 'bar')
            ->or('bar', '<', 100)
            ->intersect()
            ->where('baz', '>=', 200)
            ->fetch();
        $expected = "( (json_extract(\"test\".json, '$.foo') = ?) OR (json_extract(\"test\".json, '$.bar') < ?) ) AND ( (json_extract(\"test\".json, '$.baz') >= ?) )";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereJoinOr()
    {
        $this->builder->where('foo', '=', 'bar')
            ->or('bar', '<', 100)
            ->union()
            ->where('baz', '>=', 200)
            ->fetch();
        $expected = "( (json_extract(\"test\".json, '$.foo') = ?) OR (json_extract(\"test\".json, '$.bar') < ?) ) OR ( (json_extract(\"test\".json, '$.baz') >= ?) )";
        $this->assertSelectQueryContains($expected);
    }

    public function testDeleteWrapsSelectResult()
    {
        $this->builder->where('foo', 'NOT EMPTY')->fetch();
        $query = substr($this->queries['select'][0]['query'], 0, -1);
        $deleteBuilder = new QueryBuilder($this->collection);
        $deleteBuilder->where('foo', 'NOT EMPTY')->delete();
        $expected = "DELETE FROM \"test\" WHERE ROWID IN (SELECT ROWID FROM (".$query."));";
        $this->assertEquals($expected, $this->queries['delete'][0]['query']);
    }

    public function testCountWrapsSelectResult()
    {
        $this->builder->where('foo', 'NOT EMPTY')->fetch();
        $query = substr($this->queries['select'][0]['query'], 0, -1);
        $countBuilder = new QueryBuilder($this->collection);
        $countBuilder->where('foo', 'NOT EMPTY')->count();
        $expected = "SELECT COUNT(*) AS c FROM (".$query.");";
        $this->assertEquals($expected, $this->queries['select'][1]['query']);
    }
}
