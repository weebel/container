<?php

namespace Weebel\Container;

use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testSetInstanceToAConcrete(): void
    {
        $container = Container::getInstance();

        $instance = new MockClass();

        $container->set('mock_class', $instance);

        $this->assertEquals($instance, $container->get('mock_class'));
    }

    public function testSetInstanceByClassNameToAConcrete(): void
    {
        $container = Container::getInstance();

        $container->set('mock_class', MockClass::class);

        $this->assertInstanceOf(MockClass::class, $container->get('mock_class'));
    }

    public function testSetArrayToAKey(): void
    {
        $container = Container::getInstance();

        $array = [1, 2, 5];

        $container->set('key', $array);

        $this->assertEquals($array, $container->get('key'));

    }

    public function testSetInstanceToAConcreteWithCallable(): void
    {
        $container = Container::getInstance();

        $instance = new MockClass();

        $container->set('mock_class', fn() => $instance);

        $this->assertEquals($instance, $container->get('mock_class'));
    }

    public function testEntityNotFoundWouldBeThrownInCaseTheEntityIsNotFound(): void
    {
        $container = Container::getInstance();

        $container->flush();

        $this->expectException(EntityNotFound::class);

        $container->get('mock_class');
    }

    public function testCanHandleDifferentTagsForASingleClass(): void
    {
        $container = Container::getInstance();

        $container->set('mock_class_default', MockClass::class);

        $container->set('mock_class_new', MockClass::class);
        $container->addArgument('mock_class_new', 'property', 'new');

        $mock = $container->get('mock_class_default');
        $newMock = $container->get('mock_class_new');

        $this->assertEquals('default', $mock->property);
        $this->assertEquals('new', $newMock->property);

    }

    public function testCanAssignDifferentTagsOnTopOfAnotherTags(): void
    {
        $container = Container::getInstance();

        $container->set('mock_class_default', MockClass::class);

        $container->set('mock_class_new', 'mock_class_default');
        $container->addArgument('mock_class_new', 'property', 'new');

        $container->set('mock_class_another_one', 'mock_class_new');
        $container->addArgument('mock_class_another_one', 'number', 2);



        $mock = $container->get('mock_class_default');
        $newMock = $container->get('mock_class_new');
        $anotherMock = $container->get('mock_class_another_one');

        $this->assertEquals('default', $mock->property);
        $this->assertEquals(1, $mock->number);
        $this->assertEquals('new', $newMock->property);
        $this->assertEquals(1, $newMock->number);
        $this->assertEquals(2, $anotherMock->number);
        $this->assertEquals('new', $anotherMock->property);
    }

    public function testHashCodeOfTheResolvedObjectOfATagIsAlwaysTheSame():void
    {
        $container = Container::getInstance();

        $container->set('mock_class_default', MockClass::class);

        $container->set('mock_class_new', 'mock_class_default');
        $container->addArgument('mock_class_new', 'property', 'new');

        $mock1 = $container->get('mock_class_default');
        $mock2 = $container->get('mock_class_default');
        $this->assertEquals(spl_object_id($mock1), spl_object_id($mock2));

        $mock3 = $container->get('mock_class_new');
        $this->assertNotEquals(spl_object_id($mock1), spl_object_id($mock3));
    }

    public function testOtherTagsCanBeBoundToAServiceAsAnArgument(): void
    {
        $container = Container::getInstance();

        $container->set('mock_class_new', MockClass::class);
        $container->addArgument('mock_class_new', 'property', 'new');

        $container->set('mock_class_user', MockClassUser::class);
        $container->addArgument('mock_class_user', 'mockClass', '@mock_class_new');

        /** @var MockClassUser $mock3 */
        $mock3 = $container->get('mock_class_user');
        $this->assertEquals('new', $mock3->mockClass->property);

        /** @var MockClassUser $mock3 */
        $mock3 = $container->get(MockClassUser::class);
        $this->assertEquals('default', $mock3->mockClass->property);
    }

    public function testIfATagIsAnAliasForAnotherTagThenTheirResolvedHashCodeShouldBeTheSame():void
    {
        $container = Container::getInstance();

        $container->set('mock_class_new', MockClass::class);
        $container->addArgument('mock_class_new', 'property', 'new');

        $container->alias("another_mock", 'mock_class_new');

        $mock1 = $container->get('mock_class_new');
        $mock2 = $container->get('another_mock');

        $this->assertEquals(spl_object_id($mock1), spl_object_id($mock2));
    }

}

class MockClass
{

    public function __construct(public string $property = 'default', public int $number = 1)
    {
    }
}

class MockClassUser
{
    public function __construct(public MockClass $mockClass)
    {
    }
}