<?php 
namespace GetRealTManager;

use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\Table;

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

        $settings = [];
        do {
            $settings = $this->getSettings();
        } while(!$this->verifySettings($settings));

        $composer = $this->findComposer();

        $output->writeln('<info>Installing Laravel</info>');
        $this->executeCommand($composer . ' create-project --prefer-dist laravel/laravel ' . $name, $dir);

        $this->saveSettings($settings, $dir);
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

    /**
     * Prompts the user to enter the settings for the new site
     *
     * @return string
     */
    protected function getSettings() {
        $settings = [];        
        $questionHelper = $this->getHelper('question');

        $settings['DB_HOST'] = $questionHelper->ask($this->input, $this->output, new Question('DB_HOST: What is your database host (127.0.0.1)? ', '127.0.0.1'));
        $settings['DB_PORT'] = $questionHelper->ask($this->input, $this->output, new Question('DB_PORT: What is your database port (3306)? ', '3306'));
        $settings['DB_DATABASE'] = $questionHelper->ask($this->input, $this->output, new Question('DB_DATABASE: What is your database name? '));
        $settings['DB_USERNAME'] = $questionHelper->ask($this->input, $this->output, new Question('DB_USERNAME: What is your database username? '));
        $settings['DB_PASSWORD'] = $questionHelper->ask($this->input, $this->output, new Question('DB_PASSWORD: What is your database password? '));
        
        return $settings;
    }

    /**
     * Verfies the settings provided by the user
     *
     * @param  array    $settings
     * @return string
     */
    protected function verifySettings(array $settings) {

        $rows = [];
        foreach ($settings as $key => $value) {
            $rows[] = [$key, $value];
        }

        $table = new Table($this->output);

        $table->setHeaders(['Setting', 'Value'])
              ->setRows($rows)
              ->render();

        return $this->getHelper('question')
                    ->ask($this->input, $this->output, new ConfirmationQuestion('Do these settings look correct (yes)? ', true));
    }


    /**
     * Verfies the settings provided by the user
     *
     * @param  array    $settings
     * @param  string  $dir
     * @return string
     */
    protected function saveSettings(array $settings, $dir) {
        $contents = file($dir . '/.env', FILE_IGNORE_NEW_LINES);
        $allSettings = [];

        // Load the env contents into an associative array
        foreach ($contents as $value) {
            $content = explode('=', $value);

            // Preserve comments and new lines
            if (count($content) > 1) {
                $allSettings[$content[0]] = $content[1];
            }
            else {
                $allSettings[] = $content[0];
            }
        }

        // Combine the new settings with the old
        $allSettings = array_merge($allSettings, $settings);

        // Convert values to key=value format to be written back into config file
        array_walk($allSettings, function (&$item, $key) {
            if (!is_int($key)) {
                $item = $key . '=' . $item;
            }
        });

        file_put_contents($dir . '/.env', implode(PHP_EOL, $allSettings));
    }
    
}