<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use Mediadreams\MdNewsfrontend\Controller\NewsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

#[CoversClass(NewsController::class)]
final class NewsControllerEnableFieldsTest extends AbstractControllerTestCase
{
    private const UID_OF_PAGE = 1;
    private const UID_OF_NEWS = 1;

    protected function setUp(): void
    {
        $this->additionalSetupTypoScript = [
            'EXT:md_newsfrontend/Tests/Functional/Controller/Fixtures/TypoScript/Setup/AllowNotEnabledNews.typoscript',
        ];

        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/ContentElement.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/FrontendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/HiddenNewsOwnedByUser1.csv');
    }

    #[Test]
    public function editActionRendersEditFormForHiddenNewsOwnedByCurrentUser(): void
    {
        $request = $this->buildEditRequest();
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        // EnableFieldsObjectConverter ignores hidden=1, so the owner can edit unpublished news
        self::assertStringContainsString('Edit News', $html);
    }

    #[Test]
    public function editActionDeniesAccessToHiddenNewsForNonOwner(): void
    {
        $request = $this->buildEditRequest();
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        // EnableFieldsObjectConverter loads the record, but checkAccess() still rejects non-owners
        self::assertStringNotContainsString('Edit News', $html);
    }

    #[Test]
    public function updateActionPersistsChangesToHiddenNews(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $trustedProperties = $this->getTrustedPropertiesFromEditForm(self::UID_OF_NEWS, $context);

        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[__trustedProperties]' => $trustedProperties,
                'tx_mdnewsfrontend_newsfe[action]' => 'update',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news][__identity]' => (string)self::UID_OF_NEWS,
                'tx_mdnewsfrontend_newsfe[news][title]' => 'Updated Hidden News',
            ]);

        $this->executeFrontendSubRequest($request, $context);

        $this->assertCSVDataSet(
            __DIR__ . '/Assertions/Database/NewsController/Update/HiddenNewsTitleUpdated.csv',
        );
    }

    private function buildEditRequest(): InternalRequest
    {
        return (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameter('tx_mdnewsfrontend_newsfe[action]', 'edit')
            ->withQueryParameter('tx_mdnewsfrontend_newsfe[controller]', 'News')
            ->withQueryParameter('tx_mdnewsfrontend_newsfe[news]', (string)self::UID_OF_NEWS);
    }
}
