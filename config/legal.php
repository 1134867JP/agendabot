<?php

return [
    'version' => env('LEGAL_VERSION', '2026-08-28'),
    'entity_name' => env('LEGAL_ENTITY_NAME', 'Agendou'),
    'entity_document' => env('LEGAL_ENTITY_DOCUMENT'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
];
