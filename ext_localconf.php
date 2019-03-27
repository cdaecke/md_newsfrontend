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

        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
        
        $iconRegistry->registerIcon(
            'md_newsfrontend-plugin-newsfe',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:md_newsfrontend/Resources/Public/Icons/user_plugin_newsfe.svg']
        );
        
    }
);
