<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

call_user_func(
    function (): void {

        /**
         * Register plugin
         */
        $pluginSignature = ExtensionUtility::registerPlugin(
            'MdNewsfrontend',
            'Newsfe',
            'LLL:EXT:md_newsfrontend/Resources/Private/Language/locallang_db.xlf:tx_md_newsfrontend_newsfe.name',
            'tx_mdnewsfrontend_newsfe',
            null, // @phpstan-ignore argument.type
            'LLL:EXT:md_newsfrontend/Resources/Private/Language/locallang_db.xlf:tx_md_newsfrontend_newsfe.description',
        );

        ExtensionManagementUtility::addToAllTCAtypes(
            'tt_content',
            '--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin,pages,recursive',
            $pluginSignature,
            'after:subheader'
        );
    }
);
