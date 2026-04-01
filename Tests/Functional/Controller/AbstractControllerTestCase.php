<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Functional\Controller;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractControllerTestCase extends FunctionalTestCase
{
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
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:md_newsfrontend/Configuration/TypoScript/setup.typoscript',
                'EXT:md_newsfrontend/Tests/Functional/Controller/Fixtures/TypoScript/Setup/Rendering.typoscript',
            ],
        ]);
    }
}
