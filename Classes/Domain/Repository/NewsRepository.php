<?php
namespace Mediadreams\MdNewsfrontend\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 *
 */

/**
 * The repository for News
 */
class NewsRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    /**
     * Default orderings
     *
     */
    protected $defaultOrderings = [
        'datetime' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING,
        'uid' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * @param int $userId
     * @param int $allowNotEnabledNews
     * @return mixed
     */
    public function findByFeuserId(int $userId, int $allowNotEnabledNews = 0)
    {

        $query = $this->createQuery();
        $query->matching($query->equals('Tx_md_newsfrontend_feuser', $userId));
        if( $allowNotEnabledNews === 1) {
            $query->getQuerySettings()->setIgnoreEnableFields(true);
        }
        return $query->execute();

    }

}
