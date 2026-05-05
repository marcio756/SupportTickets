import dotenv from 'dotenv';

dotenv.config();

// Simular o comando /setchannel
const modulo = 'tickets';
const channelId = '123456789';

console.log(`\n📡 Simulando comando /setchannel`);
console.log(`Modulo: ${modulo}, Channel: ${channelId}\n`);

try {
    console.log(`📡 Enviando para Laravel: modulo=${modulo}, channel=${channelId}`);
    
    const response = await fetch('http://localhost:8000/api/v1/discord/set-channel', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            modulo: modulo,
            channel_id: channelId
        })
    });

    console.log(`📥 Resposta do Laravel: ${response.status} ${response.statusText}`);

    if (response.ok) {
        const data = await response.json();
        console.log('✅ JSON parsed:', data);
        console.log('✅ Sucesso - Canal configurado!');
    } else {
        const errorText = await response.text();
        console.error(`❌ Erro ${response.status}: ${errorText}`);
    }
} catch (error) {
    console.error('❌ Erro de conexão com Laravel:', error.message);
    console.error('Stack:', error.stack);
}
