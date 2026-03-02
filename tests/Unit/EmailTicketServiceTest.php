<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ticket;
use App\Services\EmailTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailTicketServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se um ticket novo é criado quando o email não tem ID no assunto.
     */
    public function test_it_creates_a_new_ticket_from_an_email()
    {
        // 1. Preparação: Criar um utilizador simulado na base de dados
        $cliente = User::factory()->create([
            'email' => 'cliente.teste@exemplo.com',
        ]);

        // Dados simulados do email recebido
        $emailData = [
            'from_email' => 'cliente.teste@exemplo.com',
            'subject'    => 'Problema com o meu servidor',
            'body'       => 'Olá, o meu servidor está em baixo. Podem ajudar?',
        ];

        // 2. Ação: Instanciar o serviço e processar o email
        $service = new EmailTicketService();
        $ticket = $service->processEmail($emailData);

        // 3. Verificação: Confirmar que o ticket e a mensagem inicial foram criados corretamente
        $this->assertDatabaseHas('tickets', [
            'customer_id' => $cliente->id, // Alterado para verificar a coluna correta
            'title'       => 'Problema com o meu servidor',
        ]);
        
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message'   => 'Olá, o meu servidor está em baixo. Podem ajudar?',
        ]);
    }
}