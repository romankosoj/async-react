<?php

/*
 * This file is part of KoolKode Async React.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace KoolKode\Async\React;

use AsyncInterop\Loop;
use AsyncInterop\Loop\Driver;
use KoolKode\Async\DNS\HostResolverProxy;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;

/**
 * Adapts an interop loop driver to the interface used by ReactPHP.
 * 
 * @author Martin Schröder
 */
class ReactLoop implements LoopInterface
{
    protected $controllable;
    
    protected $readStreams = [];
    
    protected $readListeners = [];
    
    protected $writeStreams = [];
    
    protected $writeListeners = [];
    
    protected $timers;
    
    protected $inNextTick = false;
    
    protected $driver;

    public function __construct(Driver $driver, bool $controllable = false)
    {
        $this->driver = $driver;
        $this->controllable = $controllable;
        
        $this->timers = new \SplObjectStorage();
    }

    /**
     * Check if the loop can control the interop loop driver.
     * 
     * @return bool
     */
    public function isControllable(): bool
    {
        return $this->controllable;
    }
    
    /**
     * Get a DNS resolver that implemets the public interface of the React DNS resolver.
     * 
     * @return Resolver
     */
    public function getResolver(): Resolver
    {
        return new ReactResolver(new HostResolverProxy());
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $this->readListeners[$key = (int) $stream][] = $listener;
        
        if (empty($this->readStreams[$key])) {
            $this->readStreams[$key] = $this->driver->onReadable($stream, function ($id, $stream) use ($key) {
                foreach ($this->readListeners[$key] as $listener) {
                    $listener($stream, $this);
                }
            });
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $this->writeListeners[$key = (int) $stream][] = $listener;
        
        if (empty($this->writeStreams[$key])) {
            $this->writeStreams[$key] = $this->driver->onWritable($stream, function ($id, $stream) use ($key) {
                foreach ($this->writeListeners[$key] as $listener) {
                    $listener($stream, $this);
                }
            });
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;
        
        if (isset($this->readStreams[$key])) {
            $this->driver->cancel($this->readStreams[$key]);
            
            unset($this->readListeners[$key]);
            unset($this->readStreams[$key]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;
        
        if (isset($this->writeStreams[$key])) {
            $this->driver->cancel($this->writeStreams[$key]);
        
            unset($this->writeListeners[$key]);
            unset($this->writeStreams[$key]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeStream($stream)
    {
        $key = (int) $stream;
        
        if (isset($this->readStreams[$key])) {
            $this->driver->cancel($this->readStreams[$key]);
            
            unset($this->readListeners[$key]);
            unset($this->readStreams[$key]);
        }
        
        if (isset($this->writeStreams[$key])) {
            $this->driver->cancel($this->writeStreams[$key]);
            
            unset($this->writeListeners[$key]);
            unset($this->writeStreams[$key]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback);
        $watcher = $this->driver->delay(\ceil($timer->getInterval() * 1000), function () use ($timer, $callback) {
            $callback($timer);
        });
        
        $this->timers[$timer] = $watcher;
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);
        $watcher = $this->driver->repeat(\ceil($timer->getInterval() * 1000), function () use ($timer, $callback) {
            $callback($timer);
        });
        
        $this->timers[$timer] = $watcher;
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if ($this->timers->contains($timer)) {
            $this->driver->cancel($this->timers[$timer]);
            $this->timers->detach($timer);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function nextTick(callable $listener)
    {
        if ($this->inNextTick) {
            $listener($this);
        } else {
            $this->driver->defer(function () use ($listener) {
                $prev = $this->inNextTick;
                $this->inNextTick = true;
                
                try {
                    $listener($this);
                } finally {
                    $this->inNextTick = $prev;
                }
            });
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function futureTick(callable $listener)
    {
        $this->driver->defer(function () use ($listener) {
            $listener($this);
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick()
    {
        if (!$this->controllable) {
            throw new \RuntimeException('Loop cannot be controlled using React API');
        }
        
        $this->driver->defer(function () {
            $this->driver->stop();
        });
        
        $this->run();
    }
    
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        if (!$this->controllable) {
            throw new \RuntimeException('Loop cannot be controlled using React API');
        }
        
        Loop::execute(function () {}, $this->driver);
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        if (!$this->controllable) {
            throw new \RuntimeException('Loop cannot be controlled using React API');
        }
        
        $this->driver->stop();
    }
}
