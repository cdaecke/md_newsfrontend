<?php
namespace Mediadreams\MdNewsfrontend\Controller;

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

use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Base controllerUnreadnewsController
 */
class BaseController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var array
     */
    protected $uploadFields = ['falMedia', 'falRelatedFiles'];

    /**
     * newsRepository
     *
     * @var \Mediadreams\MdNewsfrontend\Domain\Repository\NewsRepository
     * @inject
     */
    protected $newsRepository = null;

    /**
     * userRepository
     *
     * @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository
     * @inject
     */
    protected $userRepository = null;

    /**
     * Deactivate errorFlashMessage
     *
     * @return bool|string
     */
    public function getErrorFlashMessage()
    {
        return false;
    }

    /**
     * Initializes the view and pass additional data to template
     *
     * @param \TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view The view to be initialized
     */
    protected function initializeView(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view)
    {
        if ( strlen($this->settings['parentCategory']) > 0 ) {
            $categoryRepository = $this->objectManager->get(\TYPO3\CMS\Extbase\Domain\Repository\CategoryRepository::class);
            $categories = $categoryRepository->findByParent($this->settings['parentCategory']);

            // Assign categories to template
            $view->assign('categories', $categories);
        }

        parent::initializeView($view);
    }

    /**
     * Initialize actions
     * Add possibility to overwrite settings
     */
    protected function initializeAction()
    {
        // Use stdWrap for given defined settings
        // Thanks to Georg Ringer: https://github.com/georgringer/news/blob/2c8522ad508fa92ad39a5effe4301f7d872238a5/Classes/Controller/NewsController.php#L597
        if (
            isset($this->settings['useStdWrap']) 
            && !empty($this->settings['useStdWrap'])
        ) {
            $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
            $typoScriptArray = $typoScriptService->convertPlainArrayToTypoScriptArray($this->settings);
            $stdWrapProperties = GeneralUtility::trimExplode(',', $this->settings['useStdWrap'], true);
            foreach ($stdWrapProperties as $key) {
                if (is_array($typoScriptArray[$key . '.'])) {
                    $this->settings[$key] = $this->configurationManager->getContentObject()->stdWrap(
                        $typoScriptArray[$key],
                        $typoScriptArray[$key . '.']
                    );
                }
            }
        }

        // Get logged in user
        if ($GLOBALS['TSFE']->fe_user->user) {
            $this->feuserUid = $GLOBALS['TSFE']->fe_user->user['uid'];
            $this->feuserObj = $this->userRepository->findByUid($this->feuserUid);
        }

        parent::initializeAction();
    }

    /**
     * Check, if news record belongs to user
     * If news record does not belong to user, redirect to list action
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $newsRecord
     * @return void
     */
    protected function checkAccess(\Mediadreams\MdNewsfrontend\Domain\Model\News $newsRecord)
    {
        if ($newsRecord->getMdNewsfrontendFeuser()->getUid() != $this->feuserUid) {
            $this->addFlashMessage('Sie sind nicht berechtigt, diese Nachricht zu bearbeiten!', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
            $this->redirect('list');
        }
    }

    /**
     * Get storage pid for news record
     * If a pid was set via form value use this, otherwise use value from typoscript settings
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $newsRecord
     * @return int
     */
    protected function getStoragePid(\Mediadreams\MdNewsfrontend\Domain\Model\News $newsRecord)
    {
        if ($newsRecord->getPid() > 0) {
            return $newsRecord->getPid();
        } else {
            return (int)$this->settings['storagePid'];
        }
    }

    /**
     * Initialize the file upload for configured fields
     *
     * @param array $requestArguments
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $obj
     * @return void
     */
    protected function initializeFileUpload($requestArguments, $obj)
    {
        foreach ($this->uploadFields as $fieldName) {
            if ( !empty($requestArguments[$fieldName]) ) {
                \Mediadreams\MdNewsfrontend\Utility\FileUpload::handleUpload(
                    $requestArguments[$fieldName], 
                    $obj, 
                    $fieldName, 
                    $this->settings,
                    $this->feuserUid
                );
            }
        }
    }

    /**
     * Initialize the upload validators for configured fields
     *
     * @param array $requestArguments
     * @param \TYPO3\CMS\Extbase\Mvc\Controller\Argument $argument
     * @return void
     */
    protected function initializeFileValidator($requestArguments, $argument)
    {
        foreach ($this->uploadFields as $fieldName) {
            $this->addFileuploadValidator(
                $argument, 
                $requestArguments[$fieldName], 
                $this->settings['allowed_'.$fieldName]
            );
        }
    }

    /**
     * Add the file upload validator for given object and field
     *
     * @param News $news
     * @return void
     */
    protected function addFileuploadValidator($arguments, $fieldName, $allowedFileExtensions)
    {
        $validator = $arguments->getValidator();

        $checkFileUploadValidator = $this->objectManager->get(
            'Mediadreams\MdNewsfrontend\Domain\Validator\CheckFileUpload', 
            array(
                'filesArr'              => $fieldName,
                'allowedFileExtensions' => $allowedFileExtensions,
            )
        );

        $validator->addValidator($checkFileUploadValidator);
    }

    /**
     * Flush cache
     * See: GeorgRinger\News\Hooks\DataHandler::clearCachePostProc()
     *
     * @param int $newsUid Uid of news record
     * @param int $newsPid Pid of news record
     */
    protected function clearNewsCache($newsUid, $newsPid)
    {
        $cacheTagsToFlush = [];

        if ($newsUid) {
            $cacheTagsToFlush[] = 'tx_news_uid_' . $newsUid;
        }

        if ($newsPid) {
            $cacheTagsToFlush[] = 'tx_news_pid_' . $newsPid;
        }

        $cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
        foreach ($cacheTagsToFlush as $cacheTag) {
            $cacheManager->flushCachesInGroupByTag('pages', $cacheTag);
        }
    }
}
