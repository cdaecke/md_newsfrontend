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
