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

        $tableName = 'tx_news_domain_model_news';
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        if( $allowNotEnabledNews === 1){
            $queryBuilder
                ->getRestrictions()
                ->removeByType(StartTimeRestriction::class)
                ->removeByType(EndTimeRestriction::class);
        }

        return $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($queryBuilder->expr()->eq('Tx_md_newsfrontend_feuser', $queryBuilder->createNamedParameter($userId, \PDO::PARAM_INT)))
            ->execute();
    }

}
