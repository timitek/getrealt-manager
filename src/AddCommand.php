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

class AddCommand extends Command {

    private $input = null;
    private $output = null;
    private $name = null;
    private $directory = null;
    private $settings = null;
    private $update = null;

    /**
     * Configure the command options.
     *
     * @return void
     */
    public function configure() {
        $this
            ->setName('add')
            ->setDescription('Create / update a GetRealT site.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of your site')    
            ->addOption('update', null, InputOption::VALUE_OPTIONAL, 'update an existing site', false);
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
        $this->name = $input->getArgument('name');
        $this->directory = getcwd() . '/' . $this->name;
        $this->composer = $this->findComposer();
        $this->update = $input->getOption('update');

        if ($this->update) {
            $this->verifyExistingSite($this->name);
            $this->output->writeln(['<comment>====================</comment>',
                                    '<info>Updating ' . $this->name . '</info>', 
                                    '<comment>====================</comment>']);
        }
        else {
            $this->verifyNewSite($this->name);
            $this->output->writeln(['<comment>====================</comment>',
                                    '<info>Creating ' . $this->name . '</info>', 
                                    '<comment>====================</comment>']);
        }

        $this->settings = $this->getSettings();

        $this->processLaravel()
             ->saveSettings($this->settings)
             ->processQuarx();

    }

    /**
     * Installs Laravel and applies settings to the .env
     *
     * @param  string  $directory
     * @return $this
     */
    protected function processLaravel() {
        $this->output->writeln(['<comment>====================</comment>',
                                '<info>Processing Laravel</info>', 
                                '<comment>====================</comment>']);

        if (!$this->update) {
            $this->executeCommand($this->composer . ' create-project --prefer-dist laravel/laravel ' . $this->name, $this->directory);
        }

        return $this;
    }

    /**
     * Installs Laravel and applies settings to the .env
     *
     * @param  string  $directory
     * @return $this
     */
    protected function processQuarx() {
        $this->output->writeln(['<comment>====================</comment>',
                                '<info>Processing Quarx</info>', 
                                '<comment>====================</comment>']);

        if (!$this->update) {
            $this->executeCommand($this->composer . ' require yab/quarx', $this->directory);
        }

        $this->intoFile('/config/app.php', 
                        'Package Service Providers...' . PHP_EOL . '         */',
                         PHP_EOL . '        Yab\Quarx\QuarxProvider::class,');

        $this->executeCommand('php '. $this->directory .'/artisan vendor:publish --provider="Yab\Quarx\QuarxProvider"', $this->directory);

        if (!$this->update) {
            $this->executeCommand('php '. $this->directory .'/artisan quarx:setup', $this->directory);
        }

        return $this;
    }

    /**
     * Find the position of a string within a file (-1 if not found)
     *
     * @param  string  $file
     * @param  string  $insertAfter
     * @param  string  $newContent
     * @return $this
     */
    protected function intoFile($file, $insertAfter, $newContent) {
        $inserted = false;
        if (file_exists($this->directory . $file)) {
            $fileContents = file_get_contents($this->directory . $file);
            
            if (strpos($fileContents, $newContent) === FALSE) {
                $position = strpos($fileContents, $insertAfter);
                if ($position !== FALSE) {
                    $inserted = true;
                    file_put_contents($this->directory . $file, substr_replace($fileContents, $newContent, $position + strlen($insertAfter), 0));
                }
            }
        }
        return $inserted;
    }

    /**
     * Verify that the site can be created.
     *
     * @param  string  $directory
     * @return $this
     */
    protected function verifyNewSite() {
        if ((is_dir($this->directory) || is_file($this->directory)) && $this->directory != getcwd()) {
            throw new RuntimeException('Site already exists!  Consider using --update=true');
        }

        return $this;
    }

    /**
     * Verify that an existing site can be updated
     *
     * @param  string  $directory
     * @return $this
     */
    protected function verifyExistingSite($directory) {
        if (!is_dir($this->directory) && $this->directory != getcwd()) {
            throw new RuntimeException('This site does not exist!');
        }

        return $this;
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
     * @return $this
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

        return $this;
    }   

    /**
     * Prompts the user to enter the settings for the new site
     *
     * @return string
     */
    protected function getSettings() {
        $settings = [];        
        $questionHelper = $this->getHelper('question');

        $currentSettings = array_merge($this->loadCurrentSettings(),
                                       [
                                           'DB_HOST' => '127.0.0.1',
                                           'DB_PORT' => '3306'                                                
                                       ]);

        do {
            $this->getSetting($currentSettings, 'DB_HOST', 'What is your database host', $settings)
                 ->getSetting($currentSettings, 'DB_PORT', 'What is your database port', $settings)
                 ->getSetting($currentSettings, 'DB_DATABASE', 'What is your database name', $settings)
                 ->getSetting($currentSettings, 'DB_USERNAME', 'What is your database username', $settings)
                 ->getSetting($currentSettings, 'DB_PASSWORD', 'What is your database password', $settings);
            $currentSettings = $settings;
        } while(!$this->verifySettings($settings));
        
        return $settings;
    }

    /**
     * Gets a setting by prompting and providing the current default
     *
     * @param  array    $defaults
     * @param  string   $key
     * @param  string   $question
     * @param  array    $settings
     * @return boolean
     */
    protected function getSetting($defaults, $key, $question, &$destination) {
        $questionHelper = $this->getHelper('question');
        $defaultValue = array_key_exists($key, $defaults) ? $defaults[$key] : null;
        $formattedQuestion = '<info>' .$key . ':</info> ' . $question . ($defaultValue ? '<comment> [' . $defaults[$key] . ']</comment>' : '' ) . '? ';
        $destination[$key] = $questionHelper->ask($this->input, $this->output, new Question($formattedQuestion, $defaultValue));
        return $this;
    }

    /**
     * Verfies the settings provided by the user
     *
     * @param  array    $settings
     * @return boolean
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
                    ->ask($this->input, $this->output, new ConfirmationQuestion('Do these settings look correct <comment>[yes]</comment>? ', true));
    }

    /**
     * Loads the current settings from the .env file
     *
     * @return array
     */
    protected function loadCurrentSettings() {
        $settings = [];

        if (file_exists($this->directory . '/.env')) {
            $contents = file($this->directory . '/.env', FILE_IGNORE_NEW_LINES);

            // Load the env contents into an associative array
            foreach ($contents as $value) {
                $line = explode('=', $value);

                // Preserve comments and new lines
                if (count($line) > 1) {
                    $settings[$line[0]] = $line[1];
                }
                else {
                    $settings[] = $line[0];
                }
            }
        }

        return $settings;
    }

    /**
     * Verfies the settings provided by the user
     *
     * @param  array    $settings
     * @return $this
     */
    protected function saveSettings(array $settings) {

        // Combine the new settings with the old
        $allSettings = array_merge($this->loadCurrentSettings(), $settings);

        // Convert values to key=value format to be written back into config file
        array_walk($allSettings, function (&$item, $key) {
            if (!is_int($key)) {
                $item = $key . '=' . $item;
            }
        });

        file_put_contents($this->directory . '/.env', implode(PHP_EOL, $allSettings));

        return $this;
    }
    
}