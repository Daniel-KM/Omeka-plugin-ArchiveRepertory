<?php
/**
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * @copyright Copyright Daniel Berthereau, 2012-2016
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
        'define_routes',
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
        'archive_repertory_collection_folder' => 'id',
        'archive_repertory_collection_prefix' => '',
        'archive_repertory_collection_names' => 'a:0:{}',
        'archive_repertory_collection_convert' => 'Full',
        // Items options.
        'archive_repertory_item_folder' => 'id',
        'archive_repertory_item_prefix' => '',
        'archive_repertory_item_convert' => 'Full',
        // Files options.
        'archive_repertory_file_keep_original_name' => true,
        'archive_repertory_file_convert' => 'Full',
        'archive_repertory_file_base_original_name' => false,
        // Other derivative folders.
        'archive_repertory_derivative_folders' => '',
        'archive_repertory_move_process' => 'internal',
        // Max download without captcha (default to 30 MB).
        'archive_repertory_download_max_free_download' => 30000000,
        'archive_repertory_legal_text' => 'I agree with terms of use.',
    );

    /**
     * Default folder paths for each default type of files/derivatives.
     *
     * @see application/models/File::_pathsByType()
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

        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'upgrade.php';
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
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'plugins/archive-repertory-config-form.php',
            array(
                'allow_unicode' => $this->_checkUnicodeInstallation(),
                'local_storage' => $this->_getLocalStoragePath(),
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
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                set_option($optionKey, $post[$optionKey]);
            }
        }

        // Unlike items, collections are few and stable, so they are kept as an
        // option.
        $this->_setCollectionFolderNames();
        $this->_createCollectionFolders();
    }

    /**
     * Defines route for direct download count.
     */
    public function hookDefineRoutes($args)
    {
        // ".htaccess" always redirects direct downloads to a public url.
        if (is_admin_theme()) {
            return;
        }

        $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . '/routes.ini', 'routes'));
    }

    /**
     * Create a collection folder when a collection is created.
     *
     * @todo Add a job to process the move of items when the name of a
     * collection changes or when a collection is removed.
     */
    public function hookAfterSaveCollection($args)
    {
        $post = $args['post'];
        $collection = $args['record'];

        // Create or update the collection folder name.
        $this->_setCollectionFolderName($collection);

        // Create collection folder if needed.
        if (get_option('archive_repertory_collection_convert') != 'None') {
            $collectionNames = unserialize(get_option('archive_repertory_collection_names'));
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
            // hookAfterSaveFile() when the file is inserted. Here, the filename
            // is already sanitized.
            $newFilename = $archiveFolder . basename_special($file->filename);
            if ($file->filename != $newFilename) {
                // Check if the original file exists, else this is an undetected
                // error during the convert process.
                $path = $this->_getFullArchivePath('original');
                if (!file_exists($path . DIRECTORY_SEPARATOR . $file->filename)) {
                    $msg = __('File "%s" [%s] is not present in the original directory.', $file->filename, $file->original_filename);
                    $msg .= ' ' . __('There was an undetected error before storage, probably during the convert process.');
                    throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
                }

                $result = $this->_moveFilesInArchiveSubfolders(
                    $file->filename,
                    $newFilename,
                    $this->_getDerivativeExtension($file));
                if (!$result) {
                    $msg = __('Cannot move files inside archive directory.');
                    throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
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
     *
     * If the name is a duplicate one, a suffix is added.
     */
    public function hookAfterSaveFile($args)
    {
        // Avoid multiple renames of a file.
        static $processedFiles = array();

        $post = $args['post'];
        $file = $args['record'];

        // Files can't be moved during insert, because has_derivative is set
        // just after it. Of course, this can be bypassed, but we don't.
        if (!$args['insert']) {
            // Check stored file status.
            if ($file->stored == 0) {
                return;
            }

            // Check if file is processed.
            if (isset($processedFiles[$file->id])) {
                return;
            }

            // Check if file is a previous inserted file (check a value that
            // does not exist in an already saved file).
            if (!isset($file->_storage)) {
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
                $newFilename = $this->_convertFilenameTo($newFilename, get_option('archive_repertory_file_convert'));

                // Move file only if the name is a new one.
                $item = $file->getItem();
                $archiveFolder = $this->_getArchiveFolderName($item);
                $newFilename = $archiveFolder . $newFilename;
                $newFilename = $this->_checkExistingFile($newFilename);
                if ($file->filename != $newFilename) {
                    $result = $this->_moveFilesInArchiveSubfolders(
                        $file->filename,
                        $newFilename,
                        $this->_getDerivativeExtension($file));
                    if (!$result) {
                        $msg = __('Cannot move file inside archive directory.');
                        throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
                    }

                    // Update filename.
                    $file->filename = $newFilename;
                }
            }

            $processedFiles[$file->id] = true;

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
     * Gets identifiers of a record (with prefix if any, and only them).
     *
     * @param Record $record A collection or an item.
     * @param string $folder Optional. Allow to select a specific folder instead
     * of the default one.
     * @param boolean $first Optional. Allow to return only the first value.
     *
     * @return string|array.
     */
    protected function _getRecordIdentifiers($record, $folder = null, $first = false)
    {
        $recordType = get_class($record);
        switch ($recordType) {
            case 'Collection':
                $folder = is_null($folder) ? get_option('archive_repertory_collection_folder') : $folder;
                $prefix = get_option('archive_repertory_collection_prefix');
                break;
            case 'Item':
                $folder = is_null($folder) ? get_option('archive_repertory_item_folder') : $folder;
                $prefix = get_option('archive_repertory_item_prefix');
                break;
            default:
                return array();
        }

        switch ($folder) {
            case '':
            case 'None':
                return array();
            case 'id':
                return array((string) $record->id);
            default:
                // Use a direct query in order to improve speed.
                $db = $this->_db;
                $select = $db->select()
                    ->from($db->ElementText, array('text'))
                    ->where('element_id = ?', $folder)
                    ->where('record_type = ?', $recordType)
                    ->where('record_id = ?', $record->id)
                    ->order('id');
                if ($prefix) {
                    $select->where('text LIKE ?', $prefix . '%');
                }
                if ($first) {
                    $select->limit(1);
                    $identifiers = $db->fetchOne($select);
                }
                else {
                    $identifiers = $db->fetchCol($select);
                }
                return $identifiers;
        }
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
        $collectionFolder = $this->_getCollectionFolderName($item);
        $itemFolder = $this->_getItemFolderName($item);
        return $collectionFolder . $itemFolder;
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
        $name = '';

        // Collection folders are created when the module is configured.
        if (get_option('archive_repertory_collection_convert') && !empty($item->collection_id)) {
            $collectionNames = unserialize(get_option('archive_repertory_collection_names'));
            if (isset($collectionNames[$item->collection_id])) {
                $name = $collectionNames[$item->collection_id];
                if ($name != '') {
                    $name .= DIRECTORY_SEPARATOR;
                }
            }
        }

        return $name;
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
        $folder = get_option('archive_repertory_item_folder');

        switch ($folder) {
            case 'id':
                return (string) $item->id . DIRECTORY_SEPARATOR;
            case 'none':
            case '':
                return '';
            default:
                $name = $this->_getRecordFolderNameFromMetadata(
                    $item,
                    $folder,
                    get_option('archive_repertory_item_prefix')
                );
        }

        return $this->_convertFilenameTo($name, get_option('archive_repertory_item_convert')) . DIRECTORY_SEPARATOR;
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
        $folder = get_option('archive_repertory_collection_folder');
        switch ($folder) {
            case 'id':
                $collectionName = (string) $collection->id;
                break;
            case 'none':
            case '':
                $collectionName = '';
                break;
            default:
                $collectionName = $this->_getRecordFolderNameFromMetadata(
                    $collection,
                    $folder,
                    get_option('archive_repertory_collection_prefix')
                );
                $collectionName = $this->_sanitizeName($collectionName);
                break;
        }

        $collectionNames = unserialize(get_option('archive_repertory_collection_names'));
        $collectionNames[$collection->id] = $this->_convertFilenameTo(
            $collectionName,
            get_option('archive_repertory_collection_convert'));

        set_option('archive_repertory_collection_names', serialize($collectionNames));
    }

    /**
     * Creates a unique name for a record folder from first metadata.
     *
     * If there isn't any identifier with the prefix, the record id will be used.
     * The name is sanitized and the possible prefix is removed.
     *
     * @param object $record
     * @param integer $elementId
     * @param string $prefix
     *
     * @return string Unique sanitized name of the record.
     */
    protected function _getRecordFolderNameFromMetadata($record, $elementId, $prefix)
    {
        $identifier = $this->_getRecordIdentifiers($record, null, true);
        if ($identifier && $prefix) {
            $identifier = trim(substr($identifier, strlen($prefix)));
        }
        return empty($identifier)
            ? (string) $record->id
            : $this->_sanitizeName($identifier);
    }

    /**
     * Create collection folders if needed.
     *
     * @return void.
     */
    protected function _createCollectionFolders()
    {
        if (get_option('archive_repertory_collection_convert') != 'None') {
            $collections = get_records('Collection', array(), 0);
            $collectionNames = unserialize(get_option('archive_repertory_collection_names'));
            set_loop_records('collections', $collections);
            foreach (loop('collections') as $collection) {
                $result = $this->_createArchiveFolders($collectionNames[$collection->id]);
            }
        }
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
                    @chmod($path, 0755);
                    if (is_writable($path)) {
                        return true;
                    }
                    $msg = __('Error directory non writable: "%s".', $path);
                    throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
                }
                $msg = __('Failed to create folder "%s": a file with the same name exists...', $path);
                throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
            }

            if (!@mkdir($path, 0755, true)) {
                $msg = __('Error making directory: "%s".', $path);
                throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
            }
            @chmod($path, 0755);
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
     * Get the local storage path (by default FILES_DIR).
     */
    protected function _getLocalStoragePath()
    {
        $adapterOptions = Zend_Registry::get('storage')->getAdapter()->getOptions();
        return $adapterOptions['localDir'];
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
            $storagePath = $this->_getLocalStoragePath();
            foreach (self::$_pathsByType as $name => $path) {
                $archivePaths[$name] = $storagePath . DIRECTORY_SEPARATOR . $path;
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
                $path = realpath($storagePath . DIRECTORY_SEPARATOR . $name);
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
     *   true if files are moved, else throw Omeka_Storage_Exception.
     */
    protected function _moveFilesInArchiveSubfolders($currentArchiveFilename, $newArchiveFilename, $derivativeExtension = '')
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            $msg = __('Cannot move file inside archive directory: no filename.');
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        // Move file only if it is not in the right place.
        // If the main file is at the right place, this is always the case for
        // the derivatives.
        if ($currentArchiveFilename == $newArchiveFilename) {
            return true;
        }

        $currentArchiveFolder = dirname($currentArchiveFilename);
        $newArchiveFolder = dirname($newArchiveFilename);

        // Move the original file.
        $path = $this->_getFullArchivePath('original');
        $result = $this->_createArchiveFolders($newArchiveFolder, $path);
        $this->_moveFile($currentArchiveFilename, $newArchiveFilename, $path);

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
                    $this->_moveFile($currentDerivativeFilename, $newDerivativeFilename, $path);
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
     * Process the move operation according to admin choice.
     *
     * @return boolean True if success, else throw Omeka_Storage_Exception.
     */
    protected function _moveFile($source, $destination, $path)
    {
        $realSource = $path . DIRECTORY_SEPARATOR . $source;
        if (!file_exists($realSource)) {
            $msg = __('Error during move of a file from "%s" to "%s" (local dir: "%s"): source does not exist.',
                $source, $destination, $path);
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        $result = null;
        try {
            switch (get_option('archive_repertory_move_process')) {
                // Move file directly.
                case 'direct':
                    $realDestination = $path . DIRECTORY_SEPARATOR . $destination;
                    $result = rename($realSource, $realDestination);
                    break;

                // Move the main original file using Omeka API.
                case 'internal':
                default:
                    $operation = new Omeka_Storage_Adapter_Filesystem(array(
                        'localDir' => $path,
                    ));
                    $operation->move($source, $destination);
                    $result = true;
                    break;
            }
        } catch (Omeka_Storage_Exception $e) {
            $msg = __('Error during move of a file from "%s" to "%s" (local dir: "%s").',
                $source, $destination, $path);
            throw new Omeka_Storage_Exception($e->getMessage() . "\n" . '[ArchiveRepertory] ' . $msg);
        }

        return $result;
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
     * Checks if the file is a duplicate one. In that case, a suffix is added.
     *
     * Check is done on the basename, without extension, to avoid issues with
     * derivatives.
     *
     * @internal No check via database, because the file can be unsaved yet.
     *
     * @param string $filename
     *
     * @return string
     * The unique filename, that can be the same as input name.
     */
    protected function _checkExistingFile($filename)
    {
        // Get the partial path.
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);

        // Get the real archive path.
        $filepath = $this->_getFullArchivePath('original') . DIRECTORY_SEPARATOR . $filename;
        $folder = pathinfo($filepath, PATHINFO_DIRNAME);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        // Check folder for file with any extension or without any extension.
        $checkName = $name;
        $i = 1;
        while (glob($folder . DIRECTORY_SEPARATOR . $checkName . '{.*,.,\,,}', GLOB_BRACE)) {
            $checkName = $name . '.' . $i++;
        }

        return ($dirname ? $dirname . DIRECTORY_SEPARATOR : '')
            . $checkName
            . ($extension ? '.' . $extension : '');
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
