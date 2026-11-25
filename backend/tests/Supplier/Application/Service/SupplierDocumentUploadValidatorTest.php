<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Application\Service;

use App\Supplier\Application\Service\SupplierDocumentUploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class SupplierDocumentUploadValidatorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'supplier-doc-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) unlink($this->path);
    }

    public function testAcceptsPdfBasedOnDetectedContentAndSanitizesFilename(): void
    {
        file_put_contents($this->path, "%PDF-1.4\n% test document");
        $file = new UploadedFile($this->path, "../tax\ncertificate.pdf", 'text/plain', UPLOAD_ERR_OK, true);

        $result = (new SupplierDocumentUploadValidator())->validate($file, ['application/pdf'], 1024);

        self::assertSame('application/pdf', $result['mimeType']);
        self::assertSame('tax_certificate.pdf', $result['filename']);
        self::assertGreaterThan(0, $result['sizeBytes']);
    }

    public function testRejectsUnsupportedContentEvenWhenFilenameLooksLikePdf(): void
    {
        file_put_contents($this->path, 'plain text pretending to be a pdf');
        $file = new UploadedFile($this->path, 'evidence.pdf', 'application/pdf', UPLOAD_ERR_OK, true);

        $this->expectException(UnsupportedMediaTypeHttpException::class);
        (new SupplierDocumentUploadValidator())->validate($file, ['application/pdf'], 1024);
    }

    public function testRejectsOversizedFiles(): void
    {
        file_put_contents($this->path, "%PDF-1.4\n" . str_repeat('x', 64));
        $file = new UploadedFile($this->path, 'evidence.pdf', 'application/pdf', UPLOAD_ERR_OK, true);

        $this->expectException(HttpException::class);
        (new SupplierDocumentUploadValidator())->validate($file, ['application/pdf'], 8);
    }
}
