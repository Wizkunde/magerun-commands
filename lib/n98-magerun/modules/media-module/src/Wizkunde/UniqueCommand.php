<?php

namespace Wizkunde;

use N98\Magento\Command\AbstractMagentoCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UniqueCommand extends AbstractMagentoCommand
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
     * Whats the directory we backup in (or fetch the backup from)
     * @var string
     */
    protected $backupdir = '/var/unique';

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
          ->setName('media:unique')
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
			$this->setInputInterface($input);
			$this->setOutputInterface($output);

            if(in_array($input->getArgument('mode'), array('dry', 'test', 'live'))) {
                $this->setMode($input->getArgument('mode'));
            }

            $this->runUniqueImagesCommand();
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
	protected function runUniqueImagesCommand()
	{
        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Backups will be stored in: '. $this->getBackupdir() . '!</>');

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
            $this->getOutputInterface()->writeLn('<fg=green;options=bold>Logfile will be written in: ' . $this->getLogdir() . '</>');
        }
	}
}