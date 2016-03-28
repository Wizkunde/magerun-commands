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

class MediaOrphansCommand extends AbstractMagentoCommand
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
     * Whats the directory we backup in (or fetch the backup from)
     * @var string
     */
    protected $backupdir = '/var/orphans';

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
            ->setName('wizkunde:media:remove-orphans')
            ->setDescription('Remove orphan image files not linked to products')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('sku', 's', InputOption::VALUE_REQUIRED, 'Sku for when in test mode'),
                    new InputArgument('mode', InputOption::VALUE_REQUIRED, 'rollback, live, dry (default)')
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

            if(in_array($input->getArgument('mode'), array('dry', 'live', 'rollback'))) {
                $this->setMode($input->getArgument('mode'));
            }

            $this->logfile = 'orphans-' . $this->getMode() . '-' . date('YmdHis', time()) . '.log';

            $this->runRemoveOrphanImagesCommand();
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
     * @return string
     */
    public function getBackupdir()
    {
        if(!is_dir($this->getApplication()->getMagentoRootFolder() . $this->backupdir)) {
            mkdir($this->getApplication()->getMagentoRootFolder() . $this->backupdir);
        }

        return $this->getApplication()->getMagentoRootFolder() . $this->backupdir;
    }

    /**
     * Actually run the script to see what images are duplicates
     */
    protected function runRemoveOrphanImagesCommand()
    {
        if($this->getMode() == 'rollback') {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<fg=red;options=bold>are you sure that you want to rollback the orphan delete? (y/N): </>', false);

            if ($helper->ask($this->getInputInterface(), $this->getOutputInterface(), $question) === false) {
                $this->getOutputInterface()->writeln('<fg=red;options=bold>Canceled the rollback due to confirmation!</>');
                return;
            }

            return $this->rollbackOrphanImages();
        }

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

        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Backups will be stored in: '. $this->getBackupdir() . '!</>');
        \Mage::log('Backups will be stored in: '. $this->getBackupdir() . '!', null, $this->logfile);

        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Logs will be written in: ' . $this->getLogdir() . '/' . $this->logfile . '</>');
        \Mage::log('Logs will be written in: ' . $this->getLogdir() . '/' . $this->logfile, null, $this->logfile);

        $this->removeOrphanImages();
    }

    /**
     * Rollback all the images we moved to the backup directory
     */
    protected function rollbackOrphanImages()
    {
        $files = glob($this->getBackupdir() . DS . 'catalog' . DS . 'product' . DS . '[A-z0-9]' . DS . '[A-z0-9]' . DS . '*');
        $totalFiles = count($files);

        if($totalFiles > 0) {
            // create a new progress bar (50 units)
            $progress = new ProgressBar($this->getOutputInterface(), $totalFiles);

            foreach($files as $i => $file) {
                $targetFile = str_replace('var/orphans', 'media', $file);

                if(is_file($file)) {
                    \Mage::log("Restoring file: " . $file, null, $this->logfile);
                    rename($file, $targetFile);
                }
                $progress->advance();
            }

            $progress->finish();

            $this->getOutputInterface()->writeLn(PHP_EOL);

        } else {
            $this->getOutputInterface()->writeLn('<fg=green;options=bold>Nothing to rollback, the rollback directory contains no files!</>');
        }
    }

    /**
     * Remove the duplicates
     */
    protected function removeOrphanImages()
    {
        $resource = \Mage::getModel('core/resource');
        $db = $resource->getConnection('core_write');

        $dir = \Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product';
        $files = glob($dir . DS . '[A-z0-9]' . DS . '[A-z0-9]' . DS . '*');
        $totalFiles = count($files);

        $prefix_table = \Mage::getConfig()->getTablePrefix();
        $deleted = 0;

        // create a new progress bar (50 units)
        $progress = new ProgressBar($this->getOutputInterface(), $totalFiles);

        foreach ($files as $i => $file) {
            if (!is_file($file)) {
                continue;
            }

            \Mage::log("Processing file " . $i+1 . " of $totalFiles", null, $this->logfile);

            $filename = DS . implode(DS, array_slice(explode(DS, $file), -3));
            $count = $db->query('SELECT COUNT(*) FROM '.$prefix_table.'catalog_product_entity_media_gallery WHERE BINARY value = ?', $filename)->fetchColumn();
            if ($count == 0) {
                // Backup existing
                if(!is_dir(dirname($this->getBackupdir() . $file))) {
                    $backupFile = str_replace('/media', '/var/orphans', $file);
                    @mkdir(dirname($backupFile), 0777, true);
                    copy($file, $backupFile);
                }

                if($this->getMode() !== 'dry') {
                    unlink($file);
                    if (!file_exists($file)) {
                        \Mage::log($file . ' is deleted', null, $this->logfile);
                    } else {
                        \Mage::log($file . ' is not deleted, permission problem?', null, $this->logfile);
                        exit;
                    }
                } else {
                    \Mage::log($file . ' would be deleted', null, $this->logfile);
                }
                $deleted++;
            }

            $progress->advance();
        }

        $progress->finish();

        $this->getOutputInterface()->writeLn(PHP_EOL);

        if($this->getMode() == 'dry') {
            \Mage::log("Finished! Would have removed $deleted orphaned images", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Would have removed $deleted orphaned images</>");
        } else {
            \Mage::log("Finished! Removed $deleted orphaned images", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Removed $deleted orphaned images</>");
        }
    }
}