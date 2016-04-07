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

class ReviewImportCommand extends AbstractMagentoCommand
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
            ->setName('wizkunde:review:import-translation')
            ->setDescription('Import review translations from a XML file')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('sku', 's', InputOption::VALUE_REQUIRED, 'Sku for when in test mode'),
                    new InputOption('file', 'f', InputOption::VALUE_REQUIRED, 'File to load from'),
                    new InputOption('store', 'S', InputOption::VALUE_REQUIRED, 'New store to use'),
                    new InputArgument('mode', InputOption::VALUE_REQUIRED, 'test, live, dry (default)')
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

            $this->logfile = 'reviews-' . $this->getMode() . '-' . date('YmdHis', time()) . '.log';

            $this->runImportProductReviewsCommand();
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
     * Actually run the script to import the translations for reviews
     */
    protected function runImportProductReviewsCommand()
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

        $this->importProductReviews();
    }

    /**
     * Import Review Translations
     */
    protected function importProductReviews()
    {
        $filename = $this->getInputInterface()->getOption('file');

        $loadedXmlFile = simplexml_load_file($filename);

        $reviewModel = \Mage::getModel('review/review');
        $customerModel = \Mage::getModel('customer/customer');
        $productModel = \Mage::getModel('catalog/product');

        $totalRows = count($loadedXmlFile->xpath('//Workbook/Worksheet/Table/Row'));

        if($totalRows > 0) {
            \Mage::log("Adding reviews to store: " . $this->getInputInterface()->getOption('store'), null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Adding reviews to store: " . $this->getInputInterface()->getOption('store') . "</>");

            foreach($loadedXmlFile->xpath('//Workbook/Worksheet/Table/Row') as $i => $row) {
                if($i > 0) {
                    if(count($row->xpath('Cell[1]/Data')) > 0) {
                        $sku = (string) $row->xpath('Cell[1]/Data')[0];

                        $customer = clone($customerModel);
                        $review = clone($reviewModel);

                        $productId = $productModel->getResource()->getIdBySku($sku);

                        if($productId != null) {
                            $review->load((string) $row->xpath('Cell[2]/Data')[0]);

                            $collection = $review->getProductCollection()
                                ->addCustomerFilter($review->getCustomerId())
                                ->setStoreFilter($this->getInputInterface()->getOption('store'));

                            $collection->getSelect()
                                ->where('rt.entity_pk_value = ?', $productId);

                            if($collection->count() > 0) {
                                \Mage::log("Skipping review for SKU: $sku for customer with ID: " . $review->getCustomerId() . " (Review exists)", null, $this->logfile);
                                $this->getOutputInterface()->writeLn("<fg=green;options=bold>Skipping review for SKU: $sku for customer with ID: " . $review->getCustomerId() . " (Review exists)</>");

                                continue;
                            }

                            $customer->load($review->getCustomerId());

                            if($customer->getId() != null) {
                                if ($this->getMode() == 'live' || ($this->getMode() == 'test' && $this->getInputInterface()->getOption('sku') == $sku)) {
                                    \Mage::log("Adding review for SKU: $sku", null, $this->logfile);
                                    $this->getOutputInterface()->writeLn("<fg=green;options=bold>Adding review for SKU: $sku</>");

                                    $review->setEntityPkValue($productId)
                                        ->setTitle((string)$row->xpath('Cell[5]/Data')[0])
                                        ->setDetail((string)$row->xpath('Cell[6]/Data')[0])
                                        ->setStatusId(1)
                                        ->setEntityId(1)
                                        ->setStoreId($this->getInputInterface()->getOption('store'))
                                        ->setStores(array($this->getInputInterface()->getOption('store')))
                                        ->setCustomerId($customer->getId())
                                        ->setNickname($customer->getFirstname())
                                        ->save();

                                    $review->aggregate();
                                } else {
                                    \Mage::log("Would have added review for SKU: $sku", null, $this->logfile);
                                    $this->getOutputInterface()->writeLn("<fg=green;options=bold>Would have added review for SKU: $sku</>");
                                }
                            } else {
                                    \Mage::log("Skipping review for SKU: $sku for customer with review id: " . (string) $row->xpath('Cell[2]/Data')[0] . " (Unknown Customer)", null, $this->logfile);
                                    $this->getOutputInterface()->writeLn("<fg=green;options=bold>Skipping review for SKU: $sku for customer with review ID: " . (string) $row->xpath('Cell[2]/Data')[0] . " (Unkown customer)</>");
                            }
                        } else {
                            \Mage::log("Skipping SKU: $sku (product does not exist)", null, $this->logfile);
                            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Skipping SKU: $sku (product does not exist)</>");
                        }
                    }
                }
            }

            $this->getOutputInterface()->writeLn(PHP_EOL);
        }
    }
}