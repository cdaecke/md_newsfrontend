<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Service;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */

use Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent;
use Mediadreams\MdNewsfrontend\Exception\FileUploadException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handles validation, storage, and FAL registration of uploaded files.
 *
 * Validates the file extension against the configured allow-list, optionally enforces
 * a maximum file size, and verifies that the actual file content matches the claimed
 * extension via MIME type inspection. On success, the file is moved into the configured
 * FAL upload folder and a sys_file_reference record is created for the given news record.
 *
 * All validation failures are reported as {@see FileUploadException} with a translation
 * key so the calling controller can display a localised error message without catching
 * unrelated exceptions.
 */
class FileUploadService
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly StorageRepository $storageRepository,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Validates, stores and registers the uploaded file as a FAL sys_file_reference.
     *
     * @param array<string, mixed> $settings
     * @throws FileUploadException
     */
    public function processUploadedFile(
        UploadedFile $uploadedFile,
        string $fieldName,
        string $falFieldName,
        int $newsUid,
        int $newsPid,
        int $feUserUid,
        array $settings
    ): int {
        // Require configured allowed extensions — reject upload if not configured
        $allowedExtensions = GeneralUtility::trimExplode(',', $settings['allowed_' . $fieldName] ?? '', true);
        if ($allowedExtensions === []) {
            throw new FileUploadException('controller.file_upload_not_configured');
        }

        // Sanitize client filename: strip path components, keep only safe characters
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', basename((string)$uploadedFile->getClientFilename()));
        if ($safeFilename === '' || $safeFilename === '.') {
            $safeFilename = 'upload_' . time();
        }

        // Validate file extension from sanitized filename
        $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new FileUploadException('controller.file_extension_not_allowed');
        }

        // Validate file size
        $maxSizeKb = (int)($settings['allowed_' . $fieldName . '_size'] ?? 0);
        if ($maxSizeKb > 0 && $uploadedFile->getSize() > $maxSizeKb * 1024) {
            throw new FileUploadException('controller.file_too_large');
        }

        // Move to temp file, so we can inspect the actual content
        $tmpFile = $this->createTempFile();
        $uploadedFile->moveTo($tmpFile);

        try {
            // Validate actual MIME type from file content — prevents disguised file uploads
            $allowedMimeTypes = $this->getAllowedMimeTypesForExtension($ext);
            if ($allowedMimeTypes !== []) {
                $actualMimeType = $this->detectMimeType($tmpFile);
                if (!in_array($actualMimeType, $allowedMimeTypes, true)) {
                    throw new FileUploadException('controller.file_mime_type_not_allowed');
                }
            }

            // Resolve FAL upload folder, create it if necessary
            $uploadPath = rtrim((string)$settings['uploadPath'], '/') . '/' . $feUserUid . '/';

            try {
                $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($uploadPath);
            } catch (FolderDoesNotExistException) {
                $parts = explode(':', $uploadPath, 2);
                if (count($parts) !== 2) {
                    throw new FileUploadException('controller.file_upload_storage_not_found');
                }
                [$storageUid, $folderPath] = $parts;
                $storage = $this->storageRepository->findByUid((int)$storageUid);
                if ($storage === null) {
                    throw new FileUploadException('controller.file_upload_storage_not_found');
                }
                $folder = $storage->createFolder(ltrim($folderPath, '/'));
            }

            $file = $folder->addFile($tmpFile, $safeFilename, DuplicationBehavior::RENAME);
        } finally {
            // Remove temp file if it still exists. addFile() uses rename() on success, so the
            // file is already gone in the happy path — this only cleans up on exception paths.
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        // Create sys_file_reference record
        $connection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $connection->insert('sys_file_reference', [
            'tstamp' => time(),
            'crdate' => time(),
            'uid_local' => $file->getUid(),
            'uid_foreign' => $newsUid,
            'tablenames' => 'tx_news_domain_model_news',
            'fieldname' => $falFieldName,
            'pid' => $newsPid,
            'sorting_foreign' => 1,
            'l10n_diffsource' => '',
        ]);
        $fileReferenceUid = (int)$connection->lastInsertId();

        // Increment the file-count column on the news record (atomic: no read-modify-write race)
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_news_domain_model_news');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update('tx_news_domain_model_news')
            ->set($falFieldName, $queryBuilder->quoteIdentifier($falFieldName) . ' + 1', false)
            ->where($queryBuilder->expr()->eq('uid', $newsUid))
            ->executeStatement();

        return $fileReferenceUid;
    }

    /**
     * Creates a temporary file path for the uploaded file.
     * Extracted to allow overriding in tests without a TYPO3 bootstrap.
     */
    protected function createTempFile(): string
    {
        return GeneralUtility::tempnam('tx_mdnewsfrontend_');
    }

    /**
     * Detects the MIME type of a file by inspecting its content.
     * Extracted to allow overriding in tests without a real file on disk.
     */
    protected function detectMimeType(string $filePath): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);
    }

    /**
     * Returns the expected MIME types for a given file extension.
     * Used to verify that the actual file content matches the claimed extension.
     * Extensions not listed here skip the MIME check (unknown/uncommon types).
     *
     * The list can be extended or modified via {@see ModifyAllowedMimeTypesEvent}.
     *
     * @return array<string>
     */
    private function getAllowedMimeTypesForExtension(string $extension): array
    {
        $map = [
            'gif'  => ['image/gif'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/csv', 'text/plain'],
            'mp3'  => ['audio/mpeg'],
            'mp4'  => ['video/mp4'],
            // Legacy Office formats
            'doc'  => ['application/msword'],
            'xls'  => ['application/vnd.ms-excel'],
            'ppt'  => ['application/vnd.ms-powerpoint'],
            // Office Open XML formats are ZIP containers — finfo returns application/zip
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
        ];

        $mimeTypes = $map[$extension] ?? [];
        $event = $this->eventDispatcher->dispatch(new ModifyAllowedMimeTypesEvent($extension, $mimeTypes));
        return $event->getMimeTypes();
    }
}
