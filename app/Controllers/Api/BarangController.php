<?php
declare(strict_types=1);
namespace App\Controllers\Api;
final class BarangController extends MasterDataController
{
    protected function definition(): array
    {
        return ['table' => 'barang', 'label' => 'barang', 'filters' => ['kategori_id','satuan_id','merk','stok'], 'fields' => ['id_barang','kategori_id','satuan_id','gambar','nama_barang','merk','harga_beli','harga_jual','stok','exp_date','tgl_input','tgl_update'], 'writable' => ['id_barang','kategori_id','satuan_id','gambar','nama_barang','merk','harga_beli','harga_jual','exp_date','tgl_input','tgl_update'], 'schema' => [
            'id_barang'=>['type'=>'string','required'=>true,'max'=>255], 'kategori_id'=>['type'=>'int','required'=>true], 'satuan_id'=>['type'=>'int','required'=>false], 'gambar'=>['type'=>'int','required'=>false], 'nama_barang'=>['type'=>'string','required'=>true,'max'=>255], 'merk'=>['type'=>'string','required'=>true,'max'=>255], 'harga_beli'=>['type'=>'int','required'=>true], 'harga_jual'=>['type'=>'int','required'=>true], 'exp_date'=>['type'=>'date','required'=>false], 'tgl_input'=>['type'=>'date','required'=>false], 'tgl_update'=>['type'=>'date','required'=>false],
        ]];
    }
}
