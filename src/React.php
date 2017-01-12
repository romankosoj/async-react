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

use KoolKode\Async\Awaitable;
use KoolKode\Async\AwaitableTrait;
use KoolKode\Async\CancellationException;
use React\Promise\PromiseInterface;

/**
 * Wraps a React promise and adapts it to the awaitable API.
 * 
 * @author Martin Schröder
 */
class React implements Awaitable
{
    use AwaitableTrait;

    /**
     * The awaited promise instance.
     * 
     * @var PromiseInterface
     */
    protected $promise;

    /**
     * Wrap the given React promise into an awaitable.
     * 
     * @param PromiseInterface $promise
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise->then(function ($result) {
            $this->promise = null;
            
            $this->resolve($result);
        }, function (\Throwable $e) {
            $this->promise = null;
            
            $this->fail($e);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(string $reason, \Throwable $cause = null)
    {
        if ($this->promise) {
            try {
                if (\is_callable([$this->promise, 'cancel'])) {
                    $this->promise->cancel();
                } else {
                    $this->fail(new CancellationException($reason, 0, $cause));
                }
            } finally {
                $this->promise = null;
            }
        }
    }
}
