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

use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Event\CreateActionAfterPersistEvent;
use Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent;
use Mediadreams\MdNewsfrontend\Event\DeleteActionBeforeDeleteEvent;
use Mediadreams\MdNewsfrontend\Event\UpdateActionBeforeSaveEvent;
use Mediadreams\MdNewsfrontend\Service\NewsSlugHelper;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class NewsController
 * @package Mediadreams\MdNewsfrontend\Controller
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
        if (isset($this->feuser['uid'])) {
            $news = $this->newsRepository->findByFeuserId($this->feuser['uid'], (int)$this->settings['allowNotEnabledNews']);

            $this->assignPagination(
                $news,
                (int)$this->settings['paginate']['itemsPerPage'],
                (int)$this->settings['paginate']['maximumNumberOfLinks']
            );
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
        $this->view->assignMultiple(
            [
                'user' => $this->feuser,
                'showinpreviewOptions' => $this->getValuesForShowinpreview()
            ]
        );

        return $this->htmlResponse();
    }

    /**
     * Initialize create action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeCreateAction(): void
    {
        $this->initializeCreateUpdate(
            $this->request->getArguments(),
            $this->arguments['newNews']
        );
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
        if (!isset($arguments['datetime']) || empty($arguments['datetime'])) {
            $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        }

        $feuserObj = $this->userRepository->findByUid($this->feuser['uid']);

        $newNews->setTxMdNewsfrontendFeuser($feuserObj);

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

        // handle the fileupload
        $this->initializeFileUpload($newNews);

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new CreateActionAfterPersistEvent($newNews, $this));

        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.new_success', 'md_newsfrontend'),
            '',
            ContextualFeedbackSeverity::OK
        );

        $uri = $this->uriBuilder->uriFor('list');
        return $this->responseFactory->createResponse(307)
            ->withHeader('Location', $uri);
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
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("news")
     * @return ResponseInterface
     */
    public function editAction(News $news): ResponseInterface
    {
        $this->checkAccess($news);

        $this->view->assignMultiple(
            [
                'news' => $news,
                'showinpreviewOptions' => $this->getValuesForShowinpreview()
            ]
        );

        return $this->htmlResponse();
    }

    /**
     * Initialize update action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeUpdateAction(): void
    {
        $this->setEnableFieldsTypeConverter();

        $this->initializeCreateUpdate(
            $this->request->getArguments(),
            $this->arguments['news']
        );
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
        if ($news->getArchive() == null) {
            $news->setArchive(0);
        }

        $requestArguments = $this->request->getArguments();

        // Remove file relation from news record
        foreach ($this->uploadFields as $fieldName) {
            if (isset($requestArguments[$fieldName]['delete']) && $requestArguments[$fieldName]['delete'] == 1) {
                $removeMethod = 'remove' . ucfirst($fieldName);
                $getFirstMethod = 'getFirst' . ucfirst($fieldName);

                $news->$removeMethod($news->$getFirstMethod());
            }
        }

        // handle the fileupload
        $this->initializeFileUpload($news);

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new UpdateActionBeforeSaveEvent($news, $this));

        $this->newsRepository->update($news);
        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.edit_success', 'md_newsfrontend'),
            '',
            ContextualFeedbackSeverity::OK
        );

        $uri = $this->uriBuilder->uriFor('list');
        return $this->responseFactory->createResponse(307)
            ->withHeader('Location', $uri);
    }

    public function initializeDeleteAction():void
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

        $uri = $this->uriBuilder->uriFor('list');
        return $this->responseFactory->createResponse(307)
            ->withHeader('Location', $uri);
    }
}
