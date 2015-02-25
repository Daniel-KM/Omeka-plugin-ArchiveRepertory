<?php
/**
 * @copyright Daniel Berthereau, 2012-2014
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package ArchiveRepertory
 */

/**
 * Base class for Archive Repertory tests.
 */
class ArchiveRepertory_Test_AppTestCase extends Omeka_Test_AppTestCase
{
    const PLUGIN_NAME = 'ArchiveRepertory';

    /**
     * Default folder paths for each default type of files/derivatives.
     *
     * @see File::_pathsByType
     * @var array
     */
    protected $_pathsByType = array(
        'original' => 'original',
        'fullsize' => 'fullsize',
        'thumbnail' => 'thumbnails',
        'square_thumbnail' => 'square_thumbnails',
    );

    protected $_storagePath;

    public function setUp()
    {
        parent::setUp();

        $pluginHelper = new Omeka_Test_Helper_Plugin;
        $pluginHelper->setUp(self::PLUGIN_NAME);

        define('TEST_FILES_DIR', ARCHIVE_REPERTORY_DIR . '/tests/suite/_files');

        // Add constraints if derivatives have been added in the config file.
        $fileDerivatives = Zend_Registry::get('bootstrap')->getResource('Config')->fileDerivatives;
        if (!empty($fileDerivatives) && !empty($fileDerivatives->paths)) {
            foreach ($fileDerivatives->paths->toArray() as $type => $path) {
                set_option($type . '_constraint', 1);
            }
        }

        // Prepare config and set a test temporary storage in registry.
        $config = new Omeka_Test_Resource_Config;
        $configIni = $config->init();
        if (isset($configIni->paths->imagemagick)) {
            $this->convertDir = $configIni->paths->imagemagick;
        } else {
            $this->convertDir = dirname(`which convert`);
        }

        $storage = Zend_Registry::get('storage');
        $adapter = $storage->getAdapter();
        $adapterOptions = $adapter->getOptions();
        $this->_storagePath = $adapterOptions['localDir'];

        // Set default strategy for the creation of derivative files.
        $this->strategy = new Omeka_File_Derivative_Strategy_ExternalImageMagick;
        $this->strategy->setOptions(array('path_to_convert' => $this->convertDir));
        $this->creator = new Omeka_File_Derivative_Creator;
        $this->creator->setStrategy($this->strategy);
        Zend_Registry::set('file_derivative_creator', $this->creator);

        // Create one item on which attach files.
        $this->item = insert_item(array('public' => true));
        set_option('disable_default_file_validation', 1);
    }

    public function assertPreConditions()
    {
        $this->assertThat($this->item, $this->isInstanceOf('Item'));
        $this->assertTrue($this->item->exists());
    }
}
