# TYPO3 Extension ``md_newsfrontend``

This extension enables a frontend user to add ``ext:news``-records. Beside adding content for the normal text fields like ``title``, ``teaser``, ``bodytext`` it is also possible to upload an image and a file download as well as selecting a category.

Templates are ready to use with the [bootstrap framework](https://getbootstrap.com/) and icons will be shown, if you have [Font Awesome](https://fontawesome.com/) icon set included in your project. Validation of input is done in the frontend, rich text editing enabled for the textarea and a date picker in place for the archive date.

## Requirements

- TYPO3 >= 8.7
- ext:news >= 7.0

## Installation

- Install the extension by using the extension manager or use composer
- Include the static TypoScript of the extension
- Configure the extension by setting your own constants

## Usage

- Add the pluign ``News frontend`` on a page, which is restricted by the frontend user login
- Select a storage page in the plugin-tab in the field ``Record Storage Page``
- Now a frontend user is able to add, edit and delete own records

## Bugs and Known Issues
If you find a bug, it would be nice if you add an issue on [Github](https://github.com/cdaecke/md_newsfrontend/issues).

# THANKS

Thanks a lot to all who make this outstanding TYPO3 project possible!

## Credits

- Extension icon was copied from [ext:news](https://github.com/georgringer/news) and enriched with a pen from [Font Awesome](https://fontawesome.com/icons/pencil-alt?style=solid).
- Thanks to [Parsley.js](http://parsleyjs.org), which I use for validating the form in the frontend.
- Thanks to [tiny](https://www.tiny.cloud), which I use as rich text editor in the frontend.
- Thanks to [flatpickr](https://flatpickr.js.org/), which I use as date picker in the frontend.
- And last but not least thanks to the guys at [jQuery](http://jquery.com/).
