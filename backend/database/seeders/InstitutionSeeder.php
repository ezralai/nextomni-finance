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
                'website' => 'https://www.maybank2u.com.my',
            ],
            [
                'name' => 'CIMB Bank',
                'code' => 'CIMB',
                'country' => 'MYS',
                'website' => 'https://www.cimbclicks.com.my',
            ],
            [
                'name' => "Touch 'n Go eWallet",
                'code' => 'TNG',
                'country' => 'MYS',
                'website' => 'https://www.touchngo.com.my',
            ],
            [
                'name' => 'GrabPay',
                'code' => 'GRAB',
                'country' => 'MYS',
                'website' => 'https://www.grab.com/my',
            ],
            [
                'name' => 'Public Bank',
                'code' => 'PBB',
                'country' => 'MYS',
                'website' => 'https://www.pbebank.com',
            ],
            [
                'name' => 'RHB Bank',
                'code' => 'RHB',
                'country' => 'MYS',
                'website' => 'https://www.rhbgroup.com',
            ],
            [
                'name' => 'Hong Leong Bank',
                'code' => 'HLB',
                'country' => 'MYS',
                'website' => 'https://www.hlb.com.my',
            ],
            [
                'name' => 'Cash / Physical Wallet',
                'code' => 'CASH',
                'country' => 'MYS',
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
