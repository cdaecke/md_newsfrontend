<?php
namespace Mediadreams\MdNewsfrontend\Domain\Validator;

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
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Validator for file upload
 *
 * @api
 */
class CheckFileUpload extends \TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator
{
    /**
     * @var array
     */
    protected $supportedOptions = array(
        'filesArr'              => array(0, 'The $_FILE["image"] array', 'array'),
        'allowedFileExtensions' => array(0, 'A comma seperated list of allowed file extensions', 'string'),
    );

    /**
     * Validates the file upload
     * - do a general verification against default TYPO3 deny pattern
     * - check for genral upload errors
     * - check allowed file extensions
     *
     * @param obj $value The FrontendUser object
     * @api
     */
    public function isValid($value)
    {
        $uploadFile = $this->options['filesArr'];
        $allowedFileExtensions = $this->options['allowedFileExtensions'];

        if ( !empty($uploadFile['name']) ) {
            $this->checkDenyPattern($uploadFile);
            $this->checkUploadError($uploadFile);
            $this->checkFileExtension($uploadFile, $allowedFileExtensions);
        }
    }

    /**
     * General check against deny pattern in TYPO3
     *
     * @var $uploadFile The $_FILE["image"] parameter
     * @return bool
     */
    private function checkDenyPattern($uploadFile)
    {
        if (!GeneralUtility::verifyFilenameAgainstDenyPattern($uploadFile['name'])) {
            $this->addError(LocalizationUtility::translate('validator.file_type','md_newsfrontend'), 1540902993);
        }

        return true;
    }

    /**
     * Checks upload error
     *
     * @var $uploadFile The $_FILE["image"] parameter
     * @return bool
     */
    private function checkUploadError($uploadFile)
    {
        if ( !isset($uploadFile['error']) || is_array($uploadFile['error']) ) {
            $this->addError(LocalizationUtility::translate('validator.wrong_parameter','md_newsfrontend'), 1540929658);
        }

        switch ($uploadFile['error']) {
            case \UPLOAD_ERR_OK:
                break;
            case \UPLOAD_ERR_NO_FILE:
                $this->addError(LocalizationUtility::translate('validator.no_file','md_newsfrontend'), 1540929694);
            case \UPLOAD_ERR_INI_SIZE:
            case \UPLOAD_ERR_FORM_SIZE:
            case \UPLOAD_ERR_PARTIAL:
                $this->addError(LocalizationUtility::translate('validator.partial','md_newsfrontend').' ' . $uploadFile['error'], 1540929726);
            default:
                $this->addError(LocalizationUtility::translate('validator.unknown','md_newsfrontend'), 1540929756);
        }

        return true;
    }

    /**
     * Checks for allowed file extensions
     *
     * @var array $uploadFile The $_FILE["image"] parameter
     * @var string $allowedFileExtensions String of comma seperated file extensions which are allowed
     * @return bool
     */
    private function checkFileExtension($uploadFile, $allowedFileExtensions)
    {
        // check allowed files extensions
        if ($allowedFileExtensions !== null) {
            $filePathInfo = PathUtility::pathinfo($uploadFile['name']);
            if (!GeneralUtility::inList($allowedFileExtensions, strtolower($filePathInfo['extension']))) {
                $this->addError(LocalizationUtility::translate('validator.allowed_file_extensions','md_newsfrontend').' '.$allowedFileExtensions, 1540903586);
            }
        }

        return true;
    }
}
