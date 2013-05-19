<?php
/**
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * @copyright Daniel Berthereau, 2012-2013
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt
 * @package ArchiveRepertory
 */

/**
 * Contains code used to integrate the plugin into Omeka.
 *
 * @package ArchiveRepertory
 */
class ArchiveRepertoryPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array This plugin's hooks.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'config_form',
        'config',
        'after_save_collection',
        'after_save_item',
        'before_save_file',
        'after_delete_file',
    );

    /**
     * @var array This plugin's options.
     */
    protected $_options = array(
        'archive_repertory_add_collection_folder' => TRUE,
        'archive_repertory_collection_folders' => NULL,
        'archive_repertory_add_item_folder' => TRUE,
        'archive_repertory_item_identifier_prefix' => 'document:',
        'archive_repertory_keep_original_filename' => TRUE,
        'archive_repertory_base_original_filename' => TRUE,
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
     * Installs the plugin.
     */
    public function hookInstall()
    {
        $this->_installOptions();

        // Set default names of collection folders. Folders are created by config.
        $collection_names = array();

        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);
        foreach (loop('collections') as $collection) {
            $collection_names[$collection->id] = $this->_createCollectionDefaultName($collection);

            // Names should be saved immediately to avoid side effects if other
            // similar names are created.
            set_option('archive_repertory_collection_folders', serialize($collection_names));
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
        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);
        $collection_names = unserialize(get_option('archive_repertory_collection_folders'));

        require 'config_form.php';
    }

    /**
     * Saves plugin configuration page and creates folders if needed.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];

        // Save settings.
        set_option('archive_repertory_add_collection_folder', (int) (boolean) $post['archive_repertory_add_collection_folder']);
        set_option('archive_repertory_add_item_folder', (int) (boolean) $post['archive_repertory_add_item_folder']);
        set_option('archive_repertory_item_identifier_prefix', $this->_sanitizeString($post['archive_repertory_item_identifier_prefix']));
        set_option('archive_repertory_keep_original_filename', (int) (boolean) $post['archive_repertory_keep_original_filename']);
        set_option('archive_repertory_base_original_filename', (int) (boolean) $post['archive_repertory_base_original_filename']);

        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);
        $collection_names = unserialize(get_option('archive_repertory_collection_folders'));
        foreach (loop('collections') as $collection) {
            $id = 'archive_repertory_collection_folder_' . $collection->id;
            $collection_names[$collection->id] = $this->_sanitizeString(trim($post[$id], ' /\\'));
        }
        set_option('archive_repertory_collection_folders', serialize($collection_names));

        // Create collection folders if needed.
        if (get_option('archive_repertory_add_collection_folder')) {
            foreach ($collection_names as $folder) {
                $result = $this->_createArchiveFolders($folder);
            }
        }
    }

    /**
     * Create a collection folder when a collection is created.
     */
    public function hookAfterSaveCollection($args)
    {
        $post = $args['post'];
        $collection = $args['record'];

        // Insert a record.
        if ($args['insert']) {
            // Create the collection folder name.
            $collection_names = unserialize(get_option('archive_repertory_collection_folders'));
            if (!isset($collection_names[$collection->id])) {
                $collection_names[$collection->id] = $this->_createCollectionDefaultName($collection);
                set_option('archive_repertory_collection_folders', serialize($collection_names));
            }

            // Create collection folder.
            if (get_option('archive_repertory_add_collection_folder')) {
                $result = $this->_createArchiveFolders($collection_names[$collection->id]);
            }
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
            $newFilename = $archiveFolder . basename($file->filename);
            if ($file->filename != $newFilename) {
                $result = $this->_moveFilesInArchive($file->filename, $file->getDerivativeFilename(), $archiveFolder, $file->has_derivative_image);
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
     * Manages moving of an attached file before saving it.
     *
     * Original file name can be cleaned too (user can choose to keep base name
     * only).
     *
     * Technical notes
     * The process is divided into two times:
     * - strict renaming of files when file is inserted;
     * - moving files inside collection and item subfolders.
     * This process order is needed, because the process is different when one
     * item is imported via "Add content" and when plugin CsvImport is used.
     * This choice of implementation allows to bypass the storage constraint too
     * (File_ProcessUploadJob::perform()) and to manage creation of derivatives.
     */
    public function hookBeforeSaveFile($args)
    {
        $post = $args['post'];
        $file = $args['record'];

        // Files are renamed to their original name during insert.
        if ($args['insert']) {
            // Rename file only if desired and needed.
            if (get_option('archive_repertory_keep_original_filename')) {
                $new_filename = basename($file->original_filename);

                if ($file->filename != $new_filename) {
                    $operation = new Omeka_Storage_Adapter_Filesystem(array(
                        'localDir' => sys_get_temp_dir(),
                        'webDir' => sys_get_temp_dir(),
                    ));
                    $operation->move($file->filename, $new_filename);

                    // Update file name in database (automatically done because
                    // it's a hook).
                    $file->filename = $new_filename;
                }
            }
        }
        // After every record save, files are moved to their folder if needed.
        else {
            // Rename file only if desired.
            if (!get_option('archive_repertory_keep_original_filename')) {
                return;
            }

            // Check if file is already attached, so we can check folder name.
            if (empty($file->item_id)) {
                return;
            }

            if (get_option('archive_repertory_base_original_filename')) {
                $file->original_filename = basename($file->original_filename);
            }

            $item = $file->getItem();
            $archiveFolder = $this->_getArchiveFolderName($item);
            // Move file only if it is not in the right place...
            $newFilename = $archiveFolder . basename($file->filename);

            if (($file->filename != $newFilename)
                    // and if it's already in the archive folder...
                    && is_file(FILES_DIR . DIRECTORY_SEPARATOR . self::$_pathsByType['original'] . DIRECTORY_SEPARATOR . basename($file->filename))
                    // and not in subfolder.
                    && (basename($file->filename) == $file->filename)
                ) {
                $result = $this->_moveFilesInArchive($file->filename, $file->getDerivativeFilename(), $archiveFolder, $file->has_derivative_image);
                if (!$result) {
                    throw new Exception(__('Cannot move files inside archive directory.'));
                }

                // Update file in database (automatically done in this hook).
                $file->filename = $newFilename;
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
        return TRUE;
    }

    /**
     * Gets archive folder name of an item, that depends on activation of options.
     *
     * @param object $item
     *
     * @return string Unique and sanitized name folder name of the item.
     */
    private function _getArchiveFolderName($item)
    {
        $collection_folder = $this->_getCollectionFolderName($item);
        $item_folder = $this->_getItemFolderName($item);
        return $collection_folder . $item_folder;
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
    private function _createCollectionDefaultName($collection)
    {
        $collection_names = unserialize(get_option('archive_repertory_collection_folders'));
        if ($collection_names === FALSE) {
            $collection_names = array();
        }
        else {
            // Remove the current collection id to simplify check.
            unset($collection_names[$collection->id]);
        }

        // Default name is the first word of the collection name.
        $default_name = trim(strtok(trim(metadata($collection, array('Dublin Core', 'Title'))), " \n\r\t"));

        // If this name is already used, the id is added until name is unique.
        While (in_array($default_name, $collection_names)) {
            $default_name .= '_' . $collection->id;
        }

        return $this->_sanitizeString($default_name);
    }

    /**
     * Gets collection folder name from an item.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the collection.
     */
    private function _getCollectionFolderName($item)
    {
        // Collection folder is created when the module is installed and configured.
        if (get_option('archive_repertory_add_collection_folder') && ($item->collection_id !== NULL)) {
            $collection_names = unserialize(get_option('archive_repertory_collection_folders'));
            $collection = $collection_names[$item->collection_id];
            if ($collection != '') {
                $collection .= DIRECTORY_SEPARATOR;
            }
        }
        else {
          $collection = '';
        }

        return $collection;
    }

    /**
     * Creates a unique name for an item folder.
     *
     * Default name is the Dublin Core identifier with the selected prefix. If
     * there isn't any identifier with the prefix, the item id will be used.
     * The name is sanitized and the selected prefix is removed.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the item.
     */
    private function _createItemFolderName($item)
    {
        $identifiers = metadata($item, array('Dublin Core', 'Identifier'), 'all');
        if (empty($identifiers)) {
            return (string) $item->id;
        }

        // Get all identifiers with the chosen prefix in case they are multiple.
        $filtered_identifiers = array_values(array_filter($identifiers, 'self::_filteredIdentifier'));
        if (!isset($filtered_identifiers[0])) {
            return (string) $item->id;
        }

        // Keep only the first identifier with the configured prefix.
        $prefix = get_option('archive_repertory_item_identifier_prefix');
        $item_identifier = substr($filtered_identifiers[0], strlen($prefix));
        return $this->_sanitizeString($item_identifier);
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
    private function _filteredIdentifier($identifier)
    {
        static $prefix;
        static $prefix_len;

        if ($prefix === null) {
            $prefix = get_option('archive_repertory_item_identifier_prefix');
            $prefix_len = strlen($prefix);
        }

        return (substr($identifier, 0, $prefix_len) == $prefix);
    }

    /**
     * Gets item folder name from an item and create folder if needed.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the item.
     */
    private function _getItemFolderName($item)
    {
        if (get_option('archive_repertory_add_item_folder')) {
            return $this->_createItemFolderName($item) . DIRECTORY_SEPARATOR;
        }

        return '';
    }

    /**
     * Checks if the folders exist in the archive repertory, then creates them.
     *
     * @param string $archiveFolder Name of folder to create, without archive_dir.
     *
     * @return boolean True if the path is created, Exception if an error occurs.
     */
    private function _createArchiveFolders($archiveFolder)
    {
        if ($archiveFolder != '') {
            foreach (self::$_pathsByType as $path) {
                $fullpath = FILES_DIR . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $archiveFolder;
                $result = $this->createFolder($fullpath);
            }
        }
        return TRUE;
    }

    /**
     * Removes empty folders in the archive repertory.
     *
     * @param string $archiveFolder Name of folder to delete, without archive_dir.
     *
     * @return boolean True if the path is created, Exception if an error occurs.
     */
    private function _removeArchiveFolders($archiveFolder)
    {
        if (($archiveFolder != '.')
                && ($archiveFolder != '..')
                && ($archiveFolder != DIRECTORY_SEPARATOR)
                && ($archiveFolder != '')
            ) {
            foreach (self::$_pathsByType as $path) {
                $fullpath = FILES_DIR . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $archiveFolder;
                if (realpath($path) != realpath($fullpath)) {
                    $this->removeFolder($fullpath);
                }
            }
        }
        return TRUE;
    }

    /**
     * Moves, without renaming, a file and derivative files inside archive folder.
     *
     * New folders are created if needed. Old folders are removed if empty.
     * No update of the database is done.
     *
     * @param string $archiveFilename
     *   Name of the archive file to move.
     * @param string $derivativeFilename
     *   Name of the derivative files to move, because it can be different and it
     *   can't be determined inside this helper.
     * @param string $archiveFolder
     *   Name of the folder, finishing with the directory separator, where to move
     *   files, without archive_dir, usually "collection/dc:identifier" when this
     *   plugin is enabled.
     * @param boolean $hasDerivativeImage
     *   Move derivative images too, if any.
     *
     * @return boolean
     *   TRUE if files are moved, else FALSE.
     */
    private function _moveFilesInArchive($archiveFilename, $derivativeFilename, $archiveFolder, $hasDerivativeImage)
    {
        if ($archiveFilename == '' || $derivativeFilename == '' || $archiveFolder == '') {
            return FALSE;
        }

        // Move file only if it is not in the right place.
        $newArchiveFilename = $archiveFolder . basename($archiveFilename);
        if ($archiveFilename == $newArchiveFilename) {
            return TRUE;
        }

        // Create path if needed.
        $result = $this->_createArchiveFolders($archiveFolder);

        // Move original file using Omeka API.
        $operation = new Omeka_Storage_Adapter_Filesystem(array(
            'localDir' => FILES_DIR . DIRECTORY_SEPARATOR . self::$_pathsByType['original'],
        ));
        $operation->move($archiveFilename, $newArchiveFilename);

        // If any, move derivative files using Omeka API.
        if ($hasDerivativeImage) {
            $newDerivativeFilename = $archiveFolder . basename($derivativeFilename);
            foreach (self::$_pathsByType as $key => $path) {
                // Original is managed above.
                if ($key == 'original') {
                    continue;
                }
                // Check if the derivative file exists or not to avoid some
                // errors when moving.
                if (file_exists(FILES_DIR . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $derivativeFilename)) {
                    $operation = new Omeka_Storage_Adapter_Filesystem(array(
                        'localDir' => FILES_DIR . DIRECTORY_SEPARATOR . $path,
                    ));
                    $operation->move($derivativeFilename, $newDerivativeFilename);
                }
            }
        }

        // Remove all old empty folders.
        $oldFolder = dirname($archiveFilename);
        if ($oldFolder != $newFolder) {
            $this->_removeArchiveFolders($oldFolder);
        }

        return TRUE;
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
    public static function createFolder($path)
    {
        if ($path != '') {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    chmod($path, 0755);
                    if (is_writable($path)) {
                        return TRUE;
                    }
                    throw new Omeka_Storage_Exception(__('Error directory non writable: "%s".', $path));
                }
                throw new Omeka_Storage_Exception(__('Failed to create folder "%s": a file with the same name exists...', $path));
            }

            if (!@mkdir($path, 0755, TRUE)) {
                throw new Omeka_Storage_Exception(__('Error making directory: "%s".', $path));
            }
            chmod($path, 0755);
        }
        return TRUE;
    }

    /**
     * Checks and removes an empty folder.
     *
     * @note Currently, Omeka API doesn't provide a function to remove a folder.
     *
     * @param string $path Full path of the folder to remove.
     *
     * @return void.
     */
    public static function removeFolder($path)
    {
        $path = realpath($path);
        if (file_exists($path)
                && is_dir($path)
                && is_readable($path)
                && (count(@scandir($path)) == 2) // Only '.' and '..'.
                && is_writable($path)
            ) {
            @rmdir($path);
        }
    }

    /**
     * Returns a sanitized and unaccentued string for folder or file path.
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string to use as a folder or a file name.
     */
    private function _sanitizeString($string)
    {
        $string = trim(strip_tags($string));
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:uml|circ|tilde|acute|grave|cedil|ring)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\(\)\[\]_\-\.#~@+:]/', '_', $string);
        return preg_replace('/_+/', '_', $string);
    }
}
