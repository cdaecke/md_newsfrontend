<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

/**
 * Migrates existing tt_content records from list_type=mdnewsfrontend_newsfe
 * to the new CType=mdnewsfrontend_newsfe
 */
#[UpgradeWizard('mdNewsfrontendPluginListTypeToCTypeUpdate')]
final class MdNewsfrontendListTypeToCTypeUpdate extends AbstractListTypeToCTypeUpdate
{
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'mdnewsfrontend_newsfe' => 'mdnewsfrontend_newsfe',
        ];
    }

    public function getTitle(): string
    {
        return 'EXT:md_newsfrontend: Migrate plugin list_type to CType';
    }

    public function getDescription(): string
    {
        return 'Migrates existing tt_content records using list_type "mdnewsfrontend_newsfe" to the new CType-based plugin registration.';
    }

}
