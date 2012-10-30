<?php
/**
 * @version $Id$
 * @copyright Daniel Berthereau for École des Ponts ParisTech, 2012
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package ArchiveRepertory
 */

/**
 * Keep original names of files and put them in a hierarchical structure.
 *
 * @see README.md
 * @see config_form.php
 *
 * @todo Checks names of folders and files and appends item or file id if needed.
 * @todo Extends the collection class with the folder name and with settings.
 * @todo Adds a field or an element for folder of the item?
 * @todo Manages old files when collection folder change.
 * @todo Choice to use only the item id for the name of item folder.
 * @todo Adds tests.
 *
 * Technical notes
 * Three reasons force the division of the process.
 * 1. The process is divided into two sub-processes, so two hooks are used:
 * - before_insert_file(): strict rename of files inside temporary directory;
 * - after_save_item(): move files inside collection item subfolders of archive.
 * 2. This choice of implementation allows to bypass the storage constraint too
 * (File_ProcessUploadJob::perform()).
 * 3. Process order is different when one item is imported via "Add content" and
 * when plugin CsvImport is used. So we need to use a third hook between
 * previous ones:
 * - after_insert_file(): like after_save_item(), but used when files are
 * inserted after item (in CsvImport).
 * Hooks before_insert_file() and after_insert_file() are needed, because
 * creation of derivatives with true name should be separate of moving file in
 * the archive folder.
 */

/**
 * Contains code used to integrate the plugin into Omeka.
 *
 * @package ArchiveRepertory
 */
class ArchiveRepertoryPlugin extends Omeka_Plugin_Abstract
{
    protected $_hooks = array(
        'install',
        'uninstall',
        'admin_append_to_plugin_uninstall_message',
        'config_form',
        'config',
        'after_insert_collection',
        'after_save_item',
        'before_insert_file',
        'before_save_file',
        'after_delete_file',
    );

    protected $_options = array(
        'archive_repertory_add_collection_folder' => TRUE,
        'archive_repertory_collection_folders' => NULL,
        'archive_repertory_add_item_folder' => TRUE,
        'archive_repertory_item_identifier_prefix' => 'item:',
        'archive_repertory_keep_original_filename' => TRUE,
        'archive_repertory_base_original_filename' => TRUE,
    );

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        self::_installOptions();

        // Set default names of collection folders. Folders are created by config.
        $collection_names = array();
        // get_collections() is not available in controller.
        $collections = get_db()->getTable('Collection')->findBy(array(), 10000);
        foreach ($collections as $collection) {
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
        self::_uninstallOptions();
    }

    /**
     * Warns before the uninstallation of the plugin.
     */
    public static function hookAdminAppendToPluginUninstallMessage()
    {
        echo '<p><strong>' . __('Warning') . '</strong>:<br />';
        echo __('Collection and items folders will not be removed and files will not be renamed.') . '<br />';
        echo __('New files will be saved with the default way of Omeka.') . '<br />';
        echo __('Old files will continue to be stored and available as currently.') . '</p>';
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm()
    {
        $collections = get_collections(array(), 10000);
        $collection_names = unserialize(get_option('archive_repertory_collection_folders'));

        include('config_form.php');
    }

    /**
     * Saves plugin configuration page and creates folders if needed.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($post)
    {
        // Save settings.
        set_option('archive_repertory_add_collection_folder', (int) (boolean) $post['archive_repertory_add_collection_folder']);
        set_option('archive_repertory_add_item_folder', (int) (boolean) $post['archive_repertory_add_item_folder']);
        set_option('archive_repertory_item_identifier_prefix', $this->_sanitizeString($post['archive_repertory_item_identifier_prefix']));
        set_option('archive_repertory_keep_original_filename', (int) (boolean) $post['archive_repertory_keep_original_filename']);
        set_option('archive_repertory_base_original_filename', (int) (boolean) $post['archive_repertory_base_original_filename']);

        // get_collections() is not available in controller.
        $collections = get_db()->getTable('Collection')->findBy(array(), 10000);
        $collection_names = unserialize(get_option('archive_repertory_collection_folders'));
        foreach ($collections as $collection) {
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
     *
     * Note: A collection folder name is not changed when the name is updated.
     *
     * @param object $collection
     *
     * @return void.
     */
    public function hookAfterInsertCollection($collection)
    {
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

    /**
     * Manages folders for attached files of items.
     */
    public function hookAfterSaveItem($item)
    {
        // Check if file is at the right place, with collection and item folders.
        $archiveFolder = $this->_getArchiveFolderName($item);

        // Check if files are already attached and if they are at the right place.
        $files = $item->getFiles();
        foreach ($files as $file) {
            // Move file only if it is not in the right place.
            $newFilename = $archiveFolder . basename($file->archive_filename);
            if ($file->archive_filename != $newFilename) {
                if (!$this->_moveFilesInArchive($file->archive_filename, $file->getDerivativeFilename(), $archiveFolder, $file->has_derivative_image)) {
                    throw new Exception(__('Cannot move files inside archive directory.'));
                }

                // Update file in Omeka database immediately for each file.
                $file->archive_filename = $newFilename;
                // As it's not a file hook, the file is not automatically saved.
                $file->save();
            }
        }
    }

    /**
     * Manages strict renaming of a file before saving it.
     */
    public function hookBeforeInsertFile($file)
    {
        // Rename file only if desired and needed.
        if (get_option('archive_repertory_keep_original_filename')) {
            $new_filename = basename($file->original_filename);
            if ($file->archive_filename != $new_filename) {
                $operation = new Omeka_Storage_Adapter_Filesystem(array(
                    'localDir' => sys_get_temp_dir(),
                    'webDir' => sys_get_temp_dir(),
                ));
                $operation->move($file->archive_filename, $new_filename);

                // Update file name in database (automatically done because it's
                // a hook).
                $file->archive_filename = $new_filename;
            }
        }
    }

    /**
     * Manages moving of an attached file after saving it.
     *
     * Files are already renamed in hookBeforeInsertFile(). Here they are moved.
     * Original file name can be cleaned too (user can choose to keep base name
     * only).
     */
    public function hookBeforeSaveFile($file)
    {
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

        // get_item_by_id() is not available in controller.
        $item = get_db()->getTable('Item')->find($file->item_id);
        $archiveFolder = $this->_getArchiveFolderName($item);

        // Move file only if it is not in the right place...
        $newFilename = $archiveFolder . basename($file->archive_filename);
        if (($file->archive_filename != $newFilename)
                // and if it's already in the archive folder...
                && is_file(FILES_DIR . DIRECTORY_SEPARATOR . basename($file->archive_filename))
                // and not in subfolder.
                && (basename($file->archive_filename) == $file->archive_filename)
            ) {
            if (!$this->_moveFilesInArchive($file->archive_filename, $file->getDerivativeFilename(), $archiveFolder, $file->has_derivative_image)) {
                throw new Exception(__('Cannot move files inside archive directory.'));
            }

            // Update file in database (automatically done in this hook).
            $file->archive_filename = $newFilename;
        }
    }

    /**
     * Manages deletion of the folder of a file when this file is removed.
     */
    public function hookAfterDeleteFile($file)
    {
        // get_item_by_id() is not available in controller.
        $item = get_db()->getTable('Item')->find($file->item_id);
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
        $default_name = trim(strtok(trim($collection->name), " \n\r\t"));

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
        // item() or itemMetadata() are not available in controller, so we need
        // to load all helpers to get all Dublin Core identifiers of the item.
        require_once HELPERS;
        $identifiers = item('Dublin Core', 'Identifier', array('all' => TRUE), $item);
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
    private function _filteredIdentifier($identifier) {
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
            foreach (array(
                    FILES_DIR,
                    FULLSIZE_DIR,
                    THUMBNAIL_DIR,
                    SQUARE_THUMBNAIL_DIR,
                ) as $path) {
                $fullpath = $path . DIRECTORY_SEPARATOR . $archiveFolder;
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
            foreach (array(
                    FILES_DIR,
                    FULLSIZE_DIR,
                    THUMBNAIL_DIR,
                    SQUARE_THUMBNAIL_DIR,
                ) as $path) {
                $fullpath = $path . DIRECTORY_SEPARATOR . $archiveFolder;
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
        $operation = new Omeka_Storage_Adapter_Filesystem(array('localDir' => FILES_DIR));
        $operation->move($archiveFilename, $newArchiveFilename);

        // If any, move derivative files using Omeka API.
        if ($hasDerivativeImage) {
            $newDerivativeFilename = $archiveFolder . basename($derivativeFilename);
            foreach (array(
                    FULLSIZE_DIR,
                    THUMBNAIL_DIR,
                    SQUARE_THUMBNAIL_DIR,
                ) as $path) {
                // Check if the derivative file exists or not to avoid some
                // errors when moving.
                if (file_exists($path . DIRECTORY_SEPARATOR . $derivativeFilename)) {
                    $operation = new Omeka_Storage_Adapter_Filesystem(array('localDir' => $path));
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
                    throw new Omeka_Storage_Exception(__('Error directory non writable:') . " '$path'");
                }
                throw new Omeka_Storage_Exception(__('Failed to create folder "%s": a file with the same name exists...', $path));
            }

            if (!@mkdir($path, 0755, TRUE)) {
                throw new Omeka_Storage_Exception(__('Error making directory:') . " '$path'");
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
    private function _sanitizeString($string) {
        $string = trim(strip_tags($string));
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:uml|circ|tilde|acute|grave|cedil|ring)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\(\)\[\]_\-\.#~@+:]/', '_', $string);
        return preg_replace('/_+/', '_', $string);
    }
}

/** Installation of the plugin. */
$archiveRepertory = new ArchiveRepertoryPlugin();
$archiveRepertory->setUp();
