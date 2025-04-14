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
use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Domain\Repository\FrontendUserRepository;
use Mediadreams\MdNewsfrontend\Domain\Repository\NewsRepository;
use Mediadreams\MdNewsfrontend\Property\TypeConverter\EnableFieldsObjectConverter;
use Mediadreams\MdNewsfrontend\Utility\FileUpload;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class BaseController
 * @package Mediadreams\MdNewsfrontend\Controller
 */
class BaseController extends ActionController
{
    protected array $uploadFields = ['falMedia', 'falRelatedFiles'];
    protected array $feuser = [];

    public function __construct(
        protected CategoryRepository $categoryRepository,
        protected NewsRepository $newsRepository,
        protected FrontendUserRepository $userRepository,
        protected PersistenceManager $persistenceManager,
        protected AssetCollector $assetCollector,
    ) {}

    /**
     * Deactivate errorFlashMessage
     *
     * @return bool|string
     */
    public function getErrorFlashMessage(): bool|string
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
        if (!$this->request->getAttribute('frontend.user')->user) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.not_loggedin', 'md_newsfrontend'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        } else {
            if (!isset($this->settings['uploadPath'])) { // check if TypoScript is loaded
                $this->addFlashMessage(
                    LocalizationUtility::translate('controller.typoscript_missing', 'md_newsfrontend'),
                    '',
                    ContextualFeedbackSeverity::ERROR
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
    protected function initializeAction(): void
    {
        // Use stdWrap for given defined settings
        // Thanks to Georg Ringer: https://github.com/georgringer/news/blob/976fe5930cea9693f6cd56b650abe4e876fc70f0/Classes/Controller/NewsController.php#L627
        if (
            isset($this->settings['useStdWrap'])
            && !empty($this->settings['useStdWrap'])
        ) {
            $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
            $typoScriptArray = $typoScriptService->convertPlainArrayToTypoScriptArray($this->settings);
            $stdWrapProperties = GeneralUtility::trimExplode(',', $this->settings['useStdWrap'], true);
            foreach ($stdWrapProperties as $key) {
                if (is_array($typoScriptArray[$key . '.'] ?? null)) {
                    $this->settings[$key] = $this->request->getAttribute('currentContentObject')->stdWrap(
                        $typoScriptArray[$key] ?? '',
                        $typoScriptArray[$key . '.']
                    );
                }
            }
        }

        // Get logged in user
        if ($this->request->getAttribute('frontend.user')->user) {
            $this->feuser = $this->request->getAttribute('frontend.user')->user;
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
        if ($newsRecord->getTxMdNewsfrontendFeuser()->getUid() != $this->feuser['uid']) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.access_error', 'md_newsfrontend'),
                '',
                ContextualFeedbackSeverity::ERROR
            );

            $this->redirect('list');
        }
    }

    /**
     * This will initialize everything which is needed in create or update action
     *
     * @param Argument $argument
     * @return void
     */
    protected function initializeCreateUpdate(Argument $argument)
    {
        // add validator for upload fields
        $this->initializeFileValidator($argument);

        if (!empty($this->request->getArguments()[$argument->getName()]['datetime'])) {
            // use correct format for datetime
            $argument
                ->getPropertyMappingConfiguration()
                ->forProperty('datetime')
                ->setTypeConverterOption(
                    DateTimeConverter::class,
                    DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                    $this->settings['formatDatetime']
                );
        }

        // use correct format for archive date
        $argument
            ->getPropertyMappingConfiguration()
            ->forProperty('archive')
            ->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['formatArchive']
            );

        // use correct format for starttime
        $argument
            ->getPropertyMappingConfiguration()
            ->forProperty('starttime')
            ->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['formatDatetime']
            );

        // use correct format for endtime
        $argument
            ->getPropertyMappingConfiguration()
            ->forProperty('endtime')
            ->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                $this->settings['formatDatetime']
            );
    }

    /**
     * Initialize the upload validators for configured fields
     *
     * @param Argument $argument
     * @return void
     */
    protected function initializeFileValidator(Argument $argument)
    {
        $validator = $argument->getValidator();

        foreach ($this->uploadFields as $fieldName) {
            if (isset($this->request->getUploadedFiles()[$fieldName])) {
                $checkFileUploadValidator = GeneralUtility::makeInstance(
                    \Mediadreams\MdNewsfrontend\Domain\Validator\CheckFileUpload::class,
                    [
                        'filesArr' => $this->request->getUploadedFiles()[$fieldName],
                        'allowedFileExtensions' => $this->settings['allowed_' . $fieldName],
                    ]
                );

                $validator->addValidator($checkFileUploadValidator);
            }
        }
    }

    /**
     * Set type converter for enable fields
     * This is needed, in order to edit/show/delete hidden records
     *
     * @throws NoSuchArgumentException
     */
    protected function setEnableFieldsTypeConverter(): void
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
    protected function initializeFileUpload(News $obj)
    {
        $files = $this->request->getUploadedFiles();

        foreach ($this->uploadFields as $fieldName) {
            if (isset($files[$fieldName])) {
                // upload new file and update file reference (meta data)
                FileUpload::handleUpload(
                    $files,
                    $obj,
                    $fieldName,
                    $this->settings,
                    (string)$this->feuser['uid'],
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
    protected function updateFileReference(int $fileReferencesUid, array $fileData)
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
            ->executeStatement();
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
     * Add frontend assets (JS, CSS) to view
     *
     * @return void
     */
    protected function addFrontendAssets(): void
    {
        if ($this->settings['jquery']) {
            $this->assetCollector->addJavaScript(
                'md_newsfrontend_jquery',
                'EXT:md_newsfrontend/Resources/Public/Js/jquery-3.7.1.slim.min.js'
            );
        }

        if ($this->settings['tinymce']) {
            $this->assetCollector->addJavaScript(
                'md_newsfrontend_tinymce',
                'EXT:md_newsfrontend/Resources/Public/Js/tinymce/tinymce.min.js'
            );
        }

        if ($this->settings['flatpickr']) {
            $this->assetCollector->addStyleSheet(
                'md_newsfrontend_flatpickrCss',
                'EXT:md_newsfrontend/Resources/Public/Css/flatpickr.min.css'
            );

            $this->assetCollector->addJavaScript(
                'md_newsfrontend_flatpickr',
                'EXT:md_newsfrontend/Resources/Public/Js/flatpickr.js'
            );
        }

        if ($this->settings['parsleyjs']) {
            $this->assetCollector->addJavaScript(
                'md_newsfrontend_parsley',
                'EXT:md_newsfrontend/Resources/Public/Js/Parsley/parsley.min.js'
            );

            if ($this->settings['parsleyjsLang'] != 'en') {
                $this->assetCollector->addJavaScript(
                    'md_newsfrontend_parsleyjsLang',
                    'EXT:md_newsfrontend/Resources/Public/Js/Parsley/i18n/' . $this->settings['parsleyjsLang'] . '.js'
                );
            }
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
