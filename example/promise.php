<?php

/*
 * This file is part of KoolKode Async React.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use AsyncInterop\Loop;
use KoolKode\Async\Coroutine;
use KoolKode\Async\React\React;
use React\Promise\Deferred;

error_reporting(-1);
ini_set('display_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

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
