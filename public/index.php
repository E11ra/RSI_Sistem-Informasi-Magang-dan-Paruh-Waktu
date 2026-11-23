<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$route=query('r','dashboard');
$method=$_SERVER['REQUEST_METHOD'];
$routes=[ 'login'=>['GET',[],fn()=>(new AuthController())->tampilkanLogin()], 'login/proses'=>['POST',[],fn()=>(new AuthController())->login()], 'logout'=>['POST',['Mahasiswa','Rekruter','Admin'],fn()=>(new AuthController())->logout()], 'dashboard'=>['GET',['Mahasiswa','Rekruter','Admin'],fn()=>(new DashboardController())->index()], 'lowongan'=>['GET',['Mahasiswa','Rekruter'],fn()=>DaftarLowonganPage::bukaHalamanLowongan()], 'lowongan/detail'=>['GET',['Mahasiswa','Rekruter','Admin'],fn()=>DetailLowonganPage::bukaDetailLowongan(number('id'))], 'lowongan/form'=>['GET',['Rekruter'],fn()=>DaftarLowonganPage::pilihTambahLowongan()], 'lowongan/simpan'=>['POST',['Rekruter'],fn()=>FormPembuatanLowonganPage::publikasikan()], 'lowongan/status'=>['POST',['Rekruter'],fn()=>(new LowonganController())->ubahStatus()], 'lamaran'=>['GET',['Mahasiswa'],fn()=>RiwayatLamaranPage::bukaRiwayatLamaran()], 'lamaran/form'=>['GET',['Mahasiswa'],fn()=>DetailLowonganPage::pilihDaftar()], 'lamaran/kirim'=>['POST',['Mahasiswa'],fn()=>FormPendaftaranLowonganPage::kirimLamaran()], 'lamaran/detail'=>['GET',['Mahasiswa','Admin'],fn()=>RiwayatLamaranPage::pilihDetailLamaran(number('id'))], 'pendaftar'=>['GET',['Rekruter'],fn()=>(new SeleksiController())->getDaftarPendaftar()], 'seleksi/detail'=>['GET',['Rekruter'],fn()=>(new SeleksiController())->getProfilPendaftar(number('id'))], 'seleksi/status'=>['POST',['Rekruter'],fn()=>(new SeleksiController())->perbaruiStatusSeleksi()], 'seleksi/jadwal'=>['POST',['Rekruter'],fn()=>(new SeleksiController())->simpanJadwal()], 'profil'=>['GET',['Mahasiswa','Rekruter','Admin'],fn()=>(new ProfilController())->tampilkanProfil()], 'profil/simpan'=>['POST',['Mahasiswa','Rekruter'],fn()=>(new ProfilController())->simpanProfil()], 'profil/legalitas'=>['POST',['Rekruter'],fn()=>(new ProfilController())->unggahLegalitas()], 'chat'=>['GET',['Mahasiswa','Rekruter'],fn()=>(new ChatController())->tampilkanChat()], 'chat/kirim'=>['POST',['Mahasiswa','Rekruter'],fn()=>(new ChatController())->kirimPesan()], 'notifikasi'=>['GET',['Mahasiswa','Rekruter','Admin'],fn()=>(new NotifikasiController())->tampilkanNotifikasi()], 'notifikasi/baca'=>['POST',['Mahasiswa','Rekruter','Admin'],fn()=>(new NotifikasiController())->tandaiDibaca()], 'kalender'=>['GET',['Mahasiswa','Rekruter'],fn()=>(new KalenderController())->tampilkanKalender()], 'kalender/simpan'=>['POST',['Mahasiswa','Rekruter'],fn()=>(new KalenderController())->simpanPengingat()], 'kalender/hapus'=>['POST',['Mahasiswa','Rekruter'],fn()=>(new KalenderController())->hapusPengingat()], 'perusahaan'=>['GET',['Admin'],fn()=>(new PerusahaanController())->getDaftarPerusahaan()], 'perusahaan/detail'=>['GET',['Admin'],fn()=>(new PerusahaanController())->getDetailPerusahaan(number('id'))], 'perusahaan/dokumen'=>['POST',['Admin'],fn()=>(new PerusahaanController())->periksaDokumen()], 'perusahaan/verifikasi'=>['POST',['Admin'],fn()=>(new PerusahaanController())->verifikasiPerusahaan()], 'perusahaan/keputusan'=>['POST',['Admin'],fn()=>(new PerusahaanController())->putuskanPerusahaan()], 'perusahaan/hapus'=>['POST',['Admin'],fn()=>(new PerusahaanController())->hapusPerusahaan()], 'dokumen/lihat'=>['GET',['Mahasiswa','Rekruter','Admin'],fn()=>(new DokumenController())->lihatDokumen(number('id'))], 'rekapitulasi'=>['GET',['Rekruter','Admin'],fn()=>(new RekapitulasiController())->tampilkanRekapitulasi()], 'rekapitulasi/ekspor'=>['GET',['Rekruter','Admin'],fn()=>(new RekapitulasiController())->eksporCSV()], ];
if(!isset($routes[$route]))fail(404,'Halaman tidak ditemukan.');
[$allowedMethod,$roles,$handler]=$routes[$route];
if($method!==$allowedMethod) {
    header('Allow: '.$allowedMethod);
    fail(405,'Metode permintaan tidak diizinkan.');
}
if($roles)Auth::require($roles);
if($method==='POST') {
    if(!isset($_POST['csrf'])||!is_string($_POST['csrf'])||!hash_equals($_SESSION['csrf'],$_POST['csrf']))fail(419,'Sesi formulir tidak valid/kedaluwarsa atau unggahan melebihi batas. Muat ulang formulir dan coba kembali.');
}
if($roles)Kalender::kirimPengingatJatuhTempo();
try {
    $handler();
}
catch(DomainException $e) {
    fail(422,$e->getMessage());
}
