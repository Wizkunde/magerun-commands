<?php

namespace Wizkunde;

use N98\Magento\Command\AbstractMagentoCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\ProgressBar;

class MediaMissingCommand extends AbstractMagentoCommand
{
    /**
     * @var string
     */
    protected $mode = 'dry';

    /**
     * The directory to store the logfiles
     * @var string
     */
    protected $logdir = '/var/log';

    /**
     * The logfile that we use
     *
     * @var string
     */
    protected $logfile = '';

    /**
     * Input and output interfaces
     *
     * @var
     */
    protected $inputInterface;
    protected $outputInterface;


    /**
     * Configure the Command
     */
    protected function configure()
    {
        $this
            ->setName('wizkunde:media:remove-missing')
            ->setDescription('Remove database links to missing image files')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('sku', 's', InputOption::VALUE_REQUIRED, 'Sku for when in test mode'),
                    new InputArgument('mode', InputOption::VALUE_REQUIRED, 'live, test or dry (default)')
                ))
            );
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            \Mage::setIsDeveloperMode(true);

            $this->setInputInterface($input);
            $this->setOutputInterface($output);

            if(in_array($input->getArgument('mode'), array('dry', 'live'))) {
                $this->setMode($input->getArgument('mode'));
            }

            $this->logfile = 'missing-' . $this->getMode() . '-' . date('YmdHis', time()) . '.log';

            $this->runRemoveMissingImagesCommand();
        }
    }

    /**
     * @return mixed
     */
    public function getInputInterface()
    {
        return $this->inputInterface;
    }

    /**
     * @param mixed $inputInterface
     */
    public function setInputInterface(InputInterface $inputInterface)
    {
        $this->inputInterface = $inputInterface;
    }

    /**
     * @return mixed
     */
    public function getOutputInterface()
    {
        return $this->outputInterface;
    }

    /**
     * @param mixed $outputInterface
     */
    public function setOutputInterface(OutputInterface $outputInterface)
    {
        $this->outputInterface = $outputInterface;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @return string
     */
    public function getLogdir()
    {
        if(!is_dir($this->getApplication()->getMagentoRootFolder() . $this->logdir)) {
            mkdir($this->getApplication()->getMagentoRootFolder() . $this->logdir);
        }

        return $this->getApplication()->getMagentoRootFolder() . $this->logdir;
    }

    /**
     * Actually run the script to see what images are duplicates
     */
    protected function runRemoveMissingImagesCommand()
    {
        if($this->getMode() == 'live') {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<fg=red;options=bold>This cannot be undone, are you sure that you want to continue? (y/N): </>', false);

            if ($helper->ask($this->getInputInterface(), $this->getOutputInterface(), $question) === false) {
                $this->getOutputInterface()->writeln('<fg=red;options=bold>Canceled the live run due to confirmation!</>');
                return;
            }
        } else {
            $this->getOutputInterface()->writeLn('<fg=green;options=bold>Dry run detected, nothing will actually happen</>');
            \Mage::log('Dry run detected, nothing will actually happen', null, $this->logfile);
        }

        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Logs will be written in: ' . $this->getLogdir() . '/' . $this->logfile . '</>');
        \Mage::log('Logs will be written in: ' . $this->getLogdir() . '/' . $this->logfile, null, $this->logfile);

        $this->removeMissingImages();
    }

    /**
     * Remove the duplicates
     */
    protected function removeMissingImages()
    {
        $missing = $this->removeMediaGalleryImages();
        $missing += $this->removeVarcharImages();

        $this->getOutputInterface()->writeLn(PHP_EOL);

        if($this->getMode() == 'dry') {
            \Mage::log("Finished! Would have removed $missing missing image references", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Would have removed $missing missing image references</>");
        } else {
            \Mage::log("Finished! Removed $missing missing image references", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Removed $missing missing image references</>");
        }
    }

    /**
     * Remove images from the Media Gallery database
     *
     * @return int
     */
    protected function removeMediaGalleryImages()
    {
        $missing = 0;

        /* Clean up images from media gallery tables */
        $images = \Mage::getModel('core/resource')->getConnection('core_write')->fetchAll('SELECT value,value_id FROM '.\Mage::getConfig()->getTablePrefix().'catalog_product_entity_media_gallery');

        $progress = new ProgressBar($this->getOutputInterface(), count($images));

        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Starting on ' . count($images) . ' media gallery images</>');
        \Mage::log('Starting on ' . count($images) . ' media gallery images', null, $this->logfile);

        foreach ($images as $image) {
            if (!file_exists(\Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product' . $image['value'])) {
                $missing++;
                if($this->getMode() !== 'dry') {
                    \Mage::log($image['value'] . ' does not exist on disk; Deleting the row from media gallery database.', null, $this->logfile);
                    \Mage::getModel('core/resource')->getConnection('core_write')->query('DELETE FROM '.\Mage::getConfig()->getTablePrefix().'catalog_product_entity_media_gallery WHERE value_id = ?', $image['value_id']);
                    \Mage::getModel('core/resource')->getConnection('core_write')->query('DELETE FROM '.\Mage::getConfig()->getTablePrefix().'catalog_product_entity_media_gallery_value WHERE value_id = ?', $image['value_id']);
                } else {
                    \Mage::log($image['value'] . ' does not exist on disk; would have deleted the row from media gallery database.', null, $this->logfile);
                }
            }

            $progress->advance();
        }

        $progress->finish();

        return $missing;
    }

    /**
     * Remove images from the varchar table
     *
     * @return int
     */
    protected function removeVarcharImages()
    {
        $missing = 0;

        $eavAttribute = new \Mage_Eav_Model_Mysql4_Entity_Attribute();
        $thumbnailAttrId = $eavAttribute->getIdByCode('catalog_product', 'thumbnail');
        $smallImageAttrId = $eavAttribute->getIdByCode('catalog_product', 'small_image');
        $imageAttrId = $eavAttribute->getIdByCode('catalog_product', 'image');

        /* Clean up images from varchar table */
        $images = \Mage::getModel('core/resource')->getConnection('core_write')->fetchAll('SELECT value,value_id FROM '.\Mage::getConfig()->getTablePrefix().'catalog_product_entity_varchar WHERE attribute_id = ? OR attribute_id = ? OR attribute_id = ?', array($thumbnailAttrId, $smallImageAttrId, $imageAttrId));

        $this->getOutputInterface()->writeLn(PHP_EOL);
        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Starting on ' . count($images) . ' varchar images</>');
        \Mage::log('Starting on ' . count($images) . ' varchar images', null, $this->logfile);

        $progress = new ProgressBar($this->getOutputInterface(), count($images));

        foreach ($images as $image) {
            if (!file_exists(\Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product' . $image['value'])) {
                $missing++;
                if($this->getMode() !== 'dry') {
                    \Mage::log($image['value'] . ' does not exist on disk; Deleting the row from varchar database.', null, $this->logfile);
                    \Mage::getModel('core/resource')->getConnection('core_write')->query('DELETE FROM '.\Mage::getConfig()->getTablePrefix().'catalog_product_entity_varchar WHERE value_id = ?',  $image['value_id']);
                } else {
                    \Mage::log($image['value'] . ' does not exist on disk; would have deleted the row from varchar database.', null, $this->logfile);
                }
            }

            $progress->advance();
        }

        $progress->finish();

        return $missing;
    }
}