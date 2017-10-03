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
    private $answerFile = null;

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
            ->addOption('update', 'u', InputOption::VALUE_NONE, 'Update an existing site', null)
            ->addOption('answerfile', 'a', InputOption::VALUE_OPTIONAL, 'File to use as default answers for unattended installation', null);
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
        $this->answerFile = $input->getOption('answerfile');

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
             ->processQuarx()
             ->processGetRealT();

        $this->executeCommand('php '. $this->directory .'/artisan migrate --step', $this->directory);

        if (!$this->update) {
            $appUrl = $this->settings['APP_URL'];

            $this->output->writeln(['<comment>============================================================</comment>',
                                    '<comment>============================================================</comment>',
                                    '<info>Login at;</info>', 
                                    '<info>' . $appUrl . '/login</info>', 
                                    '<info></info>', 
                                    '<info>Email: admin@example.com</info>', 
                                    '<info>Password: admin</info>', 
                                    '<info></info>', 
                                    '<info>You may change your email and  password at;</info>', 
                                    '<info>' . $appUrl . '/user/settings</info>', 
                                    '<info></info>', 
                                    '<info>You can find additional post installation instructions at;</info>', 
                                    '<info>http://help.getrealt.com/post_installation/</info>', 
                                    '<comment>============================================================</comment>',
                                    '<comment>============================================================</comment>']);
        }
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

            // Ensure the .env file exists
            if (!file_exists($this->directory . '/.env') && file_exists($this->directory . '/.env.example')) {
                $this->executeCommand('cp '. $this->directory .'/.env.example '. $this->directory .'/.env', $this->directory);
            }

            // Generate APP_KEY
            $this->executeCommand('php '. $this->directory .'/artisan key:generate', $this->directory);
        }

        $this->replaceInFile('/config/app.php', "'name' => 'Laravel',", "'name' => '" . $this->settings['APP_NAME'] . "',");

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

        if ($this->update) {
            $this->executeCommand($this->composer . ' update yab/quarx --optimize-autoloader', $this->directory);
            $this->executeCommand($this->composer . ' dump-autoload -o', $this->directory);
            $this->executeCommand('php '. $this->directory .'/artisan optimize', $this->directory);
        }
        else {
            $this->executeCommand($this->composer . ' require yab/quarx', $this->directory);

            $this->intoFile('/config/app.php', 
                            'Package Service Providers...' . PHP_EOL . '         */',
                            PHP_EOL . '        Yab\Quarx\QuarxProvider::class,');
        }

        $this->executeCommand('php '. $this->directory .'/artisan vendor:publish --provider="Yab\Quarx\QuarxProvider" --force', $this->directory);

        if ($this->update) {
            $this->executeCommand('npm update', $this->directory);
        }
        else {
            $this->executeCommand('php '. $this->directory .'/artisan quarx:setup', $this->directory);
        }

        $this->executeCommand('npm install', $this->directory);
        $this->executeCommand('npm run dev', $this->directory);

        return $this;
    }

    /**
     * Installs GetRealT
     *
     * @param  string  $directory
     * @return $this
     */
    protected function processGetRealT() {
        $this->output->writeln(['<comment>====================</comment>',
                                '<info>Processing GetRealT</info>', 
                                '<comment>====================</comment>']);

        if ($this->update) {
            $this->executeCommand($this->composer . ' update timitek/getrealt-quarx --optimize-autoloader', $this->directory);
            $this->executeCommand($this->composer . ' dump-autoload -o', $this->directory);
            $this->executeCommand('php '. $this->directory .'/artisan optimize', $this->directory);
        }
        else {
            $this->executeCommand($this->composer . ' require timitek/getrealt-quarx', $this->directory);
        }

        $this->executeCommand('php '. $this->directory .'/artisan vendor:publish --provider="Timitek\GetRETS\Providers\GetRETSServiceProvider" --tag=config --force', $this->directory);
        $this->executeCommand('php '. $this->directory .'/artisan vendor:publish --provider="Timitek\GetRealT\Providers\GetRealTServiceProvider" --tag=config --force', $this->directory);
        $this->executeCommand('php '. $this->directory .'/artisan vendor:publish --provider="Timitek\GetRealT\Providers\GetRealTServiceProvider" --tag=public --force', $this->directory);

        $this->replaceInFile('/config/quarx.php', "'backend-title' => 'Quarx'", "'backend-title' => 'GetRealT'");
        $this->replaceInFile('/config/quarx.php', "'frontend-theme' => 'default'", "'frontend-theme' => '../../vendor/timitek/getrealt-quarx/resources/views/theme'");

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
     * Replace a string within a file
     *
     * @param  string  $file
     * @param  string  $search
     * @param  string  $replace
     * @return $this
     */
    protected function replaceInFile($file, $search, $replace) {
        $inserted = false;
        if (file_exists($this->directory . $file)) {
            $fileContents = file_get_contents($this->directory . $file);
            
            if (strpos($fileContents, $search) !== FALSE) {
                file_put_contents($this->directory . $file, str_replace($search, $replace, $fileContents));
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
            throw new RuntimeException('Site already exists!  Consider using --update');
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

        $currentSettings = array_merge([
                                           'APP_NAME' => 'GetRealT',
                                           'APP_URL' => 'http://www.getrealt.com',
                                           'MAIL_FROM_NAME' => 'GetRealT',
                                           'MAIL_DRIVER' => 'sendmail',
                                           'MAIL_HOST' => 'null',
                                           'MAIL_PORT' => 'null',
                                           'MAIL_USERNAME' => 'null',
                                           'MAIL_PASSWORD' => 'null',
                                           'MAIL_ENCRYPTION' => 'null',
                                           'DB_HOST' => '127.0.0.1',
                                           'DB_PORT' => '3306',
                                           'GETREALT_SITE_NAME' => $this->name,
                                           'GETREALT_THEME' => 'united',
                                       ], $this->loadCurrentSettings());

        $settingsVerified = false;
        $answers = $this->loadAnswerFile();
        if (!empty($answers) && count($answers)) {
            $currentSettings = array_merge($currentSettings, $answers);
            if ($this->verifySettings($currentSettings)) {
                $settingsVerified = true;
                $settings = $currentSettings;
            }
        }

        if (!$settingsVerified) {
            do {
                $this->getSetting($currentSettings, 'APP_NAME', 'What is the application name you would like assigned for this site', $settings)
                    ->getSetting($currentSettings, 'APP_URL', 'What is the primary URL for your website', $settings)
                    ->getSetting($currentSettings, 'MAIL_FROM_ADDRESS', 'For generic e-mails, what e-mail would you like to use as the "from e-mail address"', $settings)
                    ->getSetting($currentSettings, 'MAIL_FROM_NAME', 'For generic e-mails, what name would you like to use as the "from name"', $settings)
                    ->getSetting($currentSettings, 'MAIL_DRIVER', 'Which mail driver (Supported: "smtp", "sendmail", "mailgun", "mandrill", "ses","sparkpost", "log", "array")', $settings)
                    ->getSetting($currentSettings, 'MAIL_HOST', 'If you selected SMTP, what is your SMTP host address', $settings)
                    ->getSetting($currentSettings, 'MAIL_PORT', 'If you selected SMTP, what is your SMTP host port', $settings)
                    ->getSetting($currentSettings, 'MAIL_USERNAME', 'If you selected SMTP, what is your SMTP username', $settings)
                    ->getSetting($currentSettings, 'MAIL_PASSWORD', 'If you selected SMTP, what is your SMTP password', $settings)
                    ->getSetting($currentSettings, 'MAIL_ENCRYPTION', 'What E-Mail encryption protocol should be used', $settings)
                    ->getSetting($currentSettings, 'DB_HOST', 'What is your database host', $settings)
                    ->getSetting($currentSettings, 'DB_PORT', 'What is your database port', $settings)
                    ->getSetting($currentSettings, 'DB_DATABASE', 'What is your database name', $settings)
                    ->getSetting($currentSettings, 'DB_USERNAME', 'What is your database username', $settings)
                    ->getSetting($currentSettings, 'DB_PASSWORD', 'What is your database password', $settings)
                    ->getSetting($currentSettings, 'GETRETS_CUSTOMER_KEY', 'What is the customer key that was assigned to you by timitek', $settings)
                    ->getSetting($currentSettings, 'GETREALT_SITE_NAME', 'What is the name you would like to use in the sites banner as the site name', $settings)
                    ->getSetting($currentSettings, 'GETREALT_THEME', 'What is the initial theme you would like to use for the site (this can be easily changed later)', $settings)
                    ->getSetting($currentSettings, 'GETREALT_MAPS_KEY', 'What is your google maps api key <info>(https://developers.google.com/maps/documentation/javascript/get-api-key)</info>', $settings)
                    ->getSetting($currentSettings, 'GETREALT_LEADS_EMAIL', 'What e-mail address do you want your leads sent too', $settings);
                $currentSettings = $settings;
                $settingsVerified = $this->verifySettings($settings);
            } while(!$settingsVerified);
        }

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
     * Loads the answer file to be used for unattended installation
     *
     * @return array
     */
    protected function loadAnswerFile() {
        $settings = [];

        if (!empty($this->answerFile)) {
            if (file_exists($this->answerFile)) {
                $contents = file($this->answerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                // Load the env contents into an associative array
                foreach ($contents as $value) {
                    $line = explode('=', $value);

                    // Preserve comments and new lines
                    if (count($line) > 1) {
                        $settings[$line[0]] = $line[1];
                    }
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
                $formattedValue = $item;
                if ((strpos($formattedValue, " ") !== FALSE) && (strpos($formattedValue, '"') === FALSE)) {
                    $formattedValue = '"' . $formattedValue . '"';
                } 

                $item = $key . '=' . $formattedValue;
            }
        });

        file_put_contents($this->directory . '/.env', implode(PHP_EOL, $allSettings));

        return $this;
    }
    
}