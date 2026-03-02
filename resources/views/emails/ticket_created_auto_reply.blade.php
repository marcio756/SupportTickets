<!DOCTYPE html>
<html>
<head>
    <title>Ticket Recebido</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Olá, recebemos o seu pedido de suporte!</h2>
    
    <p>O seu ticket foi criado com sucesso. O título registado foi:</p>
    <blockquote style="background: #f4f4f4; padding: 10px; border-left: 5px solid #ccc;">
        {{ $ticket->title }}
    </blockquote>

    <p><strong>⚠️ IMPORTANTE:</strong><br>
    Qualquer questão ou atualização relacionada com este pedido deve ser feita <strong>respondendo diretamente a este e-mail</strong>. Não altere o Assunto do e-mail para garantirmos que a mensagem fica associada ao seu ticket original.</p>

    <p>A nossa equipa entrará em contacto em breve.</p>
    
    <p>Obrigado,<br>
    Equipa de Suporte</p>
</body>
</html>