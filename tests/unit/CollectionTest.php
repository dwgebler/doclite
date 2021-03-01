<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Tests\fakes\FakeDatabase;
use Gebler\Doclite\Tests\data\Person;

use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private $db;
    private $collection;

    public function setUp(): void
    {
        $this->db = new FakeDatabase();
        $this->collection = new Collection('test', $this->db);
    }

    public function testSetCacheLifetime()
    {
        $this->collection->setCacheLifetime(300);
        $this->assertSame(300, $this->collection->getCacheLifetime());
    }

    public function testDeleteAllFalseReadOnly()
    {
        $this->db->setReadOnly(true);
        $this->assertFalse($this->collection->deleteAll());
    }

    public function testDeleteAllTrueOnDelete()
    {
        $this->assertTrue($this->collection->deleteAll());
    }

    public function testDeleteAllDeletesAllDocumentsInCollection()
    {
        $this->collection->get("12345");
        $this->assertNotEmpty($this->collection->findAll());
        $this->collection->deleteAll();
        $this->assertEmpty($this->collection->findAll());
    }

    public function testRollbackFalseReadOnly()
    {
        $this->db->setReadOnly(true);
        $this->assertFalse($this->collection->rollback());
    }

    public function testRollbackReturnsTrueOnRollback()
    {
        $this->collection->beginTransaction();
        $this->assertTrue($this->collection->rollback());
    }

    public function testGetName()
    {
        $this->assertSame('test', $this->collection->getName());
    }

    public function testFindAll()
    {
        $this->collection->get("12345");
        $this->collection->get("67890");
        $results = $this->collection->findAll();
        $this->assertCount(2, $results);
        $this->assertEquals(["12345", "67890"], [$results[0]->getId(), $results[1]->getId()]);
    }

    public function testFindAllSetsCache()
    {
        $this->collection->enableCache();
        $this->collection->get("12345");
        $this->collection->findAll();
        $key = '4d967a30111bf29f0eba01c448b375c1629b2fed01cdfcc3aed91f1b57d5dd5e';
        $cache = $this->db->getCache('test_cache', 'findAll', $key, new \DateTimeImmutable());
        $this->assertEquals('{"12345":"{\"__id\":\"12345\"}"}', $cache);
    }

    public function testFindOneByReturnsNullOnNoResult()
    {
        $this->assertNull($this->collection->findOneBy(['foo' => 'bar']));
    }

    public function testFindOneByReturnsDocument()
    {
        $data = [
            '__id' => '12345',
            'name' => 'John Smith'
        ];
        $this->db->setResults(json_encode($data));
        $document = $this->collection->findOneBy(['name' => 'John Smith']);
        $this->assertSame('John Smith', $document->getName());
    }

    public function testFindOneByMapsToCustomClass()
    {
        $data = [
            '__id' => '12345',
            'name' => 'John Smith'
        ];
        $this->db->setResults(json_encode($data));
        $document = $this->collection->findOneBy(['name' => 'John Smith'], Person::class);
        $this->assertInstanceOf(Person::class, $document);
        $this->assertSame('John Smith', $document->getName());
    }

    public function testFindOneByMapsToCustomClassWithCustomIdField()
    {
        $data = [
            '__id' => '12345',
            'name' => 'John Smith'
        ];
        $this->db->setResults(json_encode($data));
        $document = $this->collection->findOneBy(['name' => 'John Smith'], Person::class, 'customIdField');
        $this->assertInstanceOf(Person::class, $document);
        $this->assertSame('12345', $document->getCustomIdField());
        $this->assertEmpty($document->getId());
    }

    public function testSave()
    {
        $document = $this->collection->get("12345");
        $document->setName('John Smith');
        $this->collection->save($document);
        $savedDocument = $this->collection->get("12345");
        $this->assertSame('John Smith', $savedDocument->getName());
    }

    public function testBeginTransactionFalseReadOnly()
    {
        $this->db->setReadOnly(true);
        $this->assertFalse($this->collection->beginTransaction());
    }

    public function testBeginTransactionTrueOnBeginTransaction()
    {
        $this->assertTrue($this->collection->beginTransaction());
    }

    public function testDisableCacheDoesNotFetchCachedResult()
    {
        $this->collection->enableCache();
        $document = $this->collection->get("12345");
        $document->setName('John Smith');
        $this->collection->save($document);
        $cached = $this->collection->findAll();
        $this->collection->disableCache();
        $this->db->replace('test', "12345", '{"__id":"12345","name":"Bob Smith"}');
        $uncached = $this->collection->findAll();
        $this->assertSame('John Smith', $cached[0]->getName());
        $this->assertSame('Bob Smith', $uncached[0]->getName());
    }

    public function testDisableCacheDoesNotWriteCachedResult()
    {
        $this->collection->enableCache();
        $document = $this->collection->get("12345");
        $document->setName('John Smith');
        $this->collection->save($document);
        $this->collection->findAll();
        $this->collection->disableCache();
        $document->setName('Bob Smith');
        $this->collection->save($document);
        $key = '4d967a30111bf29f0eba01c448b375c1629b2fed01cdfcc3aed91f1b57d5dd5e';
        $cache = $this->db->getCache('test_cache', 'findAll', $key, new \DateTimeImmutable());
        $this->assertSame('{"12345":"{\"__id\":\"12345\",\"name\":\"John Smith\"}"}', $cache);
    }

    public function testAddIndexFalseOnReadOnly()
    {
        $this->db->setReadOnly(true);
        $this->assertFalse($this->collection->addIndex('foo'));
    }

    public function testAddIndexTrueOnCreateIndex()
    {
        $this->assertTrue($this->collection->addIndex('foo'));
    }

    public function testClearCache()
    {
        $this->collection->enableCache();
        $document = $this->collection->get("12345");
        $document->setName('John Smith');
        $this->collection->save($document);
        $this->collection->findAll();
        $this->collection->clearCache();
        $key = '4d967a30111bf29f0eba01c448b375c1629b2fed01cdfcc3aed91f1b57d5dd5e';
        $cache = $this->db->getCache('test_cache', 'findAll', $key, new \DateTimeImmutable());
        $this->assertEmpty($cache);
    }

    public function testDeleteDocument()
    {
        $document = $this->collection->get("12345");
        $this->collection->save($document);
        $this->assertNotEmpty($this->collection->findAll());
        $this->collection->deleteDocument($document);
        $this->assertEmpty($this->collection->findAll());
    }

    public function testFindAllBy()
    {
        $data = [
            [
                '__id' => '12345',
                'name' => 'John Smith',
                'active' => true,
            ],
            [
                '__id' => '67890',
                'name' => 'Bob Jones',
                'active' => true,
            ],
        ];
        $this->db->setResults([json_encode($data[0]), json_encode($data[1])]);
        $documents = $this->collection->findAllBy(['active' => true]);
        $this->assertCount(2, $documents);
        $this->assertSame('John Smith', $documents[0]->getName());
        $this->assertSame('Bob Jones', $documents[1]->getName());
    }

    public function testCommitFalseOnReadOnly()
    {
        $this->db->setReadOnly(true);
        $this->assertFalse($this->collection->commit());
    }

    public function testCommitTrueOnCommit()
    {
        $this->collection->beginTransaction();
        $this->assertTrue($this->collection->commit());
    }
}
