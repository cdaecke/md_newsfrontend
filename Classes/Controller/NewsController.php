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
        $news = $this->newsRepository->findByMdNewsfrontendFeuser($this->feuserUid);
        $this->view->assign('news', $news);
    }

    /**
     * action new
     *
     * @return void
     */
    public function newAction()
    {
        
    }

    /**
     * Initialize create action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeCreateAction()
    {
        $requestArguments = $this->request->getArguments();

        // add validator for upload fields
        $this->initializeFileValidator($requestArguments, $this->arguments['newNews']);

        // remove category from request, if it was not provided
        if ( empty($requestArguments['newNews']['categories']) ) {
            unset($requestArguments['newNews']['categories']);
            $this->request->setArguments($requestArguments);
        }

        // use correct format for datetime
        $this->arguments->getArgument('newNews')
            ->getPropertyMappingConfiguration()
            ->forProperty('archive')
            ->setTypeConverterOption(
                'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\DateTimeConverter',
                \TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                'd.m.Y H:i'
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
        $newNews->setPid($this->getStoragePid($newNews));
        $newNews->setDatetime(new \DateTime()); // make sure, that you have set the correct timezone for $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone']
        $newNews->setMdNewsfrontendFeuser($this->feuserObj);

        $this->newsRepository->add($newNews);
        $persistenceManager = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
 
        // persist news entry in order to get the uid of the entry
        $persistenceManager->persistAll();

        $requestArguments = $this->request->getArguments();

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $newNews);
        
        $this->clearNewsCache($newNews->getUid(), $newNews->getPid());

        $this->addFlashMessage('Die Nachricht wurde erfolgreich angelegt.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
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
        $this->view->assign('news', $news);
    }

    /**
     * Initialize update action
     * Add custom validator for file upload
     *
     * @return void
     */
    public function initializeUpdateAction()
    {
        $requestArguments = $this->request->getArguments();

        // add validator for upload fields
        $this->initializeFileValidator($requestArguments, $this->arguments['news']);

        // remove category from request, if it was not provided
        if ( empty($requestArguments['news']['categories']) ) {
            unset($requestArguments['news']['categories']);
            $this->request->setArguments($requestArguments);
        }

        // use correct format for datetime
        $this->arguments->getArgument('news')
            ->getPropertyMappingConfiguration()
            ->forProperty('archive')
            ->setTypeConverterOption(
                'TYPO3\\CMS\\Extbase\\Property\\TypeConverter\\DateTimeConverter',
                \TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                'd.m.Y H:i'
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

        $news->setPid($this->getStoragePid($news));

        $requestArguments = $this->request->getArguments();

        // TODO: remove image relation from news record
        /*
        if ($requestArguments['deleteimage'] == 1) {
            $news->removeImage($news->getFirstImage());
        }
        */

        // handle the fileupload
        $this->initializeFileUpload($requestArguments, $news);

        $this->newsRepository->update($news);
        $this->clearNewsCache($news->getUid(), $news->getPid());

        $this->addFlashMessage('Die Nachricht wurde erfolgreich geändert.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
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
        $this->newsRepository->remove($news);

        $this->addFlashMessage('Die Nachricht wurde erfolgreich gelöscht.', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::OK);
        $this->redirect('list');
    }
}
