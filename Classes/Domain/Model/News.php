<?php
namespace Mediadreams\MdNewsfrontend\Domain\Model;

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
 * News
 */
class News extends \GeorgRinger\News\Domain\Model\News
{
    /**
     * Uid of feUser
     *
     * @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser
     * @lazy
     */
    protected $mdNewsfrontendFeuser = null;

    /**
     * Returns the mdNewsfrontendFeuser
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $mdNewsfrontendFeuser
     */
    public function getMdNewsfrontendFeuser()
    {
        return $this->mdNewsfrontendFeuser;
    }

    /**
     * Sets the mdNewsfrontendFeuser
     *
     * @param \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $mdNewsfrontendFeuser
     * @return void
     */
    public function setMdNewsfrontendFeuser(\TYPO3\CMS\Extbase\Domain\Model\FrontendUser $mdNewsfrontendFeuser)
    {
        $this->mdNewsfrontendFeuser = $mdNewsfrontendFeuser;
    }

    /**
     * Get first falMedia element of news
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FileReference|null
     */
    public function getFirstFalMedia()
    {
        $falMedia = $this->getFalMedia();

        if ($falMedia) {
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
    public function getFirstFalRelatedFiles()
    {
        $falRelatedFiles = $this->getFalRelatedFiles();

        if ($falRelatedFiles) {
            foreach ($falRelatedFiles as $doc) {
                return $doc;
            }
        }

        return null;
    }

    /**
     * Removes a FileReference
     *
     * @param \TYPO3\CMS\Extbase\Domain\Model\FileReference $imageToRemove The FileReference to be removed
     * @return void
     */
    public function removeFalMedia(\TYPO3\CMS\Extbase\Domain\Model\FileReference $imageToRemove)
    {
        $this->falMedia->detach($imageToRemove);
    }
}
