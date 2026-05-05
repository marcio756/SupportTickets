import { Client, GatewayIntentBits } from 'discord.js';
import dotenv from 'dotenv';

dotenv.config();

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.DirectMessages,
        GatewayIntentBits.MessageContent,
    ]
});

// Event: Bot conectado
client.on('clientReady', () => {
    console.log(`✅ Bot conectado como ${client.user.tag}`);
    client.user.setActivity('Support Tickets', { type: 'WATCHING' });
});

// Event: Interação (slash commands)
client.on('interactionCreate', async (interaction) => {
    console.log(`📩 Interação recebida: ${interaction.type}`);
    
    if (!interaction.isCommand()) {
        console.log('❌ Não é um comando');
        return;
    }

    const { commandName } = interaction;
    console.log(`🔧 Comando: /${commandName}`);

    try {
        if (commandName === 'help') {
            const helpEmbed = {
                color: 0x00FF00,
                title: '📚 Comandos Disponíveis',
                description: 'Aqui está a lista de todos os comandos do bot',
                fields: [
                    {
                        name: '1️⃣ `/help`',
                        value: 'Mostra todos os comandos disponíveis',
                        inline: false
                    },
                    {
                        name: '2️⃣ `/setchannel`',
                        value: 'Configura o canal para tickets e erros\n**Opções:**\n• `modulo`: Escolhe entre "Tickets" ou "Erros do Laravel"\n• `canal`: Seleciona o canal Discord',
                        inline: false
                    },
                    {
                        name: '3️⃣ `/seterrors`',
                        value: 'Configura especificamente o canal para erros do Laravel',
                        inline: false
                    },
                    {
                        name: '💡 Exemplos de uso',
                        value: '`/setchannel modulo:Tickets canal:#tickets`\n`/setchannel modulo:Erros canal:#erros`',
                        inline: false
                    }
                ],
                footer: { text: 'Support Tickets Bot' },
                timestamp: new Date().toISOString()
            };

            return interaction.reply({ embeds: [helpEmbed] });
        }

        if (commandName === 'setchannel') {
            const modulo = interaction.options.getString('modulo');
            const canal = interaction.options.getChannel('canal');

            if (!canal) {
                return interaction.reply({
                    content: '❌ Canal não especificado corretamente.',
                    ephemeral: true
                });
            }

            console.log(`📡 Enviando para Laravel: modulo=${modulo}, channel=${canal.id}`);
            
            const response = await fetch('http://localhost:8000/api/v1/discord/set-channel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    modulo: modulo,
                    channel_id: canal.id
                })
            });

            console.log(`📥 Resposta do Laravel: ${response.status}`);

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Sucesso:', data);
                const emoji = modulo === 'tickets' ? '🎫' : '⚠️';
                return interaction.reply({
                    content: `${emoji} Canal de **${modulo}** configurado para ${canal}`,
                    ephemeral: false
                });
            } else {
                const errorText = await response.text();
                console.error(`❌ Erro ${response.status}: ${errorText}`);
                return interaction.reply({
                    content: '❌ Erro ao configurar o canal. Verifique se o servidor Laravel está online.',
                    ephemeral: true
                });
            }
        }

        if (commandName === 'seterrors') {
            const canal = interaction.options.getChannel('canal');

            if (!canal) {
                return interaction.reply({
                    content: '❌ Canal não especificado corretamente.',
                    ephemeral: true
                });
            }

            console.log(`📡 Enviando para Laravel: modulo=errors, channel=${canal.id}`);
            
            const response = await fetch('http://localhost:8000/api/v1/discord/set-channel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    modulo: 'errors',
                    channel_id: canal.id
                })
            });

            console.log(`📥 Resposta do Laravel: ${response.status}`);

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Sucesso:', data);
                return interaction.reply({
                    content: `⚠️ Canal de **erros** configurado para ${canal}`,
                    ephemeral: false
                });
            } else {
                const errorText = await response.text();
                console.error(`❌ Erro ${response.status}: ${errorText}`);
                return interaction.reply({
                    content: '❌ Erro ao configurar o canal. Verifique se o servidor Laravel está online.',
                    ephemeral: true
                });
            }
        }
    } catch (error) {
        console.error('❌ Erro ao processar comando:', error.message);
        console.error('Stack:', error.stack);
        await interaction.reply({
            content: '❌ Ocorreu um erro ao processar o comando.',
            ephemeral: true
        });
    }
});

// Conectar o bot
client.login(process.env.DISCORD_BOT_TOKEN).catch((error) => {
    console.error('❌ Erro ao conectar ao Discord:', error.message);
    process.exit(1);
});
