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

use KoolKode\Async\Loop\NativeLoop;
use React\Dns\Resolver\Resolver;
use React\EventLoop\Timer\TimerInterface;
use React\Tests\EventLoop\AbstractLoopTest;

/**
 * @covers \KoolKode\Async\React\ReactLoop
 */
class ReactLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        return new ReactLoop(new NativeLoop(), true);
    }
    
    public function testLoopIsControllable()
    {
        $this->assertTrue($this->createLoop()->isControllable());
    }
    
    public function testLoopProvidesResolver()
    {
        $this->assertInstanceOf(Resolver::class, $this->createLoop()->getResolver());
    }
    
    public function provideControlMethods()
    {
        return [
            [
                'run'
            ],
            [
                'tick'
            ],
            [
                'stop'
            ]
        ];
    }
    
    /**
     * @dataProvider provideControlMethods
     */
    public function testCanDenyControl(string $method)
    {
        $loop = new ReactLoop(new NativeLoop());
        
        $this->expectException(\RuntimeException::class);
        $loop->$method();
    }
    
    public function testPeriodicTimerUsage()
    {
        $loop = $this->createLoop();
        $count = 0;
        
        $timer = $loop->addPeriodicTimer(.01, function (TimerInterface $timer) use (& $count) {
            if (++$count > 2) {
                $timer->cancel();
            }
        });
        
        $this->assertTrue($loop->isTimerActive($timer));
        $this->assertEquals(0, $count);
        
        $loop->run();
        
        $this->assertFalse($loop->isTimerActive($timer));
        $this->assertEquals(3, $count);
    }
}
