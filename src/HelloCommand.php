<?php 
namespace GetRealTManager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HelloCommand extends Command {

    public function configure() {
        $this->setName('hello')
             ->setDescription('Offer a greeting! ex. ./getrealt hello Josh --greeting="Hi"')
             ->addArgument('name', InputArgument::OPTIONAL, 'Your name', 'World')
             ->addOption('greeting', null, InputOption::VALUE_OPTIONAL, 'Override the default greeting', 'Hello');
    }

    public function execute(InputInterface $input, OutputInterface $output) {

        $message = sprintf('%s %s', $input->getOption('greeting'), $input->getArgument('name'));

        $output->writeln('<info>' . $message . '</info>');
    }
}