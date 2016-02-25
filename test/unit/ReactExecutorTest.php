<?php

/*
 * This file is part of KoolKode Async React.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Async\React;

use KoolKode\Async\Executor;
use KoolKode\Async\PollException;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use React\Promise\Deferred;
use function KoolKode\Async\runCallback;
use function KoolKode\Async\wait;

class ReactExecutorTest extends \PHPUnit_Framework_TestCase
{
    public function testTimer()
    {
        $executor = new Executor();
        $loop = new ReactLoopAdapter($executor);
        $done = false;
        
        $this->assertSame($executor, $loop->__debugInfo()['executor']);
        
        $callback = function () use(& $done) {
            $done = true;
        };
        
        $this->assertEquals(0, $loop->__debugInfo()['timers']);
        
        $timer = $loop->addTimer(.5, $callback);
        $this->assertEquals(1, $loop->__debugInfo()['timers']);
        $this->assertTrue($timer instanceof TimerInterface);
        $this->assertSame($loop, $timer->getLoop());
        $this->assertEquals(.5, $timer->getInterval());
        $this->assertSame($callback, $timer->getCallback());
        $this->assertTrue($timer->isActive());
        $this->assertFalse($timer->isPeriodic());
        
        $this->assertFalse($done);
        $loop->run();
        $this->assertTrue($done);
        
        $this->assertEquals(0, $loop->__debugInfo()['timers']);
    }
    
    public function testPeriodicTimer()
    {
        $loop = new ReactLoopAdapter(new Executor());
        $i = 0;
    
        $callback = function (TimerInterface $timer) use(& $i) {
            if ($i >= 3) {
                return $timer->cancel();
            }
            
            $i++;
        };
    
        $timer = $loop->addPeriodicTimer(.1, $callback);
        $this->assertTrue($timer instanceof TimerInterface);
        $this->assertSame($loop, $timer->getLoop());
        $this->assertEquals(.1, $timer->getInterval());
        $this->assertSame($callback, $timer->getCallback());
        $this->assertTrue($timer->isActive());
        $this->assertTrue($timer->isPeriodic());
    
        $this->assertEquals(0, $i);
        $loop->run();
        $this->assertEquals(3, $i);
    }
    
    public function testReadStream()
    {
        $loop = new ReactLoopAdapter(new Executor());
        $this->assertEquals(0, $loop->__debugInfo()['readers']);
        
        $in = fopen(__FILE__, 'rb');
        stream_set_blocking($in, 0);
        
        $contents = '';
        
        $loop->addReadStream($in, function ($stream, LoopInterface $loop2) use($loop, & $contents) {
            $this->assertSame($loop, $loop2);
            
            try {
                $contents .= fread($stream, 8192);
            } finally {
                if (feof($stream)) {
                    $loop2->removeStream($stream);
                }
            }
        });
        
        try {
            $this->assertEquals('', $contents);
            $this->assertEquals(1, $loop->__debugInfo()['readers']);
            $loop->run();
            $this->assertEquals(file_get_contents(__FILE__), $contents);
            $this->assertEquals(0, $loop->__debugInfo()['readers']);
        } finally {
            @fclose($in);
        }
    }
    
    public function testCanWriteStream()
    {
        $loop = new ReactLoopAdapter(new Executor());
        $this->assertEquals(0, $loop->__debugInfo()['writers']);
        
        $text = 'Lorem ipsum dolor etc. :)';
        
        $tmp = tmpfile();
        stream_set_blocking($tmp, 0);
        
        $loop->addWriteStream($tmp, function ($stream, LoopInterface $loop2) use($loop, $text) {
            $this->assertSame($loop, $loop2);
            
            try {
                fwrite($stream, $text);
            } finally {
                $loop2->removeStream($stream);
            }
        });
        
        try {
            $this->assertEquals(1, $loop->__debugInfo()['writers']);
            $loop->run();
            $this->assertEquals(0, $loop->__debugInfo()['writers']);
            
            stream_set_blocking($tmp, 1);
            rewind($tmp);
            
            $this->assertEquals($text, stream_get_contents($tmp));
        } finally {
            @fclose($tmp);
        }
    }
    
    public function testNextTick()
    {
        $loop = new ReactLoopAdapter(new Executor());
        
        $i = 0;
        $tick = NULL;
        
        $tick = function (LoopInterface $loop2) use($loop, & $tick, & $i) {
            $this->assertSame($loop, $loop2);
            
            if ($i < 3) {
                $i++;
                $loop2->nextTick($tick);
            } else {
                $loop2->stop();
            }
        };
        
        $loop->nextTick($tick);
        
        $this->assertEquals(0, $i);
        $loop->tick();
        $this->assertEquals(3, $i);
    }
    
    public function testFutureTick()
    {
        $loop = new ReactLoopAdapter(new Executor());
        
        $ticks = 0;
        
        $loop->futureTick(function (LoopInterface $loop2) use ($loop, & $ticks) {
            $this->assertSame($loop, $loop2);
            $ticks++;
            
            $loop2->futureTick(function (LoopInterface $loop) use (& $ticks) {
                $ticks++;
            });
        });
        
        $this->assertEquals(0, $ticks);
        
        $loop->tick();
        $this->assertEquals(1, $ticks);
        
        $loop->tick();
        $this->assertEquals(2, $ticks);
        
        $loop->tick();
        $this->assertEquals(2, $ticks);
    }
    
    public function testCanPipeStreams()
    {
        $loop = new ReactLoopAdapter(new Executor());
        $tmp = tmpfile();
        
        try {
            $in = new Stream(fopen(__FILE__, 'rb'), $loop);
            $in->bufferSize = filesize(__FILE__);
            
            $out = new class($tmp, $loop) extends Stream {
                
                public function handleClose()
                {
                    // Do not close stream here....
                }
            };
            
            $in->pipe($out);
            
            $this->assertEquals(0, ftell($tmp));
            $loop->run();
            
            stream_set_blocking($tmp, 1);
            rewind($tmp);
            
            $this->assertEquals(file_get_contents(__FILE__), stream_get_contents($tmp));
        } finally {
            @fclose($tmp);
        }
    }
    
    public function testWillCallPollListenerIfStreamIsClosedWhilePolling()
    {
        $executor = new Executor();
        
        $loop = new ReactLoopAdapter($executor);
        $tmp = tmpfile();
        
        try {
            $loop->addReadStream($tmp, function ($stream, LoopInterface $loop) {});
            
            @fclose($tmp);

            $this->expectException(PollException::class);
            $loop->run();
        } finally {
            @fclose($tmp);
        }
    }
    
    public function testCanResolveReactPromise()
    {
        $executor = new Executor();
        $result = NULL;
        
        $executor->runCallback(function () use (& $result) {
            $deferred = new Deferred();
            $promise = $deferred->promise();
            
            yield runCallback(function () use ($deferred) {
                yield wait(.1);
                
                $deferred->resolve(123);
            });
            
            $result = yield promise($promise);
        });
        
        $executor->run();
        
        $this->assertEquals(123, $result);
    }
    
    public function testNonPromiseValueIsReturnedAsIs()
    {
        $executor = new Executor();
        $result = NULL;
        
        $executor->runCallback(function () use (& $result) {
            $result = yield promise(123);
        });
        
        $executor->run();
        
        $this->assertEquals(123, $result);
    }
    
    public function testCanRejectReactPromise()
    {
        $executor = new Executor();
        
        $executor->runCallback(function () {
            $deferred = new Deferred();
            $promise = $deferred->promise();
            
            yield runCallback(function () use ($deferred) {
                yield wait(.1);
                
                $deferred->reject(new \LogicException('Rejected!'));
            });
            
            $this->expectException(\LogicException::class);
            
            yield promise($promise);
        });
        
        $executor->run();
    }
    
    public function testRejectPromiseWrapsNonErrorsInRuntimeException()
    {
        $executor = new Executor();
        
        $executor->runCallback(function () {
            $deferred = new Deferred();
            $promise = $deferred->promise();
            
            yield runCallback(function () use ($deferred) {
                yield wait(.1);
                
                $deferred->reject('Rejected!');
            });
            
            $this->expectException(\RuntimeException::class);
            
            yield promise($promise);
        });
        
        $executor->run();
    }
}
