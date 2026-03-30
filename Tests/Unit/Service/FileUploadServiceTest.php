<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Unit\Service;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */

use Mediadreams\MdNewsfrontend\Exception\FileUploadException;
use Mediadreams\MdNewsfrontend\Service\FileUploadService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;

final class FileUploadServiceTest extends TestCase
{
    private ResourceFactory $resourceFactory;
    private StorageRepository $storageRepository;
    private ConnectionPool $connectionPool;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->resourceFactory = $this->createMock(ResourceFactory::class);
        $this->storageRepository = $this->createMock(StorageRepository::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function createService(): FileUploadService
    {
        return new FileUploadService(
            $this->resourceFactory,
            $this->storageRepository,
            $this->connectionPool,
            $this->eventDispatcher,
        );
    }

    /**
     * Creates a FileUploadService subclass that overrides FAL/temp-file hooks so
     * tests can run without a TYPO3 bootstrap.
     *
     * @param string $detectedMimeType MIME type that detectMimeType() should return
     */
    private function createServiceWithMimeOverride(string $detectedMimeType): FileUploadService
    {
        return new class(
            $this->resourceFactory,
            $this->storageRepository,
            $this->connectionPool,
            $this->eventDispatcher,
            $detectedMimeType,
        ) extends FileUploadService {
            public function __construct(
                ResourceFactory $resourceFactory,
                StorageRepository $storageRepository,
                ConnectionPool $connectionPool,
                EventDispatcherInterface $eventDispatcher,
                private readonly string $mimeType,
            ) {
                parent::__construct($resourceFactory, $storageRepository, $connectionPool, $eventDispatcher);
            }

            protected function createTempFile(): string
            {
                return tempnam(sys_get_temp_dir(), 'tx_mdnewsfrontend_test_');
            }

            protected function detectMimeType(string $filePath): string
            {
                return $this->mimeType;
            }
        };
    }

    private function createUploadedFileMock(string $clientFilename, int $size = 1024): UploadedFile
    {
        $mock = $this->createMock(UploadedFile::class);
        $mock->method('getClientFilename')->willReturn($clientFilename);
        $mock->method('getSize')->willReturn($size);
        return $mock;
    }

    #[Test]
    public function processUploadedFileThrowsWhenExtensionNotConfigured(): void
    {
        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('controller.file_upload_not_configured');

        $this->createService()->processUploadedFile(
            $this->createUploadedFileMock('photo.jpg'),
            'falMedia',
            'fal_media',
            1,
            1,
            42,
            []
        );
    }

    #[Test]
    public function processUploadedFileThrowsWhenExtensionNotAllowed(): void
    {
        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('controller.file_extension_not_allowed');

        $this->createService()->processUploadedFile(
            $this->createUploadedFileMock('malware.exe'),
            'falMedia',
            'fal_media',
            1,
            1,
            42,
            ['allowed_falMedia' => 'jpg,png', 'uploadPath' => '1:/uploads/']
        );
    }

    #[Test]
    public function processUploadedFileThrowsWhenFileTooLarge(): void
    {
        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('controller.file_too_large');

        $twoMegabytes = 2 * 1024 * 1024;

        $this->createService()->processUploadedFile(
            $this->createUploadedFileMock('photo.jpg', $twoMegabytes),
            'falMedia',
            'fal_media',
            1,
            1,
            42,
            ['allowed_falMedia' => 'jpg', 'allowed_falMedia_size' => '1024', 'uploadPath' => '1:/uploads/']
        );
    }

    #[Test]
    public function processUploadedFileThrowsWhenMimeTypeMismatch(): void
    {
        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('controller.file_mime_type_not_allowed');

        // EventDispatcher must return an event with the original (empty) MIME list for jpg
        // so the MIME check is actually performed.
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $uploadedFile = $this->createUploadedFileMock('photo.jpg');
        // moveTo() is void — no stub needed, mock does nothing by default

        $this->createServiceWithMimeOverride('application/octet-stream')->processUploadedFile(
            $uploadedFile,
            'falMedia',
            'fal_media',
            1,
            1,
            42,
            ['allowed_falMedia' => 'jpg', 'uploadPath' => '1:/uploads/']
        );
    }
}
