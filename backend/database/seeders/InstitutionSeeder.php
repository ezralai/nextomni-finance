<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institutions = [
            [
                'name' => 'Maybank',
                'code' => 'MBB',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/mbb.svg',
                'website' => 'https://www.maybank2u.com.my',
            ],
            [
                'name' => 'CIMB Bank',
                'code' => 'CIMB',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/cimb.svg',
                'website' => 'https://www.cimbclicks.com.my',
            ],
            [
                'name' => "Touch 'n Go eWallet",
                'code' => 'TNG',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/tng.svg',
                'website' => 'https://www.touchngo.com.my',
            ],
            [
                'name' => 'GrabPay',
                'code' => 'GRAB',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/grab.svg',
                'website' => 'https://www.grab.com/my',
            ],
            [
                'name' => 'Public Bank',
                'code' => 'PBB',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/pbb.svg',
                'website' => 'https://www.pbebank.com',
            ],
            [
                'name' => 'RHB Bank',
                'code' => 'RHB',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/rhb.svg',
                'website' => 'https://www.rhbgroup.com',
            ],
            [
                'name' => 'Hong Leong Bank',
                'code' => 'HLB',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/hlb.svg',
                'website' => 'https://www.hlb.com.my',
            ],
            [
                'name' => 'Wise (formerly TransferWise)',
                'code' => 'WISE',
                'country' => 'GBR',
                'logo_url' => '/images/institutions/wise.svg',
                'website' => 'https://www.wise.com',
            ],
            [
                'name' => 'Cash / Physical Wallet',
                'code' => 'CASH',
                'country' => 'MYS',
                'logo_url' => '/images/institutions/cash.svg',
                'website' => null,
            ],
        ];

        foreach ($institutions as $item) {
            Institution::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
