<?php

namespace Wizkunde;

use N98\Magento\Command\AbstractMagentoCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UniqueCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
      $this
          ->setName('media:unique')
          ->setDescription('Remove double media files from products')
	  ->addOption('dry', null, InputOption::VALUE_NONE, 'Complete a dryrun')
	  ->addOption('test', null, InputOption::VALUE_REQUIRED, 'Test one SKU to see how it goes')
	  ->addOption('live', null, InputOption::VALUE_NONE, 'Actually do the changes')
	  ->addOption('logdir', null, InputOption::VALUE_REQUIRED, 'The directory to log in')
      ;
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
	    if($input->getOption('dry') !== false) {
		var_dump('dryrun');
	    }
	    if($input->getOption('test') !== null) {
		var_dump('test with: ' . $input->getOption('test'));
	    } 
	    if($input->getOption('live') !== false) {
		var_dump('live');
	    }
        }
    }
}
