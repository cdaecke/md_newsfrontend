# Version 5.0.1 (2025-05-20)
- [BUGFIX] Could not get value of property "Mediadreams\MdNewsfrontend\Domain\Model\FrontendUserGroup::subgroup"

All changes
https://github.com/cdaecke/md_newsfrontend/compare/5.0.0...5.0.1

# Version 5.0.0 (2025-04-17)
- [FEATURE] TYPO3 13 compatibility
- [FEATURE] ext:news 12 compatibility
- [BREAKING] Some Templates and Partials have been changed. See [this commit](https://github.com/cdaecke/md_newsfrontend/commit/069a2ab).
- [BREAKING] Pagination has changed. See [this commit](https://github.com/cdaecke/md_newsfrontend/commit/91a1dd5).

## Migration
If you have changed the following files of the extension, please update your templates accordingly
- `Resources/Private/Partials/FooterAssets.html`: Loading of the JS libraries is now done in the controller
- `Resources/Private/Partials/HeaderAssets.html`: This file was removed and the CSS is loaded in the controller
- `Resources/Private/Templates/News/Edit.html`: `f:section name="HeaderAssets"` removed
- `Resources/Private/Templates/News/News.html`: `f:section name="HeaderAssets"` removed
- `Resources/Private/Templates/News/List.html`: Since we use the `SlidingWindowPagination` from the core now, the template needs to be updated
- `Resources/Private/Partials/Pagination.html`: Since we use the `SlidingWindowPagination` from the core now, the template needs to be updated

All changes
https://github.com/cdaecke/md_newsfrontend/compare/4.0.2...5.0.0

# Version 4.0.2 (2025-03-12)
- [BUGFIX] check, if file upload field exists for upload validator

All changes
https://github.com/cdaecke/md_newsfrontend/compare/4.0.1...4.0.2

# Version 4.0.1 (2024-12-15)
- [FEATURE] Update dependency to ext:news (allow v12 of ext:news)

All changes
https://github.com/cdaecke/md_newsfrontend/compare/4.0.0...4.0.1

# Version 4.0.0 (2024-02-02)
- [FEATURE] TYPO3 12 compatibility
- [BREAKING] Signal slots removed

All changes
https://github.com/cdaecke/md_newsfrontend/compare/3.0.3...4.0.0

# Version 3.0.3 (2022-11-10)
[TASK] Update dependency to `ext:news`

All changes
https://github.com/cdaecke/md_newsfrontend/compare/3.0.2...3.0.3

# Version 3.0.2 (2022-03-31)
[BUGFIX] Remove archive date, if no value is provided

All changes
https://github.com/cdaecke/md_newsfrontend/compare/3.0.1...3.0.2

# Version 3.0.1 (2022-02-08)
[BUGFIX] use full path to models `FrontendUser` and `FileReference`, otherwise it will try to use the models of ext:news

All changes
https://github.com/cdaecke/md_newsfrontend/compare/3.0.0...3.0.1

# Version 3.0.0 (2022-01-14)
- [FEATURE] TYPO3 11 compatibility
- [FEATURE] add PSR-14 events
- [FEATURE] allow to create/edit/delete disabled news records

## BREAKING
- `List.html`-Template was changed.
- New paginator was introduced, so partial needs to be change, if you use your own.

All changes
https://github.com/cdaecke/md_newsfrontend/compare/2.0.2...3.0.0

# Version 2.0.2 (2021-01-11)
- [BUGFIX] remove lazy loading annotation from $txMdNewsfrontendFeuser since it is recommended for ObjectStorage only

    see: https://stackoverflow.com/a/65003393/415353

All changes
https://github.com/cdaecke/md_newsfrontend/compare/2.0.1...2.0.2

# Version 2.0.1 (2020-11-05)
- [BUGFIX] add proxy-class generation for ext:news again

All changes
https://github.com/cdaecke/md_newsfrontend/compare/2.0.0...2.0.1

# Version 2.0.0 (2020-06-24)
- [FEATURE] TYPO3 10 compatibility

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.1.5...2.0.0

# Version 1.1.5 (2020-03-16)
- [FEATURE] Allow to configure date format for field archive

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.1.4...1.1.5

# Version 1.1.4 (2020-01-08)
- [FEATURE] Allow to set values for field datetime
- [BUGFIX] Allow to remove all categories from a news record

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.1.3...1.1.4

# Version 1.1.3 (2019-09-26)
- [BUGFIX] Remove slashes in path_segement

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.1.2...1.1.3

# Version 1.1.2 (2019-09-18)
- [BUGFIX] Generate path_segement for news record in all TYPO3 versions

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.1.1...1.1.2

# Version 1.1.1 (2019-09-16)
- [BUGFIX] Use new slug generation of ext news (old one was removed in version 7.3)
- [BUGFIX] Make sure to add parsleyJS validation to form AFTER tinyMCE was loaded
- [FEATURE] Limit filesize of image and file upload. This can optionally be set by constants.

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.1.0...1.1.1

# Version 1.1.0 (2019-06-03)
- [FEATURE] Add signal slots

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.0.1...1.1.0

# Version 1.0.1 (2019-05-03)
- [FEATURE] Add possibility to set the "showinpreview" flag for the image

All changes
https://github.com/cdaecke/md_newsfrontend/compare/1.0.0...1.0.1
