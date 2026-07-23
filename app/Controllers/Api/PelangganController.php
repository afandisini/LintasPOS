<?php
declare(strict_types=1);
namespace App\Controllers\Api;
final class PelangganController extends MasterDataController
{
    protected function definition(): array
    {
        return ['table' => 'pelanggan', 'label' => 'pelanggan', 'filters' => ['kode_pelanggan','email_pelanggan'], 'fields' => ['kode_pelanggan','nama_pelanggan','alamat_pelanggan','telepon_pelanggan','email_pelanggan'], 'writable' => ['kode_pelanggan','nama_pelanggan','alamat_pelanggan','telepon_pelanggan','email_pelanggan'], 'schema' => [
            'kode_pelanggan'=>['type'=>'string','required'=>false,'max'=>255], 'nama_pelanggan'=>['type'=>'string','required'=>true,'max'=>255], 'alamat_pelanggan'=>['type'=>'string','required'=>false,'max'=>65535], 'telepon_pelanggan'=>['type'=>'string','required'=>false,'max'=>25], 'email_pelanggan'=>['type'=>'email','required'=>false,'max'=>255],
        ]];
    }
}
