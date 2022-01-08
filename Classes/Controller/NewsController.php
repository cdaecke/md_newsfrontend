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
        $news = $this->newsRepository->findByTxMdNewsfrontendFeuser($this->feuserUid);

        $this->assignPagination(
            $news,
            $this->settings['paginate']['itemsPerPage'],
            $this->settings['paginate']['maximumNumberOfLinks']
        );
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
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $newNews
     * @return void
     */
    public function createAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $newNews)
    {
        $arguments = $this->request->getArgument('newNews');

        // if no value is provided for field datetime, use current date
        if (!isset($arguments['datetime']) || empty($arguments['datetime'])) {
            $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        }

        $newNews->setTxMdNewsfrontendFeuser($this->feuserObj);

        // add signal slot BeforeSave
        // @deprecated will be removed in TYPO3 v12.0. Use PSR-14 based events and EventDispatcherInterface instead.
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'BeforeSave',
            [$newNews, $this]
        );

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

        // add signal slot AfterPersist
        // @deprecated will be removed in TYPO3 v12.0. Use PSR-14 based events and EventDispatcherInterface instead.
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'AfterPersist',
            [$newNews, $this]
        );

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
     * action edit
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("news")
     * @return void
     */
    public function editAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
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
        $this->initializeCreateUpdate(
            $this->request->getArguments(),
            $this->arguments['news']
        );
    }

    /**
     * action update
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @return void
     */
    public function updateAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
    {
        $this->checkAccess($news);

        $requestArguments = $this->request->getArguments();

        // Remove file relation from news record
        foreach ($this->uploadFields as $fieldName) {
            if ($requestArguments[$fieldName]['delete'] == 1) {
                $removeMethod = 'remove' . ucfirst($fieldName);
                $getFirstMethod = 'getFirst' . ucfirst($fieldName);

                $news->$removeMethod($news->$getFirstMethod());
            }
        }


        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $news);

        // add signal slot BeforeSave
        // @deprecated will be removed in TYPO3 v12.0. Use PSR-14 based events and EventDispatcherInterface instead.
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'BeforeSave',
            [$news, $this]
        );

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

    /**
     * action delete
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @return void
     */
    public function deleteAction(\Mediadreams\MdNewsfrontend\Domain\Model\News $news)
    {
        $this->checkAccess($news);

        // add signal slot BeforeSave
        // @deprecated will be removed in TYPO3 v12.0. Use PSR-14 based events and EventDispatcherInterface instead.
        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'BeforeDelete',
            [$news, $this]
        );

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
