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
final class NewsControllerUpdateFileTest extends AbstractControllerTestCase
{
    private const UID_OF_PAGE = 1;
    private const UID_OF_NEWS = 1;

    protected function setUp(): void
    {
        parent::setUp();

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
