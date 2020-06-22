<?php
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

use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generate slug for the news entry
 */
class NewsSlugHelper
{
    /**
     * @var tableName
     */
    protected $tableName = 'tx_news_domain_model_news';

    /**
     * @var slugService
     */
    protected $slugService;

    /**
     * NewsSlugHelper constructor.
     */
    public function __construct()
    {
        $fieldConfig = $GLOBALS['TCA'][$this->tableName]['columns']['path_segment']['config'];
        $this->slugService = GeneralUtility::makeInstance(SlugHelper::class, $this->tableName, 'path_segment', $fieldConfig);
    }

    /**
     * Get unique slug for entry
     *
     * @param object $obj
     * @return string
     */
    public function getSlug($obj): string
    {
        $newsArr = [
            'title' => $obj->getTitle(),
        ];

        $slug = $this->slugService->generate($newsArr,  $obj->getPid());

        $state = RecordStateFactory::forName($this->tableName)
            ->fromArray($newsArr, $obj->getPid(), $obj->getUid());

        $slug = $this->slugService->buildSlugForUniqueInSite($slug, $state);

        return $slug;
    }
}
