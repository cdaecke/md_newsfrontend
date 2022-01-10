<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Domain\Repository;

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

use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Class NewsRepository
 * @package Mediadreams\MdNewsfrontend\Domain\Repository
 */
class NewsRepository extends Repository
{
    /**
     * Default orderings
     *
     */
    protected $defaultOrderings = [
        'datetime' => QueryInterface::ORDER_DESCENDING,
        'uid' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * Get news according to given frontend user and allow disabled records as well
     *
     * @param int $userId
     * @param int $allowNotEnabledNews
     * @return mixed
     */
    public function findByFeuserId(int $userId, int $allowNotEnabledNews = 0)
    {
        $query = $this->createQuery();
        $constraints = [];

        if ($allowNotEnabledNews === 1) {
            $query->getQuerySettings()->setIgnoreEnableFields(true);
        }

        $constraints[] = $query->equals('tx_md_newsfrontend_feuser', $userId);

        $query->matching($query->logicalAnd($constraints));
        return $query->execute();
    }
}
