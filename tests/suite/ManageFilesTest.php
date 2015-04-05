<?php
class ArchiveRepertory_ManageFilesTest extends ArchiveRepertory_Test_AppTestCase
{
    protected $_fileUrl;

    /**
     * All tests are managed here to simplify management of the item.
     */
    public function testManageFiles()
    {
        $this->_fileUrl = TEST_FILES_DIR . '/image_test.png';
        $this->_testInsertFile();
        $this->_testInsertDuplicateFile();
        $this->_testChangeIdentifier();
        $this->_testChangeCollection();
        $this->_testChangeIdentifierAndCollection();
    }

    /**
     * Check insertion of one file.
     */
    protected function _testInsertFile()
    {
        $fileUrl = $this->_fileUrl;
        $this->assertTrue(file_exists($fileUrl));

        $files = insert_files_for_item($this->item, 'Filesystem', array($fileUrl));
        $this->assertEquals(1, count($files));

        // Retrieve file from the database to get a fully inserted file, with
        // all updated metadata.
        $file = $this->item->getFile();

        // Generic checks.
        $this->assertThat($file, $this->isInstanceOf('File'));
        $this->assertTrue($file->exists());
        $this->assertEquals(filesize($fileUrl), $file->size);
        $this->assertEquals(md5_file($fileUrl), $file->authentication);
        $this->assertEquals(pathinfo($fileUrl, PATHINFO_BASENAME), $file->original_filename);

        // Readable filename check.
        $storageFilepath = $this->item->id
            . DIRECTORY_SEPARATOR
            . pathinfo($fileUrl, PATHINFO_BASENAME);
        $this->assertEquals($storageFilepath, $file->filename);

        // Readable filepath check.
        $this->_checkFile($file);
    }

    /**
     * Check insertion of a second file with a duplicate name.
     *
     * @internal Omeka allows to have two files with the same name.
     */
    protected function _testInsertDuplicateFile()
    {
        $fileUrl = $this->_fileUrl;
        $files = insert_files_for_item($this->item, 'Filesystem', array($fileUrl));

        // Retrieve files from the database to get a fully inserted file, with
        // all updated metadata.
        $files = $this->item->getFiles();
        $this->assertEquals(2, count($files));
        // Get the second file.
        $file = $files[1];

        // Generic checks.
        $this->assertThat($file, $this->isInstanceOf('File'));
        $this->assertTrue($file->exists());
        $this->assertEquals(filesize($fileUrl), $file->size);
        $this->assertEquals(md5_file($fileUrl), $file->authentication);
        $this->assertEquals(pathinfo($fileUrl, PATHINFO_BASENAME), $file->original_filename);

        // Readable filename check.
        $storageFilepath = $this->item->id
            . DIRECTORY_SEPARATOR
            . pathinfo($fileUrl, PATHINFO_FILENAME)
            . '.1.'
            . pathinfo($fileUrl, PATHINFO_EXTENSION);
        $this->assertEquals($storageFilepath, $file->filename);

        // Readable filepath check.
        $this->_checkFile($file);
    }

    /**
     * Check change of the identifier of the item.
     */
    protected function _testChangeIdentifier()
    {
        // Set default option for identifier of items.
        $elementSetName = 'Dublin Core';
        $elementName = 'Identifier';
        $element = $this->db->getTable('Element')->findByElementSetNameAndElementName($elementSetName, $elementName);
        set_option('archive_repertory_item_folder', $element->id);

        // Update item.
        update_item(
            $this->item,
            array(),
            array($elementSetName => array(
                $elementName => array(array('text' => 'my_first_item', 'html' => false)),
        )));

        $files = $this->item->getFiles();
        foreach ($files as $key => $file) {
            $this->_checkFile($file);
        }
    }

    /**
     * Check change of the collection of the item.
     */
    protected function _testChangeCollection()
    {
        // Create a new collection.
        $this->collection = insert_collection(array('public' => true));

        // Update item.
        update_item($this->item, array('collection_id' => $this->collection->id));

        $files = $this->item->getFiles();
        foreach ($files as $key => $file) {
            $this->_checkFile($file);
        }
    }

    /**
     * Check simultaneous change of identifier and collection of the item.
     */
    protected function _testChangeIdentifierAndCollection()
    {
        $elementSetName = 'Dublin Core';
        $elementName = 'Identifier';

        // Create a new collection.
        $this->collection = insert_collection(array('public' => true));

        // Need to release item and to reload it.
        $itemId = $this->item->id;
        release_object($this->item);
        $this->item = get_record_by_id('Item', $itemId);

        // Update item.
        update_item(
            $this->item,
            array(
                'collection_id' => $this->collection->id,
                'overwriteElementTexts' => true,
            ),
            array($elementSetName => array(
                $elementName => array(array('text' => 'my_new_item_identifier', 'html' => false)),
        )));

        $files = $this->item->getFiles();
        foreach ($files as $key => $file) {
            $this->_checkFile($file);
        }
    }

    /**
     * Check if file and derivatives exist really.
     */
    protected function _checkFile($file)
    {
        foreach ($this->_pathsByType as $type => $path) {
            $storageFilepath = $this->_storagePath . DIRECTORY_SEPARATOR . $file->getStoragePath($type);
            $this->assertTrue(file_exists($storageFilepath));
        }
    }
}
