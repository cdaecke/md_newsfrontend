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
            'Mediadreams.MdNewsfrontend',
            'Newsfe',
            'News frontend'
        );

    }
);
