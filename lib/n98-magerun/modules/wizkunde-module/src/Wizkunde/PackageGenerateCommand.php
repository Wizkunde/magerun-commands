<?php

namespace Wizkunde;

use N98\Magento\Command\AbstractMagentoCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PackageGenerateCommand extends AbstractMagentoCommand
{
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
            ->setName('wizkunde:module:package')
            ->setDescription('Create a package.xml file for a module')
            ->setDefinition(
                new InputDefinition(array(
                    new InputArgument('module', InputOption::VALUE_REQUIRED, 'Module name (eg. Wizkunde_WebSSO)'),
                    new InputArgument('channel', InputOption::VALUE_REQUIRED, 'Package channel (eg. community)'),
                    new InputArgument('license', InputOption::VALUE_REQUIRED, 'License name'),
                    new InputArgument('license_uri', InputOption::VALUE_REQUIRED, 'License URL'),
                    new InputArgument('summary', InputOption::VALUE_REQUIRED, 'Summary'),
                    new InputArgument('description', InputOption::VALUE_REQUIRED, 'Description'),
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

            $this->runGeneratePackageCommand();
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
     * Actually run the script to see what images are duplicates
     */
    protected function runGeneratePackageCommand()
    {
        $oConnect = \Mage::getModel('connect/extension')->generatePackageXml();
        
        $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Created package.xml file for module " . $this->getInputInterface()->getArgument('module') . "</>");
    }
}
