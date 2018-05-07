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
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedFile
 * @package Apatis\Http\Message
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * @type string key name for determine $_FILES definition from global
     */
    const GLOBAL_KEY_NAME = '_FILES';

    /**
     * @var string
     */
    private $clientFilename;
    /**
     * @var string
     */
    private $clientMediaType;
    /**
     * @var int
     */
    private $error = UPLOAD_ERR_OK;
    /**
     * @var null|string
     */
    private $file;

    /**
     * @var bool
     */
    private $moved = false;

    /**
     * @var int
     */
    private $size;

    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * UploadedFile constructor.
     *
     * @param string      $file The full path to the uploaded file provided by the client.
     * @param string|null $clientFileName The file name.
     * @param string|null $clientMediaType The file media type.
     * @param int|null    $size The file size in bytes.
     * @param int         $error The UPLOAD_ERR_XXX code representing the status of the upload.
     */
    public function __construct(
        string $file,
        string $clientFileName = null,
        string $clientMediaType = null,
        int $size = null,
        int $error = UPLOAD_ERR_OK
    ) {
        $this->clientFilename = $clientFileName;
        $this->clientFilename = $clientFileName;
        $this->clientMediaType = $clientMediaType;
    }

    /**
     * {@inheritdoc}
     */
    public function getStream() : StreamInterface
    {
        if ($this->moved) {
            throw new \RuntimeException('Uploaded file has already been moved');
        }

        if (!$this->stream instanceof StreamInterface) {
            $this->stream = new Stream(fopen($this->file, 'r'));
        }

        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function moveTo($targetPath)
    {
        if ($this->moved) {
            throw new \RuntimeException('Uploaded file has already been moved');
        }

        if (!is_string($targetPath)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Target path must be as a string, %s given',
                    gettype($targetPath)
                )
            );
        }

        if (trim($targetPath) == '') {
            throw new \InvalidArgumentException(
                "Target path can not be empty or whitespace only"
            );
        }

        $targetIsStream = strpos($targetPath, '://') > 0;
        if (!$targetIsStream && !is_writable(dirname($targetPath))) {
            throw new \InvalidArgumentException('Upload target path is not writable');
        }

        $this->moved = php_sapi_name() == 'cli'
            ? rename($this->file, $targetPath)
            : move_uploaded_file($this->file, $targetPath);
        if (false === $this->moved) {
            throw new \RuntimeException(
                sprintf('Uploaded file could not be moved to %s', $targetPath)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function getError() : int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }

    /**
     * Use from global Environment
     *
     * @return array|UploadedFile[]|UploadedFile[][]
     */
    public static function fromGlobalUploadedFiles() : array
    {
        return self::parseFromArrayUploadedFiles($_FILES);
    }

    /**
     * @param array $uploadedFiles
     *
     * @return array|UploadedFile[]|UploadedFile[][]
     */
    protected static function parseFromArrayUploadedFiles(array $uploadedFiles) : array
    {
        $parsed = [];
        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (!is_array($uploadedFile) || !isset($uploadedFile['name'])) {
                continue;
            }
            if (is_array($uploadedFile['name'])) {
                foreach (array_keys($uploadedFile['name']) as $key) {
                    $parsed[$field][$key] = [
                        'name'     => $uploadedFile['name'][$key],
                        'type'     => $uploadedFile['type'][$key],
                        'tmp_name' => $uploadedFile['tmp_name'][$key],
                        'error'    => $uploadedFile['error'][$key],
                        'size'     => $uploadedFile['size'][$key],
                    ];
                    $parsed[$field] = self::parseFromArrayUploadedFiles($parsed[$field]);
                }
            } else {
                $parsed[$field] = new static(
                    $uploadedFile['tmp_name'],
                    $uploadedFile['name']?: null,
                    $uploadedFile['type']?: null,
                    $uploadedFile['size']?: null,
                    $uploadedFile['error']
                );
            }
        }

        return $parsed;
    }

    /**
     * Create a normalized tree of UploadedFile instances from the Environment.
     *
     * @param array $globals The global server variables.
     *
     * @return array|null A normalized tree of UploadedFile instances or null if none are provided.
     */
    public static function createFromGlobals(array $globals = null)
    {
        $globals = $globals === null
            || ! isset($globals['_FILES'])
            || ! is_array($globals['_FILES'])
            ? (isset($_FILES) ? $_FILES : [])
            : $globals;
        return static::parseFromArrayUploadedFiles($globals);
    }
}
