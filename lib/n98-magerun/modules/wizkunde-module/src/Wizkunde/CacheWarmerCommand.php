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

class CacheWarmerCommand extends AbstractMagentoCommand
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
     * the sitemap file that we user
     * @var string
     */
    protected $sitemapfile = 'sitemap.xml';

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
            ->setName('wizkunde:cache:warm')
            ->setDescription('Warmup caches based on the sitemap file')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('sitemap', 's', InputOption::VALUE_REQUIRED, 'Sitemap file path'),
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

            $this->logfile = 'cache-' . $this->getMode() . '-' . date('YmdHis', time()) . '.log';

            $this->runWarmupCachesCommand();
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
    protected function runWarmupCachesCommand()
    {
        if($this->getMode() == 'dry') {
            $this->getOutputInterface()->writeLn('<fg=green;options=bold>Dry run detected, nothing will actually happen</>');
            \Mage::log('Dry run detected, nothing will actually happen', null, $this->logfile);
        }

        $this->getOutputInterface()->writeLn('<fg=green;options=bold>Logs will be written in: ' . $this->getLogdir() . '/' . $this->logfile . '</>');

        $this->warmupCaches();
    }

    /**
     * Remove the duplicates
     */
    protected function warmupCaches()
    {
        $collection = \Mage::getModel('sitemap/sitemap')->getCollection();

        $i =0;
        $total = $collection->count();
        $count = 0;

        // create a new progress bar (50 units)
        $progress = new ProgressBar($this->getOutputInterface(), $total);

        foreach($collection as $sitemap)
        {
            $sitemapUrl = str_replace('/n98-magerun', '', substr_replace(\Mage::getBaseUrl() ,"",-1)) . $sitemap->getData('sitemap_path') . $sitemap->getData('sitemap_filename');

            \Mage::log("Starting sitemap: " . $sitemapUrl, null, $this->logfile);

            //Do not change anything below this line unless you know what you are doing
            ignore_user_abort(TRUE);
            set_time_limit(600);

            $xml = simplexml_load_file($sitemapUrl);
            foreach ($xml->url as $url_list) {
                $count++;

                $url = $url_list->loc;

                if($this->getMode() == 'live') {
                    $ch = curl_init();
                    curl_setopt ($ch, CURLOPT_URL, $url);
                    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt ($ch, CURLOPT_HEADER, true);
                    curl_setopt ($ch, CURLOPT_NOBODY, true);
                    $ret = curl_exec ($ch);
                    curl_close ($ch);

                    usleep(0.5*1000000);
                }

                if($this->getMode() == 'dry') {
                    \Mage::log("Would have warmed URL: $url", null, $this->logfile);
                } else {
                    \Mage::log("Warmed URL: $url", null, $this->logfile);
                }
            }
            unset($xml);

            \Mage::log("Finished crawling sitemap: $sitemapUrl", null, $this->logfile);

            $progress->advance();
        }

        $progress->finish();

        $this->getOutputInterface()->writeLn(PHP_EOL);

        if($this->getMode() == 'dry') {
            \Mage::log("Finished! Would have crawled $count URLS", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Would have crawled $count URLS</>");
        } else {
            \Mage::log("Finished! Crawled $count URLS", null, $this->logfile);
            $this->getOutputInterface()->writeLn("<fg=green;options=bold>Finished! Crawled $count URLS</>");
        }
    }
}