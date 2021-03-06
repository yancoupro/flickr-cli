<?php

namespace TheFox\FlickrCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

// use OAuth\Common\Consumer\Credentials;
// use OAuth\OAuth1\Signature\Signature;
// use OAuth\Common\Storage\Memory;
use Rezzza\Flickr\Metadata;
use Rezzza\Flickr\ApiFactory;
use Rezzza\Flickr\Http\GuzzleAdapter as RezzzaGuzzleAdapter;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class AlbumsCommand extends Command
{
    /**
     * @var int
     */
    public $exit = 0;

    /**
     * @var string The name of the configuration file. Defaults to 'config.yml'.
     */
    private $configPath;

    protected function configure()
    {
        $this->setName('albums');
        $this->setDescription('List Photosets.');

        $this->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Path to config file. Default: config.yml');

        $this->configPath = 'config.yml';
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->signalHandlerSetup();

        // Load and check the configuration file.
        if ($input->hasOption('config') && $input->getOption('config')) {
            $this->configPath = $input->getOption('config');
        }
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->configPath)) {
            print 'ERROR: config file not found: ' . $this->configPath . "\n";
            return 1;
        }
        $config = Yaml::parse($this->configPath);
        if (
            !isset($config)
            || !isset($config['flickr'])
            || !isset($config['flickr']['consumer_key'])
            || !isset($config['flickr']['consumer_secret'])
        ) {
            print 'ERROR: config invalid' . "\n";
            return 1;
        }

        // Set up the Flickr API.
        $metadata = new Metadata($config['flickr']['consumer_key'], $config['flickr']['consumer_secret']);
        $metadata->setOauthAccess($config['flickr']['token'], $config['flickr']['token_secret']);
        $apiFactory = new ApiFactory($metadata, new RezzzaGuzzleAdapter());
        $xml = $apiFactory->call('flickr.photosets.getList');

        $photosetsTitles = [];
        foreach ($xml->photosets->photoset as $n => $photoset) {
            if ($this->exit) {
                break;
            }

            $photosetsTitles[(int)$photoset->attributes()->id] = (string)$photoset->title;
        }

        asort($photosetsTitles);

        foreach ($photosetsTitles as $photosetId => $photosetTitle) {
            if ($this->exit) {
                break;
            }

            print $photosetTitle . "\n";
        }

        return 0;
    }

    private function signalHandlerSetup()
    {
        if (function_exists('pcntl_signal')) {
            declare(ticks=1);

            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
            pcntl_signal(SIGHUP, [$this, 'signalHandler']);
        }
    }

    /**
     * @param int $signal
     */
    private function signalHandler(int $signal)
    {
        $this->exit++;

        switch ($signal) {
            case SIGINT:
                print PHP_EOL;
                break;

            default:
        }

        if ($this->exit >= 2) {
            exit(1);
        }
    }
}
