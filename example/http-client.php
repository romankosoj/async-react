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
use KoolKode\Async\React\ReactLoop;
use React\HttpClient\Factory;
use React\HttpClient\Response;

error_reporting(-1);
ini_set('display_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

Loop::execute(function () {
    // Create a React event loop backed by the executing interop loop driver.
    $loop = new ReactLoop(Loop::get());
    
    // Create a new HTTP client that uses the KoolKode DNS resolver.
    $client = (new Factory())->create($loop, $loop->getResolver());
    
    $request = $client->request('GET', 'https://httpbin.org/get?foo=bar');
    
    $request->on('response', function (Response $response) {
        $status = $response->getCode();
        $headers = $response->getHeaders();
        $body = '';
        
        $response->on('data', function (string $chunk) use (& $body) {
            $body .= $chunk;
        });
        
        $response->on('end', function () use ($status, $headers, & $body) {
            printf("Received HTTP status %s (%u bytes body data):\n\n", $status, strlen($body));
            print_r($headers);
            
            echo "\n", $body, "\n";
        });
    });
    
    $request->end();
});
