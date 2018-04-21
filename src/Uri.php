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

use Psr\Http\Message\UriInterface;

/**
 * Class Uri
 * @package Apatis\Http\Message
 */
class Uri implements UriInterface
{
    /**
     * @var string
     */
    protected $user   = '';
    /**
     * @var string|null
     */
    protected $pass   = null;
    /**
     * @var string
     */
    protected $scheme = '';
    /**
     * @var string
     */
    protected $host   = '';
    /**
     * @var int|null
     */
    protected $port = null;
    /**
     * @var string
     */
    protected $path = '';
    /**
     * @var string
     */
    protected $query = '';
    /**
     * @var string
     */
    protected $fragment = '';

    /**
     * @var array
     */
    private $defaultPorts = [
        'http'  => 80,
        'https' => 443,
        'ftp' => 21,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    /**
     * @var string
     */
    private $unReservedCharacter   = 'a-zA-Z0-9_\-\.~';

    /**
     * @var string
     */
    private $characterSubDelimiter = '!\$&\'\(\)\*\+,;=';

    /*
    private $replaceQuery          = [
        '=' => '%3D',
        '&' => '%26'
    ];*/

    /**
     * @var bool
     */
    private $removeDefaultPort = true;

    /**
     * Uri constructor.
     *
     * @param string $uri
     * @param bool $removeDefaultPort
     */
    public function __construct(string $uri = '', bool $removeDefaultPort = true)
    {
        $this->removeDefaultPort = $removeDefaultPort;
        if ($uri != '') {
            foreach ($this->parseURI($uri) as $key => $value) {
                $this->{$key} = $value;
            }
            if ($this->removeDefaultPort) {
                $this->removeDefaultPort();
            }
        }
    }

    /**
     * Parse URI and return full detail
     *
     * @param string $uri
     *
     * @return array
     */
    public function parseURI(string $uri) : array
    {
        if ($uri == '') {
            throw new \InvalidArgumentException(
                "URI Could not be empty"
            );
        }

        $parsedUri = parse_url($uri);
        if ($parsedUri === false) {
            throw new \InvalidArgumentException("Unable to parse URI: $uri");
        }

        $defaultParsedUri = [
            'user'  => null,
            'pass'  => null,
            'scheme'=> '',
            'host'  => '',
            'port'  => null,
            'path'  => '',
            'query' => '',
            'fragment' => '',
        ];

        return array_merge($defaultParsedUri, $parsedUri);
    }

    /**
     * Set remove Default Port
     *
     * @param bool $removeDefaultPort
     */
    public function setRemoveDefaultPort(bool $removeDefaultPort)
    {
        $this->removeDefaultPort = $removeDefaultPort;
    }

    /**
     * Remove Default Port
     */
    public function removeDefaultPort()
    {
        if ($this->port !== null
            && isset($this->defaultPorts[$this->getScheme()])
            && $this->defaultPorts[$this->getScheme()] === $this->port
        ) {
            $this->port = null;
        }
    }

    /**
     * filter scheme
     *
     * @param string $scheme
     * @return string
     * @throws \InvalidArgumentException if scheme is not a string
     */
    protected function filterScheme($scheme) : string
    {
        if (! is_string($scheme)) {
            throw new \InvalidArgumentException('Scheme must be a string');
        }

        return strtolower(trim($scheme));
    }

    /**
     * filter host
     *
     * @param string $host
     *
     * @return string
     * @throws \InvalidArgumentException if host is not a string
     */
    protected function filterHost($host) : string
    {
        if (! is_string($host)) {
            throw new \InvalidArgumentException('Host must be a string');
        }

        return strtolower(trim($host));
    }

    /**
     * filter host
     *
     * @param string $port
     *
     * @return string
     * @throws \InvalidArgumentException if host is not a string
     */
    protected function filterPort($port)
    {
        if ($port === null) {
            return null;
        }

        $port = (int) $port;
        if (1 > $port || 0xffff < $port) {
            throw new \InvalidArgumentException(
                sprintf('Invalid port: %d. Must be between 1 and 65535', $port)
            );
        }

        return $port;
    }

    /**
     * filter path
     *
     * @param string $path
     *
     * @return string
     * @throws \InvalidArgumentException if host is not a string
     */
    protected function filterPath($path) : string
    {
        if (!is_string($path)) {
            throw new \InvalidArgumentException('Path must be a string');
        }

        $unreservedCharacter = preg_quote($this->unReservedCharacter, '/');
        $characterSubDelimiter = preg_quote($this->characterSubDelimiter, '/');
        return preg_replace_callback(
            '/(?:[^' . $unreservedCharacter . $characterSubDelimiter . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawURLEncodeMatchZero'],
            $path
        );
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param string $arg
     *
     * @return string
     *
     * @throws \InvalidArgumentException If the query or fragment is invalid.
     */
    private function filterQueryAndFragment($arg) : string
    {
        if (!is_string($arg)) {
            throw new \InvalidArgumentException('Query and fragment must be a string');
        }

        $unreservedCharacter = preg_quote($this->unReservedCharacter, '/');
        $characterSubDelimiter = preg_quote($this->characterSubDelimiter, '/');
        return preg_replace_callback(
            '/(?:[^' . $unreservedCharacter . $characterSubDelimiter . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawURLEncodeMatchZero'],
            $arg
        );
    }

    /**
     * @param array $match
     *
     * @return string
     */
    private function rawURLEncodeMatchZero(array $match) : string
    {
        return rawurlencode($match[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme() : string
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority() : string
    {
        $userInfo = $this->getUserInfo();
        $authority = $this->getHost();
        if ($userInfo !== '') {
            $authority = $userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo() : string
    {
        $userInfo = $this->user?:'';
        if ($this->pass !== null) {
            $userInfo .= ':'.$this->pass;
        }
        return $userInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function getHost() : string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath() : string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery() : string
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment() : string
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme($scheme) : UriInterface
    {
        $scheme = $this->filterScheme($scheme);
        $clone = clone $this;
        $clone->scheme = $scheme;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo($user, $password = null) : UriInterface
    {
        if ($password != '' && is_string($password)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Password must be as a string or null, %s given',
                    gettype($password)
                )
            );
        }

        $clone = clone $this;
        $clone->user = $user;
        $clone->pass = $password;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost($host) : UriInterface
    {
        $host = $this->filterHost($host);
        $clone = clone $this;
        $clone->host = $host;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort($port) : UriInterface
    {
        $port = $this->filterPort($port);
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath($path) : UriInterface
    {
        $path = $this->filterPath($path);
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery($query) : UriInterface
    {
        $query = $this->filterQueryAndFragment($query);
        $clone = clone $this;
        $clone->query = $query;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment($fragment) : UriInterface
    {
        $fragment = $this->filterQueryAndFragment($fragment);
        $clone = clone $this;
        $clone->fragment = $fragment;
        return $clone;
    }

    /**
     * Sanitize on clone object
     */
    public function __clone()
    {
        if ($this->port !== null && $this->removeDefaultPort) {
            $this->removeDefaultPort();
        }
    }

    /**
     * Convert Uri to String
     *
     * @return string
     */
    public function toUriString() : string
    {
        $uri = '';

        if (($scheme = $this->getScheme()) != '') {
            $uri .= $scheme . ':';
        }

        if (($authority = $this->getAuthority()) != '' || $scheme === 'file') {
            $uri .= '//' . $authority;
        }

        $uri .= $this->getPath();

        if (($query = $this->getQuery()) != '') {
            $uri .= '?' . $query;
        }

        if (($fragment = $this->getFragment()) != '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * {@inheritdoc}
     *
     * @return string full URI String
     */
    public function __toString() : string
    {
        return $this->toUriString();
    }

    /**
     * Fix Connection Behind Proxy Scheme
     *
     * @param array|null $globals
     *
     * @return array
     */
    public static function fixSchemeProxyFromGlobals(array $globals = null) : array
    {
        $globals = $globals === null ? $_SERVER : $globals;
        if (!empty($globals['HTTPS']) && strtolower($globals['HTTPS']) !== 'off'
            // hide behind proxy / maybe cloud flare cdn
            || isset($globals['HTTP_X_FORWARDED_PROTO']) && $globals['HTTP_X_FORWARDED_PROTO'] === 'https'
            || !empty($globals['HTTP_FRONT_END_HTTPS']) && strtolower($globals['HTTP_FRONT_END_HTTPS']) !== 'off'
        ) {
            // fixing HTTPS Environment
            $globals['HTTPS'] = 'on';
        }

        return $globals;
    }

    /**
     * Create new Uri from environment.
     *
     * @param array|null $globals The global server variables.
     *
     * @return static|UriInterface
     */
    public static function createFromGlobals(array $globals = null) : UriInterface
    {
        $globals = $globals === null
            ? static::fixSchemeProxyFromGlobals($_SERVER)
            : $globals;

        // Scheme
        $isSecure = isset($globals['HTTPS']) ? $globals['HTTPS'] : null;
        $scheme = (empty($isSecure) || $isSecure === 'off') ? 'http' : 'https';
        // Authority: Username and password
        $username = isset($globals['PHP_AUTH_USER']) ? $globals['PHP_AUTH_USER'] : null;
        $password = isset($globals['PHP_AUTH_PW']) ? $globals['PHP_AUTH_PW'] : null;
        // Authority: Host
        if (isset($globals['HTTP_HOST'])) {
            $host = $globals['HTTP_HOST'];
        } elseif (isset($globals['SERVER_NAME'])) {
            $host = $globals['SERVER_NAME'];
        } else {
            $host = 'localhost';
        }

        // Authority: Port
        $port = (int) (isset($globals['SERVER_PORT']) ? $globals['SERVER_PORT'] :80);
        if (preg_match('/^(\[[a-fA-F0-9:.]+\])(:\d+)?\z/', $host, $matches)) {
            $host = $matches[1];
            if (isset($matches[2])) {
                $port = (int) substr($matches[2], 1);
            }
        } else {
            $pos = strpos($host, ':');
            if ($pos !== false) {
                $port = (int) substr($host, $pos + 1);
                $host = strstr($host, ':', true);
            }
        }

        $globalsRequestUri = (isset($globals['REQUEST_URI']) ? $globals['REQUEST_URI']:'');
        // parse_url() requires a full URL. As we don't extract the domain name or scheme, we use a stand-in.
        $requestUri = parse_url('http://example.com' . $globalsRequestUri, PHP_URL_PATH);
        $requestUri = rawurldecode((string) $requestUri);

        // Query string
        $queryString = isset($globals['QUERY_STRING']) ? $globals['QUERY_STRING'] :'';
        if ($queryString === '') {
            $queryString = parse_url('http://example.com' . $globalsRequestUri, PHP_URL_QUERY);
        }

        $uri = "{$scheme}://{$host}:{$port}{$requestUri}";
        if ($queryString !== '') {
            $uri .= "?{$queryString}";
        }

        // Build Uri
        $uri = new static($uri, true);
        if ($username !== null) {
            $uri = $uri->withUserInfo($username, $password);
        }
        return $uri;
    }
}
