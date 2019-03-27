<?php
defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'md_newsfrontend', 
    'Configuration/TypoScript', 
    'News frontend'
);
