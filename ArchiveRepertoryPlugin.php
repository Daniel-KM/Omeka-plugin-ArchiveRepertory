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
        'archive_repertory_add_collection_folder' => TRUE,
        'archive_repertory_collection_folders' => NULL,
        // Items options.
        'archive_repertory_item_folder' => 'id',
        'archive_repertory_item_identifier_prefix' => 'document:',
        'archive_repertory_convert_folder_to_ascii' => TRUE,
        // Files options.
        'archive_repertory_keep_original_filename' => TRUE,
        'archive_repertory_convert_filename_to_ascii' => FALSE,
        'archive_repertory_base_original_filename' => FALSE,
        // Other derivative folders.
        'archive_repertory_derivative_folders' => '',
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
     * Upgrades the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];

        if (version_compare($oldVersion, '2.3', '<')) {
            set_option('archive_repertory_item_folder', (get_option('archive_repertory_add_item_folder')
                ? 'Dublin Core:Identifier'
                : 'None'));
            delete_option('archive_repertory_add_item_folder');
            set_option('archive_repertory_convert_folder_to_ascii', $this->_options['archive_repertory_convert_folder_to_ascii']);
            set_option('archive_repertory_convert_filename_to_ascii', $this->_options['archive_repertory_convert_filename_to_ascii']);
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

        $item_folder = get_option('archive_repertory_item_folder');
        // TODO To simplify with the direct function.
        // Get only Dublin Core elements for select form.
        $listElements = get_db()->getTable('Element')->findPairsForSelectForm(array('item_type_id' => null));
        // It's more sustainable to memorize true name than an internal code
        // and it's simpler to get the normal order.
        $elements = get_db()->getTable('Element')->findBySet('Dublin Core');
        foreach ($elements as $element) {
            foreach ($listElements['Dublin Core'] as $key => $name) {
                if ($element->id == $key) {
                    $listElements['Dublin Core:' . $element->name] = $name;
                    unset($listElements[$key]);
                }
            }
        }
        unset($listElements['Dublin Core']);

        // Check compatibility of the plugin with Omeka.
        include_once BASE_DIR . '/application/models/File.php';
        $fileTest = new FILE;
        $fileTest->filename = 'hd1:users/test/v2.1/image.omeka.png';
        $compatible = ($fileTest->getDerivativeFilename() == 'hd1:users/test/v2.1/image.omeka' . '.' . $fileTest::DERIVATIVE_EXT);

        // Check compatibility of the server with Unicode.
        $allowUnicode = $this->_checkUnicodeInstallation();

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
        set_option('archive_repertory_add_collection_folder', (boolean) $post['archive_repertory_add_collection_folder']);
        set_option('archive_repertory_item_folder', $post['archive_repertory_item_folder']);
        set_option('archive_repertory_item_identifier_prefix', trim($post['archive_repertory_item_identifier_prefix']));
        set_option('archive_repertory_convert_folder_to_ascii', (boolean) $post['archive_repertory_convert_folder_to_ascii']);
        set_option('archive_repertory_keep_original_filename', (boolean) $post['archive_repertory_keep_original_filename']);
        set_option('archive_repertory_convert_filename_to_ascii', (boolean) $post['archive_repertory_convert_filename_to_ascii']);
        set_option('archive_repertory_base_original_filename', (boolean) $post['archive_repertory_base_original_filename']);
        set_option('archive_repertory_derivative_folders', trim($post['archive_repertory_derivative_folders']));

        $collections = get_records('Collection', array(), 0);
        set_loop_records('collections', $collections);
        $collection_names = unserialize(get_option('archive_repertory_collection_folders'));
        foreach (loop('collections') as $collection) {
            $id = 'archive_repertory_collection_folder_' . $collection->id;
            $collection_names[$collection->id] = $this->_sanitizeName($post[$id]);
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
            // We don't use original filename here, because this is managed in
            // hookAfterSavefile() when the file is inserted. Here, the filename
            // is already sanitized.
            $newFilename = $archiveFolder . basename($file->filename);
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
            if (get_option('archive_repertory_base_original_filename')) {
                $file->original_filename = basename($file->original_filename);
            }

            // Rename file only if wanted and needed.
            if (get_option('archive_repertory_keep_original_filename')) {
                // Get the new filename.
                $newFilename = $this->_sanitizeName(basename($file->original_filename));
                if (get_option('archive_repertory_convert_filename_to_ascii')) {
                    $newFilename = $this->_convertNameToAscii($newFilename);
                }

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
        return TRUE;
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
        // Collection folder is created when the module is installed and configured.
        if (get_option('archive_repertory_add_collection_folder') && !empty($item->collection_id)) {
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
                $name = $this->_createItemFolderName($item, array(
                    substr($item_folder, 0, strrpos($item_folder, ':')),
                    substr($item_folder, strrpos($item_folder, ':') + 1),
                ));
        }

        return (get_option('archive_repertory_convert_folder_to_ascii'))
            ? $this->_convertNameToAscii($name) . DIRECTORY_SEPARATOR
            : $name . DIRECTORY_SEPARATOR;
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

        return $this->_sanitizeName($default_name);
    }

    /**
     * Creates a unique name for an item folder from first metadata.
     *
     * If there isn't any identifier with the prefix, the item id will be used.
     * The name is sanitized.
     *
     * @param object $item
     * @param array|string $metadata
     *
     * @return string Unique sanitized name of the item.
     */
    protected function _createItemFolderName($item, $metadata)
    {
        $identifier = metadata($item, $metadata, 0);
        return empty($identifier)
            ? (string) $item->id
            : $this->_sanitizeName($identifier);
    }

    /**
     * Creates a unique name for an item folder from Dublin Core identifier.
     *
     * Default name is the Dublin Core identifier with the selected prefix. If
     * there isn't any identifier with the prefix, the item id will be used.
     * The name is sanitized and the selected prefix is removed.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the item.
     */
    protected function _createItemFolderNameFromDCidentifier($item)
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
        return $this->_sanitizeName($item_identifier);
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
            $prefix = get_option('archive_repertory_item_identifier_prefix');
            $prefix_len = strlen($prefix);
        }

        return (substr($identifier, 0, $prefix_len) == $prefix);
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
                if (strpos($value, '|') === FALSE) {
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
        return TRUE;
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
        return TRUE;
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
     *   TRUE if files are moved, else FALSE.
     */
    protected function _moveFilesInArchiveSubfolders($currentArchiveFilename, $newArchiveFilename, $derivativeExtension = '')
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            return FALSE;
        }

        // Move file only if it is not in the right place.
        // If the main file is at the right place, this is always the case for
        // the derivatives.
        if ($currentArchiveFilename == $newArchiveFilename) {
            return TRUE;
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

        return TRUE;
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
        $string = trim($string, ' /\\?<>:*%|"\'`&;');
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\_\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -250);
    }

    /**
     * Returns a sanitized and unaccentued string for folder or file name.
     *
     * @param string $string The string to convert to ascii.
     *
     * @see ArchiveRepertoryPlugin::_sanitizeName()
     *
     * @return string The converted string to use as a folder or a file name.
     */
    protected function _convertNameToAscii($string)
    {
        $string = $this->_sanitizeName($string);
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -250);
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
        $filename = "File~1 -À-é-ï-ô-ů-ȳ-Ø-ß-ñ-Ч-Ł-'.Test.png";

        /**
         * An ugly, non-ASCII-character safe replacement of escapeshellarg().
         *
         * @see http://www.php.net/manual/function.escapeshellarg.php
         */
        function escapeshellarg_special($string) {
          return "'" . str_replace("'", "'\\''", $string) . "'";
        }

        $result = array();

        // Command line via web check.
        if (escapeshellarg($filename) != escapeshellarg_special($filename)) {
            $result['cli'] = __('- An error occurs when testing function "escapeshellarg(\'%s\')".', $filename);
        }

        // File system check.
        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        if (!(touch($filepath) && file_exists($filepath))) {
            $result['fs'] = __('- A file system error occurs when testing function "touch \'%s\'".', $filepath);
        }

        return $result;
    }
}
