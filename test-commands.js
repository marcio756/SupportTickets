import dotenv from 'dotenv';

dotenv.config();

const botToken = process.env.DISCORD_BOT_TOKEN;
const clientId = process.env.DISCORD_CLIENT_ID;

const url = `https://discord.com/api/v10/applications/${clientId}/commands`;

console.log(`🔍 Verificando comandos registados no Discord...`);
console.log(`URL: ${url}`);

try {
    const response = await fetch(url, {
        headers: {
            'Authorization': `Bot ${botToken}`
        }
    });

    if (response.ok) {
        const commands = await response.json();
        console.log(`\n✅ Encontrados ${commands.length} comandos:\n`);
        commands.forEach((cmd, idx) => {
            console.log(`${idx + 1}. /${cmd.name} - ${cmd.description}`);
        });
    } else {
        console.error(`❌ Erro ${response.status}: ${await response.text()}`);
    }
} catch (error) {
    console.error('❌ Erro:', error.message);
}
