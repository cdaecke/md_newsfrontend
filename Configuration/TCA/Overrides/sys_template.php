<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

ExtensionManagementUtility::addStaticFile(
    'md_newsfrontend',
    'Configuration/TypoScript',
    'News frontend'
);
