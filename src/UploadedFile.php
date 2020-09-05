<?php
declare(strict_types=1);

namespace Azonmedia\SwooleToPsr;

//use Guzaba2\Http\Body\Stream;
use Azonmedia\Exceptions\NotImplementedException;
use Azonmedia\Exceptions\RunTimeException;
use Azonmedia\Exceptions\InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Represents Uploaded Files.
 *
 * It manages and normalizes uploaded files according to the PSR-7 standard.
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * The client-provided full path to the file
     *
     * @note this is public to maintain BC with 3.1.0 and earlier.
     *
     * @var string
     */
    public $file;
    /**
     * The client-provided file name.
     *
     * @var string
     */
    protected $name;
    /**
     * The client-provided media type of the file.
     *
     * @var string
     */
    protected $type;
    /**
     * The size of the file in bytes.
     *
     * @var int
     */
    protected $size;
    /**
     * A valid PHP UPLOAD_ERR_xxx code for the file upload.
     *
     * @var int
     */
    protected $error = UPLOAD_ERR_OK;
    /**
     * Indicates if the upload is from a SAPI environment.
     *
     * @var bool
     */
    protected $sapi = false;
    /**
     * An optional StreamInterface wrapping the file resource.
     *
     * @var StreamInterface
     */
    protected $stream;
    /**
     * Indicates if the uploaded file has already been moved.
     *
     * @var bool
     */
    protected $moved = false;

    /**
     * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file data.
     *
     * @param array $uploaded_files The non-normalized tree of uploaded file data.
     *
     * @return array A normalized tree of UploadedFile instances.
     */
    public static function parseUploadedFiles(array $uploaded_files)
    {
        $parsed = [];
        foreach ($uploaded_files as $field => $uploaded_file) {
            if (!isset($uploaded_file['error'])) {
                if (is_array($uploaded_file)) {
                    $parsed[$field] = static::parseUploadedFiles($uploaded_file);
                }
                continue;
            }

            $parsed[$field] = [];
            if (!is_array($uploaded_file['error'])) {
                $parsed[$field] = new static(
                    $uploaded_file['tmp_name'],
                    isset($uploaded_file['name']) ? $uploaded_file['name'] : null,
                    isset($uploaded_file['type']) ? $uploaded_file['type'] : null,
                    isset($uploaded_file['size']) ? $uploaded_file['size'] : null,
                    $uploaded_file['error'],
                    true
                );
            } else {
                $sub_array = [];
                foreach ($uploaded_file['error'] as $file_idx => $error) {
                    // normalise subarray and re-parse to move the input's keyname up a level
                    $sub_array[$file_idx]['name'] = $uploaded_file['name'][$file_idx];
                    $sub_array[$file_idx]['type'] = $uploaded_file['type'][$file_idx];
                    $sub_array[$file_idx]['tmp_name'] = $uploaded_file['tmp_name'][$file_idx];
                    $sub_array[$file_idx]['error'] = $uploaded_file['error'][$file_idx];
                    $sub_array[$file_idx]['size'] = $uploaded_file['size'][$file_idx];

                    $parsed[$field] = static::parseUploadedFiles($sub_array);
                }
            }
        }

        return $parsed;
    }

    /**
     * Construct a new UploadedFile instance.
     *
     * @param string      $file The full path to the uploaded file provided by the client.
     * @param string|null $name The file name.
     * @param string|null $type The file media type.
     * @param int|null    $size The file size in bytes.
     * @param int         $error The UPLOAD_ERR_XXX code representing the status of the upload.
     * @param bool        $sapi Indicates if the upload is in a SAPI environment.
     */
    public function __construct($file, $name = null, $type = null, $size = null, $error = UPLOAD_ERR_OK, $sapi = false)
    {
        $this->file = $file;
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->error = $error;
        $this->sapi = $sapi;
    }

    /**
     * Retrieve a stream representing the uploaded file.
     *
     * This method MUST return a StreamInterface instance, representing the
     * uploaded file. The purpose of this method is to allow utilizing native PHP
     * stream functionality to manipulate the file upload, such as
     * stream_copy_to_stream() (though the result will need to be decorated in a
     * native PHP stream wrapper to work with such functions).
     *
     * If the moveTo() method has been called previously, this method MUST raise
     * an exception.
     *
     * @return StreamInterface Stream representation of the uploaded file.
     * @throws RunTimeException in cases when no stream is available or can be
     *     created.
     */
    public function getStream()
    {
        throw new NotImplementedException(sprintf('%s is not implemented', __METHOD__));
        if ($this->moved) {
            throw new RunTimeException(sprintf('Uploaded file %1$s has already been moved', $this->name));
        }
        if ($this->stream === null) {
            $this->stream = new Stream(fopen($this->file, 'r'));
        }

        return $this->stream;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Use this method as an alternative to move_uploaded_file(). This method is
     * guaranteed to work in both SAPI and non-SAPI environments.
     * Implementations must determine which environment they are in, and use the
     * appropriate method (move_uploaded_file(), rename(), or a stream
     * operation) to perform the operation.
     *
     * $target_path may be an absolute path, or a relative path. If it is a
     * relative path, resolution should be the same as used by PHP's rename()
     * function.
     *
     * The original file or stream MUST be removed on completion.
     *
     * If this method is called more than once, any subsequent calls MUST raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @param string $target_path Path to which to move the uploaded file.
     *
     * @throws InvalidArgumentException if the $path specified is invalid.
     * @throws RunTimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     */
    public function moveTo($target_path)
    {
        if ($this->moved) {
            throw new RunTimeException('Uploaded file already moved.');
        }

        $target_is_stream = strpos($target_path, '://') > 0;
        if (!$target_is_stream && !is_writable(dirname($target_path))) {
            throw new InvalidArgumentException('Upload target path is not writable.');
        }

        if ($target_is_stream) {
            if (!copy($this->file, $target_path)) {
                throw new RunTimeException(sprintf('Error moving uploaded file %1$s to %2$s.', $this->name, $target_path));
            }
            if (!unlink($this->file)) {
                throw new RunTimeException(sprintf('Error removing uploaded file %1$s.', $this->name));
            }
        } elseif ($this->sapi) {
            if (!is_uploaded_file($this->file)) {
                throw new RunTimeException(sprintf('%1$s is not a valid uploaded file.', $this->file));
            }

            if (!move_uploaded_file($this->file, $target_path)) {
                throw new RunTimeException(sprintf('Error moving uploaded file %1$s to %2$s.', $this->name, $target_path));
            }
        } else {
            if (!rename($this->file, $target_path)) {
                throw new RunTimeException(sprintf('Error moving uploaded file %1$s to %2$s.', $this->name, $target_path));
            }
        }

        $this->moved = true;
    }

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, this method MUST return
     * UPLOAD_ERR_OK.
     *
     * Implementations SHOULD return the value stored in the "error" key of
     * the file in the $_FILES array.
     *
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename.
     *
     * Implementations SHOULD return the value stored in the "name" key of
     * the file in the $_FILES array.
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename()
    {
        return $this->name;
    }

    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type.
     *
     * Implementations SHOULD return the value stored in the "type" key of
     * the file in the $_FILES array.
     *
     * @return string|null The media type sent by the client or null if none
     *     was provided.
     */
    public function getClientMediaType()
    {
        return $this->type;
    }

    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize()
    {
        return $this->size;
    }
}
