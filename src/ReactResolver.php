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

use KoolKode\Async\DNS\Address;
use KoolKode\Async\DNS\HostResolver;
use React\Dns\RecordNotFoundException;
use React\Dns\Model\Message;
use React\Dns\Query\CancellationException;
use React\Dns\Query\Query;
use React\Dns\Resolver\Resolver;
use React\Promise\Deferred;

/**
 * Adapts the KoolKode DNS host resolver to the resolver API used by React.
 * 
 * @author Martin Schröder
 */
class ReactResolver extends Resolver
{
    /**
     * DNS host resolver to be used.
     * 
     * @var HostResolver
     */
    protected $resolver;
    
    /**
     * Create a new React DNS resolver backed by the given hst resolver.
     * 
     * @param HostResolver $resolver
     */
    public function __construct(HostResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($domain)
    {
        $lookup = $this->resolver->resolve($domain);
        
        $defer = new Deferred(function (callable $resolve, callable $reject) use ($lookup, $domain) {
            $e = new CancellationException(\sprintf('DNS query for %s has been cancelled', $domain));
            
            $reject($e);
            
            $lookup->cancel('DNS lookup cancelled');
        });
        
        $lookup->when(function (\Throwable $e = null, Address $result = null) use ($defer, $domain) {
            if ($e) {
                $defer->reject(new RecordNotFoundException('No IP found for domain ' . $domain, 0, $e));
            } else {
                $defer->resolve($result->getIterator()->current());
            }
        });
        
        return $defer->promise();
    }
    
    /**
     * Method is not supported.
     */
    public function extractAddress(Query $query, Message $response)
    {
        throw new \BadMethodCallException('Method not supported by KoolKode DNS resolver');
    }
    
    /**
     * Method is not supported.
     */
    public function resolveAliases(array $answers, $name)
    {
        throw new \BadMethodCallException('Method not supported by KoolKode DNS resolver');
    }
}
