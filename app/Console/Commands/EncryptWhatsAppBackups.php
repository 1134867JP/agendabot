<?php

namespace App\Console\Commands;

use App\Services\WhatsAppConversationBackupService;
use Illuminate\Console\Command;

class EncryptWhatsAppBackups extends Command
{
    protected $signature = 'whatsapp:encrypt-backups';

    protected $description = 'Criptografa backups legados do WhatsApp usando a APP_KEY';

    public function handle(WhatsAppConversationBackupService $backups): int
    {
        $quantidade = $backups->criptografarBackupsLegados();
        $this->info("{$quantidade} backup(s) legado(s) criptografado(s).");

        return self::SUCCESS;
    }
}
