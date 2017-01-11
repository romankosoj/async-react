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

use KoolKode\Async\CancellationException;
use KoolKode\Async\Test\AsyncTestCase;
use React\Promise\Deferred;

/**
 * @covers \KoolKode\Async\React\React
 */
class ReactTest extends AsyncTestCase
{
    public function testCanResolveUsingDone()
    {
        $defer = new Deferred();
        $defer->resolve(123);
        
        $this->assertEquals(123, yield new React($defer->promise()));
    }

    public function testCanRejectUsingDone()
    {
        $defer = new Deferred();
        $defer->reject($ex = new \LogicException('Fail'));
        
        try {
            yield new React($defer->promise());
        } catch (\LogicException $e) {
            return $this->assertSame($ex, $e);
        }
        
        $this->fail('Failed to assert rejection error');
    }

    public function testCanCancelPromise()
    {
        $ex = new \LogicException('Cancel');
        
        $defer = new Deferred(function (callable $resolve, callable $reject) use ($ex) {
            $reject(new CancellationException('Cancelled', 0, $ex));
        });
        
        $react = new React($defer->promise());
        $react->cancel('');
        
        try {
            yield $react;
        } catch (CancellationException $e) {
            $this->assertEquals('Cancelled', $e->getMessage());
            $this->assertSame($ex, $e->getPrevious());
            
            return;
        }
        
        $this->fail('Failed to assert cancellation exception');
    }

    public function testCanCancelUncancellablePromise()
    {
        $react = new class() extends React {

            public function __construct()
            {
                $this->promise = 123;
            }
        };
        
        $react->cancel('Cancelled', $ex = new \LogicException('Cancel'));
        
        try {
            yield $react;
        } catch (CancellationException $e) {
            $this->assertEquals('Cancelled', $e->getMessage());
            $this->assertSame($ex, $e->getPrevious());
            
            return;
        }
        
        $this->fail('Failed to assert cancellation exception');
    }
}
