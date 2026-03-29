<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Event;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */

/**
 * PSR-14 event to modify the list of allowed MIME types for a given file extension.
 *
 * Dispatch is triggered during file upload validation, once per uploaded file.
 * Event listeners can add, remove, or replace MIME types for any extension.
 *
 * Example listener registration (Configuration/Services.yaml):
 *
 *   MyVendor\MyExtension\EventListener\AddSvgMimeType:
 *     tags:
 *       - name: event.listener
 *         identifier: 'my-extension/add-svg-mime-type'
 *         event: Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent
 *
 * Example listener:
 *
 *   public function __invoke(ModifyAllowedMimeTypesEvent $event): void
 *   {
 *       if ($event->getExtension() === 'svg') {
 *           $event->setMimeTypes(['image/svg+xml', 'text/plain']);
 *       }
 *   }
 */
final class ModifyAllowedMimeTypesEvent
{
    public function __construct(
        private readonly string $extension,
        private array $mimeTypes,
    ) {
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getMimeTypes(): array
    {
        return $this->mimeTypes;
    }

    public function setMimeTypes(array $mimeTypes): void
    {
        $this->mimeTypes = $mimeTypes;
    }

    public function addMimeType(string $mimeType): void
    {
        if (!in_array($mimeType, $this->mimeTypes, true)) {
            $this->mimeTypes[] = $mimeType;
        }
    }
}
