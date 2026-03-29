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

use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Event\CreateActionAfterPersistEvent;
use Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent;
use Mediadreams\MdNewsfrontend\Event\DeleteActionBeforeDeleteEvent;
use Mediadreams\MdNewsfrontend\Event\UpdateActionBeforeSaveEvent;
use Mediadreams\MdNewsfrontend\Service\NewsSlugHelper;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class NewsController
 */
class NewsController extends BaseController
{
    /**
     * action list
     *
     * @return ResponseInterface
     */
    public function listAction(): ResponseInterface
    {
        if (isset($this->feUser['uid'])) {
            $news = $this->newsRepository->findByFeuserId($this->feUser['uid'], (int)$this->settings['allowNotEnabledNews']);

            $this->view->assignMultiple($this->getPaginatedItems($news));
        }

        return $this->htmlResponse();
    }

    /**
     * action new
     *
     * @return ResponseInterface
     */
    public function newAction(): ResponseInterface
    {
        $this->addFrontendAssets();

        $this->view->assignMultiple(
            [
                'user' => $this->feUser,
                'showinpreviewOptions' => $this->getValuesForShowinpreview(),
            ]
        );

        return $this->htmlResponse();
    }

    /**
     * Initialize create action
     * Add custom validator for file upload
     */
    public function initializeCreateAction(): void
    {
        $this->initializeCreateUpdate($this->arguments['newNews']);
    }

    /**
     * action create
     *
     * @param News $newNews
     * @return ResponseInterface
     */
    public function createAction(News $newNews): ResponseInterface
    {
        $arguments = $this->request->getArgument('newNews');

        // if no value is provided for field datetime, use current date
        if (!is_array($arguments) || ($arguments['datetime'] ?? '') === '') {
            $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        }

        $feUserObj = $this->userRepository->findByUid($this->feUser['uid']);

        if ($feUserObj !== null) {
            $newNews->setTxMdNewsfrontendFeuser($feUserObj);
        }

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new CreateActionBeforeSaveEvent($newNews, $this));

        $this->newsRepository->add($newNews);

        // persist news entry in order to get the uid of the entry
        $this->persistenceManager->persistAll();

        // generate and set slug for news record
        $slugHelper = GeneralUtility::makeInstance(NewsSlugHelper::class);
        $slug = $slugHelper->getSlug($newNews);
        $newNews->setPathSegment($slug);
        $this->newsRepository->update($newNews);

        // process file uploads and update file reference metadata
        foreach ($this->uploadFields as $fieldName) {
            $fileReferenceUid = $this->processUploadedFile($fieldName, $newNews->getUid(), $newNews->getPid());
            $fileData = $this->request->getArguments()[$fieldName] ?? [];
            if ($fileReferenceUid > 0 && is_array($fileData)) {
                $this->updateFileReference($fileReferenceUid, $fileData);
            }
        }

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new CreateActionAfterPersistEvent($newNews, $this));

        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.new_success', 'md_newsfrontend'),
            '',
            ContextualFeedbackSeverity::OK
        );

        return $this->redirect('list');
    }

    /**
     * initializeEditAction
     *
     * This is needed in order to get disabled news as well!
     */
    public function initializeEditAction(): void
    {
        $this->setEnableFieldsTypeConverter();
    }

    /**
     * action edit
     *
     * @param News $news
     * @return ResponseInterface
     */
    #[IgnoreValidation(['argumentName' => 'news'])]
    public function editAction(News $news): ResponseInterface
    {
        $this->checkAccess($news);
        $this->addFrontendAssets();

        $this->view->assignMultiple(
            [
                'news' => $news,
                'showinpreviewOptions' => $this->getValuesForShowinpreview(),
            ]
        );

        return $this->htmlResponse();
    }

    /**
     * Initialize update action
     * Add custom validator for file upload
     */
    public function initializeUpdateAction(): void
    {
        $this->setEnableFieldsTypeConverter();

        $this->initializeCreateUpdate($this->arguments['news']);
    }

    /**
     * action update
     *
     * @param News $news
     * @return ResponseInterface
     */
    public function updateAction(News $news): ResponseInterface
    {
        $this->checkAccess($news);

        // If archive date was deleted
        if (!$news->getArchive() instanceof \DateTime) {
            $news->setArchive(0); // @phpstan-ignore argument.type
        }

        // Detach existing file references when the user checks "delete" or uploads a replacement.
        // The physical FAL file is deleted first; Extbase handles sys_file_reference deletion during persistAll().
        foreach ($this->uploadFields as $fieldName) {
            $shouldDelete = ($this->request->getArguments()[$fieldName . 'Delete'] ?? '') === '1';
            $uploadedFile = $this->request->getUploadedFiles()[$fieldName] ?? null;
            $hasNewUpload = $uploadedFile instanceof UploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK;

            if ($shouldDelete || $hasNewUpload) {
                $existingRef = $news->{'getFirst' . ucfirst((string)$fieldName)}(); // @phpstan-ignore method.dynamicName
                if ($existingRef !== null) {
                    try {
                        $falFile = $existingRef->getOriginalResource()->getOriginalFile();
                        $falFile->getStorage()->deleteFile($falFile);
                    } catch (\Exception) {
                        // File already gone or storage inaccessible — continue
                    }

                    $news->{'get' . ucfirst((string)$fieldName)}()->detach($existingRef); // @phpstan-ignore method.dynamicName
                }
            }
        }

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new UpdateActionBeforeSaveEvent($news, $this));

        $this->newsRepository->update($news);
        $this->persistenceManager->persistAll();

        // Process new file uploads and update file reference metadata
        foreach ($this->uploadFields as $fieldName) {
            $fileData = $this->request->getArguments()[$fieldName] ?? [];
            $newFileRefUid = $this->processUploadedFile($fieldName, $news->getUid(), $news->getPid());
            if ($newFileRefUid > 0) {
                // New file uploaded: update its metadata
                if (is_array($fileData)) {
                    $this->updateFileReference($newFileRefUid, $fileData);
                }
            } else {
                // No new upload: update metadata of the still-existing file reference (if any)
                $fileReference = $news->{'getFirst' . ucfirst((string)$fieldName)}(); // @phpstan-ignore method.dynamicName
                if ($fileReference !== null && $fileReference->getUid() > 0 && is_array($fileData)) {
                    $this->updateFileReference($fileReference->getUid(), $fileData);
                }
            }
        }

        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.edit_success', 'md_newsfrontend'),
            '',
            ContextualFeedbackSeverity::OK
        );

        return $this->redirect('list');
    }

    public function initializeDeleteAction(): void
    {
        $this->setEnableFieldsTypeConverter();
    }

    /**
     * action delete
     *
     * @param News $news
     * @return ResponseInterface
     */
    public function deleteAction(News $news): ResponseInterface
    {
        $this->checkAccess($news);

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new DeleteActionBeforeDeleteEvent($news, $this));

        $this->newsRepository->remove($news);

        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.delete_success', 'md_newsfrontend'),
            '',
            ContextualFeedbackSeverity::OK
        );

        return $this->redirect('list');
    }
}
