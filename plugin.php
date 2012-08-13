<?php
/**
 * @version $Id$
 * @copyright Copyright (c) 2012 Daniel Berthereau for École des Ponts ParisTech
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt
 * @package ArchiveRepertory
 */

/**
 * Keep original names of files and put them in a hierarchical structure.
 *
 * @see README.md
 * @see config_form.php
 *
 * TODO Checks names of folders and files and appends item or file id if needed.
 * TODO Extends the collection class with the folder name and with settings.
 * TODO Adds a field or an element for folder of the item?
 * TODO Manages old files when collection folder or identifier change.
 * TODO Choice to use only the item id for the name of item folder.
 *
 * Technical notes
 * The process is divided into two sub-processes, and two hooks are used:
 * - before_insert_file(): strict rename of files inside temporary directory;
 * - after_save_item(): move files inside collection item subfolders of archive.
 * This choice of implementation allows to bypass the storage constraint too
 * (File_ProcessUploadJob::perform()).
 */

/** Plugin version number */
define('ARCHIVE_REPERTORY_PLUGIN_VERSION', get_plugin_ini('ArchiveRepertory', 'version'));

/** Installation of the plugin. */
$archiverepertory = new ArchiveRepertoryPlugin();
$archiverepertory->setUp();

/**
 * Contains code used to integrate Archive Repertory into Omeka.
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
  );

  protected $_filters = array(
  );

  /**
   * Installs the Archive Repertory plugin.
   */
  public static function hookInstall()
  {
    set_option('archive_repertory_add_collection_folder', (int) TRUE);
    set_option('archive_repertory_add_item_folder', (int) TRUE);
    set_option('archive_repertory_item_identifier_prefix', 'item:');
    set_option('archive_repertory_keep_original_filename', (int) TRUE);

    // Set default names of collection folders. Folders are created by config.
    $collection_folders = unserialize(get_option('archive_repertory_collection_folders'));
    if ($collection_folders === FALSE) {
      $collection_folders = array();
      set_option('archive_repertory_collection_folders', serialize($collection_folders));
    }
    $collections = get_collections(array(), 100000);
    foreach ($collections as $collection) {
      if (!isset($collection_folders[$collection->id])) {
        $collection_folders[$collection->id] = self::_createCollectionFolderName($collection);
      }
      // Names should be saved immediately to avoid side effects if other
      // similar names are created.
      set_option('archive_repertory_collection_folders', serialize($collection_folders));
    }
  }

  /**
   * Uninstalls the Archive Repertory plugin.
   */
  public static function hookUninstall()
  {
    delete_option('archive_repertory_add_collection_folder');
    delete_option('archive_repertory_add_item_folder');
    delete_option('archive_repertory_item_identifier_prefix');
    delete_option('archive_repertory_keep_original_filename');
    delete_option('archive_repertory_collection_folders');
  }

  /**
   * Warns before the uninstallation of the Archive Repertory plugin.
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
  public static function hookConfigForm()
  {
    $collections = get_collections(array(), 100000);
    $collection_folders = unserialize(get_option('archive_repertory_collection_folders'));

    include('config_form.php');
  }

  /**
   * Saves plugin configuration page and creates folders if needed.
   *
   * @param array Options set in the config form.
   */
  public static function hookConfig($post)
  {
    // Save settings.
    set_option('archive_repertory_add_collection_folder', (int) (boolean) $post['archive_repertory_add_collection_folder']);
    set_option('archive_repertory_add_item_folder', (int) (boolean) $post['archive_repertory_add_item_folder']);
    set_option('archive_repertory_item_identifier_prefix', trim($post['archive_repertory_item_identifier_prefix']));
    set_option('archive_repertory_keep_original_filename', (int) (boolean) $post['archive_repertory_keep_original_filename']);

    $collections = get_collections(array(), 10000);
    $collection_folders = unserialize(get_option('archive_repertory_collection_folders'));
    foreach ($collections as $collection) {
      $id = 'archive_repertory_collection_folder_' . $collection->id;
      $collection_folders[$collection->id] = trim($post[$id], '/\\');
    }
    set_option('archive_repertory_collection_folders', serialize($collection_folders));

    // Create collection folders if needed.
    if (get_option('archive_repertory_add_collection_folder')) {
      foreach ($collection_folders as $folder) {
        $result = self::_createPathsInArchive($folder);
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
  public static function hookAfterInsertCollection($collection)
  {
    // Create the collection folder name.
    $collection_folders = unserialize(get_option('archive_repertory_collection_folders'));
    if (!isset($collection_folders[$collection->id])) {
      $collection_folders[$collection->id] = self::_createCollectionFolderName($collection);
      set_option('archive_repertory_collection_folders', serialize($collection_folders));
    }

    // Create collection folder.
    if (get_option('archive_repertory_add_collection_folder')) {
      $result = self::_createPathsInArchive($collection_folders[$collection->id]);
    }
  }

  /**
   * Manages folders for attached files of items.
   */
  public static function hookAfterSaveItem($item)
  {
    // Check if file is at the right place, with collection and item folders.
    $collection_folder = self::_getCollectionFolderName($item);
    $item_folder = self::_getItemFolderName($item);
    $folder = $collection_folder . $item_folder;
    // Create path if needed.
    $result = self::_createPathsInArchive($folder);

    $files = $item->getFiles();
    foreach ($files as $file) {
      $new_filename = $folder . basename($file->archive_filename);

      // Move file is it'is not in the right place.
      if ($file->archive_filename != $new_filename) {
        // Memorize old path in order to remove old folders at end of process.
        $oldpath = dirname($file->archive_filename);

        // Check and move original file using Omeka API.
        $operation = new Omeka_Storage_Adapter_Filesystem(array('localDir' => FILES_DIR));
        $operation->move($file->archive_filename, $new_filename);

        // Check and move derivative files using Omeka API.
        $derivative_filename = $file->getDerivativeFilename();
        foreach (array(
            FULLSIZE_DIR,
            THUMBNAIL_DIR,
            SQUARE_THUMBNAIL_DIR,
          ) as $path) {
          $operation = new Omeka_Storage_Adapter_Filesystem(array('localDir' => $path));
          $operation->move($derivative_filename, $folder . basename($derivative_filename));
        }

        // Update file in Omeka database.
        $file->archive_filename = $new_filename;
        $file->save();

        // Remove all old empty folders.
        if ($oldpath  != '.') {
          foreach (array(
              FILES_DIR,
              FULLSIZE_DIR,
              THUMBNAIL_DIR,
              SQUARE_THUMBNAIL_DIR,
            ) as $path) {
            $fullpath = $path . DIRECTORY_SEPARATOR . $oldpath;
            if (realpath($path) != realpath($fullpath)) {
              self::removeFolder($fullpath);
            }
          }
        }
      }
    }
  }

  /**
   * Manages name of an attached file before saving it.
   */
  public static function hookBeforeInsertFile($file)
  {
    // Rename file if desired and needed.
    if (get_option('archive_repertory_keep_original_filename')) {
      $new_filename = basename($file->original_filename);
      if ($file->archive_filename != $new_filename) {
        $operation = new Omeka_Storage_Adapter_Filesystem(array(
          'localDir' => sys_get_temp_dir(),
          'webDir' => sys_get_temp_dir(),
        ));
        $operation->move($file->archive_filename, $new_filename);

        // Update file in database (automatically done because it's a hook).
        $file->archive_filename = $new_filename;
        $file->original_filename = $new_filename;
      }
    }
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
  private function _createCollectionFolderName($collection)
  {
    $collection_folders = unserialize(get_option('archive_repertory_collection_folders'));
    if ($collection_folders === FALSE) {
      $collection_folders = array();
    }
    else {
      // Remove the current collection id to simplify check.
      unset($collection_folders[$collection->id]);
    }

    // Default name is the first word of the collection name.
    $default_name = trim(strtok(trim($collection->name), " \n\r\t"));

    // If this name is already used, the id is added until name is unique.
    While (in_array($default_name, $collection_folders)) {
      $default_name .= '_' . $collection->id;
    }

    return self::_sanitizeString($default_name);
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
    $collection_folders = unserialize(get_option('archive_repertory_collection_folders'));
    if (get_option('archive_repertory_add_collection_folder') && ($item->collection_id !== NULL)) {
      $collection = $collection_folders[$item->collection_id];
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
   * Default name is the Dublin Core identifier if it's unique. If there isn't
   * any identifier, the item id is used. If there are multiple identifier, the
   * first with the prefix will be used.
   *
   * @param object $item
   *
   * @return string Unique sanitized name of the item.
   */
  private function _createItemFolderName($item)
  {
    $identifiers = item('Dublin Core', 'Identifier', array('all' => TRUE), $item);
    switch (count($identifiers)) {
      case 0:
        $item_identifier = (string) $item->id;
        break;

      case 1:
        if ($identifiers[0] == '') {
          $item_identifier = (string) $item->id;
        }
        else {
          $prefix = get_option('archive_repertory_item_identifier_prefix');
          $prefix_len = strlen($prefix);
          $item_identifier = (substr($identifiers[0], 0, $prefix_len) == $prefix) ?
              // Remove prefix
              substr($identifiers[0], $prefix_len) :
              $identifiers[0];
          $item_identifier = self::_sanitizeString($item_identifier);
        }
        break;

      default:
        $prefix = get_option('archive_repertory_item_identifier_prefix');
        if ($prefix == '') {
          if ($identifiers[0] == '') {
            $item_identifier = (string) $item->id;
          }
          else {
            $item_identifier = self::_sanitizeString($identifiers[0]);
          }
        }
        else {
          $prefix_len = strlen($prefix);
          $filtered_identifiers = array_values(array_filter($identifiers, function ($identifier) use ($prefix, $prefix_len) { return (substr($identifier, 0, $prefix_len) == $prefix); } ));
          $item_identifier = (isset($filtered_identifiers[0])) ?
              substr($filtered_identifiers[0], $prefix_len) :
              $identifiers[0];
          $item_identifier = self::_sanitizeString($item_identifier);
        }
        break;
    }

    return $item_identifier;
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
    // No item folder.
    if (get_option('archive_repertory_add_item_folder')) {
      return self::_createItemFolderName($item) . DIRECTORY_SEPARATOR;
    }

    return '';
  }

  /**
   * Checks if the folders exist in the archive repertory, then creates them.
   *
   * @param string $folder Name of the folder to create, without archive_dir.
   *
   * @return boolean True if the path is created, Exception if an error occurs.
   */
  private function _createPathsInArchive($folder)
  {
    if ($folder != '') {
      foreach (array(
          FILES_DIR,
          FULLSIZE_DIR,
          THUMBNAIL_DIR,
          SQUARE_THUMBNAIL_DIR,
        ) as $path) {
        $fullpath = $path . DIRECTORY_SEPARATOR . $folder;
        $result = self::createFolder($fullpath);
      }
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
        && (count(@scandir($path)) == 2)
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
    $string = preg_replace('/[^[:alnum:]\-_\(\)\[\]]/', '_', $string);
    return preg_replace('/_+/', '_', $string);
  }
}
