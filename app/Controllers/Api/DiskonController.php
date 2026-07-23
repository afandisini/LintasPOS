<?php
declare(strict_types=1);
namespace App\Controllers\Api;
final class DiskonController extends MasterDataController
{
    protected function definition(): array
    {
        return ['table' => 'diskon', 'label' => 'diskon', 'filters' => ['barang_id','tgl_start','tgl_end'], 'fields' => ['barang_id','diskon','tgl_start','tgl_end'], 'writable' => ['barang_id','diskon','tgl_start','tgl_end'], 'schema' => [
            'barang_id'=>['type'=>'string','required'=>false,'max'=>255], 'diskon'=>['type'=>'int','required'=>true], 'tgl_start'=>['type'=>'date','required'=>false], 'tgl_end'=>['type'=>'date','required'=>false],
        ]];
    }
}
