<?php

declare(strict_types=1);

use GeorgRinger\News\Domain\Model\Category;
use Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser;
use Mediadreams\MdNewsfrontend\Domain\Model\FrontendUserGroup;
use Mediadreams\MdNewsfrontend\Domain\Model\News;

return [
    News::class => [
        'tableName' => 'tx_news_domain_model_news',
        'recordType' => 0,
    ],

    FrontendUser::class => [
        'tableName' => 'fe_users',
    ],

    FrontendUserGroup::class => [
        'tableName' => 'fe_groups',
    ],

    Category::class => [
        'tableName' => 'sys_category',
    ],
];
