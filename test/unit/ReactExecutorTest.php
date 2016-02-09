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

use KoolKode\Async\Test\AsyncTrait;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class ReactExecutorTest extends \PHPUnit_Framework_TestCase
{
    use AsyncTrait;

    public function testTimer()
    {
        $executor = $this->createExecutor();
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
        $loop = new ReactLoopAdapter($this->createExecutor());
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
        $loop = new ReactLoopAdapter($this->createExecutor());
        $this->assertEquals(0, $loop->__debugInfo()['readers']);
        
        $fp = fopen(__FILE__, 'rb');
        stream_set_blocking($fp, 0);
        
        $contents = '';
        
        $loop->addReadStream($fp, function ($stream, LoopInterface $loop2) use($loop, & $contents) {
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
            @fclose($fp);
        }
    }
    
    public function testCanWriteStream()
    {
        $loop = new ReactLoopAdapter($this->createExecutor());
        $this->assertEquals(0, $loop->__debugInfo()['writers']);
        
        $text = 'Lorem ipsum dolor etc. :)';
        
        $fp = tmpfile();
        stream_set_blocking($fp, 0);
        
        $loop->addWriteStream($fp, function ($stream, LoopInterface $loop2) use($loop, $text) {
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
            
            stream_set_blocking($fp, 1);
            rewind($fp);
            
            $this->assertEquals($text, stream_get_contents($fp));
        } finally {
            @fclose($fp);
        }
    }
    
    public function testNextTick()
    {
        $loop = new ReactLoopAdapter($this->createExecutor());
        
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
        $loop = new ReactLoopAdapter($this->createExecutor());
        
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
        $loop = new ReactLoopAdapter($this->createExecutor());
        
        $buffer = tmpfile();
        
        try {
            $in = new Stream(fopen(__FILE__, 'rb'), $loop);
            $in->bufferSize = filesize(__FILE__);
            
            $out = new class($buffer, $loop) extends Stream {
                
                public function handleClose()
                {
                    // Do not close stream here....
                }
            };
            
            $in->pipe($out);
            
            $this->assertEquals(0, ftell($buffer));
            $loop->run();
            
            stream_set_blocking($buffer, 1);
            rewind($buffer);
            
            $this->assertEquals(file_get_contents(__FILE__), stream_get_contents($buffer));
        } finally {
            @fclose($buffer);
        }
    }
}
