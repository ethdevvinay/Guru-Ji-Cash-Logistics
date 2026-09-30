<?php

declare(strict_types=1);

namespace App\Enums;

/** Fine-grained permissions for ADMIN users (spec §6). Collectors and retailers never hold these. */
enum AdminPermission: string
{
    case PickupsManage = 'pickups.manage';
    case PickupsOverride = 'pickups.override';
    case LedgerView = 'ledger.view';
    case WalletAdjust = 'wallet.adjust';
    case PenaltiesManage = 'penalties.manage';
    case VaultOperate = 'vault.operate';
    case VaultSignoff = 'vault.signoff';
    case BanksManage = 'banks.manage';
    case DepositsManage = 'deposits.manage';
    case ReconciliationManage = 'reconciliation.manage';
    case LiveView = 'live.view';
    case SosManage = 'sos.manage';
    case BroadcastsManage = 'broadcasts.manage';
    case CollectorsManage = 'collectors.manage';
    case RetailersManage = 'retailers.manage';
    case ZonesManage = 'zones.manage';
    case DevicesManage = 'devices.manage';
    case DocumentsManage = 'documents.manage';
    case DocumentsView = 'documents.view';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case AuditView = 'audit.view';
    case UsersManage = 'users.manage';
    case PermissionsManage = 'permissions.manage';
    case SettingsManage = 'settings.manage';
    case ImportsManage = 'imports.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
