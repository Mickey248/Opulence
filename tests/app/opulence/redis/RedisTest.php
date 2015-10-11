<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Tests the Redis wrapper
 */
namespace Opulence\Redis;

use InvalidArgumentException;
use Predis\Client;

class RedisTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests that commands go to the default client
     */
    public function testCommandsGoToDefaultClient()
    {
        $default = $this->getMock(Client::class, ["get"], [], "", false);
        $default->expects($this->any())
            ->method("get")
            ->with("baz")
            ->willReturn("foo");
        $foo = $this->getMock(Client::class, ["get"], [], "", false);
        $foo->expects($this->any())
            ->method("get")
            ->willReturn("bar");
        $redis = new Redis(
            [
                "default" => $default,
                "foo" => $foo
            ],
            new TypeMapper()
        );
        $this->assertEquals("foo", $redis->get("baz"));
    }

    /**
     * Tests getting the type mapper
     */
    public function testGettingTypeMapper()
    {
        $typeMapper = new TypeMapper();
        $redis = new Redis($this->getMock(Client::class), $typeMapper);
        $this->assertSame($typeMapper, $redis->getTypeMapper());
    }

    /**
     * Tests not passing a default
     */
    public function testNotPassingDefault()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new Redis(["foo" => "bar"], new TypeMapper());
    }

    /**
     * Tests passing an array of clients
     */
    public function testPassingArrayOfClients()
    {
        $default = $this->getMock(Client::class);
        $foo = $this->getMock(Client::class);
        $redis = new Redis(
            [
                "default" => $default,
                "foo" => $foo
            ],
            new TypeMapper()
        );
        $this->assertSame($default, $redis->getClient());
        $this->assertSame($default, $redis->getClient("default"));
        $this->assertSame($foo, $redis->getClient("foo"));
    }

    /**
     * Tests passing a single client
     */
    public function testPassingSingleClient()
    {
        $default = $this->getMock(Client::class);
        $redis = new Redis($default, new TypeMapper());
        $this->assertSame($default, $redis->getClient());
        $this->assertSame($default, $redis->getClient("default"));
    }
}