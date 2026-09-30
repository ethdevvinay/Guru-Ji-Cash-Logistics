<?php

declare(strict_types=1);

namespace App\Enums;

/** Permission bundles applied to ADMIN users (spec §6). They are presets, not roles. */
enum AdminPermissionPreset: string
{
    case Operations = 'operations';
    case VaultFinance = 'vault_finance';
    case Viewer = 'viewer';

    /** @return list<AdminPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Operations => [
                AdminPermission::LiveView, AdminPermission::PickupsManage, AdminPermission::CollectorsManage,
                AdminPermission::RetailersManage, AdminPermission::ZonesManage, AdminPermission::DevicesManage,
                AdminPermission::SosManage, AdminPermission::BroadcastsManage, AdminPermission::DocumentsView,
            ],
            self::VaultFinance => [
                AdminPermission::VaultOperate, AdminPermission::VaultSignoff, AdminPermission::BanksManage,
                AdminPermission::DepositsManage, AdminPermission::ReconciliationManage, AdminPermission::LedgerView,
                AdminPermission::WalletAdjust, AdminPermission::PenaltiesManage, AdminPermission::ReportsView,
                AdminPermission::ReportsExport,
            ],
            self::Viewer => [
                AdminPermission::LiveView, AdminPermission::LedgerView, AdminPermission::ReportsView,
                AdminPermission::AuditView, AdminPermission::DocumentsView,
            ],
        };
    }
}
