<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Infrastructure\Storage;

use App\Supplier\Infrastructure\Storage\LocalPrivateSupplierDocumentStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalPrivateSupplierDocumentStorageTest extends TestCase
{
    private string $root;
    private string $source;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/supplier-private-' . bin2hex(random_bytes(6));
        $this->source = tempnam(sys_get_temp_dir(), 'supplier-source-');
        file_put_contents($this->source, 'private compliance evidence');
    }

    protected function tearDown(): void
    {
        if (is_file($this->source)) unlink($this->source);
        if (is_dir($this->root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->root);
        }
    }

    public function testStoresReadsAndDeletesAnOpaqueObjectWithChecksum(): void
    {
        $storage = new LocalPrivateSupplierDocumentStorage($this->root);
        $stored = $storage->put($this->source, 1024);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $stored->objectKey);
        self::assertSame(hash('sha256', 'private compliance evidence'), $stored->sha256);
        self::assertSame(strlen('private compliance evidence'), $stored->sizeBytes);

        $stream = $storage->read($stored->objectKey);
        self::assertSame('private compliance evidence', stream_get_contents($stream));
        fclose($stream);

        $storage->delete($stored->objectKey);
        $this->expectException(RuntimeException::class);
        $storage->read($stored->objectKey);
    }

    public function testRejectsPathTraversalKeys(): void
    {
        $storage = new LocalPrivateSupplierDocumentStorage($this->root);
        $this->expectException(RuntimeException::class);
        $storage->read('../outside');
    }

    public function testEnforcesActualStreamSizeAndRemovesPartialObject(): void
    {
        $storage = new LocalPrivateSupplierDocumentStorage($this->root);
        $this->expectException(RuntimeException::class);
        $storage->put($this->source, 4);
    }
}
