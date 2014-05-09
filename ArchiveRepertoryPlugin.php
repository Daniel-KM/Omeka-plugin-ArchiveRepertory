<?php
/**
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * @copyright Copyright Daniel Berthereau, 2012-2014
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package ArchiveRepertory
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'ArchiveRepertoryFunctions.php';

/**
 * The Archive Repertory plugin.
 * @package Omeka\Plugins\ArchiveRepertory
 */
class ArchiveRepertoryPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array This plugin's hooks.
     */
    protected $_hooks = array(
        'install',
        'upgrade',
        'uninstall',
        'config_form',
        'config',
        'after_save_collection',
        'after_save_item',
        'after_save_file',
        'after_delete_file',
    );

    /**
     * @var array This plugin's options.
     */
    protected $_options = array(
        // Collections options.
        'archive_repertory_collection_folder' => 'Dublin Core:Title',
        'archive_repertory_collection_identifier_prefix' => '',
        'archive_repertory_collection_string_folders' => null,
        'archive_repertory_collection_convert_name' => 'Full',
        // Items options.
        'archive_repertory_item_folder' => 'id',
        'archive_repertory_item_identifier_prefix' => '',
        'archive_repertory_item_convert_name' => 'Full',
        // Files options.
        'archive_repertory_file_keep_original_name' => true,
        'archive_repertory_file_convert_name' => 'Full',
        'archive_repertory_file_base_original_name' => false,
        // Other derivative folders.
        'archive_repertory_derivative_folders' => '',
        // Max download without captcha (default to 30 MB).
        'archive_repertory_download_max_free_download' => 30000000,
        'archive_repertory_legal_text' => 'I agree with terms of use.',
    );

    /**
     * Folder paths for each type of files/derivatives.
     *
     * @var array
     */
    static private $_pathsByType = array(
        'original' => 'original',
        'fullsize' => 'fullsize',
        'thumbnail' => 'thumbnails',
        'square_thumbnail' => 'square_thumbnails',
    );

    /**
     * Derivative extension for each type of files/derivatives, used when a
     * plugin doesn't use the Omeka standard ones. These lasts are used by
     * default. The dot before the extension should be specified if needed.
     *
     * This setting is used only when files are moved or deleted without use of
     * the original plugin, because the original plugin knows to create and
     * to delete them at the right place, of course.
     *
     * @var array
     */
    private $_derivativeExtensionsByType = array();

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        $this->_installOptions();

        // Set default names of collection folders. Folders are created by config.
        $this->_setCollectionFolderNames();
    }

    /**
     * Upgrades the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];

        if (version_compare($oldVersion, '2.6', '<')) {
            // Collections options.
            set_option('archive_repertory_collection_folder', (get_option('archive_repertory_add_collection_folder')
                ? 'String'
                : 'None'));
            delete_option('archive_repertory_add_collection_folder');
            set_option('archive_repertory_collection_identifier_prefix', $this->_options['archive_repertory_collection_identifier_prefix']);
            set_option('archive_repertory_collection_string_folders', get_option('archive_repertory_collection_folders'));
            delete_option('archive_repertory_collection_folders');
            set_option('archive_repertory_collection_convert_name', $this->_options['archive_repertory_collection_convert_name']);
            // Items options.
            // Convert option from an installation before release 2.3.
            set_option('archive_repertory_item_folder', (get_option('archive_repertory_add_item_folder')
                ? 'Dublin Core:Identifier'
                : 'None'));
            delete_option('archive_repertory_add_item_folder');
            set_option('archive_repertory_item_identifier_prefix', $this->_options['archive_repertory_item_identifier_prefix']);
            set_option('archive_repertory_item_convert_name', get_option('archive_repertory_convert_folder_to_ascii'));
            delete_option('archive_repertory_convert_folder_to_ascii');
            // Files options.
            set_option('archive_repertory_file_keep_original_name', get_option('archive_repertory_keep_original_filename'));
            delete_option('archive_repertory_keep_original_filename');
            set_option('archive_repertory_file_convert_name', get_option('archive_repertory_convert_filename_to_ascii'));
            delete_option('archive_repertory_convert_filename_to_ascii');
            set_option('archive_repertory_file_base_original_name', get_option('archive_repertory_base_original_filename'));
            delete_option('archive_repertory_base_original_filename');
            // Other derivative folders.
            if (get_option('archive_repertory_derivative_folders') == '') {
                set_option('archive_repertory_derivative_folders', $this->_options['archive_repertory_derivative_folders']);
            }
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm()
    {
        // Prepare variables for the config form.
        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);

        // It's more sustainable to memorize true name than an internal code and
        // it's simpler to get the normal order.
        $dublinCoreIdentifier = $this->_db->getTable('Element')->findByElementSetNameAndElementName('Dublin Core', 'Identifier');

        echo get_view()->partial('plugins/archive-repertory-config-form.php', array(
            'collection_names' => unserialize(get_option('archive_repertory_collection_string_folders')),
            'collection_folder' => $this->_getOption('archive_repertory_collection_folder'),
            'item_folder' => $this->_getOption('archive_repertory_item_folder'),
            'is_compatible' => $this->_checkOmekaCompatibility(),
            'allow_unicode' => $this->_checkUnicodeInstallation(),
            'dublincore_identifier' => $dublinCoreIdentifier->id,
        ));
    }

    /**
     * Saves plugin configuration page and creates folders if needed.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];

        // Collection options.
        set_option('archive_repertory_collection_folder', $this->_setOption($post['archive_repertory_collection_folder']));
        set_option('archive_repertory_collection_identifier_prefix', trim($post['archive_repertory_collection_identifier_prefix']));
        set_option('archive_repertory_collection_convert_name', $post['archive_repertory_collection_convert_name']);
        // Items options.
        set_option('archive_repertory_item_folder', $this->_setOption($post['archive_repertory_item_folder']));
        set_option('archive_repertory_item_identifier_prefix', trim($post['archive_repertory_item_identifier_prefix']));
        set_option('archive_repertory_item_convert_name', $post['archive_repertory_item_convert_name']);
        // Files options.
        set_option('archive_repertory_file_keep_original_name', (boolean) $post['archive_repertory_file_keep_original_name']);
        set_option('archive_repertory_file_convert_name', $post['archive_repertory_file_convert_name']);
        set_option('archive_repertory_file_base_original_name', (boolean) $post['archive_repertory_file_base_original_name']);
        // Other options.
        set_option('archive_repertory_derivative_folders', trim($post['archive_repertory_derivative_folders']));
        set_option('archive_repertory_warning_max_size_download', (integer) ($post['archive_repertory_warning_max_size_download']));
        set_option('archive_repertory_legal_text', trim($post['archive_repertory_legal_text']));

        // Unlike items, collections are few and stable, so they are kept as an
        // option.
        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);
        $collectionNames = unserialize(get_option('archive_repertory_collection_string_folders'));
        foreach (loop('collections') as $collection) {
            $id = 'archive_repertory_collection_folder_' . $collection->id;
            $collectionNames[$collection->id] = $this->_sanitizeName($post[$id]);
        }
        set_option('archive_repertory_collection_string_folders', serialize($collectionNames));
        $this->_setCollectionFolderNames();

        // Create collection folders.
        if (get_option('archive_repertory_collection_convert_name') != 'None') {
            $collectionNames = unserialize(get_option('archive_repertory_collection_string_folders'));
            set_loop_records('collections', $collections);
            foreach (loop('collections') as $collection) {
                $result = $this->_createArchiveFolders($collectionNames[$collection->id]);
            }
        }
    }

    /**
     * Helper for config that allows to get option for collection and item.
     *
     * @param string $option Name of the option
     *
     * @return string|integer
     * Value of the option.
     */
    private function _getOption($option)
    {
        $option = get_option($option);
        switch ($option) {
            case empty($option):
                return 'None';

            case 'None':
            case 'String':
            case 'id':
                return  $option;

            default:
                list($elementSetName, $elementName) = explode(':', $option);
                if ($elementSetName && $elementName) {
                    $element = $this->_db->getTable('Element')->findByElementSetNameAndElementName($elementSetName, $elementName);
                    if ($element) {
                        return $element->id;
                    }
                }
        }

        return  'None';
    }

    /**
     * Helper for config that allows to set option for collection and item.
     *
     * @param string $option Name of the option
     *
     * @return string|integer
     * Value of the option.
     */
    private function _setOption($option)
    {
        switch ($option) {
            case empty($option):
                return 'None';

            case 'None':
            case 'String':
            case 'id':
                return  $option;

            default:
                $element = get_record_by_id('Element', $option);
                if ($element) {
                    return $element->set_name . ':' . $element->name;
                }
        }

        return  'None';
    }

    /**
     * Create a collection folder when a collection is created.
     */
    public function hookAfterSaveCollection($args)
    {
        $post = $args['post'];
        $collection = $args['record'];

        // Create the collection folder name when option is "String" (exception).
        $collectionNames = unserialize(get_option('archive_repertory_collection_string_folders'));
        if (!isset($collectionNames[$collection->id]) && get_option('archive_repertory_collection_convert_name') == 'String') {
            $collectionNames[$collection->id] = $this->_createCollectionDefaultName($collection);
            set_option('archive_repertory_collection_string_folders', serialize($collectionNames));
        }

        // Create or update the collection folder name.
        $this->_setCollectionFolderName($collection);

        // Create collection folder.
        if (get_option('archive_repertory_collection_convert_name') != 'None') {
            $collectionNames = unserialize(get_option('archive_repertory_collection_string_folders'));
            $result = $this->_createArchiveFolders($collectionNames[$collection->id]);
        }
    }

    /**
     * Manages folders for attached files of items.
     */
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];

        // Check if file is at the right place, with collection and item folders.
        $archiveFolder = $this->_getArchiveFolderName($item);

        // Check if files are already attached and if they are at the right place.
        $files = $item->getFiles();
        foreach ($files as $file) {
            // Move file only if it is not in the right place.
            // We don't use original filename here, because this is managed in
            // hookAfterSavefile() when the file is inserted. Here, the filename
            // is already sanitized.
            $newFilename = $archiveFolder . basename_special($file->filename);
            if ($file->filename != $newFilename) {
                $result = $this->_moveFilesInArchiveSubfolders(
                    $file->filename,
                    $newFilename,
                    $this->_getDerivativeExtension($file));
                if (!$result) {
                    throw new Exception(__('Cannot move files inside archive directory.'));
                }

                // Update file in Omeka database immediately for each file.
                $file->filename = $newFilename;
                // As it's not a file hook, the file is not automatically saved.
                $file->save();
            }
        }
    }

    /**
     * Moves/renames an attached file just at the end of the save process.
     *
     * Original file name can be cleaned too (user can choose to keep base name
     * only).
     */
    public function hookAfterSaveFile($args)
    {
        $post = $args['post'];
        $file = $args['record'];

        // Files can't be moved during insert, because has_derivative is set
        // just after it. Of course, this can be bypassed, but we don't.
        if (!$args['insert']) {
            // Check stored file status.
            if ($file->stored == 0) {
                return;
            }

            // Check if main file is already in the archive folder.
            if (!is_file($this->_getFullArchivePath('original') . DIRECTORY_SEPARATOR . $file->filename)) {
                return;
            }

            // Memorize current filenames.
            $file_filename = $file->filename;
            $file_original_filename = $file->original_filename;

            // Keep only basename of original filename in metadata if wanted.
            if (get_option('archive_repertory_file_base_original_name')) {
                $file->original_filename = basename_special($file->original_filename);
            }

            // Rename file only if wanted and needed.
            if (get_option('archive_repertory_file_keep_original_name')) {
                // Get the new filename.
                $newFilename = basename_special($file->original_filename);
                $newFilename = $this->_sanitizeName($newFilename);
                $newFilename = $this->_convertFilenameTo($newFilename, get_option('archive_repertory_file_convert_name'));

                // Move file only if the name is a new one.
                $item = $file->getItem();
                $archiveFolder = $this->_getArchiveFolderName($item);
                $newFilename = $archiveFolder . $newFilename;
                if ($file->filename != $newFilename) {
                    $result = $this->_moveFilesInArchiveSubfolders(
                        $file->filename,
                        $newFilename,
                        $this->_getDerivativeExtension($file));
                    if (!$result) {
                        throw new Exception(__('Cannot move files inside archive directory.'));
                    }

                    // Update filename.
                    $file->filename = $newFilename;
                }
            }

            // Update file only if needed. It uses normal hook, so this hook
            // will be call one more time, but filenames will be already updated
            // so there is no risk of infinite loop.
            if ($file_filename != $file->filename
                    || $file_original_filename != $file->original_filename
                ) {
                $file->save();
            }
        }
    }

    /**
     * Manages deletion of the folder of a file when this file is removed.
     */
    public function hookAfterDeleteFile($args)
    {
        $file = $args['record'];
        $item = $file->getItem();
        $archiveFolder = $this->_getArchiveFolderName($item);
        $result = $this->_removeArchiveFolders($archiveFolder);
        return true;
    }

    /**
     * Gets archive folder name of an item, that depends on activation of options.
     *
     * @param object $item
     *
     * @return string Unique and sanitized name folder name of the item.
     */
    protected function _getArchiveFolderName($item)
    {
        $collection_folder = $this->_getCollectionFolderName($item);
        $item_folder = $this->_getItemFolderName($item);
        return $collection_folder . $item_folder;
    }

    /**
     * Gets collection folder name from an item.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the collection.
     */
    protected function _getCollectionFolderName($item)
    {
        $collection = '';

        // Collection folders are created when the module is configured.
        if (get_option('archive_repertory_collection_convert_name') && !empty($item->collection_id)) {
            $collection_names = unserialize(get_option('archive_repertory_collection_string_folders'));
            $collection = $collection_names[$item->collection_id];
            if ($collection != '') {
                $collection .= DIRECTORY_SEPARATOR;
            }
        }

        return $collection;
    }

    /**
     * Gets item folder name from an item and create folder if needed.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the item.
     */
    protected function _getItemFolderName($item)
    {
        $item_folder = get_option('archive_repertory_item_folder');

        switch ($item_folder) {
            // This case is a common exception.
            case 'Dublin Core:Identifier':
                $name = $this->_createItemFolderNameFromDCidentifier($item);
                break;
            case 'id':
                return (string) $item->id . DIRECTORY_SEPARATOR;
            case 'None':
            case '':
                return '';
            default:
                $name = $this->_createRecordFolderName($item, array(
                    trim(substr($item_folder, 0, strrpos($item_folder, ':'))),
                    trim(substr($item_folder, strrpos($item_folder, ':') + 1)),
                ));
        }

        return $this->_convertFilenameTo($name, get_option('archive_repertory_item_convert_name')) . DIRECTORY_SEPARATOR;
    }

    /**
     * Prepare collection folder names.
     *
     * @return void.
     */
    protected function _setCollectionFolderNames()
    {
        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);
        foreach (loop('collections') as $collection) {
            $this->_setCollectionFolderName($collection);
        }
    }

    /**
     * Creates the default name for a collection folder.
     *
     * @param object $collection
     *
     * @return string Unique sanitized name of the collection.
     */
    protected function _setCollectionFolderName($collection)
    {
        $collection_folder = get_option('archive_repertory_collection_folder');
        $collectionNames = unserialize(get_option('archive_repertory_collection_string_folders'));
        switch ($collection_folder) {
            case '':
            case 'None':
                $collectionName = '';
                break;
            case 'id':
                $collectionName = (string) $collection->id;
                break;
            case 'String':
                $collectionName = $collectionNames[$collection->id];
                break;
            // This case is a common exception.
            case 'Dublin Core:Identifier':
                $collectionName = $this->_createCollectionFolderNameFromDCidentifier($collection);
                break;
            default:
                $collectionName = $this->_createRecordFolderName($collection, array(
                    trim(substr($collection_folder, 0, strrpos($collection_folder, ':'))),
                    trim(substr($collection_folder, strrpos($collection_folder, ':') + 1)),
                ));
                break;
        }
        $collectionName = $this->_sanitizeName($collectionName);
        $collectionNames[$collection->id] = $this->_convertFilenameTo(
            $collectionName,
            get_option('archive_repertory_collection_convert_name'));

        set_option('archive_repertory_collection_string_folders', serialize($collectionNames));
    }

    /**
     * Creates the default name for a collection folder.
     *
     * Default name is the first word of the collection name. The id is added if
     * this name is already used.
     *
     * @param object $collection
     *
     * @return string Unique sanitized name of the collection.
     */
    protected function _createCollectionDefaultName($collection)
    {
        $collectionNames = unserialize(get_option('archive_repertory_collection_string_folders'));
        if ($collectionNames === false) {
            $collectionNames = array();
        }
        else {
            // Remove the current collection id to simplify check.
            unset($collectionNames[$collection->id]);
        }

        // Default name is the first word of the collection name.
        $title = $collection->getElementTexts('Dublin Core', 'Title');
        $title = isset($title[0]) ? $title[0]->text : '';
        $defaultName = trim(strtok(trim($title), " \n\r\t"));

        // If this name is already used, the id is added until name is unique.
        While (in_array($defaultName, $collectionNames)) {
            $defaultName .= '_' . $collection->id;
        }

        return $this->_sanitizeName($defaultName);
    }

    /**
     * Creates a unique name for a record folder from first metadata.
     *
     * If there isn't any identifier with the prefix, the record id will be used.
     * The name is sanitized.
     *
     * @param object $record
     * @param array|string $metadata
     *
     * @return string Unique sanitized name of the record.
     */
    protected function _createRecordFolderName($record, $metadata)
    {
        if (is_array($metadata)) {
            $metadata = array_values($metadata);
            $identifier = $record->getElementTexts($metadata[0], $metadata[1]);
            $identifier = isset($identifier[0]) ? $identifier[0]->text : '';
        }
        else {
            $identifier = $record->getProperty($metadata);
        }
        return empty($identifier)
            ? (string) $record->id
            : $this->_sanitizeName($identifier);
    }

    /**
     * Creates a unique name for a collection folder from Dublin Core identifier.
     *
     * @see ArchiveRepertoryPlugin::_createRecordFolderNameFromDCidentifier()
     *
     * @param object $collection
     *
     * @return string Unique sanitized name of the collection.
     */
    protected function _createCollectionFolderNameFromDCidentifier($collection)
    {
        return $this->_createRecordFolderNameFromDCidentifier(
            $collection,
            get_option('archive_repertory_collection_identifier_prefix'));
    }

    /**
     * Creates a unique name for an item folder from Dublin Core identifier.
     *
     * @see ArchiveRepertoryPlugin::_createRecordFolderNameFromDCidentifier()
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the item.
     */
    protected function _createItemFolderNameFromDCidentifier($item)
    {
        return $this->_createRecordFolderNameFromDCidentifier(
            $item,
            get_option('archive_repertory_item_identifier_prefix'));
    }

    /**
     * Creates a unique name for a record folder from Dublin Core identifier.
     *
     * Default name is the Dublin Core identifier with the selected prefix. If
     * there isn't any identifier with the prefix, the record id will be used.
     * The name is sanitized and the selected prefix is removed.
     *
     * @param object $record
     *
     * @return string Unique sanitized name of the record.
     */
    private function _createRecordFolderNameFromDCidentifier($record, $prefix)
    {
        $identifiers = $record->getElementTexts('Dublin Core', 'Identifier');
        if (empty($identifiers)) {
            return (string) $record->id;
        }

        // Get all identifiers with the chosen prefix in case they are multiple.
        foreach ($identifiers as $key => $identifier) {
            $identifiers[$key] = $identifier->text;
        }
        $filtered_identifiers = array_values(array_filter($identifiers, 'self::_filteredIdentifier'));
        if (!isset($filtered_identifiers[0])) {
            return (string) $record->id;
        }

        // Keep only the first identifier with the configured prefix.
        $record_identifier = substr($filtered_identifiers[0], strlen($prefix));
        return $this->_sanitizeName($record_identifier);
    }

    /**
     * Check if an identifier of an item begins with the configured prefix.
     *
     * @param string $identifier
     *   Identifier to check.
     *
     * @return boolean
     *   True if identifier begins with the prefix, false else.
     */
    protected function _filteredIdentifier($identifier)
    {
        static $prefix;
        static $prefix_len;

        if ($prefix === null) {
            $prefix = strtolower(get_option('archive_repertory_item_identifier_prefix'));
            $prefix_len = strlen($prefix);
        }

        return (strtolower(substr($identifier, 0, $prefix_len)) == $prefix);
    }

    /**
     * Checks and creates a folder.
     *
     * @note Currently, Omeka API doesn't provide a function to create a folder.
     *
     * @param string $path Full path of the folder to create.
     *
     * @return boolean True if the path is created, Exception if an error occurs.
     */
    protected function _createFolder($path)
    {
        if ($path != '') {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    chmod($path, 0755);
                    if (is_writable($path)) {
                        return true;
                    }
                    throw new Omeka_Storage_Exception(__('Error directory non writable: "%s".', $path));
                }
                throw new Omeka_Storage_Exception(__('Failed to create folder "%s": a file with the same name exists...', $path));
            }

            if (!@mkdir($path, 0755, true)) {
                throw new Omeka_Storage_Exception(__('Error making directory: "%s".', $path));
            }
            chmod($path, 0755);
        }
        return true;
    }

    /**
     * Checks and removes an empty folder.
     *
     * @note Currently, Omeka API doesn't provide a function to remove a folder.
     *
     * @param string $path Full path of the folder to remove.
     * @param boolean $evenNonEmpty Remove non empty folder
     *   This parameter can be used with non standard folders.
     *
     * @return void.
     */
    protected function _removeFolder($path, $evenNonEmpty = false)
    {
        $path = realpath($path);
        if (file_exists($path)
                && is_dir($path)
                && is_readable($path)
                && ((count(@scandir($path)) == 2) // Only '.' and '..'.
                    || $evenNonEmpty)
                && is_writable($path)
            ) {
            $this->_rrmdir($path);
        }
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dirPath Directory name.
     *
     * @return boolean
     */
    protected function _rrmdir($dirPath)
    {
        $glob = glob($dirPath);
        foreach ($glob as $g) {
            if (!is_dir($g)) {
                unlink($g);
            }
            else {
                $this->_rrmdir("$g/*");
                rmdir($g);
            }
        }
        return true;
    }

    /**
     * Get the archive folder from a name path
     *
     * Example: 'original' can return '/var/www/omeka/files/original'.
     *
     * @param string $namePath the name of the path.
     *
     * @return string
     *   Full archive path, or empty if none.
     */
    protected function _getFullArchivePath($namePath)
    {
        $archivePaths = $this->_getFullArchivePaths();
        return isset($archivePaths[$namePath])
             ? $archivePaths[$namePath]
             : '';
    }

    /**
     * Get all archive folders with full paths, eventually with other derivative
     * folders. This function updates the derivative extensions too.
     *
     * @return array of folders.
     */
    protected function _getFullArchivePaths()
    {
        static $archivePaths = array();

        if (empty($archivePaths)) {
            foreach (self::$_pathsByType as $name => $path) {
                $archivePaths[$name] = FILES_DIR . DIRECTORY_SEPARATOR . $path;
            }

            $derivatives = explode(',', get_option('archive_repertory_derivative_folders'));
            foreach ($derivatives as $key => $value) {
                if (strpos($value, '|') === false) {
                    $name = trim($value);
                }
                else {
                    list($name, $extension) = explode('|', $value);
                    $name = trim($name);
                    $extension = trim($extension);
                    if ($extension != '') {
                        $this->_derivativeExtensionsByType[$name] = $extension;
                    }
                }
                $path = realpath(FILES_DIR . DIRECTORY_SEPARATOR . $name);
                if (!empty($name) && !empty($path) && $path != '/') {
                    $archivePaths[$name] = $path;
                }
                else {
                    unset($derivatives[$key]);
                    set_option('archive_repertory_derivative_folders', implode(', ', $derivatives));
                }
            }
        }

        return $archivePaths;
    }

    /**
     * Checks if the folders exist in the archive repertory, then creates them.
     *
     * @param string $archiveFolder
     *   Name of folder to create inside archive dir.
     * @param string $pathFolder
     *   (Optional) Name of folder where to create archive folder. If not set,
     *   the archive folder will be created in all derivative paths.
     *
     * @return boolean
     *   True if each path is created, Exception if an error occurs.
     */
    protected function _createArchiveFolders($archiveFolder, $pathFolder = '')
    {
        if ($archiveFolder != '') {
            $folders = empty($pathFolder)
                ? $this->_getFullArchivePaths()
                : array($pathFolder);
            foreach ($folders as $path) {
                $fullpath = $path . DIRECTORY_SEPARATOR . $archiveFolder;
                $result = $this->_createFolder($fullpath);
            }
        }
        return true;
    }

    /**
     * Removes empty folders in the archive repertory.
     *
     * @param string $archiveFolder Name of folder to delete, without files dir.
     *
     * @return boolean True if the path is created, Exception if an error occurs.
     */
    protected function _removeArchiveFolders($archiveFolder)
    {
        if (($archiveFolder != '.')
                && ($archiveFolder != '..')
                && ($archiveFolder != DIRECTORY_SEPARATOR)
                && ($archiveFolder != '')
            ) {
            foreach ($this->_getFullArchivePaths() as $path) {
                $folderPath = $path . DIRECTORY_SEPARATOR . $archiveFolder;
                if (realpath($path) != realpath($folderPath)) {
                    $this->_removeFolder($folderPath);
                }
            }
        }
        return true;
    }

    /**
     * Get the derivative filename from a filename and an extension. A check can
     * be done on the derivative type to allow use of a non standard extension,
     * for example with a plugin that doesn't follow standard naming.
     *
     * @param string $filename
     * @param string $defaultExtension
     * @param string $derivativeType
     *   The derivative type allows to use a non standard extension.
     *
     * @return string
     *   Filename with the new extension.
     */
    protected function _getDerivativeFilename($filename, $defaultExtension, $derivativeType = null)
    {
        $base = pathinfo($filename, PATHINFO_EXTENSION) ? substr($filename, 0, strrpos($filename, '.')) : $filename;
        $fullExtension = !is_null($derivativeType) && isset($this->_derivativeExtensionsByType[$derivativeType])
            ? $this->_derivativeExtensionsByType[$derivativeType]
            : '.' . $defaultExtension;
        return $base . $fullExtension;
    }

    /**
     * Get the derivative filename from a filename and an extension.
     *
     * @param object $file
     *
     * @return string
     *   Extension used for derivative files (usually "jpg" for images).
     */
    protected function _getDerivativeExtension($file)
    {
        return $file->has_derivative_image ? pathinfo($file->getDerivativeFilename(), PATHINFO_EXTENSION) : '';
    }

    /**
     * Moves/renames a file and its derivatives inside archive/files subfolders.
     *
     * New folders are created if needed. Old folders are removed if empty.
     * No update of the database is done.
     *
     * @param string $currentArchiveFilename
     *   Name of the current archive file to move.
     * @param string $newArchiveFilename
     *   Name of the new archive file, with archive folder if any (usually
     *   "collection/dc:identifier/").
     * @param optional string $derivativeExtension
     *   Extension of the derivative files to move, because it can be different
     *   from the new archive filename and it can't be determined here.
     *
     * @return boolean
     *   true if files are moved, else false.
     */
    protected function _moveFilesInArchiveSubfolders($currentArchiveFilename, $newArchiveFilename, $derivativeExtension = '')
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            return false;
        }

        // Move file only if it is not in the right place.
        // If the main file is at the right place, this is always the case for
        // the derivatives.
        if ($currentArchiveFilename == $newArchiveFilename) {
            return true;
        }

        $currentArchiveFolder = dirname($currentArchiveFilename);
        $newArchiveFolder = dirname($newArchiveFilename);

        // Move the main original file using Omeka API.
        $result = $this->_createArchiveFolders($newArchiveFolder, $this->_getFullArchivePath('original'));
        $operation = new Omeka_Storage_Adapter_Filesystem(array(
            'localDir' => $this->_getFullArchivePath('original'),
        ));
        $operation->move($currentArchiveFilename, $newArchiveFilename);

        // If any, move derivative files using Omeka API.
        if ($derivativeExtension != '') {
            foreach ($this->_getFullArchivePaths() as $derivativeType => $path) {
                // Original is managed above.
                if ($derivativeType == 'original') {
                    continue;
                }
                // We create a folder in any case, even if there isn't any file
                // inside, in order to be fully compatible with any plugin that
                // manages base filename only.
                $result = $this->_createArchiveFolders($newArchiveFolder, $path);

                // Determine the current and new derivative filename, standard
                // or not.
                $currentDerivativeFilename = $this->_getDerivativeFilename($currentArchiveFilename, $derivativeExtension, $derivativeType);
                $newDerivativeFilename = $this->_getDerivativeFilename($newArchiveFilename, $derivativeExtension, $derivativeType);

                // Check if the derivative file exists or not to avoid some
                // errors when moving.
                if (file_exists($path . DIRECTORY_SEPARATOR . $currentDerivativeFilename)) {
                    $operation = new Omeka_Storage_Adapter_Filesystem(array(
                        'localDir' => $path,
                    ));
                    $operation->move($currentDerivativeFilename, $newDerivativeFilename);
                }
            }
        }

        // Remove all old empty folders.
        if ($currentArchiveFolder != $newArchiveFolder) {
            $this->_removeArchiveFolders($currentArchiveFolder);
        }

        return true;
    }

    /**
     * Returns a sanitized string for folder or file path.
     *
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    protected function _sanitizeName($string)
    {
        $string = strip_tags($string);
        // The first character is a space and the last one is a no-break space.
        $string = trim($string, ' /\\?<>:*%|"\'`&; ');
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -250);
    }

    /**
     * Returns a formatted string for folder or file name.
     *
     * @internal The string should be already sanitized.
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *      *
     * @see ArchiveRepertoryPlugin::_sanitizeName()
     *
     * @param string $string The string to sanitize.
     * @param string $format The format to convert to.
     *
     * @return string The sanitized string.
     */
    protected function _convertFilenameTo($string, $format)
    {
        switch ($format) {
            case 'Keep name':
                return $string;
            case 'First letter':
                return $this->_convertFirstLetterToAscii($string);
            case 'Spaces':
                return $this->_convertSpacesToUnderscore($string);
            case 'First and spaces':
                $string = $this->_convertFilenameTo($string, 'First letter');
                return $this->_convertSpacesToUnderscore($string);
            case 'Full':
            default:
                return $this->_convertNameToAscii($string);
        }
    }

    /**
     * Returns an unaccentued string for folder or file name.
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to convert to ascii.
     *
     * @return string The converted string to use as a folder or a file name.
     */
    private function _convertNameToAscii($string)
    {
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -250);
    }

    /**
     * Returns a formatted string for folder or file path (first letter only).
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    private function _convertFirstLetterToAscii($string)
    {
        $first = $this->_convertNameToAscii($string);
        if (empty($first)) {
            return '';
        }
        return $first[0] . $this->_substr_unicode($string, 1);
    }

    /**
     * Returns a formatted string for folder or file path (spaces only).
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    private function _convertSpacesToUnderscore($string)
    {
        return preg_replace('/\s+/', '_', $string);
    }

    /**
     * Get a sub string from a string when mb_substr is not available.
     *
     * @see http://www.php.net/manual/en/function.mb-substr.php#107698
     *
     * @param string $string
     * @param integer $start
     * @param integer $length (optional)
     *
     * @return string
     */
    protected function _substr_unicode($string, $start, $length = null) {
        return join('', array_slice(
            preg_split("//u", $string, -1, PREG_SPLIT_NO_EMPTY), $start, $length));
    }

    /**
     * Checks if the plugin is compatible with the current version of Omeka.
     *
     * @return boolean
     */
    protected function _checkOmekaCompatibility()
    {
        $testName = 'hd1:users/test/v2.1/image.omeka';

        include_once BASE_DIR . '/application/models/File.php';
        $fileTest = new FILE;
        $fileTest->filename = $testName . '.png';
        $compatible = ($fileTest->getDerivativeFilename() == $testName . '.' . FILE::DERIVATIVE_EXT);

        return $compatible;
    }

    /**
     * Checks if all the system (server + php + web environment) allows to
     * manage Unicode filename securely.
     *
     * @internal This function simply checks the true result of functions
     * escapeshellarg() and touch with a non Ascii filename.
     *
     * @return array of issues.
     */
    protected function _checkUnicodeInstallation()
    {
        $result = array();

        // First character check.
        $filename = 'éfilé.jpg';
        if (basename($filename) != $filename) {
            $result['ascii'] = __('An error occurs when testing function "basename(\'%s\')".', $filename);
        }

        // Command line via web check (comparaison with a trivial function).
        $filename = "File~1 -À-é-ï-ô-ů-ȳ-Ø-ß-ñ-Ч-Ł-'.Test.png";

        if (escapeshellarg($filename) != escapeshellarg_special($filename)) {
            $result['cli'] = __('An error occurs when testing function "escapeshellarg(\'%s\')".', $filename);
        }

        // File system check.
        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        if (!(touch($filepath) && file_exists($filepath))) {
            $result['fs'] = __('A file system error occurs when testing function "touch \'%s\'".', $filepath);
        }

        return $result;
    }
}
