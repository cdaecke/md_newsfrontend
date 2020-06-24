<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {
        
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Mediadreams.MdNewsfrontend',
            'Newsfe',
            [
                'News' => 'list, new, create, edit, update, delete'
            ],
            // non-cacheable actions
            [
                'News' => 'list, create, update, delete'
            ]
        );
        
    }
);
