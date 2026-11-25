<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Storage;

use App\Supplier\Application\Port\StoredSupplierDocument;
use App\Supplier\Application\Port\SupplierDocumentStorage;
use RuntimeException;

final readonly class LocalPrivateSupplierDocumentStorage implements SupplierDocumentStorage
{
    public function __construct(private string $root)
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Private document storage directory could not be created.');
        }
        @chmod($this->root, 0700);
    }

    public function put(string $sourcePath, int $maxBytes): StoredSupplierDocument
    {
        if ($maxBytes < 1 || !is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException('The uploaded document is not readable or has an invalid size limit.');
        }

        $key = bin2hex(random_bytes(32));
        $directory = $this->root . DIRECTORY_SEPARATOR . substr($key, 0, 2);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private document storage directory could not be created.');
        }
        @chmod($directory, 0700);
        $destination = $directory . DIRECTORY_SEPARATOR . $key;

        $input = fopen($sourcePath, 'rb');
        $output = fopen($destination, 'xb');
        if ($input === false || $output === false) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            throw new RuntimeException('Private document storage could not open the upload streams.');
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('The uploaded document could not be read completely.');
                }
                if ($chunk === '') continue;
                $bytes += strlen($chunk);
                if ($bytes > $maxBytes) {
                    throw new RuntimeException('The uploaded document exceeds the configured size limit.');
                }
                hash_update($hash, $chunk);
                $written = fwrite($output, $chunk);
                if ($written !== strlen($chunk)) {
                    throw new RuntimeException('The uploaded document could not be written completely.');
                }
            }
            if ($bytes === 0) {
                throw new RuntimeException('Empty documents are not accepted.');
            }
            if (!fflush($output)) {
                throw new RuntimeException('The uploaded document could not be flushed to private storage.');
            }
        } catch (\Throwable $exception) {
            fclose($input);
            fclose($output);
            @unlink($destination);
            throw $exception;
        }

        fclose($input);
        fclose($output);
        @chmod($destination, 0600);

        return new StoredSupplierDocument($key, $bytes, hash_final($hash));
    }

    public function read(string $objectKey)
    {
        $path = $this->path($objectKey);
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The requested document is not available in private storage.');
        }

        return $stream;
    }

    public function delete(string $objectKey): void
    {
        $path = $this->path($objectKey);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('The private document object could not be deleted.');
        }
    }

    private function path(string $objectKey): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $objectKey) !== 1) {
            throw new RuntimeException('Invalid private document object key.');
        }

        return $this->root . DIRECTORY_SEPARATOR . substr($objectKey, 0, 2) . DIRECTORY_SEPARATOR . $objectKey;
    }
}
