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

use DateTime;
use Mediadreams\MdNewsfrontend\Domain\Model\News;
use Mediadreams\MdNewsfrontend\Event\CreateActionAfterPersistEvent;
use Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent;
use Mediadreams\MdNewsfrontend\Event\DeleteActionBeforeDeleteEvent;
use Mediadreams\MdNewsfrontend\Event\UpdateActionBeforeSaveEvent;
use Mediadreams\MdNewsfrontend\Service\NewsSlugHelper;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class NewsController
 * @package Mediadreams\MdNewsfrontend\Controller
 */
class NewsController extends BaseController
{
    /**
     * persistenceManager
     *
     * @var PersistenceManager
     */
    protected $persistenceManager = null;

    /**
     * @param PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        if ((int)$this->feuserUid > 0) {
            $news = $this->newsRepository->findByFeuserId($this->feuserUid, (int)$this->settings['allowNotEnabledNews']);

            $this->assignPagination(
                $news,
                (int)$this->settings['paginate']['itemsPerPage'],
                (int)$this->settings['paginate']['maximumNumberOfLinks']
            );
        }
    }

    /**
     * action new
     *
     * @return void
     */
    public function newAction()
    {
        $this->view->assignMultiple(
            [
                'user' => $this->feuserObj,
                'showinpreviewOptions' => $this->getValuesForShowinpreview()
            ]
        );
    }

    /**
     * Initialize create action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeCreateAction()
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
     * @return void
     */
    public function createAction(News $newNews)
    {
        $arguments = $this->request->getArgument('newNews');

        // if no value is provided for field datetime, use current date
        if (!isset($arguments['datetime']) || empty($arguments['datetime'])) {
            $newNews->setDatetime(new DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        }

        $newNews->setTxMdNewsfrontendFeuser($this->feuserObj);

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

        $requestArguments = $this->request->getArguments();

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $newNews);

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new CreateActionAfterPersistEvent($newNews, $this));

        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.new_success', 'md_newsfrontend'),
            '',
            AbstractMessage::OK
        );

        $this->redirect('list');
    }

    /**
     * initializeEditAction
     *
     * This is needed in order to get disabled news as well!
     */
    public function initializeEditAction(): void
    {
        $this->setEnableFieldsTypeConverter('news');
    }

    /**
     * action edit
     *
     * @param News $news
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("news")
     * @return void
     */
    public function editAction(News $news)
    {
        $this->checkAccess($news);

        $this->view->assignMultiple(
            [
                'news' => $news,
                'showinpreviewOptions' => $this->getValuesForShowinpreview()
            ]
        );
    }

    /**
     * Initialize update action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeUpdateAction()
    {
        $this->setEnableFieldsTypeConverter('news');

        $this->initializeCreateUpdate(
            $this->request->getArguments(),
            $this->arguments['news']
        );
    }

    /**
     * action update
     *
     * @param News $news
     * @return void
     */
    public function updateAction(News $news)
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
        $this->initializeFileUpload($requestArguments, $news);

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new UpdateActionBeforeSaveEvent($news, $this));

        $this->newsRepository->update($news);
        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.edit_success', 'md_newsfrontend'),
            '',
            AbstractMessage::OK
        );

        $this->redirect('list');
    }

    public function initializeDeleteAction()
    {
        $this->setEnableFieldsTypeConverter('news');
    }

    /**
     * action delete
     *
     * @param News $news
     * @return void
     */
    public function deleteAction(News $news)
    {
        $this->checkAccess($news);

        // PSR-14 Event
        $this->eventDispatcher->dispatch(new DeleteActionBeforeDeleteEvent($news, $this));

        $this->newsRepository->remove($news);

        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.delete_success', 'md_newsfrontend'),
            '',
            AbstractMessage::OK
        );

        $this->redirect('list');
    }
}
