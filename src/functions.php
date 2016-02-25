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
use KoolKode\Async\SystemCall;
use KoolKode\Async\Task;
use React\Promise\PromiseInterface;

/**
 * Resolves a react promise, passing an argument that is not a React promise will return the input value.
 * 
 * Throws an exception into the task if the promise is rejected.
 * 
 * Non-error rejection reasons will be wrapped within a RuntimeException.
 * 
 * @param mixed $promise React promise or any other value.
 * @return mixed Resolved value.
 */
function promise($promise): SystemCall
{
    return new class($promise) extends SystemCall {

        protected $promise;

        public function __construct($promise)
        {
            $this->promise = $promise;
        }

        public function __debugInfo(): array
        {
            return [
                'call' => 'Promise',
                'promise' => ($this->promise instanceof PromiseInterface)
            ];
        }

        public function __invoke(Task $task, ExecutorInterface $executor)
        {
            if (!$this->promise instanceof PromiseInterface) {
                return $task->send($this->promise);
            }
            
            $this->promise->then(function ($result) use ($task) {
                $task->getExecutor()->schedule($task->send($result));
            }, function ($reason) use ($task) {
                if (!$reason instanceof \Throwable) {
                    $reason = new \RuntimeException($reason);
                }
                
                $task->getExecutor()->schedule($task->error($reason));
            });
        }
    };
}
