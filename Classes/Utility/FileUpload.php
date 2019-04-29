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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

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
     * @param array $requestArguments The $_REQUEST array
     * @param obj $obj Object to attach the file to
     * @param string $propertyName Name of the property
     * @param array $settings Extension settings
     * @param string $subfolder Name of subfolder
     * @return void
     */
    public static function handleUpload($requestArguments, $obj, $propertyName, $settings, $subfolder = '')
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var \TYPO3\CMS\Core\Resource\StorageRepository $storageRepository */         
        $storageRepository = $objectManager->get(StorageRepository::class);
        $storage = $storageRepository->findByUid('1');
        $folder = rtrim($settings['uploadPath'], '/');

        if ($subfolder) {
            $folder = $folder.'/'.$subfolder;
        }

        if ($storage->hasFolder($folder)) {
            $targetFolder = $storage->getFolder($folder);
        } else {
            $targetFolder = $storage->createFolder($folder);
        }

        $originalFilePath = $requestArguments[$propertyName]['tmp_name'];
        $newFileName = $requestArguments[$propertyName]['name'];

        if (file_exists($originalFilePath)) {
            // upload file
            $movedNewFile = $storage->addFile($originalFilePath, $targetFolder, $newFileName);

            // create file references
            self::updateFileReferences($requestArguments, $obj, $propertyName, $movedNewFile->getUid());            
        }
    }

    /**
     * Handle the file upload and attach the file to the given object
     *
     * @param array $requestArguments The $_REQUEST array
     * @param obj $obj Object to attach the file to
     * @param string $propertyName Name of the property
     * @param int $fileUid The uid of uploaded file
     * @return void
     */
    protected static function updateFileReferences($requestArguments, $obj, $propertyName, $fileUid)
    {
        $timestamp = time();
        $showinpreview = !isset($requestArguments[$propertyName]['showinpreview'])? 0:$requestArguments[$propertyName]['showinpreview'];

        $dbField = self::camelCase2unserScore($propertyName);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable('sys_file_reference');

        // delete old file references
        $queryBuilder
            ->update('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $obj->getUid()),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($dbField))
            )
            ->set('tstamp', $timestamp)
            ->set('deleted', 1)
            ->execute();

        // add new file reference
        $queryBuilder
            ->insert('sys_file_reference')
            ->values([
                'pid'               => $obj->getPid(),
                'tstamp'            => $timestamp,
                'crdate'            => $timestamp,
                'uid_local'         => $fileUid,
                'uid_foreign'       => $obj->getUid(),
                'tablenames'        => 'tx_news_domain_model_news',
                'fieldname'         => $dbField,
                'sorting_foreign'   => 1,
                'table_local'       => 'sys_file',
                'title'             => $requestArguments[$propertyName]['title'],
                'description'       => $requestArguments[$propertyName]['description'],
                'showinpreview'     => (int)$showinpreview
            ])
            ->execute();

        // update news record
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable('tx_news_domain_model_news');
        
        $queryBuilder
            ->update('tx_news_domain_model_news')
            ->where(
                $queryBuilder->expr()->eq('uid', $obj->getUid())
            )
            ->set($dbField, 1)
            ->execute();
    }

    /**
     * Convert a camelCase string to a under_score string
     *
     * @param string $input The camelCase input string
     * @return string The under_score string
     */
    protected static function camelCase2unserScore($input) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];

        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('_', $ret);
    }
}
