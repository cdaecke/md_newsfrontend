<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {
        
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'MdNewsfrontend',
            'Newsfe',
            [
                \Mediadreams\MdNewsfrontend\Controller\NewsController::class => 'list, new, create, edit, update, delete'
            ],
            // non-cacheable actions
            [
                \Mediadreams\MdNewsfrontend\Controller\NewsController::class => 'list, create, update, delete'
            ]
        );
        
    }
);
