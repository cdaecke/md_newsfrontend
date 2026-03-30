<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Exception;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */
class FileUploadException extends \RuntimeException
{
    public function __construct(
        private readonly string $translationKey,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($translationKey, $code, $previous);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}
