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

use KoolKode\Async\ExecutorInterface;
use KoolKode\Async\Poll;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;

/**
 * Adapts an executor the the loop interface used by react.
 * 
 * @author Martin Schröder
 */
class ReactLoopAdapter implements LoopInterface
{
    /**
     * Underlying async executor.
     * 
     * @var ExecutorInterface
     */
    protected $executor;
    
    /**
     * Uses react timers as keys and related KoolKode timers as values.
     * 
     * @var \SplObjectStorage
     */
    protected $timers;
    
    /**
     * Keeps track of active read polls.
     * 
     * @var array
     */
    protected $readPolls = [];
    
    /**
     * Keeps track of active write polls.
     *
     * @var array
     */
    protected $writePolls = [];
    
    /**
     * Adapt the given executor to reacts event loop interface.
     * 
     * @param ExecutorInterface $executor
     */
    public function __construct(ExecutorInterface $executor)
    {
        $this->executor = $executor;
        $this->timers = new \SplObjectStorage();
    }
    
    /**
     * Dumps adapter info and the underlying executor.
     * 
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'timers' => $this->timers->count(),
            'readers' => count($this->readPolls),
            'writers' => count($this->writePolls),
            'executor' => $this->executor
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;
        
        if (!isset($this->readPolls[$key])) {
            $this->readPolls[$key] = $this->executor->addReadPoll($this->createPoll($stream, $listener));
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;
        
        if (!isset($this->writePolls[$key])) {
            $this->writePolls[$key] = $this->executor->addWritePoll($this->createPoll($stream, $listener));
        }
    }
    
    /**
     * Create a poll that delegates to the given listener passing the loop adapter.
     * 
     * @param resource $stream
     * @param callable $listener
     * @return Poll
     */
    protected function createPoll($stream, callable $listener): Poll
    {
        return new class($stream, $listener, $this) extends Poll {

            protected $listener;

            protected $loop;

            public function __construct($stream, callable $listener, ReactLoopAdapter $loop)
            {
                parent::__construct($stream);
                
                $this->listener = $listener;
                $this->loop = $loop;
            }

            public function notify(ExecutorInterface $executor)
            {
                ($this->listener)($this->stream, $this->loop);
            }
        };
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;
        
        if (isset($this->readPolls[$key])) {
            try {
                $this->executor->removeReadPoll($this->readPolls[$key]);
            } finally {
                unset($this->readPolls[$key]);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;
        
        if (isset($this->writePolls[$key])) {
            try {
                $this->executor->removeWritePoll($this->writePolls[$key]);
            } finally {
                unset($this->writePolls[$key]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeStream($stream)
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }
    
    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback);
        
        $this->timers[$timer] = $this->executor->addTimer($interval, function () use($callback, $timer) {
            $this->timers->detach($timer);
            
            $callback($timer);
        });
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);
        
        $this->timers[$timer] = $this->executor->addPeriodicTimer($interval, function () use($callback, $timer) {
            $callback($timer);
        });
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if ($this->timers->contains($timer)) {
            try {
                $this->executor->cancelTimer($this->timers[$timer]);
            } finally {
                $this->timers->detach($timer);
            }
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
        $this->executor->nextTick(function () use($listener) {
            $listener($this);
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function futureTick(callable $listener)
    {
        $this->executor->futureTick(function () use($listener) {
            $listener($this);
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick()
    {
        $this->executor->tick();
    }
    
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->executor->run();
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->executor->stop();
    }
}
