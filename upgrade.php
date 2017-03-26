<?php
// Manage all upgrade processes.

if (version_compare($oldVersion, '2.6', '<')) {
    // Collections options.
    set_option('archive_repertory_collection_folder', (get_option('archive_repertory_add_collection_folder')
        ? 'String'
        : 'none'));
    delete_option('archive_repertory_add_collection_folder');
    set_option('archive_repertory_collection_prefix', $this->_options['archive_repertory_collection_prefix']);
    set_option('archive_repertory_collection_names', get_option('archive_repertory_collection_folders'));
    delete_option('archive_repertory_collection_folders');
    set_option('archive_repertory_collection_convert', $this->_options['archive_repertory_collection_convert']);
    // Items options.
    // Convert option from an installation before release 2.3.
    set_option('archive_repertory_item_folder', (get_option('archive_repertory_add_item_folder')
        ? 'Dublin Core:Identifier'
        : 'none'));
    delete_option('archive_repertory_add_item_folder');
    set_option('archive_repertory_item_prefix', $this->_options['archive_repertory_item_prefix']);
    set_option('archive_repertory_item_convert', get_option('archive_repertory_convert_folder_to_ascii'));
    delete_option('archive_repertory_convert_folder_to_ascii');
    // Files options.
    set_option('archive_repertory_file_keep_original_name', get_option('archive_repertory_keep_original_filename'));
    delete_option('archive_repertory_keep_original_filename');
    set_option('archive_repertory_file_convert', get_option('archive_repertory_convert_filename_to_ascii'));
    delete_option('archive_repertory_convert_filename_to_ascii');
    set_option('archive_repertory_file_base_original_name', get_option('archive_repertory_base_original_filename'));
    delete_option('archive_repertory_base_original_filename');
    // Other derivative folders.
    if (get_option('archive_repertory_derivative_folders') == '') {
        set_option('archive_repertory_derivative_folders', $this->_options['archive_repertory_derivative_folders']);
    }
}

if (version_compare($oldVersion, '2.9', '<')) {
    // Move option String to Dublin Core:Identifier field.
    if (get_option('archive_repertory_collection_folder') == 'String') {
        // Reset option to simplify process in "after save collection".
        set_option('archive_repertory_collection_folder', 'none');
        // Allow to manage upgrade from 2.6 and 2.8.
        $prefix = get_option('archive_repertory_collection_prefix');
        $prefix = $prefix ? $prefix : get_option('archive_repertory_collection_identifier_prefix');
        // Allow to upgrade from old releases.
        $collectionNames = get_option('archive_repertory_collection_names');
        $collectionNames = $collectionNames ? $collectionNames : get_option('archive_repertory_collection_folders');
        $collectionNames = $collectionNames ? $collectionNames : get_option('archive_repertory_collection_string_folders');
        $collectionNames = $collectionNames ? unserialize($collectionNames) : array();
        $collections = get_records('Collection', array(), 0);
        foreach ($collections as $collection) {
            if (!empty($collectionNames[$collection->id])) {
                $identifier = $prefix . $collectionNames[$collection->id];
                $identifiers = $this->_getRecordIdentifiers($collection, 'Dublin Core:Identifier');
                if (!in_array($identifier, $identifiers)) {
                    $collection = update_collection(
                        $collection,
                        array(),
                        array(
                            'Dublin Core' => array(
                                'Identifier' => array(
                                    array(
                                        'text' => $identifier,
                                        'html' => false,
                    )))));
                }
            }
        }
        set_option('archive_repertory_collection_folder', 'Dublin Core:Identifier');
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->addMessage(__('If no error appears, the upgrade process works fine.')
            . ' ' . __('The identifier of each collection has been moved to "Dublin Core:Identifier".'));
    }
    // Save folder names of collections in all cases.
    $this->_setCollectionFolderNames();
    delete_option('archive_repertory_collection_folders');
    delete_option('archive_repertory_collection_string_folders');
}

if (version_compare($oldVersion, '2.9.1', '<')) {
    // Allow to manage upgrade from 2.6 and 2.8.
    $prefix = get_option('archive_repertory_collection_prefix');
    $prefix = $prefix ? $prefix : get_option('archive_repertory_collection_identifier_prefix');
    set_option('archive_repertory_collection_prefix', $prefix);
    delete_option('archive_repertory_collection_identifier_prefix');
    set_option('archive_repertory_collection_convert', get_option('archive_repertory_collection_convert_name'));
    delete_option('archive_repertory_collection_convert_name');
    $prefix = get_option('archive_repertory_item_prefix');
    $prefix = $prefix ? $prefix : get_option('archive_repertory_item_identifier_prefix');
    set_option('archive_repertory_item_prefix', $prefix);
    delete_option('archive_repertory_item_identifier_prefix');
    set_option('archive_repertory_item_convert', get_option('archive_repertory_item_convert_name'));
    delete_option('archive_repertory_item_convert_name');
    set_option('archive_repertory_file_convert', get_option('archive_repertory_file_convert_name'));
    delete_option('archive_repertory_file_convert_name');
}

if (version_compare($oldVersion, '2.9.2', '<')) {
    foreach (array(
            'archive_repertory_collection_folder',
            'archive_repertory_item_folder',
        ) as $option) {
        $folder = get_option($option);
        switch ($folder) {
            case '':
            case 'None':
                $folder = 'none';
                break;
            case 'id':
                break;
            default:
                $folder = $this->_db->getTable('Element')->findByElementSetNameAndElementName(
                    trim(substr($folder, 0, strrpos($folder, ':'))),
                    trim(substr($folder, strrpos($folder, ':') + 1)));
                $folder = $folder ? $folder->id : 'none';
        }
        set_option($option, $folder);
    }
}

if (version_compare($oldVersion, '2.14.1', '<')) {
    foreach (array(
            'archive_repertory_collection_folder',
            'archive_repertory_item_folder',
            'archive_repertory_collection_convert',
            'archive_repertory_item_convert',
            'archive_repertory_file_convert',
        ) as $option) {
        $value = strtolower(get_option($option));
        if ($value == 'keep name') {
            $value = 'keep';
        }
        elseif ($value == 'none') {
            $value = '';
        }
        set_option($option, $value);
    }
    if (!get_option('archive_repertory_file_keep_original_name')) {
        set_option('archive_repertory_file_convert', 'hash');
    }
    delete_option('archive_repertory_file_keep_original_name');
}
