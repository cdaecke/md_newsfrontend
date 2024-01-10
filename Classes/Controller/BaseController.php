<?php

declare(strict_types=1);

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

use GeorgRinger\News\Domain\Repository\CategoryRepository;
use GeorgRinger\NumberedPagination\NumberedPagination;
use Mediadreams\MdNewsfrontend\Domain\Model\FrontendUser;
use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Domain\Repository\FrontendUserRepository;
use Mediadreams\MdNewsfrontend\Domain\Repository\NewsRepository;
use Mediadreams\MdNewsfrontend\Property\TypeConverter\EnableFieldsObjectConverter;
use Mediadreams\MdNewsfrontend\Utility\FileUpload;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class BaseController
 * @package Mediadreams\MdNewsfrontend\Controller
 */
class BaseController extends ActionController
{
    /**
     * @var array
     */
    protected $uploadFields = ['falMedia', 'falRelatedFiles'];

    /**
     * categoryRepository
     *
     * @var CategoryRepository
     */
    protected $categoryRepository = null;

    /**
     * newsRepository
     *
     * @var NewsRepository
     */
    protected $newsRepository = null;

    /**
     * userRepository
     *
     * @var FrontendUserRepository
     */
    protected $userRepository = null;

    /**
     * @var int
     */
    protected $feuserUid = 0;

    /**
     * @var FrontendUser
     */
    protected $feuserObj = null;

    /**
     * NewsController constructor.
     * @param CategoryRepository $categoryRepository
     * @param NewsRepository $newsRepository
     * @param FrontendUserRepository $userRepository
     */
    public function __construct(
        CategoryRepository $categoryRepository,
        NewsRepository $newsRepository,
        FrontendUserRepository $userRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->newsRepository = $newsRepository;
        $this->userRepository = $userRepository;
    }

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
     * @param $view The view to be initialized
     */
    protected function initializeView($view)
    {
        // check if user is logged in
        if (!$GLOBALS['TSFE']->fe_user->user) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.not_loggedin', 'md_newsfrontend'),
                '',
                AbstractMessage::ERROR
            );
        } else {
            if (!isset($this->settings['uploadPath'])) { // check if TypoScript is loaded
                $this->addFlashMessage(
                    LocalizationUtility::translate('controller.typoscript_missing', 'md_newsfrontend'),
                    '',
                    AbstractMessage::ERROR
                );
            }
        }

        if (!empty($this->settings['parentCategory']) > 0) {
            $categories = $this->categoryRepository->findByParent($this->settings['parentCategory']);

            // Assign categories to template
            $view->assign('categories', $categories);
        }
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
     * @param News $newsRecord
     * @return void
     */
    protected function checkAccess(News $newsRecord)
    {
        if ($newsRecord->getTxMdNewsfrontendFeuser()->getUid() != $this->feuserUid) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.access_error', 'md_newsfrontend'),
                '',
                AbstractMessage::ERROR
            );

            $this->redirect('list');
        }
    }

    /**
     * This will initialize everything which is needed in create or update action
     *
     * @param array $requestArguments
     * @param Argument $argument
     * @return void
     */
    protected function initializeCreateUpdate($requestArguments, $argument)
    {
        // add validator for upload fields
        $this->initializeFileValidator($requestArguments, $argument);

        if (!empty($requestArguments[$argument->getName()]['datetime'])) {
            // use correct format for datetime
            $argument
                ->getPropertyMappingConfiguration()
                ->forProperty('datetime')
                ->setTypeConverterOption(
                    'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\DateTimeConverter',
                    DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                    $this->settings['formatDatetime']
                );
        }

        // use correct format for archive date
        $argument
            ->getPropertyMappingConfiguration()
            ->forProperty('archive')
            ->setTypeConverterOption(
                'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\DateTimeConverter',
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['formatArchive']
            );

        // use correct format for starttime
        $argument
            ->getPropertyMappingConfiguration()
            ->forProperty('starttime')
            ->setTypeConverterOption(
                'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\DateTimeConverter',
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['formatDatetime']
            );

        // use correct format for endtime
        $argument
            ->getPropertyMappingConfiguration()
            ->forProperty('endtime')
            ->setTypeConverterOption(
                'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\DateTimeConverter',
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['formatDatetime']
            );
    }

    /**
     * Initialize the upload validators for configured fields
     *
     * @param array $requestArguments
     * @param Argument $argument
     * @return void
     */
    protected function initializeFileValidator($requestArguments, $argument)
    {
        foreach ($this->uploadFields as $fieldName) {
            $this->addFileuploadValidator(
                $argument,
                $requestArguments[$fieldName],
                $this->settings['allowed_' . $fieldName]
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

        $checkFileUploadValidator = GeneralUtility::makeInstance(
            \Mediadreams\MdNewsfrontend\Domain\Validator\CheckFileUpload::class,
            [
                'filesArr' => $fieldName,
                'allowedFileExtensions' => $allowedFileExtensions,
            ]
        );

        $validator->addValidator($checkFileUploadValidator);
    }

    /**
     * Set type converter for enable fields
     * This is needed, in order to edit/show/delete hidden records
     *
     * @param string $object
     * @throws NoSuchArgumentException
     */
    protected function setEnableFieldsTypeConverter(string $object): void
    {
        if ((int)$this->settings['allowNotEnabledNews'] === 1) {
            $this->arguments->getArgument('news')
                ->getPropertyMappingConfiguration()
                ->setTypeConverter(GeneralUtility::makeInstance(EnableFieldsObjectConverter::class));
        }
    }

    /**
     * Initialize the file upload for configured fields
     *
     * @param News $obj
     * @return void
     */
    protected function initializeFileUpload($obj)
    {
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() >= 12) {
            $files = $this->request->getUploadedFiles();
        } else {
            $files = [];
            if (isset($this->request->getUploadedFiles()['tx_mdnewsfrontend_newsfe'])) {
                $files = $this->request->getUploadedFiles()['tx_mdnewsfrontend_newsfe'];
            }
        }

        foreach ($this->uploadFields as $fieldName) {
            if (isset($files[$fieldName])) {
                // upload new file and update file reference (meta data)
                FileUpload::handleUpload(
                    $files,
                    $obj,
                    $fieldName,
                    $this->settings,
                    $this->feuserUid,
                    $this->request->getArguments()
                );
            } else {
                $methodName = 'getFirst' . ucfirst($fieldName);

                if ($obj->$methodName()) {
                    // update meta data
                    $this->updateFileReference(
                        $obj->$methodName()->getUid(),
                        $this->request->getArguments()[$fieldName]
                    );
                }
            }
        }
    }

    /**
     * Update meta data of file references
     *
     * @param int $fileReferencesUid uid of sys_file_reference record
     * @param array $fileData All data about the file
     * @return void
     */
    protected function updateFileReference($fileReferencesUid, $fileData)
    {
        $showinpreview = !isset($fileData['showinpreview']) ? 0 : $fileData['showinpreview'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');

        $queryBuilder
            ->update('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid', $fileReferencesUid)
            )
            ->set('tstamp', time())
            ->set('title', $fileData['title'])
            ->set('description', $fileData['description'])
            ->set('showinpreview', (int)$showinpreview)
            ->execute();
    }

    /**
     * This gets the values for the "Show in preview" select box in the template
     *
     * @return array
     */

    protected function getValuesForShowinpreview()
    {
        return [
            0 => LocalizationUtility::translate('image_showinpreview.0', 'md_newsfrontend'),
            1 => LocalizationUtility::translate('image_showinpreview.1', 'md_newsfrontend'),
            2 => LocalizationUtility::translate('image_showinpreview.2', 'md_newsfrontend')
        ];
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

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        foreach ($cacheTagsToFlush as $cacheTag) {
            $cacheManager->flushCachesInGroupByTag('pages', $cacheTag);
        }
    }

    /**
     * Assign pagination to current view object
     *
     * @param $items
     * @param int $itemsPerPage
     * @param int $maximumNumberOfLinks
     * @throws NoSuchArgumentException
     */
    protected function assignPagination($items, $itemsPerPage = 10, $maximumNumberOfLinks = 5)
    {
        $currentPage = $this->request->hasArgument('currentPage') ? (int)$this->request->getArgument('currentPage') : 1;

        $paginator = new QueryResultPaginator(
            $items,
            $currentPage,
            $itemsPerPage
        );

        $pagination = new NumberedPagination(
            $paginator,
            $maximumNumberOfLinks
        );

        $this->view->assign('pagination', [
            'paginator' => $paginator,
            'pagination' => $pagination,
        ]);
    }
}
