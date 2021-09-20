<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\Tests\fakes\FakeDatabase;
use Gebler\Doclite\Tests\data\Person;

use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private $db;
    private $collection;

    protected function setUp(): void
    {
        $this->db = new FakeDatabase();
        $this->db->setFtsEnabled(true);
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
        $this->assertNotEmpty(iterator_to_array($this->collection->findAll()));
        $this->collection->deleteAll();
        $this->assertEmpty(array_filter(iterator_to_array($this->collection->findAll())));
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
        $results = iterator_to_array($this->collection->findAll());
        $this->assertCount(2, $results);
        $this->assertEquals(["12345", "67890"], [$results[0]->getId(), $results[1]->getId()]);
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
        $cached = iterator_to_array($this->collection->findAll());
        $this->collection->disableCache();
        $this->db->replace('test', "12345", '{"__id":"12345","name":"Bob Smith"}');
        $uncached = iterator_to_array($this->collection->findAll());
        $this->assertSame('John Smith', $cached[0]->getName());
        $this->assertSame('Bob Smith', $uncached[0]->getName());
    }

    public function testDisableCacheDoesNotWriteCachedResult()
    {
        $this->collection->enableCache();
        $document = $this->collection->get("12345");
        $document->setName('John Smith');
        $this->collection->save($document);
        $r = iterator_to_array($this->collection->findAllBy(['name' => 'John Smith']));
        $this->collection->disableCache();
        $document->setName('Bob Smith');
        $this->collection->save($document);
        $key = 'e38ea8b0f2f4ccd9a41e74de62d1db2388f46911676528783994527a18b57acf';
        $cache = iterator_to_array($this->db->getCache('test_cache', 'findAllBy', $key, new \DateTimeImmutable()));
        $this->assertSame('"{\"__id\":\"12345\",\"name\":\"John Smith\"}"', $cache[0]);
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
        $cache = iterator_to_array($this->db->getCache('test_cache', 'findAll', $key, new \DateTimeImmutable()));
        $this->assertEmpty($cache[0]);
    }

    public function testDeleteDocument()
    {
        $document = $this->collection->get("12345");
        $this->collection->save($document);
        $this->assertNotEmpty($this->collection->findAll());
        $this->collection->deleteDocument($document);
        $this->assertEmpty(array_filter(iterator_to_array($this->collection->findAll())));
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
        $documents = iterator_to_array($this->collection->findAllBy(['active' => true]));
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

    public function testInternalIdFieldInsertedToDocumentOnSave()
    {
        $person = new Person();
        $person->setId("12345");
        $person->setName("John Smith");
        $person->setCustomIdField("Some value");
        $this->collection->save($person);
        $this->db->setResults($this->db->getById("test", "12345"));
        $samePerson = $this->collection->findOneBy(["id" => "12345"], Person::class);
        $this->assertEquals($person, $samePerson);
    }

    public function testSearchThrowsExceptionOnFtsNotEnabled()
    {
        $this->db->setFtsEnabled(false);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_NO_FTS5);
        $this->collection->search('foo', ['bar']);
    }

    public function testHasFulltextIndexReturnsTrueForExistingIndex()
    {
        $this->collection->getFullTextIndex('foo', 'bar');
        $this->assertTrue($this->collection->hasFullTextIndex('bar', 'foo'));
    }

    public function testHasFulltextIndexThrowsExceptionOnFtsNotEnabled()
    {
        $this->db->setFtsEnabled(false);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_NO_FTS5);
        $this->collection->hasFullTextIndex('bar', 'foo');
    }

    public function testGetFulltextIndexThrowsExceptionOnFtsNotEnabled()
    {
        $this->db->setFtsEnabled(false);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_NO_FTS5);
        $this->collection->getFullTextIndex('foo', 'bar');
    }

    public function testHasFulltextIndexReturnsFalseForNonExistingIndex()
    {
        $this->assertFalse($this->collection->hasFullTextIndex('bar', 'foo'));
    }

    public function testGetFulltextIndexReturnsIndexHashForIndex()
    {
        $this->collection->getFullTextIndex('bar', 'baz');
        $hash = $this->collection->getFullTextIndex('baz', 'bar');
        $expected = '56fa8a3e46dd485eaeae3c8635d77260a15b404d59b4cfc8fd51ee1ccf27205e';
        $this->assertSame($expected, $hash);
    }

    public function testGetFulltextIndexCreatesAndReturnsIndexHashForNewIndex()
    {
        $hash = $this->collection->getFullTextIndex('foo', 'bar');
        $expected = '08f23ef9f1721663b87c5a98b90483ba79e95dfdb36f248d19c28b9b453eaff2';
        $this->assertSame($expected, $hash);
    }

    public function testGetFulltextIndexDeletesExistingIndexForIndexIsSupersetOfExistingIndexFields()
    {
        $this->collection->getFullTextIndex('foo', 'bar');
        $hashes = $this->db->scanFtsTables($this->collection->getName());
        $this->assertArrayHasKey('08f23ef9f1721663b87c5a98b90483ba79e95dfdb36f248d19c28b9b453eaff2', $hashes);
        $newHash = $this->collection->getFullTextIndex('baz', 'foo', 'bar');
        $newHashes = $this->db->scanFtsTables($this->collection->getName());
        $expected = [
            $newHash => ['baz', 'foo', 'bar'],
        ];
        $this->assertTrue($expected === $newHashes);
    }

}
