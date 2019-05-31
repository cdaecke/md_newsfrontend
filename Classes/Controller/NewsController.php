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

use TYPO3\CMS\Core\Messaging\AbstractMessage;

use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use GeorgRinger\News\Service\Transliterator\Transliterator;

/**
 * NewsController
 */
class NewsController extends BaseController
{
    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $news = $this->newsRepository->findByTxMdNewsfrontendFeuser($this->feuserUid);
        $this->view->assign('news', $news);
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
        $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        $newNews->setTxMdNewsfrontendFeuser($this->feuserObj);

        // generate and set slug for news record
        $newNews->setPathSegment( Transliterator::urlize( $newNews->getTitle() ) );

        // add signal slot BeforeSave
        $this->signalSlotDispatcher->dispatch(
            __CLASS__, 
            __FUNCTION__ . 'BeforeSave', 
            [$newNews, $this]
        );

        $this->newsRepository->add($newNews);
        $persistenceManager = $this->objectManager->get(PersistenceManager::class);
 
        // persist news entry in order to get the uid of the entry
        $persistenceManager->persistAll();

        $requestArguments = $this->request->getArguments();

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $newNews);

        // add signal slot AfterPersist
        $this->signalSlotDispatcher->dispatch(
            __CLASS__, 
            __FUNCTION__ . 'AfterPersist', 
            [$newNews, $this]
        );
        
        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.new_success','md_newsfrontend'),
            '', 
            AbstractMessage::OK
        );

        $this->redirect('list');
    }

    /**
     * action edit
     *
     * @param \Mediadreams\MdNewsfrontend\Domain\Model\News $news
     * @ignorevalidation $news
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
                $removeMethod = 'remove'.ucfirst($fieldName);
                $getFirstMethod = 'getFirst'.ucfirst($fieldName);

                $news->$removeMethod($news->$getFirstMethod());
            }
        }
        

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $news);

        // add signal slot BeforeSave
        $this->signalSlotDispatcher->dispatch(
            __CLASS__, 
            __FUNCTION__ . 'BeforeSave', 
            [$news, $this]
        );

        $this->newsRepository->update($news);
        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.edit_success','md_newsfrontend'),
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
        $this->signalSlotDispatcher->dispatch(
            __CLASS__, 
            __FUNCTION__ . 'BeforeDelete', 
            [$news, $this]
        );

        $this->newsRepository->remove($news);

        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage(
            LocalizationUtility::translate('controller.delete_success','md_newsfrontend'),
            '', 
            AbstractMessage::OK
        );

        $this->redirect('list');
    }
}
