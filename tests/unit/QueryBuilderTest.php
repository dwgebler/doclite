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
        yield [];
    }

    public function stubDml($query, $params)
    {
        $this->queries['delete'][] = [
            'query' => $query,
            'params' => $params,
        ];
        return 1;
    }

    protected function setUp(): void
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
        $this->builder->where('foo', '=', 'bar')->fetchArray();
        $expected = 'SELECT DISTINCT "test".ROWID, "test".json FROM "test" WHERE ( (json_extract("test".json, \'$.foo\') = ?) ) ORDER BY "test".ROWID LIMIT -1 OFFSET 0';
        $query = array_shift($this->queries['select']);
        $this->assertStringStartsWith($expected, $query['query']);
    }

    public function testDefaultWhereAll()
    {
        $this->builder->fetchArray();
        $expected = 'SELECT DISTINCT "test".ROWID, "test".json FROM "test" WHERE 1';
        $query = array_shift($this->queries['select']);
        $this->assertStringStartsWith($expected, $query['query']);
    }

    public function testOrderByAsc()
    {
        $this->builder->where('foo', '=', 'bar')->orderBy('baz')->fetchArray();
        $expected = 'ORDER BY json_extract("test".json, \'$.baz\') ASC';
        $this->assertSelectQueryContains($expected);
    }

    public function testOrderByDesc()
    {
        $this->builder->where('foo', '=', 'bar')->orderBy('baz', 'desc')->fetchArray();
        $expected = 'ORDER BY json_extract("test".json, \'$.baz\') DESC';
        $this->assertSelectQueryContains($expected);
    }

    public function testDefaultOffSetLimit()
    {
        $this->builder->where('foo', '=', 'bar')->fetchArray();
        $expected = "LIMIT -1 OFFSET 0";
        $this->assertSelectQueryContains($expected);
    }

    public function testCustomLimitDefaultOffset()
    {
        $this->builder->where('foo', '=', 'bar')->limit(50)->fetchArray();
        $expected = "LIMIT 50 OFFSET 0";
        $this->assertSelectQueryContains($expected);
    }

    public function testCustomOffsetDefaultLimit()
    {
        $this->builder->where('foo', '=', 'bar')->offset(50)->fetchArray();
        $expected = "LIMIT -1 OFFSET 50";
        $this->assertSelectQueryContains($expected);
    }

    public function testCustomLimitCustomOffset()
    {
        $this->builder->where('foo', '=', 'bar')->offset(40)->limit(50)->fetchArray();
        $expected = "LIMIT 50 OFFSET 40";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereContains()
    {
        $this->builder->where('foo', 'CONTAINS', 'bar')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar%']);
    }

    public function testWhereJsonTree()
    {
        $this->builder->where('foo.bar[]', 'CONTAINS', 'baz')->fetchArray();
        $expected = "json_tree(\"test\".json, '$.foo.bar') AS t1 WHERE ( (t1.value LIKE ?) )";
        $this->assertSelectQueryContains($expected, ['%baz%']);
    }

    public function testWhereStartsWith()
    {
        $this->builder->where('foo', 'STARTS', 'bar')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') LIKE ?)";
        $this->assertSelectQueryContains($expected, ['bar%']);
    }

    public function testWhereEndsWith()
    {
        $this->builder->where('foo', 'ENDS', 'bar')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar']);
    }

    public function testWhereNotContains()
    {
        $this->builder->where('foo', 'NOT CONTAINS', 'bar')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar%']);
    }

    public function testWhereNotStartsWith()
    {
        $this->builder->where('foo', 'NOT STARTS', 'bar')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT LIKE ?)";
        $this->assertSelectQueryContains($expected, ['bar%']);
    }

    public function testWhereNotEndsWith()
    {
        $this->builder->where('foo', 'NOT ENDS', 'bar')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT LIKE ?)";
        $this->assertSelectQueryContains($expected, ['%bar']);
    }

    public function testWhereRegExp()
    {
        $this->builder->where('foo', 'MATCHES', '^[A-Za-z]*$')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') REGEXP ?)";
        $this->assertSelectQueryContains($expected, ['^[A-Za-z]*$']);
    }

    public function testWhereNotRegExp()
    {
        $this->builder->where('foo', 'NOT MATCHES', '^[A-Za-z]*$')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') NOT REGEXP ?)";
        $this->assertSelectQueryContains($expected, ['^[A-Za-z]*$']);
    }

    public function testWhereIsNull()
    {
        $this->builder->where('foo', 'EMPTY')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') IS NULL )";
        $this->assertEmpty($this->queries['select'][0]['params']);
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereIsNotNull()
    {
        $this->builder->where('foo', 'NOT EMPTY')->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') IS NOT NULL )";
        $this->assertEmpty($this->queries['select'][0]['params']);
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereAnd()
    {
        $this->builder->where('foo', '=', 'bar')
            ->and('bar', '<', 100)->fetchArray();
        $expected ="(json_extract(\"test\".json, '$.foo') = ?) AND (json_extract(\"test\".json, '$.bar') < ?)";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereOr()
    {
        $this->builder->where('foo', '=', 'bar')
            ->or('bar', '<', 100)->fetchArray();
        $expected = "(json_extract(\"test\".json, '$.foo') = ?) OR (json_extract(\"test\".json, '$.bar') < ?)";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereJoinAnd()
    {
        $this->builder->where('foo', '=', 'bar')
            ->or('bar', '<', 100)
            ->intersect()
            ->where('baz', '>=', 200)
            ->fetchArray();
        $expected = "( (json_extract(\"test\".json, '$.foo') = ?) OR (json_extract(\"test\".json, '$.bar') < ?) ) AND ( (json_extract(\"test\".json, '$.baz') >= ?) )";
        $this->assertSelectQueryContains($expected);
    }

    public function testWhereJoinOr()
    {
        $this->builder->where('foo', '=', 'bar')
            ->or('bar', '<', 100)
            ->union()
            ->where('baz', '>=', 200)
            ->fetchArray();
        $expected = "( (json_extract(\"test\".json, '$.foo') = ?) OR (json_extract(\"test\".json, '$.bar') < ?) ) OR ( (json_extract(\"test\".json, '$.baz') >= ?) )";
        $this->assertSelectQueryContains($expected);
    }

    public function testDeleteForgesWhereClause()
    {
        $this->builder->where('foo', 'NOT EMPTY')->delete();
        $expected = "DELETE FROM \"test\" WHERE ( (json_extract(\"test\".json, '$.foo') IS NOT NULL ) ) LIMIT -1;";
        $this->assertEquals($expected, $this->queries['delete'][0]['query']);
    }

    public function testCountForgesWhereClause()
    {
        $this->builder->where('foo', 'NOT EMPTY')->fetchArray();
        $query = substr($this->queries['select'][0]['query'], 0, -1);
        $countBuilder = new QueryBuilder($this->collection);
        $countBuilder->where('foo', 'NOT EMPTY')->count();
        $expected = "SELECT COUNT (DISTINCT \"test\".ROWID) AS c FROM \"test\" WHERE ( (json_extract(\"test\".json, '$.foo') IS NOT NULL ) );";
        $this->assertEquals($expected, $this->queries['select'][1]['query']);
    }

    public function testQueryExecutionResetsParameters()
    {
        $query = $this->builder->where('foo', '=', 'bar');
        $query->fetchArray();
        $query->count();
        $query->delete();
        $this->assertEquals(['bar'], $this->queries['select'][0]['params']);
        $this->assertEquals(['bar'], $this->queries['select'][1]['params']);
        $this->assertEquals(['bar'], $this->queries['delete'][0]['params']);
    }

    public function testJoinWithExcludeJoiningFieldGeneratesJsonRemoveSql()
    {
        $joinCollection = $this->createMock(Collection::class);
        $joinCollection->method('getName')->wilLReturn('foobar');
        $query = $this->builder->join($joinCollection, 'user_id', 'id', true);
        $query->fetchArray();
        $expected = "json_set(\"test\".json, '$.foobar', json(json_group_array(DISTINCT json_remove(json(j1.json), '$.user_id')))) AS json FROM \"test\", \"foobar\" AS j1 WHERE 1 AND (json_extract(j1.json,'$.user_id') = json_extract(test.json, '$.id'))";
        $this->assertSelectQueryContains($expected);
    }

    public function testJoinGeneratesJsonSetSql()
    {
        $joinCollection = $this->createMock(Collection::class);
        $joinCollection->method('getName')->wilLReturn('foobar');
        $query = $this->builder->join($joinCollection, 'user_id', 'id', false);
        $query->fetchArray();
        $expected = "json_set(\"test\".json, '$.foobar', json(json_group_array(DISTINCT json(j1.json)))) AS json FROM \"test\", \"foobar\" AS j1 WHERE 1 AND (json_extract(j1.json,'$.user_id') = json_extract(test.json, '$.id'))";
        $this->assertSelectQueryContains($expected);
    }

    public function testSearchGeneratesFulltextSql()
    {
        $this->collection->method('getFullTextIndex')->willReturn('abc123');
        iterator_to_array($this->builder->search('text to search', ['foo', 'bar']));
        $expected = "SELECT s.rowid, s.rank, test.json FROM fts_test_abc123 s INNER JOIN test ON test.rowid = s.rowid  WHERE fts_test_abc123 MATCH ?";
        $this->assertSelectQueryContains($expected);
    }
}
