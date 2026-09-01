<?php

namespace App\Support;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Centraliza o escopo operacional da equipe de um estabelecimento.
 *
 * O papel profissional nunca é uma regra apenas de interface: as consultas e
 * ações sensíveis passam por esta classe para impedir acesso por URL/API a
 * dados de outro profissional.
 */
final class TenantAccess
{
    public static function papel(Tenant $tenant, ?User $user = null): ?string
    {
        $user ??= auth()->user();

        if (! $user || $user->is_super_admin) {
            return 'admin';
        }

        return $tenant->users()->whereKey($user->id)->value('papel');
    }

    public static function profissionalId(Tenant $tenant, ?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user || self::papel($tenant, $user) !== 'profissional') {
            return null;
        }

        $profissionalId = Profissional::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->value('id');

        abort_unless($profissionalId, 403, 'Seu acesso profissional não está vinculado a um profissional ativo.');

        return (int) $profissionalId;
    }

    public static function scopeAgendamentos(Builder|Relation $query, Tenant $tenant, ?User $user = null): Builder|Relation
    {
        $profissionalId = self::profissionalId($tenant, $user);

        return $profissionalId ? $query->where('profissional_id', $profissionalId) : $query;
    }

    public static function scopeConversas(Builder|Relation $query, Tenant $tenant, ?User $user = null): Builder|Relation
    {
        $profissionalId = self::profissionalId($tenant, $user);

        return $profissionalId ? $query->where('profissional_id', $profissionalId) : $query;
    }

    public static function scopeClientes(Builder|Relation $query, Tenant $tenant, ?User $user = null): Builder|Relation
    {
        $profissionalId = self::profissionalId($tenant, $user);

        if (! $profissionalId) {
            return $query;
        }

        return $query->where(function (Builder $clientes) use ($profissionalId): void {
            $clientes->whereHas('agendamentos', fn (Builder $agendamentos) => $agendamentos->where('profissional_id', $profissionalId))
                ->orWhereHas('conversas', fn (Builder $conversas) => $conversas->where('profissional_id', $profissionalId));
        });
    }

    public static function assertAgendamento(Agendamento $agendamento, Tenant $tenant, ?User $user = null): void
    {
        abort_unless((int) $agendamento->tenant_id === (int) $tenant->id, 403);

        $profissionalId = self::profissionalId($tenant, $user);
        abort_if($profissionalId && (int) $agendamento->profissional_id !== $profissionalId, 403);
    }

    public static function assertConversa(Conversa $conversa, Tenant $tenant, ?User $user = null): void
    {
        abort_unless((int) $conversa->tenant_id === (int) $tenant->id, 403);

        $profissionalId = self::profissionalId($tenant, $user);
        abort_if($profissionalId && (int) $conversa->profissional_id !== $profissionalId, 403);
    }

    public static function assertCliente(Cliente $cliente, Tenant $tenant, ?User $user = null): void
    {
        abort_unless((int) $cliente->tenant_id === (int) $tenant->id, 403);

        $profissionalId = self::profissionalId($tenant, $user);
        if (! $profissionalId) {
            return;
        }

        $temAcesso = $cliente->agendamentos()->where('profissional_id', $profissionalId)->exists()
            || $cliente->conversas()->where('profissional_id', $profissionalId)->exists();

        abort_unless($temAcesso, 403);
    }
}
