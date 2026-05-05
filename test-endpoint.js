import dotenv from 'dotenv';

dotenv.config();

const url = 'http://localhost:8000/api/v1/discord/set-channel';
const payload = {
    modulo: 'tickets',
    channel_id: '123456789'
};

console.log(`📡 Testando endpoint: ${url}`);
console.log(`📦 Payload: ${JSON.stringify(payload)}\n`);

try {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    });

    console.log(`✅ Status: ${response.status}`);
    const data = await response.text();
    console.log(`📥 Resposta: ${data}`);
} catch (error) {
    console.error(`❌ Erro: ${error.message}`);
}
