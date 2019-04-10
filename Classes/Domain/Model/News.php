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
    protected $txMdNewsfrontendFeuser = null;

    /**
     * Returns the txMdNewsfrontendFeuser
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $txMdNewsfrontendFeuser
     */
    public function getTxMdNewsfrontendFeuser()
    {
        return $this->txMdNewsfrontendFeuser;
    }

    /**
     * Sets the txMdNewsfrontendFeuser
     *
     * @param \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $txMdNewsfrontendFeuser
     * @return void
     */
    public function setTxMdNewsfrontendFeuser(\TYPO3\CMS\Extbase\Domain\Model\FrontendUser $txMdNewsfrontendFeuser)
    {
        $this->txMdNewsfrontendFeuser = $txMdNewsfrontendFeuser;
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
     * Removes a FileReference from falMedia
     *
     * TODO:
     * Somehow this does not set the entry in sys_file_reference to deleted but removes 
     * uid_foreign, tablenames and fieldname
     * Is this the intended behaviour???
     *
     * @param \TYPO3\CMS\Extbase\Domain\Model\FileReference $imageToRemove The FileReference to be removed
     * @return void
     */
    public function removeFalMedia(\TYPO3\CMS\Extbase\Domain\Model\FileReference $imageToRemove)
    {
        $this->falMedia->detach($imageToRemove);
    }

    /**
     * Removes a FileReference from falRelatedFiles
     *
     * TODO:
     * Somehow this does not set the entry in sys_file_reference to deleted but removes 
     * uid_foreign, tablenames and fieldname
     * Is this the intended behaviour???
     *
     * @param \TYPO3\CMS\Extbase\Domain\Model\FileReference $fileToRemove The FileReference to be removed
     * @return void
     */
    public function removeFalRelatedFiles(\TYPO3\CMS\Extbase\Domain\Model\FileReference $fileToRemove)
    {
        $this->falRelatedFiles->detach($fileToRemove);
    }
}
