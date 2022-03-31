<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "md_newsfrontend"
 *
 * Auto generated by Extension Builder 2019-03-25
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'News frontend',
    'description' => 'This extension enables frontend users to created news records in the frontend.',
    'category' => 'plugin',
    'author' => 'Christoph Daecke',
    'author_email' => 'typo3@mediadreams.org',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '3.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'news' => '7.0.0-9.99.99',
            'numbered_pagination' => '1.0.1-1.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
