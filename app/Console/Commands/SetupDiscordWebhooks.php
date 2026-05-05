<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Command to interactively setup Discord webhook URLs in the environment file.
 */
class SetupDiscordWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:setup-webhooks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configures the Discord webhook URLs interactively in the .env file';

    /**
     * Execute the console command.
     * 
     * @return void
     */
    public function handle(): void
    {
        $this->info('A iniciar a configuração dos Webhooks do Discord...');

        $errorWebhook = $this->ask('Por favor, insere o URL para o DISCORD_ERROR_WEBHOOK_URL');
        $ticketWebhook = $this->ask('Por favor, insere o URL para o DISCORD_TICKET_WEBHOOK_URL');

        if ($errorWebhook) {
            $this->setEnvironmentValue('DISCORD_ERROR_WEBHOOK_URL', $errorWebhook);
        }

        if ($ticketWebhook) {
            $this->setEnvironmentValue('DISCORD_TICKET_WEBHOOK_URL', $ticketWebhook);
        }

        $this->info('Os Webhooks do Discord foram configurados com sucesso no teu ficheiro .env!');
    }

    /**
     * Updates or adds a key-value pair in the .env file.
     *
     * @param string $key The environment variable key
     * @param string $value The environment variable value
     * @return void
     */
    private function setEnvironmentValue(string $key, string $value): void
    {
        $envFile = app()->environmentFilePath();
        
        if (!File::exists($envFile)) {
            $this->error('Ficheiro .env não encontrado. Por favor, copia o .env.example primeiro.');
            return;
        }

        $str = file_get_contents($envFile);

        // Check if the key already exists in the .env file
        $keyPosition = strpos($str, "{$key}=");

        if ($keyPosition !== false) {
            // Replace the existing line with the new key-value pair
            $str = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $str);
        } else {
            // Append the key-value pair to the end of the file
            $str .= "\n{$key}={$value}\n";
        }

        file_put_contents($envFile, $str);
    }
}