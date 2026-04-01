<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Tests\Unit\Event;

/**
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2019 Christoph Daecke <typo3@mediadreams.org>
 */

use Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ModifyAllowedMimeTypesEvent::class)]
final class ModifyAllowedMimeTypesEventTest extends UnitTestCase
{
    #[Test]
    public function addMimeTypeAppendsNewType(): void
    {
        $event = new ModifyAllowedMimeTypesEvent('svg', ['image/svg+xml']);
        $event->addMimeType('text/plain');

        self::assertSame(['image/svg+xml', 'text/plain'], $event->getMimeTypes());
    }

    #[Test]
    public function addMimeTypeDoesNotAddDuplicate(): void
    {
        $event = new ModifyAllowedMimeTypesEvent('jpg', ['image/jpeg']);
        $event->addMimeType('image/jpeg');

        self::assertSame(['image/jpeg'], $event->getMimeTypes());
    }

    #[Test]
    public function addMimeTypeWorksOnEmptyList(): void
    {
        $event = new ModifyAllowedMimeTypesEvent('xyz', []);
        $event->addMimeType('application/octet-stream');

        self::assertSame(['application/octet-stream'], $event->getMimeTypes());
    }

    #[Test]
    public function setMimeTypesReplacesExistingList(): void
    {
        $event = new ModifyAllowedMimeTypesEvent('pdf', ['application/pdf']);
        $event->setMimeTypes(['application/pdf', 'application/x-pdf']);

        self::assertSame(['application/pdf', 'application/x-pdf'], $event->getMimeTypes());
    }

    #[Test]
    public function getExtensionReturnsConstructorValue(): void
    {
        $event = new ModifyAllowedMimeTypesEvent('png', []);

        self::assertSame('png', $event->getExtension());
    }
}
