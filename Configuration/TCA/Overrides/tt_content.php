<?php

defined('TYPO3') or die();

call_user_func(
    function()
    {

        /**
         * Register plugin
         *
         */
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            'MdNewsfrontend',
            'Newsfe',
            'LLL:EXT:md_newsfrontend/Resources/Private/Language/locallang_db.xlf:tx_md_newsfrontend_newsfe.name',
            'tx_mdnewsfrontend_newsfe',
            null,
            'LLL:EXT:md_newsfrontend/Resources/Private/Language/locallang_db.xlf:tx_md_newsfrontend_newsfe.description',
        );

    }
);
