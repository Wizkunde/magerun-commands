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

class MediaDuplicatesCommand extends AbstractMagentoCommand
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
    protected $backupdir = '/var/duplicates';

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
          ->setName('wizkunde:media:remove-duplicates')
          ->setDescription('Remove double media files from products')
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

            if(in_array($input->getArgument('mode'), array('dry', 'test', 'live'))) {
                $this->setMode($input->getArgument('mode'));
            }

            $this->logfile = 'duplicates-' . $this->getMode() . '-' . date('YmdHis', time()) . '.log';

            $this->runRemoveDuplicateImagesCommand();
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
	protected function runRemoveDuplicateImagesCommand()
	{
		if($this->getMode() == 'live') {
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('<fg=red;options=bold>This cannot be undone, are you sure that you want to continue? (y/N): </>', false);

			if ($helper->ask($this->getInputInterface(), $this->getOutputInterface(), $question) === false) {
				$this->getOutputInterface()->writeln('<fg=red;options=bold>Canceled the live run due to confirmation!</>');
				return;
			}
		} else if($this->getMode() == 'test') {
            if($this->getInputInterface()->getOption('sku') == '') {
                $this->getOutputInterface()->writeln('<fg=red;options=bold>Cant execute test mode when sku is not set, please use --sku together with testmode!</>');
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

        $this->removeDuplicateImages();
	}

    /**
     * Remove the duplicates
     */
    protected function removeDuplicateImages()
    {

        $mediaApi = \Mage::getModel("catalog/product_attribute_media_api");
        $products = \Mage::getModel('catalog/product')->getCollection();

        $i =0;
        $total = count($products);
        $count = 0;

        // create a new progress bar (50 units)
        $progress = new ProgressBar($this->getOutputInterface(), $total);

        foreach($products as $prod)
        {
            $product = \Mage::getModel('catalog/product')->load($prod->getId());
            $md5_values = array();

            //protected base image
            $base_image = $product->getImage();
            if($base_image != 'no_selection')
            {
                $filepath =  \Mage::getBaseDir('media') .'/catalog/product' . $base_image  ;
                if(file_exists($filepath))
                    $md5_values[] = md5(file_get_contents($filepath));
            }

            $i ++;

            \Mage::log("Processing product $i of $total (SKU: " . $product->getSku() . ")", null, $this->logfile);

            // Loop through product images
            $images = $product->getMediaGalleryImages();
            if($images){
                foreach($images as $image){
                    //protected base image
                    if($image->getFile() == $base_image)
                        continue;

                    $filepath =  \Mage::getBaseDir('media') .'/catalog/product' . $image->getFile()  ;
                    if(file_exists($filepath)) {
                        $md5 = md5(file_get_contents($filepath));
                    } else {
                        continue;
                    }

                    if(in_array($md5, $md5_values))
                    {
                        // Backup existing
                        if(!is_dir(dirname($this->getBackupdir() . '/catalog/product' . $image->getFile()))) {
                            @mkdir(dirname($this->getBackupdir() . '/catalog/product' . $image->getFile()), 0777, true);
                            copy($filepath, $this->getBackupdir() . '/catalog/product' . $image->getFile());
                        }

                        if($this->getMode() == 'live' || ($this->getMode() == 'test' && $product->getSku() == $this->getInputInterface()->getOption('sku'))) {
                            $mediaApi->remove($product->getId(),  $image->getFile());
                            \Mage::log("Removed duplicate image from " . $product->getSku(), null, $this->logfile);
                        } else {
                            \Mage::log("Would remove duplicate image from " . $product->getSku(), null, $this->logfile);
                        }

                        \Mage::log("Image name: " . $image->getFile(), null, $this->logfile);

                        $count++;
                    } else {
                        $md5_values[] = $md5;
                    }

                }
            }

            $progress->advance();

        }

        $progress->finish();

        $this->getOutputInterface()->writeLn(PHP_EOL);

        if($this->getMode() == 'dry') {
            \Mage::log("Finished! Would have removed $count duplicated images", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Would have removed $count duplicated images</>");
        } else {
            \Mage::log("Finished! Removed $count duplicated images", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Removed $count duplicated images</>");
        }
    }
}