<?php
declare(strict_types=1);
namespace App\Controllers\Api;
final class SupplierController extends MasterDataController
{
    protected function definition(): array
    {
        return ['table' => 'supplier', 'label' => 'supplier', 'fields' => ['nama_supplier','alamat_supplier','telepon_supplier','email_supplier'], 'writable' => ['nama_supplier','alamat_supplier','telepon_supplier','email_supplier'], 'schema' => [
            'nama_supplier'=>['type'=>'string','required'=>true,'max'=>255], 'alamat_supplier'=>['type'=>'string','required'=>false,'max'=>255], 'telepon_supplier'=>['type'=>'string','required'=>false,'max'=>25], 'email_supplier'=>['type'=>'email','required'=>false,'max'=>255],
        ]];
    }
}
