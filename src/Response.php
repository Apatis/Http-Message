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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Response
 * @package Apatis\Http\Message
 */
class Response extends Message implements ResponseInterface
{
    /**
     * Status code
     *
     * @var int
     */
    protected $status = 200;

    /**
     * Reason phrase
     *
     * @var string
     */
    protected $reasonPhrase = '';

    /**
     * Status codes and reason phrases
     *
     * @var array
     */
    protected $statusMessageList = [
        //Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        //Successful 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        //Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        //Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        499 => 'Client Closed Request',
        //Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];

    /**
     * Create new HTTP response.
     *
     * @param int                   $status  The response status code.
     * @param array|null $headers The response headers.
     * @param StreamInterface|null  $body    The response body.
     */
    public function __construct($status = 200, array $headers = null, StreamInterface $body = null)
    {
        $this->status = $this->filterStatus($status);
        $this->setHeaders($headers);
        $this->stream = $body ? $body : new Body(fopen('php://temp', 'r+'));
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode() : int
    {
        return $this->status;
    }

    /**
     * @param int $code
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getStatusMessageFromCode(int $code) : string
    {
        if (isset($this->statusMessageList[$code])) {
            return $this->statusMessageList[$code];
        }
        throw new \InvalidArgumentException(
            'Invalid status code'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = '') : ResponseInterface
    {
        $code = $this->filterStatus($code);
        if (!is_string($reasonPhrase) && !method_exists($reasonPhrase, '__toString')) {
            throw new \InvalidArgumentException(
                sprintf(
                    'ReasonPhrase must be a string %s given',
                    gettype($reasonPhrase)
                )
            );
        }

        $clone = clone $this;
        $clone->status = $code;
        if ($reasonPhrase === '') {
            $reasonPhrase = $this->getStatusMessageFromCode($code);
        }

        $clone->reasonPhrase = $reasonPhrase;
        return $clone;
    }

    /**
     * Filter HTTP status code.
     *
     * @param  int $status HTTP status code.
     * @return int
     * @throws \InvalidArgumentException If an invalid HTTP status code is provided.
     */
    protected function filterStatus($status)
    {
        $status = is_numeric($status) ? abs($status) : $status;
        if (!is_integer($status) || $status < 100 || $status > 599) {
            throw new \InvalidArgumentException('Invalid HTTP status code');
        }

        return $status;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase() : string
    {
        return $this->reasonPhrase;
    }

    /**
     * Get list of status message
     * @see $statusMessageList
     *
     * @return array
     */
    public function getStatusMessageList() : array
    {
        return $this->statusMessageList;
    }
}
