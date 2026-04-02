<?php

declare(strict_types=1);

use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\TYPO314\v0\DropFifthParameterForExtensionUtilityConfigurePluginRector;
use Ssch\TYPO3Rector\TYPO313\v4\MigratePluginContentElementAndPluginSubtypesRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        LevelSetList::UP_TO_PHP_82,
        SetList::TYPE_DECLARATION,
        Typo3LevelSetList::UP_TO_TYPO3_14,
    ]);

    // To have a better analysis from phpstan, we teach it here some more things
    $rectorConfig->phpstanConfig(Typo3Option::PHPSTAN_FOR_RECTOR_PATH);

    // FQN classes are not imported by default. If you don't do it manually after every Rector run, enable it by:
    $rectorConfig->importNames();

    // Disable parallel otherwise non php file processing is not working i.e. typoscript or flexform
    $rectorConfig->disableParallel();

    // this will not import root namespace classes, like \DateTime or \Exception
    $rectorConfig->importShortClasses(false);

    // Define your target version which you want to support
    $rectorConfig->phpVersion(PhpVersion::PHP_82);

    $rectorConfig->paths([
        getcwd() . '/',
    ]);

    $rectorConfig->skip([
        getcwd() . '/**/Resources/',
        getcwd() . '/**/Configuration/ExtensionBuilder/*',
        getcwd() . '/**/Resources/**/node_modules/*',
        getcwd() . '/**/Resources/**/NodeModules/*',
        getcwd() . '/**/Resources/**/BowerComponents/*',
        getcwd() . '/**/Resources/**/bower_components/*',
        getcwd() . '/**/Resources/**/build/*',
        getcwd() . '/vendor/*',
        getcwd() . '/Build/*',
        getcwd() . '/public/*',
        getcwd() . '/.github/*',
        getcwd() . '/.Build/*',
        // FQN required: extends GeorgRinger\News\Domain\Model\News which defines properties
        // with GeorgRinger FrontendUser/FileReference types — use-imports would cause fatal errors
        getcwd() . '/Classes/Domain/Model/News.php',

        NameImportingPostRector::class => [
            'ext_localconf.php',
            'ext_emconf.php',
            'ext_tables.php',
            getcwd() . '/**/Configuration/*',
            getcwd() . '/**/Configuration/**/*.php',
        ],
        // Keep Install namespace for v13+v14 compatibility; Core namespace is v14-only
        RenameClassRector::class => [
            getcwd() . '/Classes/Updates/',
        ],
        // v14-only: 5th parameter of configurePlugin() is still required in v13
        DropFifthParameterForExtensionUtilityConfigurePluginRector::class,
        MigratePluginContentElementAndPluginSubtypesRector::class,
    ]);

    $rectorConfig->ruleWithConfiguration(
        ExtEmConfRector::class,
        [
            ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '13.4.0-14.4.99',
            ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [
                'dependencies',
                'conflicts',
                'suggests',
                'private',
                'download_password',
                'TYPO3_version',
                'PHP_version',
                'internal',
                'module',
                'loadOrder',
                'lockType',
                'shy',
                'priority',
                'modify_tables',
                'CGLcompliance',
                'CGLcompliance_note',
            ],
        ]
    );
};
