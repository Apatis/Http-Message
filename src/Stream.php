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

use Psr\Http\Message\StreamInterface;

/**
 * Class Stream
 * @package Apatis\Http\Message
 *
 * This Stream support zLib (GZIP) stream implementation
 * but on some case it can't be fully work on some case.
 * @link http://php.net/manual/en/ref.zlib.php
 */
class Stream implements StreamInterface
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @var int
     */
    private $size;

    /**
     * @var bool
     */
    private $seekable;

    /**
     * @var bool
     */
    private $readable;

    /**
     * @var bool
     */
    private $writable;

    /**
     * @var array|mixed|null
     */
    private $uri;

    /**
     * @var array|mixed
     */
    private $customMetadata;

    /**
     * @var array Hash of readable and writable stream types
     */
    private $modes = [
        'readable' => [
            'r'   => true,
            'w+'  => true,
            'r+'  => true,
            'x+'  => true,
            'c+'  => true,
            'rb'  => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'rt'  => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a+'  => true
        ],
        'writable' => [
            'w'   => true,
            'w+'  => true,
            'rw'  => true,
            'r+'  => true,
            'x+'  => true,
            'c+'  => true,
            'wb'  => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a'   => true,
            'a+'  => true,
        ]
    ];

    /**
     * @var string determine stream type
     */
    private $streamType;

    /**
     * Stream constructor.
     *
     * @param resource $stream
     * @param array $options
     */
    public function __construct($stream, array $options = [])
    {
        if (! is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }
        if (isset($options['size'])) {
            if (! is_int($options['size'])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Options size must be as an integer, %s given',
                        gettype($options['size'])
                    )
                );
            }

            $this->size = $options['size'];
        }

        $this->customMetadata = isset($options['metadata'])
            ? $options['metadata']
            : [];
        $this->stream         = $stream;
        $meta                 = stream_get_meta_data($this->stream);
        $this->streamType     = isset($meta['stream_type']) ? $meta['stream_type'] : null;
        $this->seekable       = (bool)$meta['seekable'];
        $this->readable       = in_array($meta['mode'], $this->modes['readable']);
        $this->writable       = in_array($meta['mode'], $this->modes['writable']);
        $this->uri            = $this->getMetadata('uri');
    }

    /**
     * Helper callback processor
     *
     * @param string $task
     * @param array ...$params
     *
     * @return array|bool|int|string
     * @access private
     */
    private function processStreamCallback(string $task, ...$params)
    {
        if (! isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $task = strtolower($task);
        $fn = 'f';
        if ($this->streamType === 'ZLIB') {
            if ($task === 'stat') {
                return false;
            }
            $fn = 'gz';
        }
        // default using f$task -> eg fopen() on gzip using gzopen
        $fn   .= $task;
        if (! function_exists($fn)) {
            return false;
        }
        switch ($task) {
            case 'seek':
            case 'write':
                if ($task === 'seek' && ! $this->seekable) {
                    throw new \RuntimeException('Stream is not seekable');
                } elseif ($task === 'write' && ! $this->writable) {
                    throw new \RuntimeException('Cannot write to a non-writable stream');
                }

                $params[1] = isset($params[1]) ? $params[1] : SEEK_SET;
                return $fn($this->stream, $params[0], $params[1]);
            case 'read':
                return $fn($this->stream, $params[0]);
            default:
                return $fn($this->stream, ...$params);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                $this->processStreamCallback(__FUNCTION__);
            }
            $this->detach();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        if (! isset($this->stream)) {
            return null;
        }

        $result         = $this->stream;
        $this->stream   = null;
        $this->size     = null;
        $this->uri      = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (! isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }

        $stats = $this->processStreamCallback('stat');
        if (isset($stats['size'])) {
            $this->size = $stats['size'];

            return $this->size;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell() : int
    {
        $result = $this->processStreamCallback(__FUNCTION__);
        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function eof() : bool
    {
        return $this->processStreamCallback(__FUNCTION__);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable() : bool
    {
        return $this->seekable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (! is_numeric($offset) || ! is_int(abs($offset))) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Offset must be as an integer, %s given',
                    gettype($offset)
                )
            );
        }

        if ($this->processStreamCallback(__FUNCTION__, (int)$offset, $whence) === -1) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to seek to stream position  %d with whence %s',
                    (int) $offset,
                    var_export($whence, true)
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable() : bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function write($string) : int
    {
        if (! is_string($string)) {
            throw new \InvalidArgumentException(
                sprintf('Parameter must be as a string, %s given', gettype($string))
            );
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result     = $this->processStreamCallback(__FUNCTION__, $string);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable() : bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length) : string
    {
        if (! is_numeric($length) || ! is_int(abs($length))) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Length must be as an integer, %s given',
                    gettype($length)
                )
            );
        }


        if (! $this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        $length = (int)$length;
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }
        if (0 === $length) {
            return '';
        }

        if (($string = $this->processStreamCallback(__FUNCTION__, $length)) === false) {
            throw new \RuntimeException('Unable to read from stream');
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents() : string
    {
        if (! isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if (! isset($this->stream)) {
            return $key ? null : [];
        } elseif (! $key) {
            return $this->customMetadata + stream_get_meta_data($this->stream);
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }

        $meta = stream_get_meta_data($this->stream);

        return isset($meta[$key]) ? $meta[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString() : string
    {
        try {
            $this->seek(0);

            return (string)stream_get_contents($this->stream);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }
}
