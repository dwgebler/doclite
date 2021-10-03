<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Document;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\Tests\fakes\FakeDatabase;
use Gebler\Doclite\Tests\data\Person;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    private $db;
    private $collection;
    private $document;

    protected function setUp(): void
    {
        $this->db = new FakeDatabase();
        $this->collection = new Collection('test', $this->db);
        $this->document = new Document(['__id' => '12345', 'name' => 'John Smith'], $this->collection);
    }

    public function testRegenerateId()
    {
        $originalId = $this->document->getId();
        $this->document->regenerateId();
        $newId = $this->document->getId();
        $uuid = '/[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}/';
        $this->assertNotEquals($originalId, $newId);
        $this->assertSame('12345', $originalId);
        $this->assertMatchesRegularExpression($uuid, $newId);
    }

    public function testSetDocliteId()
    {
        $this->document->setDocliteId('67890');
        $this->assertSame('67890', $this->document->getId());
    }

    public function testToArray()
    {
        $this->assertEquals(['__id' => '12345', 'name' => 'John Smith'], $this->document->toArray());
    }

    public function testGetByProperty()
    {
        $name = $this->document->name;
        $this->assertSame('John Smith', $name);
    }

    public function testSetByProperty()
    {
        $this->document->FooBar = 'baz';
        $data = $this->document->toArray();
        $this->assertSame('baz', $data['FooBar']);
    }

    public function testSetByCall()
    {
        $this->document->setFooBar('baz');
        $this->assertSame('baz', $this->document->foo_bar);
    }

    public function testGetByCall()
    {
        $this->document->foo_bar = 'baz';
        $this->assertSame('baz', $this->document->getFooBar());
    }

    public function testGetId()
    {
        $this->assertSame('12345', $this->document->getId());
    }

    public function testMapFieldToCustomClass()
    {
        $this->document->setPerson(['id' => 'foo', 'name' => 'Charlie Foo']);
        $this->document->map('person', Person::class);
        $person = $this->document->getPerson();
        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame('Charlie Foo', $person->getName());
    }

    public function testRemoveJsonSchema()
    {
        $this->document->addJsonSchema('{"type":"object","properties":{"__id":{"type":"string"},"name":{"type":"string"}}}');
        $this->document->removeJsonSchema();
        $this->document->setName(123);
        $this->assertSame(123, $this->document->getName());
    }

    public function testSetValue()
    {
        $this->document->setValue('foo.bar', 'baz');
        $data = $this->document->toArray();
        $this->assertSame('baz', $data['foo']['bar']);
    }

    public function testValidateJsonSchema()
    {
        $this->document->addJsonSchema('{"type":"object","properties":{"__id":{"type":"string"},"name":{"type":"string"}}}');
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_INVALID_DATA);
        $this->document->setName(123);
    }
    
    public function testAddJsonSchemaExceptionOnInvalidJsonSchema()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_INVALID_JSON_SCHEMA);
        $this->document->addJsonSchema('a{');
    }

    public function testGetTimeExceptionOnNonUuid()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_INVALID_UUID);
        $this->document->getTime();
    }

    public function testGetTimeReturnsTime()
    {
        $this->document->setId('31ab5e6a-7ade-11eb-9439-0242ac130002');
        $time = $this->document->getTime();
        $expected = '2021-03-01 22:33:49';
        $this->assertSame($expected, $time->format('Y-m-d H:i:s'));
    }

    public function testGetDocliteId()
    {
        $this->assertSame('12345', $this->document->getDocliteId());
    }

    public function testGetValue()
    {
        $this->document->setValue('foo.bar', 'baz');
        $this->assertSame('baz', $this->document->getValue('foo.bar'));
    }

    public function testDelete()
    {
        $this->document->save();
        $this->assertTrue($this->document->delete());
    }

    public function testSave()
    {
        $this->assertTrue($this->document->save());
    }


    public function testGetData()
    {
        $this->assertSame(['__id' => '12345', 'name' => 'John Smith'], $this->document->getData());
    }
}
