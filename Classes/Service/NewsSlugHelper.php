<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Service;

/**
 *
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2020 Christoph Daecke <typo3@mediadreams.org>
 *
 */

use Mediadreams\MdNewsfrontend\Domain\Model\News;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generate slug for the news entry
 */
class NewsSlugHelper
{
    /**
     * @var string $tableName
     */
    protected string $tableName = 'tx_news_domain_model_news';

    /**
     * @var SlugHelper slugService
     */
    protected SlugHelper $slugService;

    /**
     * NewsSlugHelper constructor.
     */
    public function __construct()
    {
        $fieldConfig = $GLOBALS['TCA'][$this->tableName]['columns']['path_segment']['config'];
        $this->slugService = GeneralUtility::makeInstance(
            SlugHelper::class,
            $this->tableName,
            'path_segment',
            $fieldConfig
        );
    }

    /**
     * Get unique slug for entry
     *
     * @param News $obj
     * @return string
     */
    public function getSlug(News $obj): string
    {
        $newsArr = [
            'title' => $obj->getTitle(),
        ];

        $slug = $this->slugService->generate($newsArr, $obj->getPid());

        $state = RecordStateFactory::forName($this->tableName)
            ->fromArray($newsArr, $obj->getPid(), $obj->getUid());

        return $this->slugService->buildSlugForUniqueInSite($slug, $state);
    }
}
