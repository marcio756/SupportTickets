<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Registers the global Slash Commands with the Discord API.
 */
class RegisterDiscordCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:register-commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registers slash commands in the Discord Application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botToken = config('services.discord.bot_token');
        $clientId = config('services.discord.client_id');

        if (! $botToken || ! $clientId) {
            $this->error('Faltam as credenciais do Discord no ficheiro .env (DISCORD_BOT_TOKEN ou DISCORD_CLIENT_ID).');
            return;
        }

        $url = "https://discord.com/api/v10/applications/{$clientId}/commands";

        $commands = [
            [
                'name' => 'help',
                'description' => 'Mostra todos os comandos disponíveis do bot',
                'options' => []
            ],
            [
                'name' => 'setchannel',
                'description' => 'Configura o canal onde os alertas do Laravel serão enviados.',
                'options' => [
                    [
                        'name' => 'modulo',
                        'description' => 'O módulo que queres configurar',
                        'type' => 3, // String
                        'required' => true,
                        'choices' => [
                            ['name' => 'Tickets', 'value' => 'tickets'],
                            ['name' => 'Erros do Laravel', 'value' => 'errors']
                        ]
                    ],
                    [
                        'name' => 'canal',
                        'description' => 'O canal para onde enviar as mensagens',
                        'type' => 7, // Channel
                        'required' => true,
                    ]
                ]
            ],
            [
                'name' => 'seterrors',
                'description' => 'Configura o canal específico para erros do Laravel.',
                'options' => [
                    [
                        'name' => 'canal',
                        'description' => 'O canal para onde enviar os erros',
                        'type' => 7, // Channel
                        'required' => true,
                    ]
                ]
            ]
        ];

        $this->info('A enviar comandos para o Discord...');

        $response = Http::withHeaders([
            'Authorization' => "Bot {$botToken}"
        ])->put($url, $commands);

        if ($response->successful()) {
            $this->info('Comandos registados com sucesso! Pode demorar até 1 hora a propagar globalmente (ou instantâneo num servidor de testes).');
        } else {
            $this->error('Erro ao registar comandos: ' . $response->body());
        }
    }
}