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
use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Domain\Repository\FrontendUserRepository;
use Mediadreams\MdNewsfrontend\Domain\Repository\NewsRepository;
use Mediadreams\MdNewsfrontend\Property\TypeConverter\EnableFieldsObjectConverter;
use Mediadreams\MdNewsfrontend\Utility\FileUpload;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Fluid\View\TemplateView;

/**
 * Class BaseController
 * @package Mediadreams\MdNewsfrontend\Controller
 */
class BaseController extends ActionController
{
    protected array $uploadFields = ['falMedia', 'falRelatedFiles'];
    protected array $feUser = [];

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
     * TODO: Remove type declaration `TemplateView` as soon as TYPO3 v12 is not supported anymore!
     *
     * @param TemplateView|FluidViewAdapter $view The view to be initialized
     */
    protected function initializeView(TemplateView|FluidViewAdapter $view)
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
            $this->feUser = $this->request->getAttribute('frontend.user')->user;
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
    protected function checkAccess(News $newsRecord): void
    {
        if ($newsRecord->getTxMdNewsfrontendFeuser()->getUid() != $this->feUser['uid']) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.access_error', 'md_newsfrontend'),
                '',
                ContextualFeedbackSeverity::ERROR
            );

            $response = $this->redirect('list');

            throw new PropagateResponseException($response, 200);
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
                    (string)$this->feUser['uid'],
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
    protected function clearNewsCache(int $newsUid, int $newsPid): void
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
     * Get paginated items and paginator for query result
     *
     * @param QueryResult $items
     * @return array
     */
    protected function getPaginatedItems(QueryResult $items): array
    {
        $currentPage = $this->request->hasArgument('currentPageNumber')
            ? (int)$this->request->getArgument('currentPageNumber')
            : 1;

        $itemsPerPage = isset($this->settings['paginate']['itemsPerPage'])? (int)$this->settings['paginate']['itemsPerPage'] : 10;
        $maxNumPages = isset($this->settings['paginate']['maximumNumberOfLinks'])? (int)$this->settings['paginate']['maximumNumberOfLinks'] : 5;

        $paginator = new QueryResultPaginator(
            $items,
            $currentPage,
            $itemsPerPage,
        );
        $pagination = new SlidingWindowPagination(
            $paginator,
            $maxNumPages,
        );

        return [
            'pagination' => $pagination,
            'paginator' => $paginator,
            'currentPageNumber' => $paginator->getCurrentPageNumber(),
        ];
    }
}
