<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use Doctrine\DBAL\ParameterType;
use GuzzleHttp\Psr7\Stream;
use Mediadreams\MdNewsfrontend\Controller\NewsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

#[CoversClass(NewsController::class)]
final class NewsControllerUpdateFileTest extends AbstractControllerTestCase
{
    private const UID_OF_PAGE = 1;
    private const UID_OF_NEWS = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Provide a physical file backing for the sys_file fixture record (storage=1, identifier=/test-image.jpg).
        // Without this, the FAL Indexer (triggered by addFile() during upload) scans the storage,
        // finds the sys_file record has no corresponding file on disk, marks it deleted, and throws
        // "File has been deleted." This is harmless for the delete/keep tests since they never call addFile().
        $fileadminPath = $this->instancePath . '/fileadmin/';
        if (!is_dir($fileadminPath)) {
            mkdir($fileadminPath, 0777, true);
        }
        file_put_contents($fileadminPath . 'test-image.jpg', 'dummy');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/ContentElement.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/FrontendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/NewsOwnedByUser1.csv');
    }

    #[Test]
    public function updateActionWithDeleteFlagRemovesFalMediaReference(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        // Fetch __trustedProperties before importing file fixtures so that f:image
        // in the edit template does not try to process a non-existent FAL file.
        $trustedProperties = $this->getTrustedPropertiesFromEditForm(self::UID_OF_NEWS, $context);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileStorage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFile.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileReferenceForFalMedia.csv');

        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'update',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news][__identity]' => (string)self::UID_OF_NEWS,
                'tx_mdnewsfrontend_newsfe[news][title]' => 'Test News owned by User 1',
                'tx_mdnewsfrontend_newsfe[falMediaDelete]' => '1',
            ]);

        $this->executeFrontendSubRequest($request, $context);

        // sys_file_reference is hard-deleted by Extbase (M:N relation table semantics).
        // assertCSVDataSet cannot assert absence, so we query directly.
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $count = (int)$queryBuilder
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter(self::UID_OF_NEWS, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('fal_media')),
            )
            ->executeQuery()
            ->fetchOne();

        self::assertSame(0, $count, 'sys_file_reference for fal_media should have been removed after delete flag');
    }

    #[Test]
    public function updateActionWithoutDeleteFlagKeepsFalMediaReference(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        // Fetch __trustedProperties before importing file fixtures (same reason as above).
        $trustedProperties = $this->getTrustedPropertiesFromEditForm(self::UID_OF_NEWS, $context);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileStorage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFile.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileReferenceForFalMedia.csv');

        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'update',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news][__identity]' => (string)self::UID_OF_NEWS,
                'tx_mdnewsfrontend_newsfe[news][title]' => 'Test News owned by User 1',
            ]);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            __DIR__ . '/Assertions/Database/NewsController/Update/FileReferenceKept.csv',
        );
    }

    #[Test]
    public function updateActionWithNewUploadReplacesExistingFalMediaReference(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        // Get trusted properties before importing file fixtures (avoid f:image FAL error)
        $trustedProperties = $this->getTrustedPropertiesFromEditForm(self::UID_OF_NEWS, $context);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileStorage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFile.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileReferenceForFalMedia.csv');

        // UploadedFile::moveTo() uses is_uploaded_file() for file-path inputs (fails outside HTTP).
        // Using a stream bypasses that check and writes content directly to the target path.
        $gifContent = $this->getMinimalGifContent();
        $stream = new Stream(fopen('php://temp', 'rb+'));
        $stream->write($gifContent);
        $stream->rewind();
        $uploadedFile = new UploadedFile($stream, strlen($gifContent), UPLOAD_ERR_OK, 'new-image.gif', 'image/gif');

        // Fluid renders <f:form.upload name="falMedia"> as tx_mdnewsfrontend_newsfe[falMedia],
        // so uploaded files must be nested under the plugin namespace.
        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'update',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news][__identity]' => (string)self::UID_OF_NEWS,
                'tx_mdnewsfrontend_newsfe[news][title]' => 'Test News owned by User 1',
            ])
            ->withUploadedFiles(['tx_mdnewsfrontend_newsfe' => ['falMedia' => $uploadedFile]]);

        $this->executeFrontendSubRequest($request, $context);

        // Old reference (uid_local=100) is gone; exactly one new reference pointing to the new sys_file exists
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $refs = $queryBuilder
            ->select('uid', 'uid_local')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter(self::UID_OF_NEWS, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('fal_media')),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(1, $refs, 'Exactly one fal_media reference should exist after upload');
        self::assertNotEquals(100, $refs[0]['uid_local'], 'Old sys_file (uid=100) should be replaced by the newly uploaded file');

        // FileUploadService increments the fal_media counter atomically
        $newsRow = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_news_domain_model_news')
            ->select(['fal_media'], 'tx_news_domain_model_news', ['uid' => self::UID_OF_NEWS])
            ->fetchAssociative();
        self::assertSame(1, (int)($newsRow['fal_media'] ?? 0), 'fal_media counter should be 1 after upload');
    }

    #[Test]
    public function updateActionWithNewUploadSetsFileReferenceMetadata(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $trustedProperties = $this->getTrustedPropertiesFromEditForm(self::UID_OF_NEWS, $context);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileStorage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFile.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/SysFileReferenceForFalMedia.csv');

        $gifContent = $this->getMinimalGifContent();
        $stream = new Stream(fopen('php://temp', 'rb+'));
        $stream->write($gifContent);
        $stream->rewind();
        $uploadedFile = new UploadedFile($stream, strlen($gifContent), UPLOAD_ERR_OK, 'meta-test.gif', 'image/gif');

        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'update',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news][__identity]' => (string)self::UID_OF_NEWS,
                'tx_mdnewsfrontend_newsfe[news][title]' => 'Test News owned by User 1',
                'tx_mdnewsfrontend_newsfe[falMediaMeta][title]' => 'My image title',
                'tx_mdnewsfrontend_newsfe[falMediaMeta][description]' => 'My image description',
                'tx_mdnewsfrontend_newsfe[falMediaMeta][showinpreview]' => '1',
            ])
            ->withUploadedFiles(['tx_mdnewsfrontend_newsfe' => ['falMedia' => $uploadedFile]]);

        $this->executeFrontendSubRequest($request, $context);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $ref = $queryBuilder
            ->select('title', 'description', 'showinpreview')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter(self::UID_OF_NEWS, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('fal_media')),
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($ref, 'A fal_media reference should exist after upload');
        self::assertSame('My image title', $ref['title']);
        self::assertSame('My image description', $ref['description']);
        self::assertSame(1, (int)$ref['showinpreview']);
    }

    /**
     * Returns the binary content of a minimal 1×1 pixel GIF89a image (35 bytes).
     * GIF is used because it has the simplest binary structure and finfo reliably detects it.
     */
    private function getMinimalGifContent(): string
    {
        return base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
    }

    private function getTrustedPropertiesFromEditForm(int $newsUid, InternalRequestContext $context): string
    {
        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[action]' => 'edit',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news]' => (string)$newsUid,
            ]);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        return $this->getTrustedPropertiesFromHtml($html);
    }

    private function getTrustedPropertiesFromHtml(string $html): string
    {
        $matches = [];
        preg_match('/__trustedProperties\]" value="([a-zA-Z0-9&{};:,_\[\]\\\\]+)"/', $html, $matches);
        if (!isset($matches[1])) {
            throw new \RuntimeException('Could not fetch trustedProperties from returned HTML.', 1744028933);
        }

        return html_entity_decode($matches[1]);
    }
}
