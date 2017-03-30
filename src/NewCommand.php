<?php 
namespace GetRealTManager;

use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command {

    private $input = null;
    private $output = null;

    /**
     * Configure the command options.
     *
     * @return void
     */
    public function configure() {
        $this
            ->setName('new')
            ->setDescription('Create a new GetRealT site.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of your site');    
    }
    
    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output) {

        $this->input = $input;
        $this->output = $output;
        
        $name = $input->getArgument('name');
        $dir = getcwd() . '/' . $name;

        $this->verifyNewSite($name);

        $composer = $this->findComposer();

        $output->writeln('<info>Installing Laravel</info>');
        $this->executeCommand($composer . ' create-project --prefer-dist laravel/laravel ' . $name, $dir);
    }

    /**
     * Verify that the site can be created.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyNewSite($directory) {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Site already exists!');
        }
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer() {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }
        return 'composer';
    }

    /**
     * Executes a sub process
     *
     * @param  string  $command
     * @param  string  $dir
     * @return string
     */
    protected function executeCommand($command, $dir) {
        $process = new Process($command, $dir, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $output = $this->output;
        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });        
    }
}