<?php

/*
 * This file is part of KoolKode Async.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Async\React;

use KoolKode\Async\Test\AsyncTestCase;
use React\Dns\Query\CancellationException;
use React\Dns\RecordNotFoundException;
use React\Dns\Query\Query;
use React\Dns\Model\Message;

/**
 * @covers \KoolKode\Async\React\ReactResolver
 */
class ReactResolverTest extends AsyncTestCase
{
    public function testCanResolveAddress()
    {
        $resolver = new ReactResolver();
        
        $this->assertEquals('127.0.0.1', yield new React($resolver->resolve('localhost')));
    }

    public function testWillNotResolveForUnknownHost()
    {
        $resolver = new ReactResolver();
        
        $this->expectException(RecordNotFoundException::class);
        
        yield new React($resolver->resolve('foobar'));
    }

    public function testCanCancelLookup()
    {
        $resolver = new ReactResolver();
        
        $lookup = $resolver->resolve('google.com');
        $lookup->cancel();
        
        $this->expectException(CancellationException::class);
        
        yield new React($lookup);
    }

    public function testCannotExtractAdress()
    {
        $resolver = new ReactResolver();
        
        $this->expectException(\BadMethodCallException::class);
        
        $resolver->extractAddress($this->createMock(Query::class), $this->createMock(Message::class));
    }

    public function testCannotResolveAliass()
    {
        $resolver = new ReactResolver();
        
        $this->expectException(\BadMethodCallException::class);
        
        $resolver->resolveAliases([], '');
    }
}
