<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Collector;
use App\Models\Device;
use App\Models\Permission;
use App\Models\Retailer;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Territory;
use App\Models\User;
use App\Models\Vault;
use App\Models\Wallet;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $adminRole = Role::create([
            'name' => 'Super Admin',
            'slug' => 'ADMIN',
            'description' => 'Central control, dispatch, disputes & multi-bank vault settlement',
        ]);

        $collectorRole = Role::create([
            'name' => 'Cash Collector',
            'slug' => 'COLLECTOR',
            'description' => 'Field executive with 100m geofence note count desk & thermal printer',
        ]);

        $retailerRole = Role::create([
            'name' => 'Retailer Merchant',
            'slug' => 'RETAILER',
            'description' => 'Merchant shop raising on-demand cash pickups & 1-tap wallet reload',
        ]);

        // 2. Permissions
        $modules = ['users', 'collectors', 'retailers', 'pickups', 'cash_collection', 'wallet', 'vault', 'banks', 'reconciliation', 'sos', 'broadcasts', 'reports'];
        foreach ($modules as $module) {
            foreach (['view', 'create', 'update', 'delete', 'export'] as $action) {
                $perm = Permission::create([
                    'name' => ucfirst($action) . ' ' . ucfirst($module),
                    'slug' => "{$module}.{$action}",
                    'module' => $module,
                ]);
                $adminRole->permissions()->attach($perm);
            }
        }

        // 3. System Operational Settings
        Setting::set('float_limit_default_paise', 10000000, 'INTEGER', 'Default Collector Cash Float Limit (₹1,00,000 in paise)');
        Setting::set('broadcast_countdown_seconds', 90, 'INTEGER', 'Pickup Broadcast Countdown Race Window');
        Setting::set('geofence_radius_meters', 100, 'INTEGER', 'Server-Side Geofence Enforcement Radius');
        Setting::set('free_cancellation_seconds', 180, 'INTEGER', 'Free Cancellation Window for Retailers (3 Minutes)');
        Setting::set('cancellation_penalty_paise', 5000, 'INTEGER', 'Retailer Cancellation Penalty (₹50 in paise)');
        Setting::set('penalty_collector_share_paise', 3500, 'INTEGER', 'Collector Fuel Share from Penalty (₹35 in paise)');
        Setting::set('penalty_admin_share_paise', 1500, 'INTEGER', 'Admin Operations Share from Penalty (₹15 in paise)');
        Setting::set('daily_outstanding_alert_time', '09:00', 'STRING', 'Daily 1-Time Outstanding Push Notification Time (IST)');

        // 4. Territories and Zones
        $territory = Territory::create([
            'name' => 'Rohtak Urban',
            'code' => 'ROH-URBAN',
            'state' => 'Haryana',
            'is_active' => true,
        ]);

        $zoneWard1 = Zone::create(['territory_id' => $territory->id, 'name' => 'Ward 1', 'code' => 'W1']);
        $zoneWard4 = Zone::create(['territory_id' => $territory->id, 'name' => 'Ward 4', 'code' => 'W4']);
        $zoneStation = Zone::create(['territory_id' => $territory->id, 'name' => 'Station Road', 'code' => 'STN']);
        $zoneMainChowk = Zone::create(['territory_id' => $territory->id, 'name' => 'Main Chowk', 'code' => 'MCK']);

        // 5. Central Vault & Bank Accounts
        Vault::create([
            'name' => 'Rohtak Central Vault Desk',
            'current_cash_paise' => 48500000, // ₹4,85,000 locked
        ]);

        BankAccount::create([
            'bank_name' => 'HDFC Bank',
            'account_name' => 'Guruji Logistics Current Account',
            'account_number_masked' => 'XXXX-XXXX-9012',
            'ifsc_code' => 'HDFC0001248',
            'branch' => 'Rohtak Main Branch',
            'account_type' => 'CURRENT',
            'is_active' => true,
        ]);

        BankAccount::create([
            'bank_name' => 'State Bank of India (SBI)',
            'account_name' => 'Guruji Logistics Settlement Account',
            'account_number_masked' => 'XXXX-XXXX-4589',
            'ifsc_code' => 'SBIN0000624',
            'branch' => 'Rohtak Civil Lines',
            'account_type' => 'CURRENT',
            'is_active' => true,
        ]);

        BankAccount::create([
            'bank_name' => 'ICICI Bank',
            'account_name' => 'Guruji Logistics Reserve Account',
            'account_number_masked' => 'XXXX-XXXX-7132',
            'ifsc_code' => 'ICIC0000185',
            'branch' => 'Rohtak Model Town',
            'account_type' => 'CURRENT',
            'is_active' => true,
        ]);

        // 6. Super Admin User
        $adminUser = User::create([
            'name' => 'System Super Admin',
            'email' => 'admin@rockvanta.com',
            'mobile' => '+919812000001',
            'password' => Hash::make('Admin@2026#CMS'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
        $adminUser->roles()->attach($adminRole);

        // 7. Field Collectors (from Spec PDF)
        $collectorData = [
            [
                'name' => 'Rahul Kumar',
                'email' => 'col.rahul104@rockvanta.com',
                'mobile' => '+919812000003',
                'password' => 'Collector@Rahul#104',
                'code' => 'COL-104',
                'bike' => 'HR-12-AJ-4921',
                'zone_id' => $zoneWard4->id,
                'float_paise' => 4000000, // ₹40,000
                'lat' => 28.895500,
                'lng' => 76.606600,
                'battery' => 84,
                'duty' => 'ON_JOB',
            ],
            [
                'name' => 'Amit Sharma',
                'email' => 'col.amit102@rockvanta.com',
                'mobile' => '+919812000007',
                'password' => 'Collector@Amit#102',
                'code' => 'COL-102',
                'bike' => 'HR-12-BE-8120',
                'zone_id' => $zoneStation->id,
                'float_paise' => 4000000,
                'lat' => 28.891200,
                'lng' => 76.598000,
                'battery' => 92,
                'duty' => 'ON_JOB',
            ],
            [
                'name' => 'Sonu Verma',
                'email' => 'col.sonu108@rockvanta.com',
                'mobile' => '+919812000008',
                'password' => 'Collector@Sonu#108',
                'code' => 'COL-108',
                'bike' => 'HR-12-CM-3391',
                'zone_id' => $zoneMainChowk->id,
                'float_paise' => 3200000, // ₹32,000
                'lat' => 28.903000,
                'lng' => 76.612000,
                'battery' => 68,
                'duty' => 'ON_DUTY',
            ],
        ];

        foreach ($collectorData as $cd) {
            $user = User::create([
                'name' => $cd['name'],
                'email' => $cd['email'],
                'mobile' => $cd['mobile'],
                'password' => Hash::make($cd['password']),
                'role' => 'COLLECTOR',
                'status' => 'ACTIVE',
            ]);
            $user->roles()->attach($collectorRole);

            Collector::create([
                'user_id' => $user->id,
                'collector_code' => $cd['code'],
                'bike_number' => $cd['bike'],
                'zone_id' => $cd['zone_id'],
                'float_limit_paise' => 10000000, // ₹1,00,000 limit
                'current_float_paise' => $cd['float_paise'],
                'duty_status' => $cd['duty'],
                'battery_percent' => $cd['battery'],
                'current_lat' => $cd['lat'],
                'current_lng' => $cd['lng'],
                'last_location_at' => now(),
            ]);

            Device::create([
                'user_id' => $user->id,
                'device_uuid' => (string) Str::uuid(),
                'device_model' => 'Samsung Galaxy M34 5G',
                'os_version' => 'Android 14',
                'app_version' => '1.0.0',
                'is_verified' => true,
                'is_active' => true,
                'last_active_at' => now(),
            ]);
        }

        // 8. Retailer Merchants (from Spec PDF)
        $retailerData = [
            [
                'shop_name' => 'Radhe Digital Store',
                'owner' => 'Radheshyam Gupta',
                'email' => 'ret.abcdigital@rockvanta.com',
                'mobile' => '+919812000004',
                'password' => 'Retailer@ABC#88',
                'address' => 'Shop No. 12, Main Road, Ward 4, Rohtak',
                'zone_id' => $zoneWard4->id,
                'lat' => 28.895800,
                'lng' => 76.606900,
                'wallet_paise' => 10000000, // ₹1,00,000 disbursed
                'outstanding_paise' => 2000000, // ₹20,000 udhar
            ],
            [
                'shop_name' => 'Sharma Telecom & AEPS',
                'owner' => 'Pankaj Sharma',
                'email' => 'ret.sharma@rockvanta.com',
                'mobile' => '+919812000012',
                'password' => 'Retailer@Sharma#12',
                'address' => 'Near Railway Station, Station Road, Rohtak',
                'zone_id' => $zoneStation->id,
                'lat' => 28.891500,
                'lng' => 76.598500,
                'wallet_paise' => 6000000, // ₹60,000 disbursed
                'outstanding_paise' => 0, // ₹0 Settled
            ],
            [
                'shop_name' => 'Gupta Grahak Seva Kendra',
                'owner' => 'Ramesh Gupta',
                'email' => 'ret.gupta@rockvanta.com',
                'mobile' => '+919812000015',
                'password' => 'Retailer@Gupta#15',
                'address' => 'Opposite City Center, Main Chowk, Rohtak',
                'zone_id' => $zoneMainChowk->id,
                'lat' => 28.903500,
                'lng' => 76.612500,
                'wallet_paise' => 8500000, // ₹85,000 disbursed
                'outstanding_paise' => 2500000, // ₹25,000 udhar
            ],
        ];

        foreach ($retailerData as $rd) {
            $user = User::create([
                'name' => $rd['owner'],
                'email' => $rd['email'],
                'mobile' => $rd['mobile'],
                'password' => Hash::make($rd['password']),
                'role' => 'RETAILER',
                'status' => 'ACTIVE',
            ]);
            $user->roles()->attach($retailerRole);

            $retailer = Retailer::create([
                'user_id' => $user->id,
                'shop_name' => $rd['shop_name'],
                'owner_name' => $rd['owner'],
                'zone_id' => $rd['zone_id'],
                'address' => $rd['address'],
                'latitude' => $rd['lat'],
                'longitude' => $rd['lng'],
                'outstanding_paise' => $rd['outstanding_paise'],
                'is_kyc_verified' => true,
                'is_blocked' => false,
            ]);

            Wallet::create([
                'retailer_id' => $retailer->id,
                'balance_paise' => $rd['wallet_paise'],
                'locked_balance_paise' => 0,
                'currency' => 'INR',
            ]);

            Device::create([
                'user_id' => $user->id,
                'device_uuid' => (string) Str::uuid(),
                'device_model' => 'Redmi Note 12',
                'os_version' => 'Android 13',
                'app_version' => '1.0.0',
                'is_verified' => true,
                'is_active' => true,
                'last_active_at' => now(),
            ]);
        }
    }
}
