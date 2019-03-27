<?php
namespace Mediadreams\MdNewsfrontend\Utility;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use Mediadreams\MdNewsfrontend\Domain\Model\FileReference;

class FileUpload
{
    /**
     * Handle the file upload and attach the file to the given object
     * ATTENTION: This class is just doing the file upload. Validation of file has to be done by a validator!
     * 
     * Since \TYPO3\CMS\Core\DataHandling\DataHandler can not be used in the frontend, 
     * we have to build it on our own: https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Fal/UsingFal/ExamplesFileFolder.html#in-the-frontend-context
     * Backend file upload: https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Fal/UsingFal/ExamplesFileFolder.html#in-the-backend-context
     *
     * @param array $uploadFile The $_FILES array
     * @param obj $obj Object to attach the file to
     * @param string $propertyName Name of the property
     * @param array $settings Extension settings
     * @param string $subfolder Name of subfolder
     * @return void
     */
    public static function handleUpload($uploadFile, $obj, $propertyName, $settings, $subfolder = '')
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var \TYPO3\CMS\Core\Resource\StorageRepository $storageRepository */         
        $storageRepository = $objectManager->get(StorageRepository::class);
        $storage = $storageRepository->findByUid('1');
        $folder = rtrim($settings['uploadPath'], "/");

        if ($subfolder) {
            $folder = $folder.'/'.$subfolder;
        }

        if ($storage->hasFolder($folder)) {
            $targetFolder = $storage->getFolder($folder);
        } else {
            $targetFolder = $storage->createFolder($folder);
        }

        $originalFilePath = $uploadFile['tmp_name'];
        $newFileName = $uploadFile['name'];

        if (file_exists($originalFilePath)) {
            // if user has already an image -> remove it!
            if ($obj->getFirstImage()) {
                $obj->removeImage($obj->getFirstImage());
            }

            $movedNewFile = $storage->addFile($originalFilePath, $targetFolder, $newFileName);
            $newFileReference = $objectManager->get(FileReference::class);
            $newFileReference->setFile($movedNewFile);
            $method = 'add' . ucfirst($propertyName);
            $obj->{$method}($newFileReference);
        }
    }
}
