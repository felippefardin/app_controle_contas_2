<?php

declare(strict_types=1);

function lgpd_registration_version(): string
{
    return '2.0';
}

function lgpd_registration_sections(): array
{
    return [
        ['title' => '1. Objeto e aceite do contrato', 'body' => 'Este documento reúne os Termos de Uso e o Aviso de Privacidade do App Controle de Contas. Ao concluir o cadastro, o titular confirma que leu as condições, compreendeu como o serviço funciona e concorda em criar a conta e utilizar a plataforma conforme estas regras.'],
        ['title' => '2. Serviço disponibilizado', 'body' => 'A plataforma oferece recursos de organização financeira, contas a pagar e receber, vendas, caixa, estoque, relatórios, usuários e funcionalidades relacionadas ao plano escolhido. Recursos, limites, período de teste e suporte podem variar conforme o plano apresentado antes da contratação.'],
        ['title' => '3. Teste, planos e cobranças', 'body' => 'O período gratuito é de 15 ou 30 dias, conforme o plano. Ao contratar um plano pago, cobranças e renovações são processadas pelo provedor de pagamento informado no sistema. O usuário deve manter seus dados de cobrança atualizados. Falhas de pagamento podem causar aviso, limitação ou suspensão de acesso, sem apagar imediatamente os dados.'],
        ['title' => '4. Dados pessoais tratados', 'body' => 'Podemos tratar nome, e-mail, telefone, CPF ou CNPJ, senha protegida por hash, empresa, plano, registros de acesso, endereço IP, preferências, dados de suporte, informações financeiras inseridas pelo próprio usuário e identificadores de pagamento. O sistema não precisa receber a senha ou o número completo do cartão utilizado no provedor de pagamento.'],
        ['title' => '5. Finalidades e bases legais', 'body' => 'Os dados são utilizados para criar e autenticar a conta, executar o contrato, fornecer funcionalidades, processar assinaturas, prevenir fraude, proteger o sistema, atender suporte, cumprir obrigações legais e exercer direitos. Dados indispensáveis ao serviço são tratados com base na execução do contrato, cumprimento legal, segurança e interesses legítimos aplicáveis; por isso, o funcionamento da conta não depende de um consentimento genérico.'],
        ['title' => '6. Compartilhamento necessário', 'body' => 'Dados podem ser compartilhados, no limite necessário, com fornecedores que operam hospedagem, banco de dados, envio de e-mails, pagamentos, segurança, suporte e obrigações fiscais. Esses fornecedores atuam para viabilizar o serviço ou cumprir dever legal. Não vendemos dados pessoais.'],
        ['title' => '7. Inteligência artificial e automações', 'body' => 'Quando habilitados, recursos de análise inteligente podem utilizar valores e informações agregadas para produzir alertas ou recomendações. A automação não deve apagar, alterar ou realizar lançamentos financeiros sem uma ação autorizada no sistema. O painel permanece utilizável quando a inteligência artificial estiver indisponível.'],
        ['title' => '8. Segurança, acesso e responsabilidade', 'body' => 'Adotamos controles técnicos e administrativos compatíveis com o serviço, mas nenhum ambiente é totalmente imune a incidentes. O titular deve manter sua senha em sigilo, utilizar dados verdadeiros, controlar os usuários convidados e comunicar acessos suspeitos. É proibido usar a plataforma para fraude, invasão, conteúdo ilícito ou violação de direitos de terceiros.'],
        ['title' => '9. Retenção, desativação e exclusão', 'body' => 'Os dados são mantidos durante a prestação do serviço e pelos prazos necessários ao cumprimento legal, prevenção de fraude e exercício de direitos. Na desativação temporária, os dados podem permanecer preservados por até um ano, sem novas cobranças, conforme informado na solicitação. Na exclusão definitiva, os dados operacionais são apagados e não poderão ser recuperados; provas de aceite e registros legalmente necessários podem ser mantidos pelo prazo informado na política.'],
        ['title' => '10. Direitos do titular', 'body' => 'O titular pode solicitar confirmação de tratamento, acesso, correção, informação sobre compartilhamento, portabilidade quando aplicável, anonimização, bloqueio ou eliminação nos casos previstos em lei, além de revisão de decisões automatizadas aplicáveis. As solicitações podem exigir validação de identidade para proteger a conta.'],
        ['title' => '11. Comunicações', 'body' => 'Mensagens essenciais sobre segurança, cobrança, suporte, alterações contratuais e funcionamento da conta fazem parte do serviço. Comunicações promocionais são opcionais e podem ser recusadas sem impedir o uso normal da plataforma.'],
        ['title' => '12. Alterações e contato', 'body' => 'Mudanças relevantes nestes termos serão informadas de modo claro e uma nova manifestação poderá ser solicitada quando necessário. Dúvidas e solicitações de privacidade podem ser encaminhadas pelos canais de Suporte e Proteção de Dados disponíveis no sistema.'],
    ];
}

function lgpd_registration_manifestation(): string
{
    return 'Confirmo que li os Termos de Uso e o Aviso de Privacidade, compreendi as finalidades e condições apresentadas e solicito a criação da minha conta no App Controle de Contas.';
}
