
plugin.tx_mdnewsfrontend_newsfe {
    view {
        templateRootPaths.0 = EXT:md_newsfrontend/Resources/Private/Templates/
        templateRootPaths.1 = {$plugin.tx_mdnewsfrontend_newsfe.view.templateRootPath}
        partialRootPaths.0 = EXT:md_newsfrontend/Resources/Private/Partials/
        partialRootPaths.1 = {$plugin.tx_mdnewsfrontend_newsfe.view.partialRootPath}
        layoutRootPaths.0 = EXT:md_newsfrontend/Resources/Private/Layouts/
        layoutRootPaths.1 = {$plugin.tx_mdnewsfrontend_newsfe.view.layoutRootPath}
    }

    persistence {
        #storagePid = 
        #recursive = 1
    }

    features {
        #skipDefaultArguments = 1
        # if set to 1, the enable fields are ignored in BE context
        ignoreAllEnableFieldsInBe = 0
        # Should be on by default, but can be disabled if all action in the plugin are uncached
        requireCHashArgumentForActionArguments = 1
    }
    
    mvc {
        #callDefaultActionIfActionCantBeResolved = 1
    }

    settings {
        storagePid = {$plugin.tx_mdnewsfrontend_newsfe.settings.storagePid}
        parentCategory = {$plugin.tx_mdnewsfrontend_newsfe.settings.parentCategory}
        
        uploadPath = {$plugin.tx_mdnewsfrontend_newsfe.settings.uploadPath}
        allowed_falMedia = {$plugin.tx_mdnewsfrontend_newsfe.settings.allowed_falMedia}
        allowed_falRelatedFiles = {$plugin.tx_mdnewsfrontend_newsfe.settings.allowed_falRelatedFiles}

        jquery = {$plugin.tx_mdnewsfrontend_newsfe.settings.jquery}

        flatpickr = {$plugin.tx_mdnewsfrontend_newsfe.settings.flatpickr}

        parsleyjs = {$plugin.tx_mdnewsfrontend_newsfe.settings.parsleyjs}
        parsleyjsLang = {$plugin.tx_mdnewsfrontend_newsfe.settings.parsleyjsLang}

        tinymce = {$plugin.tx_mdnewsfrontend_newsfe.settings.tinymce}
        tinymceLang = {$plugin.tx_mdnewsfrontend_newsfe.settings.tinymceLang}
    }
}
