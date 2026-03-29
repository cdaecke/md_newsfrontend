<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Domain\Model;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */
/**
 * News
 */
class News extends \GeorgRinger\News\Domain\Model\News
{
    /**
     * Uid of feUser
     * Do not replace qualifier with an import, because it will try to use `FrontendUser` of ext:news
     *
     * @var \Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser
     */
    protected ?\Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser $txMdNewsfrontendFeuser = null;

    /**
     * Returns the txMdNewsfrontendFeuser
     *
     * @return \Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser $txMdNewsfrontendFeuser
     */
    public function getTxMdNewsfrontendFeuser(): ?\Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser
    {
        return $this->txMdNewsfrontendFeuser;
    }

    /**
     * Sets the txMdNewsfrontendFeuser
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser $txMdNewsfrontendFeuser
     */
    public function setTxMdNewsfrontendFeuser(\Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser $txMdNewsfrontendFeuser): void
    {
        $this->txMdNewsfrontendFeuser = $txMdNewsfrontendFeuser;
    }

    /**
     * Get first falMedia element of news
     * Do not replace qualifier with an import, because it will try to use `FileReference` of ext:news
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FileReference|null
     */
    public function getFirstFalMedia(): ?object
    {
        $falMedia = $this->getFalMedia();

        if ($falMedia instanceof \TYPO3\CMS\Extbase\Persistence\ObjectStorage) {
            foreach ($falMedia as $image) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Get first falRelatedFiles element of news
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FileReference|null
     */
    public function getFirstFalRelatedFiles(): ?object
    {
        $falRelatedFiles = $this->getFalRelatedFiles();

        if ($falRelatedFiles instanceof \TYPO3\CMS\Extbase\Persistence\ObjectStorage) {
            foreach ($falRelatedFiles as $doc) {
                return $doc;
            }
        }

        return null;
    }

}
