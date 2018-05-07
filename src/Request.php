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

use Apatis\Http\Cookie\Cookies;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class Request
 * @package Apatis\Http\Message
 */
class Request extends Message implements ServerRequestInterface
{
    /**
     * @var UriInterface
     */
    protected $uri;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var null|string
     */
    protected $requestTarget;

    /**
     * @var array
     */
    protected $serverParams = [];

    /**
     * @var string[]
     */
    protected $cookies;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @var array
     */
    protected $uploadedFiles;

    /**
     * The request query string params
     *
     * @var array
     */
    protected $queryParams;

    /**
     * @var object|null|array
     */
    protected $bodyParsed = false;

    /**
     * List of request body parsers (e.g., url-encoded, JSON, XML, multipart)
     *
     * @var callable[]
     */
    protected $bodyParsers = [];

    /**
     * Request constructor.
     *
     * @param $method
     * @param $uri
     * @param array $headers
     * @param array $serverParams
     * @param array $cookies
     * @param StreamInterface $body
     * @param array $uploadedFiles
     */
    public function __construct(
        $method,
        $uri,
        array $headers,
        array $serverParams,
        array $cookies,
        StreamInterface $body,
        array $uploadedFiles = []
    ) {
        if (! ($uri instanceof UriInterface)) {
            if (! is_string($uri)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Parameter uri must be as a string or instance of %s, %s given',
                        UriInterface::class,
                        gettype($uri)
                    )
                );
            }

            $uri = new Uri($uri);
        }

        $this->setHeaders($headers);
        $this->attributes    = [];
        $this->method        = is_string($method) ? strtoupper($method) : '';
        $this->uri           = $uri;
        $this->serverParams  = $serverParams;
        $this->uploadedFiles = UploadedFile::parseFromArrayUploadedFiles($uploadedFiles);
        $this->cookies       = $cookies;
        if (! isset($serverParams['SERVER_PROTOCOL'])) {
            $serverParams['SERVER_PROTOCOL'] = 'HTTP/1.1';
        }

        $serverParams['SERVER_PROTOCOL'] = strtoupper($serverParams['SERVER_PROTOCOL']);
        $this->protocol                  = str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL']);

        if (! $this->hasHeader('Host') || $this->uri->getHost() != '') {
            $this->setHeader('Host', $this->uri->getHost());
        }

        $this->stream = $body ?: new RequestBody();
        $this->registerDefaultParser();
    }

    /**
     * Register Default Parser
     */
    protected function registerDefaultParser()
    {
        $this->registerMediaTypeParser('application/json', function (string $input) {
            $result = json_decode($input, true);
            if (! is_array($result)) {
                return null;
            }

            return $result;
        }, null);
        $this->registerMediaTypeParser('application/xml', function (string $input) {
            $backup        = libxml_disable_entity_loader(true);
            $backup_errors = libxml_use_internal_errors(true);
            $result        = simplexml_load_string($input);
            libxml_disable_entity_loader($backup);
            libxml_clear_errors();
            libxml_use_internal_errors($backup_errors);
            if ($result === false) {
                return null;
            }

            return $result;
        }, null);
        $this->registerMediaTypeParser('text/xml', function (string $input) {
            $backup        = libxml_disable_entity_loader(true);
            $backup_errors = libxml_use_internal_errors(true);
            $result        = simplexml_load_string($input);
            libxml_disable_entity_loader($backup);
            libxml_clear_errors();
            libxml_use_internal_errors($backup_errors);
            if ($result === false) {
                return null;
            }

            return $result;
        }, null);
        $this->registerMediaTypeParser('application/x-www-form-urlencoded', function (string $input) {
            parse_str($input, $data);
            return $data;
        }, null);
    }

    /**
     * This method is applied to the cloned object
     * after PHP performs an initial shallow-copy. This
     * method completes a deep-copy by creating new objects
     * for the cloned object's internal reference pointers.
     */
    public function __clone()
    {
        $this->stream     = clone $this->stream;
    }

    /**
     * Register media type parser.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @param string $mediaType A HTTP media type (excluding content-type
     *     params).
     * @param callable $callable A callable that returns parsed contents for
     *     media type.
     * @param bool|null|object $bind true to bind current object
     * @return static
     */
    public function registerMediaTypeParser($mediaType, callable $callable, $bind = true) : Request
    {
        if ($bind === true || is_object($bind) || is_null($bind)) {
            if ($callable instanceof \Closure) {
                $bind = is_object($bind)
                    ? $bind
                    : ($bind ? $this : null);
                $callable = $callable->bindTo($bind);
            }
        }

        $this->bodyParsers[(string)$mediaType] = $callable;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget() : string
    {
        if ($this->requestTarget) {
            return $this->requestTarget;
        }

        $path = $this->uri->getPath();
        if ($path == '' || $path[0] != '') {
            $path = '/' . $path;
        }

        if (($query = $this->uri->getQuery())) {
            $path .= '?' . $query;
        }

        $this->requestTarget = $path;

        return $this->requestTarget;
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget($requestTarget) : ServerRequestInterface
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new \InvalidArgumentException(
                'Invalid request target provided; must be a string and cannot contain whitespace'
            );
        }

        $clone                = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod($method) : ServerRequestInterface
    {
        if (! is_string($method)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Method must be as a string, %s given",
                    gettype($method)
                )
            );
        }
        $clone         = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri() : UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, $preserveHost = false) : ServerRequestInterface
    {
        $clone      = clone $this;
        $clone->uri = $uri;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams() : array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams() : array
    {
        return $this->cookies;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies) : ServerRequestInterface
    {
        $clone          = clone $this;
        $clone->cookies = $cookies;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams() : array
    {
        if (is_array($this->queryParams)) {
            return $this->queryParams;
        }

        if ($this->uri === null) {
            return [];
        }

        parse_str($this->uri->getQuery(), $this->queryParams); // <-- URL decodes data

        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query) : ServerRequestInterface
    {
        $clone              = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles() : array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles) : ServerRequestInterface
    {
        $clone                = clone $this;
        $clone->uploadedFiles = $uploadedFiles;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        if ($this->bodyParsed !== false) {
            return $this->bodyParsed;
        }
        if (! $this->stream) {
            return null;
        }
        $mediaType = $this->getMediaType();
        // look for a media type with a structured syntax suffix (RFC 6839)
        $parts = explode('+', $mediaType);
        if (count($parts) >= 2) {
            $mediaType = 'application/' . $parts[count($parts) - 1];
        }

        if (isset($this->bodyParsers[$mediaType]) === true) {
            $body   = (string)$this->getBody();
            $parsed = $this->bodyParsers[$mediaType]($body);
            if (! is_null($parsed) && ! is_object($parsed) && ! is_array($parsed)) {
                throw new \RuntimeException(
                    'Request body media type parser return value must be an array, an object, or null'
                );
            }
            $this->bodyParsed = $parsed;

            return $this->bodyParsed;
        }

        return null;
    }

    /**
     * Get request media type, if known.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string|null The request media type, minus content-type params
     */
    public function getMediaType()
    {
        $contentType = $this->getContentType();
        if (is_string($contentType)) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);

            return isset($contentTypeParts[0]) ? strtolower($contentTypeParts[0]) : null;
        }

        return null;
    }

    /**
     * Get request content type.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string|null The request content type, if known
     */
    public function getContentType()
    {
        $result = $this->getHeader('Content-Type');

        return $result ? $result[0] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data) : ServerRequestInterface
    {
        if (! is_null($data) && ! is_object($data) && ! is_array($data)) {
            throw new \InvalidArgumentException(
                'Parsed body value must be an array, an object, or null'
            );
        }

        $clone             = clone $this;
        $clone->bodyParsed = $data;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes() : array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($name, $default = null)
    {
        return isset($this->attributes[$name])
            ? $this->attributes[$name]
            : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($name, $value) : ServerRequestInterface
    {
        $clone                    = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($name) : ServerRequestInterface
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    /**
     * @param array|null $globals if $globals is null use $_SERVER
     *
     * @return array
     */
    public static function parseForHeadersFromGlobal(array $globals = null) : array
    {
        $globals = $globals === null ? $_SERVER : $globals;
        $globals = self::determineHeaderAuthorization($globals);
        /**
         * Special HTTP headers that do not have the "HTTP_" prefix
         *
         * @var array
         */
        $special = [
            'CONTENT_TYPE'    => 1,
            'CONTENT_LENGTH'  => 1,
            'PHP_AUTH_USER'   => 1,
            'PHP_AUTH_PW'     => 1,
            'PHP_AUTH_DIGEST' => 1,
            'AUTH_TYPE'       => 1,
        ];

        $data = [];
        foreach ($globals as $key => $value) {
            $key = strtoupper($key);
            if (isset($special[$key]) || strpos($key, 'HTTP_') === 0) {
                if ($key !== 'HTTP_CONTENT_LENGTH') {
                    $key        = self::reconstructOriginalKey($key);
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Reconstruct original header name
     *
     * This method takes an HTTP header name from the Environment
     * and returns it as it was probably formatted by the actual client.
     *
     * @param string $key An HTTP header key from the $_SERVER global variable
     *
     * @return string The reconstructed key
     *
     * @example CONTENT_TYPE => Content-Type
     * @example HTTP_USER_AGENT => User-Agent
     */
    private static function reconstructOriginalKey(string $key)
    {
        if (strpos($key, 'HTTP_') === 0) {
            $key = substr($key, 5);
        }

        return strtr(ucwords(strtr(strtolower($key), '_', ' ')), ' ', '-');
    }

    /**
     * If HTTP_AUTHORIZATION does not exist tries to get it from
     * @see getallheaders() when available.
     *
     * @param array $globals The Slim application Environment
     *
     * @return array
     */
    public static function determineHeaderAuthorization(array $globals)
    {
        $authorization = isset($globals['HTTP_AUTHORIZATION']) ? $globals['HTTP_AUTHORIZATION'] : null;
        if (empty($authorization) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $headers = array_change_key_case($headers, CASE_LOWER);
            if (isset($headers['authorization'])) {
                $globals['HTTP_AUTHORIZATION'] = $headers['authorization'];
            }
        }

        return $globals;
    }

    /**
     * Manipulate on CLI Request
     *  eg CLI Request:
     *      php (args[0])filename.php (args[1])/path/ (args[2])POST (args[3])httpquery=query&query2=valuequery2
     *
     * @param array $globals
     *
     * @return array
     */
    protected static function manipulateIfCLIRequest(array $globals) : array
    {
        if (php_sapi_name() !== 'cli') {
            return $globals;
        }

        global $argv;
        $args = $argv;
        !isset($args[1]) && $args[1] = '/';
        $args[1] = substr($args[1], 0, 1) !== '/'
            ? '/'. $args[1]
            : $args[1];
        $query_string = explode('?', $args[1]);
        array_shift($query_string);
        $query_string = implode('?', $query_string);

        !isset($args[2]) && $args[2] = 'GET';
        !isset($args[3]) && $args[3] = '';

        if (!isset($globals['REQUEST_URI'])) {
            $globals['REQUEST_URI'] = $args[1];
        }
        if (!isset($globals['QUERY_STRING'])) {
            $globals['QUERY_STRING'] = $query_string;
        }
        if (!isset($globals['HTTP_HOST'])) {
            $globals['HTTP_HOST'] = 'cli';
        }
        if (!isset($globals['REQUEST_METHOD'])) {
            $globals['REQUEST_METHOD'] = $args[2];
        }
        if ($args[3] !== '') {
            file_put_contents('php://input', $args[3]);
        }

        return $globals;
    }

    /**
     * Create new HTTP request with data extracted from the application
     * Environment object that Allowed CLI
     *
     * @param array $globals The global server variables.
     *
     * @return static|Request
     */
    public static function createFromGlobalsResolveCLIRequest(array $globals = null) : Request
    {
        $globals = static::manipulateIfCLIRequest(
            ($globals === null
                ? Uri::fixSchemeProxyFromGlobals($_SERVER)
                : $globals
            )
        );

        return static::createFromGlobals($globals);
    }

    /**
     * Create new HTTP request with data extracted from the application
     * Environment object
     *
     * @param array $globals The global server variables.
     *
     * @return static|Request
     */
    public static function createFromGlobals(array $globals = null) : Request
    {
        $globals = $globals === null
                ? Uri::fixSchemeProxyFromGlobals($_SERVER)
                : $globals;

        $method        = isset($globals['REQUEST_METHOD']) ? $globals['REQUEST_METHOD'] : null;
        $method        = $method ? strtoupper($method) : $method;
        $uri           = Uri::createFromGlobals($globals);
        $body          = new RequestBody();
        $uploadedFiles = UploadedFile::createFromGlobals($globals);
        $headers       = static::parseForHeadersFromGlobal($globals);
        $request       = new static($method, $uri, $headers, $globals, [], $body, $uploadedFiles);
        $cookies       = Cookies::parseHeader($request->getHeader('Cookie', []));

        /**
         * @var Request $request
         */
        $request = $request->withCookieParams($cookies);
        if ($method === 'POST' && in_array(
            $request->getMediaType(),
            ['application/x-www-form-urlencoded', 'multipart/form-data']
        )) {
            // parsed body must be $_POST
            $request = $request->withParsedBody($_POST);
        }

        return $request;
    }
}
