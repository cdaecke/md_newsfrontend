<?php
declare(strict_types=1);

return [
    \Mediadreams\MdNewsfrontend\Domain\Model\News::class => [
        'tableName' => 'tx_news_domain_model_news',
        'recordType' => 0,
    ],

    \Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser::class => [
        'tableName' => 'fe_users',
    ],

    \Mediadreams\MdNewsfrontend\Domain\Model\FrontendUserGroup::class => [
        'tableName' => 'fe_groups',
    ],

    \GeorgRinger\News\Domain\Model\Category::class => [
        'tableName' => 'sys_category',
    ],
];
