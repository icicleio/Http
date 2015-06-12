<?php
namespace Icicle\Http\Client;

use Icicle\Http\Message\RequestInterface;
use Icicle\Stream\ReadableStreamInterface;

interface ClientInterface
{
    /**
     * @coroutine
     *
     * @param string $method
     * @param string|\Icicle\Http\Message\UriInterface $uri
     * @param string[]|null $headers
     * @param \Icicle\Stream\ReadableStreamInterface|null $body
     * @param float|int|null $timeout
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     *
     * @reject \Icicle\Http\Exception\MessageException
     * @reject \Icicle\Http\Exception\ParseException
     */
    public function request(
        $method,
        $uri,
        array $headers = null,
        ReadableStreamInterface $body = null,
        $timeout = RequesterInterface::DEFAULT_TIMEOUT,
        array $options = null
    );

    /**
     * @coroutine
     *
     * @param \Icicle\Http\Message\RequestInterface $request
     * @param float|int|null $timeout
     * @param mixed[] $options
     *
     * @return \Generator
     *
     * @resolve \Icicle\Http\Message\ResponseInterface
     *
     * @reject \Icicle\Http\Exception\MessageException
     * @reject \Icicle\Http\Exception\ParseException
     */
    public function send(
        RequestInterface $request,
        $timeout = RequesterInterface::DEFAULT_TIMEOUT,
        array $options = null
    );
}