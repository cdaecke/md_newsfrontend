# TYPO3 Extension ``md_newsfrontend``

This extension enables a frontend user to add ``ext:news``-records. Beside adding content for the normal text fields like ``title``, ``teaser``, ``bodytext`` it is also possible to upload an image and a file download as well as selecting a category.

Templates are ready to use with the [bootstrap framework](https://getbootstrap.com/) and icons will be shown, if you have [Font Awesome](https://fontawesome.com/) icon set included in your project. Validation of input is done in the frontend, rich text editing enabled for the textarea and a date picker in place for the archive date.

## Requirements

- TYPO3 >= 10.4
- ext:news >= 7.0

## Installation

- Install the extension by using the extension manager or use composer
- Include the static TypoScript of the extension
- Configure the extension by setting your own constants

## Usage

- Add the pluign ``News frontend`` on a page, which is restricted by the frontend user login
- Select a storage page in the plugin-tab in the field ``Record Storage Page``
- Now a frontend user is able to add, edit and delete own records

### Signal slots

<mark>Signal slots are deprecated and will be removed in TYPO3 v12.0.
Please use PSR-14 Events instead (see below)!</mark>

Following signal slots are available:

- `createActionBeforeSave`: Called just before saving a new record
- `createActionAfterPersist`: Called after a new record was saved (new record Id is available)
- `updateActionBeforeSave`: Called just before an existig record will be updated
- `deleteActionBeforeDelete`: Called just before a record will be deleted

Example, how to catch a signal:

Add following lines in `ext_localconf.php` of your own extension:

```php
$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);

// slot for ext:md_newsfrontend
$signalSlotDispatcher->connect(
    \Mediadreams\MdNewsfrontend\Controller\NewsController::class,
    'createActionBeforeSave',
    \Vendor\Extension\Slot\YourClass::class,
    'yourMethod'
);
```

Add the class `Vendor\Extension\Slot\YourClass` with the method `yourMethod` in your extension.

Example:

```php
namespace Vendor\Extension\Slot;

class YourClass
{

    /**
     *
     * @param object $news The news object
     * @param object $obj The controller object
     * @return void
     */
    public function yourMethod($news, $obj) {
        // do something...
    }
}
```

## PSR-14 Events

Following PSR-14 events are available:

- `Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSave`: Called just before saving a new record
- `Mediadreams\MdNewsfrontend\Event\CreateActionAfterPersist`: Called after a new record was saved (new record Id is available)
- `Mediadreams\MdNewsfrontend\Event\UpdateActionBeforeSave`: Called just before an existig record will be updated
- `Mediadreams\MdNewsfrontend\Event\DeleteActionBeforeDelete`: Called just before a record will be deleted

### Register an event

Add following lines in `Configuration/Services.yaml` of your own extension:

```yaml
services:
  Vendor\Extension\EventListener\MyListener:
    tags:
      - name: event.listener
        identifier: 'ext-mdnewsfrontend/createActionBeforeSaveEvent'
        method: 'yourMethod'
        event: Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent
```

Add the class `Vendor\Extension\EventListener\MyListener` with the method `enrichNews` in your extension:

```php
namespace Vendor\Extension\EventListener;

use Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent;

final class MyListener
{
    public function yourMethod(CreateActionBeforeSaveEvent $obj)
    {
        // Get news object 
        $news = $obj->getNews();
        $news->setTeaser('Set some teaser...');

        // Get NewsController
        $newsController = $obj->getNewsController();
    }
}

```

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
