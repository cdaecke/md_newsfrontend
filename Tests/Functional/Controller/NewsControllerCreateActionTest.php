<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use Mediadreams\MdNewsfrontend\Controller\NewsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

#[CoversClass(NewsController::class)]
final class NewsControllerCreateActionTest extends AbstractControllerTestCase
{
    private const UID_OF_PAGE = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/ContentElement.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/FrontendUsers.csv');
    }

    #[Test]
    public function createActionSetsPathSegmentFromTitle(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $trustedProperties = $this->getTrustedPropertiesFromNewForm($context);

        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'create',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[newNews][title]' => 'My Test News',
            ]);

        $this->executeFrontendSubRequest($request, $context);

        $row = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_news_domain_model_news')
            ->select(['path_segment'], 'tx_news_domain_model_news', ['title' => 'My Test News'])
            ->fetchAssociative();

        self::assertNotFalse($row, 'News record should have been created');
        self::assertSame('my-test-news', $row['path_segment']);
    }

    #[Test]
    public function createActionGeneratesUniqueSlugForDuplicateTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/NewsWithPathSegment.csv');

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $trustedProperties = $this->getTrustedPropertiesFromNewForm($context);

        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'create',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[newNews][title]' => 'My Test News',
            ]);

        $this->executeFrontendSubRequest($request, $context);

        // The existing record already has path_segment='my-test-news', so the new one must differ
        $rows = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_news_domain_model_news')
            ->select(['path_segment'], 'tx_news_domain_model_news', ['title' => 'My Test News'])
            ->fetchAllAssociative();

        $slugs = array_column($rows, 'path_segment');
        self::assertCount(2, $slugs, 'Two news records with the same title should exist');
        self::assertCount(2, array_unique($slugs), 'Both path_segment values must be unique');
    }
}
