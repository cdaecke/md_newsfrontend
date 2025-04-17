<?php

declare(strict_types=1);

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

use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class CheckFileUpload
 * Validator for file upload
 *
 * @package Mediadreams\MdNewsfrontend\Domain\Validator
 */
class CheckFileUpload extends \TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator
{
    /**
     * @var array
     */
    protected $supportedOptions = [
        'filesArr' => [0, 'The $_FILE["image"] array', 'array'],
        'allowedFileExtensions' => [0, 'A comma seperated list of allowed file extensions', 'string'],
    ];

    /**
     * CheckFileUpload constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * Validates the file upload
     * - do a general verification against default TYPO3 deny pattern
     * - check for genral upload errors
     * - check allowed file extensions
     *
     * @param mixed $value The News object
     * @api
     */
    protected function isValid(mixed $value): void
    {
        /** @var UploadedFile $uploadFile */
        $uploadFile = $this->options['filesArr'];
        $allowedFileExtensions = $this->options['allowedFileExtensions'];

        if (!empty($uploadFile->getClientFilename())) {
            $this->checkDenyPattern($uploadFile);
            $this->checkUploadError($uploadFile);
            $this->checkFileExtension($uploadFile, $allowedFileExtensions);
        }
    }

    /**
     * General check against deny pattern in TYPO3
     *
     * @var UploadedFile $uploadFile The uploaded file object
     * @return bool
     */
    private function checkDenyPattern(UploadedFile $uploadFile): bool
    {
        if (!GeneralUtility::makeInstance(FileNameValidator::class)->isValid($uploadFile->getClientFilename())) {
            $this->addError(LocalizationUtility::translate('validator.file_type', 'md_newsfrontend'), 1540902993);
            return false;
        }

        return true;
    }

    /**
     * Checks upload error
     *
     * @var UploadedFile $uploadFile The uploaded file object
     * @return bool
     */
    private function checkUploadError(UploadedFile $uploadFile): bool
    {
        if ($uploadFile->getError() === null) {
            $this->addError(LocalizationUtility::translate('validator.wrong_parameter', 'md_newsfrontend'), 1540929658);
        }

        switch ($uploadFile->getError()) {
            case \UPLOAD_ERR_OK:
                return true;
            case \UPLOAD_ERR_NO_FILE:
                $this->addError(LocalizationUtility::translate('validator.no_file', 'md_newsfrontend'), 1540929694);
                return false;
            case \UPLOAD_ERR_INI_SIZE:
            case \UPLOAD_ERR_FORM_SIZE:
            case \UPLOAD_ERR_PARTIAL:
                $this->addError(LocalizationUtility::translate('validator.partial', 'md_newsfrontend') . ' ' . $uploadFile['error'], 1540929726);
                return false;
            default:
                $this->addError(LocalizationUtility::translate('validator.unknown', 'md_newsfrontend'), 1540929756);
                return false;
        }
    }

    /**
     * Checks for allowed file extensions
     *
     * @var UploadedFile $uploadFile The uploaded file object
     * @var string $allowedFileExtensions String of comma seperated file extensions which are allowed
     * @return bool
     */
    private function checkFileExtension(UploadedFile $uploadFile, string $allowedFileExtensions): bool
    {
        // check allowed files extensions
        if ($allowedFileExtensions !== '') {
            $filePathInfo = PathUtility::pathinfo($uploadFile->getClientFilename());
            if (!GeneralUtility::inList($allowedFileExtensions, strtolower($filePathInfo['extension']))) {
                $this->addError(LocalizationUtility::translate('validator.allowed_file_extensions', 'md_newsfrontend') . ' ' . $allowedFileExtensions, 1540903586);
                return false;
            }
        }

        return true;
    }
}
