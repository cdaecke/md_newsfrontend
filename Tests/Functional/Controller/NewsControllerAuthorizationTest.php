<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use Mediadreams\MdNewsfrontend\Controller\NewsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

#[CoversClass(NewsController::class)]
final class NewsControllerAuthorizationTest extends AbstractControllerTestCase
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
    public function editActionForUnauthenticatedUserDoesNotRenderEditForm(): void
    {
        $request = $this->buildEditRequest();

        $html = (string)$this->executeFrontendSubRequest($request)->getBody();

        self::assertStringNotContainsString('Edit News', $html);
    }

    #[Test]
    public function editActionForUserWithoutOwnershipDoesNotRenderEditForm(): void
    {
        $request = $this->buildEditRequest();
        $context = (new InternalRequestContext())->withFrontendUserId(2);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringNotContainsString('Edit News', $html);
    }

    #[Test]
    public function editActionForOwnerRendersEditForm(): void
    {
        $request = $this->buildEditRequest();
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();

        self::assertStringContainsString('Edit News', $html);
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
