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
 * (c) 2023 Christoph Daecke <typo3@mediadreams.org>
 *
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FrontendUserRepository extends Repository
{
    public function initializeObject(): void
    {
        $this->defaultQuerySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $this->defaultQuerySettings->setRespectStoragePage(false);
    }

}
