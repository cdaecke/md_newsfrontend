<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractControllerTestCase extends FunctionalTestCase
{
    /**
     * Additional TypoScript setup files appended to the root page template.
     * Override in subclasses before calling parent::setUp() to inject test-specific settings.
     *
     * @var list<non-empty-string>
     */
    protected array $additionalSetupTypoScript = [];

    protected function setUp(): void
    {
        $this->testExtensionsToLoad = [
            'georgringer/news',
            'mediadreams/md_newsfrontend',
        ];

        $this->coreExtensionsToLoad = [
            'typo3/cms-fluid-styled-content',
        ];

        $this->pathsToLinkInTestInstance = [
            'typo3conf/ext/md_newsfrontend/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
        ];

        $this->configurationToUseInTestInstance = [
            'FE' => [
                'cacheHash' => [
                    'enforceValidation' => false,
                ],
            ],
        ];

        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteStructure.csv');
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:md_newsfrontend/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => array_merge(
                [
                    'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                    'EXT:md_newsfrontend/Configuration/TypoScript/setup.typoscript',
                    'EXT:md_newsfrontend/Tests/Functional/Controller/Fixtures/TypoScript/Setup/Rendering.typoscript',
                ],
                $this->additionalSetupTypoScript,
            ),
        ]);
    }

    protected function getTrustedPropertiesFromEditForm(int $newsUid, InternalRequestContext $context, int $pageId = 1): string
    {
        $request = (new InternalRequest())
            ->withPageId($pageId)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[action]' => 'edit',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
                'tx_mdnewsfrontend_newsfe[news]' => (string)$newsUid,
            ]);
        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();
        return $this->getTrustedPropertiesFromHtml($html);
    }

    protected function getTrustedPropertiesFromNewForm(InternalRequestContext $context, int $pageId = 1): string
    {
        $request = (new InternalRequest())
            ->withPageId($pageId)
            ->withQueryParameters([
                'tx_mdnewsfrontend_newsfe[action]' => 'new',
                'tx_mdnewsfrontend_newsfe[controller]' => 'News',
            ]);
        $html = (string)$this->executeFrontendSubRequest($request, $context)->getBody();
        return $this->getTrustedPropertiesFromHtml($html);
    }

    protected function getTrustedPropertiesFromHtml(string $html): string
    {
        $matches = [];
        preg_match('/__trustedProperties\]" value="([a-zA-Z0-9&{};:,_\[\]\\\\]+)"/', $html, $matches);
        if (!isset($matches[1])) {
            throw new \RuntimeException('Could not fetch trustedProperties from returned HTML.', 1744028933);
        }
        return html_entity_decode($matches[1]);
    }
}
