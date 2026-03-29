<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$additionalFields = [
    'tx_md_newsfrontend_feuser' => [
        'exclude' => true,
        'label' => 'LLL:EXT:md_newsfrontend/Resources/Private/Language/locallang_db.xlf:tx_mdnewsfrontend_domain_model_news.tx_md_newsfrontend_feuser',
        'config' => [
            'type' => 'group',
            'allowed' => 'fe_users',
            'foreign_table' => 'fe_users',
            'size' => 1,
            'minitems' => 0,
            'maxitems' => 1,
            'default' => 0,
            'eval' => 'int',
            'suggestOptions' => [
                'type' => 'suggest',
                'default' => [
                    'searchWholePhrase' => true,
                ],
            ],
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tx_news_domain_model_news', $additionalFields);
ExtensionManagementUtility::addToAllTCAtypes('tx_news_domain_model_news', 'tx_md_newsfrontend_feuser', '', 'after:bodytext');
