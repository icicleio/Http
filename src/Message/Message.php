<?php
namespace Icicle\Http\Message;

use Icicle\Http\Exception\InvalidHeaderException;
use Icicle\Http\Exception\UnsupportedVersionException;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\MemorySink;

abstract class Message implements MessageInterface
{
    /**
     * @var string
     */
    private $protocol = '1.1';

    /**
     * @var string[]
     */
    private $headerNameMap = [];

    /**
     * @var string[][]
     */
    private $headers = [];

    /**
     * @var string[]
     */
    private $cookieNameMap = [];

    /**
     * @var string[]
     */
    private $cookies = [];

    /**
     * @var string[]
     */
    private $formattedCookies = [];

    /**
     * @var \Icicle\Stream\ReadableStreamInterface
     */
    private $stream;

    /**
     * @param string[][] $headers
     * @param \Icicle\Stream\ReadableStreamInterface|null $stream
     * @param string $protocol
     *
     * @throws \Icicle\Http\Exception\MessageException
     */
    public function __construct(array $headers = [], ReadableStreamInterface $stream = null, $protocol = '1.1')
    {
        if (!empty($headers)) {
            $this->addHeaders($headers);
        }

        $this->stream = $stream ?: new MemorySink();
        $this->protocol = $this->filterProtocolVersion($protocol);
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion()
    {
        return $this->protocol;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name)
    {
        return array_key_exists(strtolower($name), $this->headerNameMap);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCookie($name)
    {
        return array_key_exists(strtolower($name), $this->cookieNameMap);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->headerNameMap)) {
            return [];
        }

        $name = $this->headerNameMap[$name];

        return $this->headers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie($name)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->cookieNameMap)) {
            return null;
        }

        $name = $this->cookieNameMap[$name];

        return $this->cookies[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);

        return empty($value) ? '' : implode(',', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->protocol = $new->filterProtocolVersion($version);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->setHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookie($name, $value, $expire = 0, $path = '', $domain = '', $secure = false, $httpOnly = false)
    {
        $new = clone $this;

        $name = trim($name);
        $value = trim($value);

        $normalized = strtolower($name);

        $new->cookies[$name] = $value;
        $new->formattedCookies[$name] = $this->formatCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);

        $new->cookieNameMap[$normalized] = $name;

        $new->setHeader("Set-Cookie", $new->formattedCookies);

        return $new;
    }

    /**
     * Formats cookie details as a header string.
     *
     * @param string     $name
     * @param string     $value
     * @param int        $expire
     * @param string     $path
     * @param string     $domain
     * @param bool|false $secure
     * @param bool|false $httpOnly
     *
     * @return string
     */
    protected function formatCookie($name, $value, $expire = 0, $path = '', $domain = '', $secure = false, $httpOnly = false) {
        // This code comes from https://github.com/symfony/http-foundation
        // Copyright (c) 2004-2015 Fabien Potencier
        // Much love! <3

        $formatted = urlencode($name).'=';

        if ('' === (string) $value) {
            $formatted .= 'deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001);
        } else {
            $formatted .= urlencode($value);

            if ($expire !== 0) {
                $formatted .= '; expires='.gmdate('D, d-M-Y H:i:s T', $expire);
            }
        }

        if ($path) {
            $formatted .= '; path='.$path;
        }

        if ($domain) {
            $formatted .= '; domain='.$domain;
        }

        if (true === $secure) {
            $formatted .= '; secure';
        }

        if (true === $httpOnly) {
            $formatted .= '; httponly';
        }

        return $formatted;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->addHeader($name, $value);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name)
    {
        $new = clone $this;
        $new->removeHeader($name);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutCookie($name)
    {
        $new = clone $this;

        $normalized = strtolower($name);

        if (array_key_exists($normalized, $new->cookieNameMap)) {
            $name = $new->cookieNameMap[$normalized];
            unset($new->cookies[$name], $new->cookieNameMap[$normalized]);
        }

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(ReadableStreamInterface $stream)
    {
        $new = clone $this;
        $new->stream = $stream;
        return $new;
    }

    /**
     * Sets the headers from the given array.
     *
     * @param string[] $headers
     */
    protected function setHeaders(array $headers)
    {
        $this->headerNameMap = [];
        $this->headers = [];

        $this->addHeaders($headers);
    }

    /**
     * Adds headers from the given array.
     *
     * @param string[] $headers
     */
    protected function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
    }

    /**
     * Sets the named header to the given value.
     *
     * @param string $name
     * @param string|string[] $value
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the header name or value is invalid.
     */
    protected function setHeader($name, $value)
    {
        if (!$this->isHeaderNameValid($name)) {
            throw new InvalidHeaderException('Header name is invalid.');
        }

        $normalized = strtolower($name);
        $value = $this->filterHeader($value);

        // Header may have been previously set with a different case. If so, remove that header.
        if (isset($this->headerNameMap[$normalized]) && $this->headerNameMap[$normalized] !== $name) {
            unset($this->headers[$this->headerNameMap[$normalized]]);
        }

        $this->headerNameMap[$normalized] = $name;
        $this->headers[$name] = $value;
    }

    /**
     * Adds the value to the named header, or creates the header with the given value if it did not exist.
     *
     * @param string $name
     * @param string|string[] $value
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the header name or value is invalid.
     */
    protected function addHeader($name, $value)
    {
        if (!$this->isHeaderNameValid($name)) {
            throw new InvalidHeaderException('Header name is invalid.');
        }

        if (strtolower($name) === "cookie") {
            $this->setCookies($value);
        }

        $normalized = strtolower($name);
        $value = $this->filterHeader($value);

        if (array_key_exists($normalized, $this->headerNameMap)) {
            $name = $this->headerNameMap[$normalized]; // Use original case to add header value.
            $this->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $this->headerNameMap[$normalized] = $name;
            $this->headers[$name] = $value;
        }
    }

    /**
     * Splits a cookie value and sets cookies in internal storage.
     *
     * @param array|string $value
     *
     * @return $this
     */
    protected function setCookies($values) {
        if (!is_array($values)) {
            $values = [$values];
        }

        foreach ($values as $value) {
            $cookies = explode(";", $value);

            foreach ($cookies as $cookie) {
                list($key, $value) = explode("=", $cookie);

                $key = trim($key);
                $value = trim($value);

                $normalized = strtolower($key);

                $this->cookieNameMap[$normalized] = $key;
                $this->cookies[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Removes the given header if it exists.
     *
     * @param string $name
     */
    protected function removeHeader($name)
    {
        $normalized = strtolower($name);

        if (array_key_exists($normalized, $this->headerNameMap)) {
            $name = $this->headerNameMap[$normalized];
            unset($this->headers[$name], $this->headerNameMap[$normalized]);
        }
    }

    /**
     * @param string $protocol
     *
     * @return string
     *
     * @throws \Icicle\Http\Exception\UnsupportedVersionException If the protocol is not valid.
     */
    private function filterProtocolVersion($protocol)
    {
        switch ($protocol) {
            case '1.1':
            case '1.0':
                return $protocol;

            default:
                throw new UnsupportedVersionException('Invalid protocol version.');
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function isHeaderNameValid($name)
    {
        return (bool) preg_match('/^[A-Za-z0-9`~!#$%^&_|\'\-]+$/', $name);
    }

    /**
     * Converts a given header value to an integer-indexed array of strings.
     *
     * @param mixed|mixed[] $values
     *
     * @return string[]
     *
     * @throws \Icicle\Http\Exception\InvalidHeaderException If the given value cannot be converted to a string and
     *     is not an array of values that can be converted to strings.
     */
    private function filterHeader($values)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $lines = [];

        foreach ($values as $value) {
            if (is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $value = (string) $value;
            } elseif (!is_string($value)) {
                throw new InvalidHeaderException('Header values must be strings or an array of strings.');
            }

            if (preg_match("/[^\t\r\n\x20-\x7e\x80-\xfe]|\r\n/", $value)) {
                throw new InvalidHeaderException('Invalid character(s) in header value.');
            }

            $lines[] = $value;
        }

        return $lines;
    }
}