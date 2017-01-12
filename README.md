# KoolKode React Bridge

[![Build Status](https://travis-ci.org/koolkode/async-react.svg?branch=master)](https://travis-ci.org/koolkode/async-react)

Enables [React](https://github.com/reactphp) components to be used with [koolkode/async](https://github.com/koolkode/async).

## Event Loop

You can use `ReactLoop` to create a React event loop that is backed by an async interop loop. The loop is bound to a single async interop loop driver. The adapter implements `LoopInterface` and must be used as the loop for all React components. The `ReactLoop` cannot be controlled using React's methods (`tick()`, `run()`, `stop()`) by default (this behavior can be changed using a constructor argument).

```php
use AsyncInterop\Loop;
use KoolKode\Async\React\ReactLoop;

Loop::execute(function () {
    $loop = new ReactLoop(Loop::get());
    
    // Create some React components here (no need to call run() on the loop!).
});
```

## Promise

You can use `React` to wrap a react `PromiseInterface`. Doing so allows one to yield decorated promises from a coroutine or use them in any place that requires an async interop `Promise`. Yielding a React promise directly from a coroutine will have no effect (the yield expression will simply return the promise as-is)!

```php
use AsyncInterop\Loop;
use KoolKode\Async\Coroutine;
use KoolKode\Async\React\React;
use React\Promise\Deferred;

Loop::execute(function () {
    new Coroutine(function () {
        $defer = new Deferred();
        
        Loop::delay(1000, function () use ($defer) {
            $defer->resolve(123);
        });
        
        echo "Await promise...\n";
        
        var_dump(yield new React($defer->promise()));
    });
});
```

## DNS Resolver

The KoolKode DNS resolver can be used from within React components. `ReactResolver` is an adapter that takes a KoolKode DNS resolver and exposes it using the API of React's `Resolver`. An easy way to obtain the DNS resolver is to call the `getResolver()` method of `ReactLoop` (see example).

```php
use AsyncInterop\Loop;
use KoolKode\Async\React\React;

Loop::execute(function () {
    $loop = new ReactLoop(Loop::get());
    
    // This is a DNS resolver that can be used with any React component.
    $resolver = $loop->getResolver();
});
```
