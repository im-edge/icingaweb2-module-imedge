<?php

namespace Icinga\Module\Imedge;

use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

use function React\Promise\all;

if (function_exists('\\React\\Async\\await')) {
    /**
     * @return mixed
     * @throws \Exception
     */
    function await(PromiseInterface $promise)
    {
        return \React\Async\await($promise);
    }

    /**
     * @param PromiseInterface[] $promises
     * @throws \Exception
     */
    function awaitAll(array $promises): array
    {
        return \React\Async\await(all($promises));
    }
} elseif (function_exists('Clue\React\Block\await')) {
    /**
     * @return mixed
     * @throws \Exception
     */
    function await(PromiseInterface $promise)
    {
        return \Clue\React\Block\await($promise, Loop::get());
    }

    /**
     * @param PromiseInterface[] $promises
     * @throws \Exception
     */
    function awaitAll(array $promises): array
    {
        return \Clue\React\Block\awaitAll($promises, Loop::get());
    }
}
