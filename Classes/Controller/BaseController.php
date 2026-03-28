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
use Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent;
use Mediadreams\MdNewsfrontend\Domain\Repository\FrontendUserRepository;
use Mediadreams\MdNewsfrontend\Domain\Repository\NewsRepository;
use Mediadreams\MdNewsfrontend\Property\TypeConverter\EnableFieldsObjectConverter;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
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

/**
 * Class BaseController
 * @package Mediadreams\MdNewsfrontend\Controller
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
     * @param \TYPO3Fluid\Fluid\View\ViewInterface $view The view to be initialized
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
        // Upload fields are handled manually via processUploadedFile(); skip them in PropertyMapper.
        $argument->getPropertyMappingConfiguration()->skipProperties(...$this->uploadFields);

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
     * Process an uploaded file for a given field:
     * validates extension/size, moves the file into the FAL upload folder,
     * creates the sys_file_reference record and updates the count on the news record.
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

        // Require configured allowed extensions — reject upload if not configured
        $allowedExtensions = GeneralUtility::trimExplode(',', $this->settings['allowed_' . $fieldName] ?? '', true);
        if (empty($allowedExtensions)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.file_upload_not_configured', 'md_newsfrontend') ?? 'File upload is not configured.',
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return 0;
        }

        // Sanitize client filename: strip path components, keep only safe characters
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', basename((string)$uploadedFile->getClientFilename()));
        if ($safeFilename === '' || $safeFilename === '.') {
            $safeFilename = 'upload_' . time();
        }

        // Validate file extension from sanitized filename
        $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.file_extension_not_allowed', 'md_newsfrontend') ?? 'File extension not allowed.',
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return 0;
        }

        // Validate file size
        $maxSizeKb = (int)($this->settings['allowed_' . $fieldName . '_size'] ?? 0);
        if ($maxSizeKb > 0 && $uploadedFile->getSize() > $maxSizeKb * 1024) {
            $this->addFlashMessage(
                LocalizationUtility::translate('controller.file_too_large', 'md_newsfrontend') ?? 'File is too large.',
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return 0;
        }

        // Move to temp file, so we can inspect the actual content
        $tmpFile = GeneralUtility::tempnam('tx_mdnewsfrontend_');
        $uploadedFile->moveTo($tmpFile);

        // Validate actual MIME type from file content — prevents disguised file uploads
        $allowedMimeTypes = $this->getAllowedMimeTypesForExtension($ext);
        if (!empty($allowedMimeTypes)) {
            $actualMimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpFile);
            if (!in_array($actualMimeType, $allowedMimeTypes, true)) {
                unlink($tmpFile);
                $this->addFlashMessage(
                    LocalizationUtility::translate('controller.file_mime_type_not_allowed', 'md_newsfrontend') ?? 'File content does not match the file extension.',
                    '',
                    ContextualFeedbackSeverity::ERROR
                );
                return 0;
            }
        }

        // Resolve FAL upload folder, create it if necessary
        $uploadPath = rtrim($this->settings['uploadPath'], '/') . '/' . $this->feUser['uid'] . '/';
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        try {
            $folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($uploadPath);
        } catch (FolderDoesNotExistException $e) {
            [$storageUid, $folderPath] = explode(':', $uploadPath, 2);
            $storage = $resourceFactory->getStorageObject((int)$storageUid);
            $folder = $storage->createFolder(ltrim($folderPath, '/'));
        }

        $file = $folder->addFile(
            $tmpFile,
            $safeFilename,
            DuplicationBehavior::RENAME
        );

        $falFieldName = $this->uploadFieldMapping[$fieldName];

        // Create sys_file_reference record
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file_reference');
        $connection->insert('sys_file_reference', [
            'tstamp' => time(),
            'crdate' => time(),
            'uid_local' => $file->getUid(),
            'uid_foreign' => $newsUid,
            'tablenames' => 'tx_news_domain_model_news',
            'fieldname' => $falFieldName,
            'pid' => $newsPid,
            'sorting_foreign' => 1,
            'l10n_diffsource' => '',
        ]);
        $fileReferenceUid = (int)$connection->lastInsertId();

        // Increment the file-count column on the news record
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_news_domain_model_news');
        $queryBuilder->getRestrictions()->removeAll();
        $currentCount = (int)$queryBuilder
            ->select($falFieldName)
            ->from('tx_news_domain_model_news')
            ->where($queryBuilder->expr()->eq('uid', $newsUid))
            ->executeQuery()
            ->fetchOne();

        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_news_domain_model_news')
            ->update('tx_news_domain_model_news', [$falFieldName => $currentCount + 1], ['uid' => $newsUid]);

        return $fileReferenceUid;
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
     * Returns the expected MIME types for a given file extension.
     * Used to verify that the actual file content matches the claimed extension.
     * Extensions not listed here skip the MIME check (unknown/uncommon types).
     */
    protected function getAllowedMimeTypesForExtension(string $extension): array
    {
        $map = [
            'gif'  => ['image/gif'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/csv', 'text/plain'],
            'mp3'  => ['audio/mpeg'],
            'mp4'  => ['video/mp4'],
            // Office Open XML formats are ZIP containers — finfo returns application/zip
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
        ];

        $mimeTypes = $map[$extension] ?? [];

        $event = $this->eventDispatcher->dispatch(new ModifyAllowedMimeTypesEvent($extension, $mimeTypes));

        return $event->getMimeTypes();
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
