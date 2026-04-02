<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use Mediadreams\MdNewsfrontend\Controller\NewsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Property\Exception\TargetNotFoundException;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

#[CoversClass(NewsController::class)]
final class NewsControllerHiddenNewsWithoutSettingTest extends AbstractControllerTestCase
{
    private const UID_OF_PAGE = 1;
    private const UID_OF_NEWS = 1;

    protected function setUp(): void
    {
        // $additionalSetupTypoScript is intentionally NOT set here:
        // allowNotEnabledNews defaults to 0, so hidden records must not be loadable.
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/ContentElement.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/FrontendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/NewsController/HiddenNewsOwnedByUser1.csv');
    }

    #[Test]
    public function editActionDoesNotRenderEditFormForHiddenNewsWhenSettingIsDisabled(): void
    {
        $request = (new InternalRequest())
            ->withPageId(self::UID_OF_PAGE)
            ->withQueryParameter('tx_mdnewsfrontend_newsfe[action]', 'edit')
            ->withQueryParameter('tx_mdnewsfrontend_newsfe[controller]', 'News')
            ->withQueryParameter('tx_mdnewsfrontend_newsfe[news]', (string)self::UID_OF_NEWS);
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        // Without allowNotEnabledNews=1, PersistentObjectConverter cannot load hidden records
        // and throws TargetNotFoundException instead of rendering the form.
        $this->expectException(TargetNotFoundException::class);
        $this->executeFrontendSubRequest($request, $context);
    }
}
