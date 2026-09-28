<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        foreach ([['Ali','ali@example.test',1000000],['Sara','sara@example.test',500000],['Ayesha','ayesha@example.test',200000]] as [$name,$email,$balance]) {
            $id = DB::table('users')->insertGetId(['name'=>$name,'email'=>$email,'password'=>Hash::make('DemoPass!2026'), 'created_at'=>now(),'updated_at'=>now()]);
            DB::table('wallets')->insert(['user_id'=>$id,'balance_paisa'=>$balance,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
}
