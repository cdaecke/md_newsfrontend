<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Property\TypeConverter;

/**
 *
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2022 Christoph Daecke <typo3@mediadreams.org>
 *
 */

use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use TYPO3\CMS\Extbase\Property\Exception\TargetNotFoundException;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;

class EnableFieldsObjectConverter extends PersistentObjectConverter
{
    /**
     * Fetch an object from persistence layer.
     *
     * @param mixed $identity
     * @param string $targetType
     * @return object
     * @throws InvalidSourceException
     * @throws TargetNotFoundException
     */
    protected function fetchObjectFromPersistence($identity, string $targetType): object
    {
        if (ctype_digit((string)$identity)) {
            $query = $this->persistenceManager->createQueryForType($targetType);
            $query->getQuerySettings()->setIgnoreEnableFields(true);
            $constraints = $query->equals('uid', $identity);
            $object = $query->matching($constraints)->execute()->getFirst();
        } else {
            throw new InvalidSourceException('The identity property "' . $identity . '" is no UID.', 1641843520);
        }

        if ($object === null) {
            throw new TargetNotFoundException(
                'Object with identity "' . print_r($identity, true) . '" not found.', 1641843538
            );
        }

        return $object;
    }
}
