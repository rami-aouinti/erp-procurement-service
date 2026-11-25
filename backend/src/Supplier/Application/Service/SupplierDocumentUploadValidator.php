<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class SupplierDocumentUploadValidator
{
    private const SAFE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /** @param list<string> $allowedMimeTypes @return array{mimeType:string,sizeBytes:int,filename:string} */
    public function validate(UploadedFile $file, array $allowedMimeTypes, int $maxBytes): array
    {
        if (!$file->isValid()) {
            throw new HttpException(400, 'The uploaded file could not be received.');
        }
        if ($maxBytes < 1 || $file->getSize() === false || $file->getSize() < 1 || $file->getSize() > $maxBytes) {
            throw new HttpException(413, 'The document is empty or exceeds the configured size limit.');
        }

        $path = $file->getPathname();
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (!is_string($mime) || !in_array($mime, self::SAFE_MIME_TYPES, true) || !in_array($mime, $allowedMimeTypes, true)) {
            throw new UnsupportedMediaTypeHttpException('The document content type is not allowed.');
        }

        $filename = $file->getClientOriginalName();
        $filename = str_replace(["\0", "\r", "\n", '/', '\\'], '_', $filename);
        $filename = trim($filename);
        if ($filename === '') $filename = 'document';

        return [
            'mimeType' => $mime,
            'sizeBytes' => (int) $file->getSize(),
            'filename' => mb_substr($filename, 0, 255),
        ];
    }
}
