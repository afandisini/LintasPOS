<?php
declare(strict_types=1);
namespace App\Controllers\Api;
final class JasaController extends MasterDataController
{
    protected function definition(): array
    {
        return ['table' => 'jasa', 'label' => 'jasa', 'filters' => ['kategori_id','satuan_id'], 'fields' => ['id_jasa','kategori_id','satuan_id','gambar_img','nama','harga','tgl_input','tgl_update'], 'writable' => ['id_jasa','kategori_id','satuan_id','gambar_img','nama','harga','tgl_input','tgl_update'], 'schema' => [
            'id_jasa'=>['type'=>'string','required'=>true,'max'=>255], 'kategori_id'=>['type'=>'int','required'=>true], 'satuan_id'=>['type'=>'int','required'=>false], 'gambar_img'=>['type'=>'int','required'=>false], 'nama'=>['type'=>'string','required'=>true,'max'=>255], 'harga'=>['type'=>'int','required'=>true], 'tgl_input'=>['type'=>'date','required'=>false], 'tgl_update'=>['type'=>'date','required'=>false],
        ]];
    }
}
