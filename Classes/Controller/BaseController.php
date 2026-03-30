<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Controller;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */
use GeorgRinger\News\Domain\Repository\CategoryRepository;
use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Domain\Repository\FrontendUserRepository;
use Mediadreams\MdNewsfrontend\Domain\Repository\NewsRepository;
use Mediadreams\MdNewsfrontend\Exception\FileUploadException;
use Mediadreams\MdNewsfrontend\Property\TypeConverter\EnableFieldsObjectConverter;
use Mediadreams\MdNewsfrontend\Service\FileUploadService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\UploadedFile;
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
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Class BaseController
 */
class BaseController extends ActionController
{
    protected array $uploadFields = ['falMedia', 'falRelatedFiles'];

    protected array $feUser = [];

    /**
     * Maps Extbase property names to FAL fieldnames in sys_file_reference / tx_news_domain_model_news.
     */
    protected array $uploadFieldMapping = [
        'falMedia' => 'fal_media',
        'falRelatedFiles' => 'fal_related_files',
    ];

    public function __construct(
        protected CategoryRepository $categoryRepository,
        protected NewsRepository $newsRepository,
        protected FrontendUserRepository $userRepository,
        protected PersistenceManager $persistenceManager,
        protected AssetCollector $assetCollector,
        protected FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Deactivate errorFlashMessage
     *
     * @return bool|string
     */
    protected function getErrorFlashMessage(): bool|string
    {
        return false;
    }

    /**
     * Initializes the view and pass additional data to template
     *
     * @param ViewInterface $view The view to be initialized
     */
    protected function initializeView(ViewInterface $view): void
    {
        // check if user is logged in
        if ($this->request->getAttribute('frontend.user')->user === null) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.not_loggedin', 'md_newsfrontend'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        } elseif (!isset($this->settings['uploadPath'])) {
            // check if TypoScript is loaded
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.typoscript_missing', 'md_newsfrontend'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }

        if ((int)($this->settings['parentCategory'] ?? 0) > 0) {
            $categories = $this->categoryRepository->findBy(['parent' => $this->settings['parentCategory']]);

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
            && $this->settings['useStdWrap'] !== ''
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
        if ($this->request->getAttribute('frontend.user')->user !== null) {
            $this->feUser = $this->request->getAttribute('frontend.user')->user;
        }

        parent::initializeAction();
    }

    /**
     * Check if the news record belongs to the logged-in frontend user.
     * Returns a redirect response if access is denied, null if access is granted.
     *
     * @param News $newsRecord
     * @return ResponseInterface|null
     */
    protected function checkAccess(News $newsRecord): ?ResponseInterface
    {
        $feuser = $newsRecord->getTxMdNewsfrontendFeuser();
        if ($feuser === null || $feuser->getUid() !== (int)($this->feUser['uid'] ?? 0)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.access_error', 'md_newsfrontend'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('list');
        }
        return null;
    }

    /**
     * This will initialize everything which is needed in create or update action
     *
     * @param Argument $argument
     */
    protected function initializeCreateUpdate(Argument $argument): void
    {
        // Upload fields are handled manually via processUploadedFile(); skip them in PropertyMapper.
        $argument->getPropertyMappingConfiguration()->skipProperties(...$this->uploadFields);

        if (($this->request->getArguments()[$argument->getName()]['datetime'] ?? '') !== '') {
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
     * Guards the upload request and delegates to {@see FileUploadService::processUploadedFile()}.
     * Returns 0 if no file was uploaded, the upload failed, or a validation error occurred
     * (in which case a flash message is added automatically).
     *
     * The form upload field must use name="<fieldName>" (top-level, not property-bound)
     * so the file is available at $request->getUploadedFiles()[$fieldName].
     *
     * @param string $fieldName  Extbase property name (e.g. 'falMedia')
     * @param int    $newsUid    UID of the persisted news record
     * @param int    $newsPid    PID of the persisted news record
     * @return int               UID of the created sys_file_reference record, 0 on no upload or error
     */
    protected function processUploadedFile(string $fieldName, int $newsUid, int $newsPid): int
    {
        $uploadedFile = $this->request->getUploadedFiles()[$fieldName] ?? null;
        if (!($uploadedFile instanceof UploadedFile) || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return 0;
        }

        try {
            return $this->fileUploadService->processUploadedFile(
                $uploadedFile,
                $fieldName,
                $this->uploadFieldMapping[$fieldName],
                $newsUid,
                $newsPid,
                (int)($this->feUser['uid'] ?? 0),
                $this->settings
            );
        } catch (FileUploadException $e) {
            $this->addFlashMessage(
                LocalizationUtility::translate($e->getTranslationKey(), 'md_newsfrontend') ?? $e->getMessage(),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return 0;
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
     * Update meta data of file references
     *
     * @param int $fileReferencesUid uid of sys_file_reference record
     * @param array $fileData All data about the file
     */
    protected function updateFileReference(int $fileReferencesUid, array $fileData): void
    {
        $showinpreview = $fileData['showinpreview'] ?? 0;

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
    protected function getValuesForShowinpreview(): array
    {
        return [
            0 => LocalizationUtility::translate('image_showinpreview.0', 'md_newsfrontend'),
            1 => LocalizationUtility::translate('image_showinpreview.1', 'md_newsfrontend'),
            2 => LocalizationUtility::translate('image_showinpreview.2', 'md_newsfrontend'),
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

        if ($newsUid !== 0) {
            $cacheTagsToFlush[] = 'tx_news_uid_' . $newsUid;
        }

        if ($newsPid !== 0) {
            $cacheTagsToFlush[] = 'tx_news_pid_' . $newsPid;
        }

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        foreach ($cacheTagsToFlush as $cacheTag) {
            $cacheManager->flushCachesInGroupByTag('pages', $cacheTag);
        }
    }

    /**
     * Add frontend assets (JS, CSS) to view
     */
    protected function addFrontendAssets(): void
    {
        if ($this->settings['jquery']) {
            $this->assetCollector->addJavaScript(
                'md_newsfrontend_jquery',
                'EXT:md_newsfrontend/Resources/Public/Js/jquery.slim.min.js'
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
                'EXT:md_newsfrontend/Resources/Public/Js/flatpickr.min.js'
            );
        }

        if ($this->settings['parsleyjs']) {
            $this->assetCollector->addJavaScript(
                'md_newsfrontend_parsley',
                'EXT:md_newsfrontend/Resources/Public/Js/Parsley/parsley.min.js'
            );

            if ($this->settings['parsleyjsLang'] !== 'en') {
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
     * @param QueryResult<News> $items
     * @return array
     */
    protected function getPaginatedItems(QueryResult $items): array
    {
        $currentPage = $this->request->hasArgument('currentPageNumber')
            ? (int)$this->request->getArgument('currentPageNumber')
            : 1;

        $itemsPerPage = isset($this->settings['paginate']['itemsPerPage']) ? (int)$this->settings['paginate']['itemsPerPage'] : 10;
        $maxNumPages = isset($this->settings['paginate']['maximumNumberOfLinks']) ? (int)$this->settings['paginate']['maximumNumberOfLinks'] : 5;

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
