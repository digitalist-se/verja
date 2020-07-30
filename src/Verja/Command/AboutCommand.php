<?php

namespace Verja\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AboutCommand extends Command
{

    protected $container;
    protected static $defaultName = 'about';

    public function __construct(ContainerBuilder $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $HelpText = '<info>Display the about</info>';

        $this->setName("about")
            ->setDescription("about Verja")
            ->setHelp($HelpText);
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<info>Verja helps you to track recent CVE postings." .
        "</info>");
    }
}
