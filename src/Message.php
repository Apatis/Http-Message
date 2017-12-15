<?php
/**
 * MIT License
 *
 * Copyright (c) 2017 Pentagonal Development
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Apatis\Http\Message;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Message
 * @package Apatis\Http\Message
 *
 * Object class Implementation of @uses MessageInterface
 */
class Message implements MessageInterface
{
    const HTTP_1_0 = '1.0';
    // alias of @const HTTP_1_0
    const HTTP_1   = self::HTTP_1_0;
    const HTTP_1_1 = '1.1';
    const HTTP_2   = '2';
    const HTTP_2_0 = '2.0';

    /**
     * @var array Map of all registered headers, as original name => array of values
     */
    protected $headers = [];

    /**
     * @var array Map of lowercase header name => original name at registration
     */
    protected $headerNames  = [];

    /**
     * @var string $protocol protocol version
     */
    protected $protocol = '1.1';

    /**
     * @var StreamInterface $stream for body stream
     */
    protected $stream;

    /**
     * A map of valid protocol versions
     *
     * @var array
     */
    protected $supportedHTTPProtocolVersions = [
        self::HTTP_1_0 => true,
        self::HTTP_1_1 => true,
        self::HTTP_2_0 => true,
        self::HTTP_2   => true,
    ];

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion() : string
    {
        return $this->protocol;
    }

    /**
     * {@inheritdoc}
     * @return static
     * @throws \InvalidArgumentException if protocol is not a string
     */
    public function withProtocolVersion($version) : MessageInterface
    {
        if (! is_string($version)) {
            throw new \InvalidArgumentException(
                'Protocol version must be as a string %s given'
            );
        }

        $clone = clone $this;
        $clone->protocol = $version;
        /**
         * @var MessageInterface $clone
         */
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name) : bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name) : array
    {
        $name = strtolower($name);
        if (!isset($this->headerNames[$name])) {
            return [];
        }

        $name = $this->headerNames[$name];
        return $this->headers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine($name) : string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value) : MessageInterface
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $value      = $this->trimHeaderValues($value);
        $normalized = $this->normalizeHeaderKey($name);
        $clone      = clone $this;
        if (isset($clone->headerNames[$normalized])) {
            unset($clone->headers[$clone->headerNames[$normalized]]);
        }
        $clone->headerNames[$normalized] = $name;
        $clone->headers[$name] = $value;

        /**
         * @var MessageInterface $clone
         */
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value) : MessageInterface
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);
        $clone = clone $this;
        if (isset($clone->headerNames[$normalized])) {
            $name = $clone->headerNames[$normalized];
            $clone->headers[$name] = array_merge($clone->headers[$name], $value);
        } else {
            $clone->headerNames[$normalized] = $name;
            $clone->headers[$name] = $value;
        }
        /**
         * @var MessageInterface $clone
         */
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name) : MessageInterface
    {
        $normalized = strtolower($name);
        $clone = clone $this;
        if (!isset($this->headerNames[$normalized])) {
            return $clone;
        }

        $name = $clone->headerNames[$normalized];
        unset($clone->headers[$name], $clone->headerNames[$normalized]);
        /**
         * @var MessageInterface $clone
         */
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody() : StreamInterface
    {
        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body) : MessageInterface
    {
        $clone = clone $this;
        $clone->stream = $body;

        /**
         * @var MessageInterface $clone
         */
        return $clone;
    }

    /**
     * @param string $name
     * @param string $value
     */
    protected function setHeader(string $name, string $value)
    {
        $this->setHeaders([$name => $value]);
    }

    /**
     * @param array $headers
     */
    protected function setHeaders(array $headers)
    {
        $this->headerNames = [];
        $this->headers = [];
        foreach ($headers as $header => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            $value = $this->trimHeaderValues($value);
            $normalized = $this->normalizeHeaderKey($header);
            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    /**
     * Trims whitespace from the header values.
     *
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     *
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param string[] $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private function trimHeaderValues(array $values)
    {
        return array_map(function ($value) {
            return trim($value, " \t");
        }, $values);
    }

    /**
     * Normalize header name
     *
     * This method transforms header names into a
     * normalized form. This is how we enable case-insensitive
     * header names in the other methods in this class.
     *
     * @param  string $key The case-insensitive header name
     *
     * @return string Normalized header name
     */
    public function normalizeHeaderKey(string $key)
    {
        $key = strtr(strtolower($key), '_', '-');
        if (strpos($key, 'http-') === 0) {
            $key = substr($key, 5);
        }
        return $key;
    }

    /**
     * Disable magic setter to ensure immutability
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value)
    {
        // Do nothing
    }

    /**
     * Get List of supported Protocol versions
     *
     * @return array
     */
    public function getSupportedProtocolVersions() : array
    {
        return array_keys($this->supportedHTTPProtocolVersions);
    }
}
