<?php

return [
    'basico' => [
        'nome'      => 'Básico',
        'valor'     => 79.90,
        'recursos'  => 3,
        'descricao' => 'Até 3 recursos, bot WhatsApp, agenda',
        'destaque'  => false,
        'features'  => [
            'Bot WhatsApp com IA',
            'Até 3 recursos',
            'Agenda visual',
            'Reserva manual',
        ],
        'nao_inclui' => [
            'Relatórios avançados',
            'Suporte prioritário',
            'API access',
            'White-label',
        ],
    ],
    'profissional' => [
        'nome'      => 'Profissional',
        'valor'     => 129.90,
        'recursos'  => 10,
        'descricao' => 'Até 10 recursos, relatórios, suporte prioritário',
        'destaque'  => true,
        'features'  => [
            'Bot WhatsApp com IA',
            'Até 10 recursos',
            'Agenda visual',
            'Reserva manual',
            'Relatórios avançados',
            'Suporte prioritário',
        ],
        'nao_inclui' => [
            'API access',
            'White-label',
        ],
    ],
    'ilimitado' => [
        'nome'      => 'Ilimitado',
        'valor'     => 199.90,
        'recursos'  => null,
        'descricao' => 'Recursos ilimitados, API access, white-label',
        'destaque'  => false,
        'features'  => [
            'Bot WhatsApp com IA',
            'Recursos ilimitados',
            'Agenda visual',
            'Reserva manual',
            'Relatórios avançados',
            'Suporte prioritário',
            'API access',
            'White-label',
        ],
        'nao_inclui' => [],
    ],
];
