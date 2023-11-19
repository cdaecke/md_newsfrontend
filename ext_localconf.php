<?php

defined('TYPO3') or die();

call_user_func(
    function () {

        /**
         * Extend ext:news
         */
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['classes']['Domain/Model/News'][] = 'md_newsfrontend';


        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'MdNewsfrontend',
            'Newsfe',
            [
                \Mediadreams\MdNewsfrontend\Controller\NewsController::class => 'list, new, create, edit, update, delete'
            ],
            // non-cacheable actions
            [
                \Mediadreams\MdNewsfrontend\Controller\NewsController::class => 'list, create, edit, update, delete'
            ]
        );

    }
);
