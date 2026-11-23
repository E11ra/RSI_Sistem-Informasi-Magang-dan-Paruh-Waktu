<?php
declare(strict_types=1);
final class LamaranController  {
    private int $idMahasiswa;
    public function __construct() {
        $this->idMahasiswa=(int)(Auth::user()['idMahasiswa']??0);
    }
    public function cekStatusMelamar(int $idMahasiswa,int $idLowongan):void {
        $this->pemilik($idMahasiswa);
        $l=$this->tersedia($idLowongan);
        $pesan=Lamaran::cekSudahMelamar($idMahasiswa,$idLowongan)?'Anda sudah mendaftar pada lowongan ini':'';
        DetailLowonganPage::tampilkanDetail($l,$pesan);
    }
    public function bukaFormLamaran(int $idLowongan):void {
        $this->pemilik($this->idMahasiswa);
        $l=$this->tersedia($idLowongan);
        if(Lamaran::cekSudahMelamar($this->idMahasiswa,$idLowongan)) {
            DetailLowonganPage::tampilkanDetail($l,'Anda sudah mendaftar pada lowongan ini');
            return;
        }
        FormPendaftaranLowonganPage::tampilkanFormPendaftaran($l);
    }
    public function prosesForm():void {
        $this->pemilik($this->idMahasiswa);
        $id=number('idLowongan');
        $l=$this->tersedia($id);
        $action=post('aksiForm','kirim');
        if(Lamaran::cekSudahMelamar($this->idMahasiswa,$id)) {
            flash('Anda sudah mendaftar pada lowongan ini.','info');
            go('lowongan/detail',['id'=>$id]);
        }
        $draft=Lamaran::getDraft($this->idMahasiswa,$id);
        $docs=$draft? $draft['dokumenPersyaratan']:[];
        $errors=[];
        $u=Auth::user();
        foreach(array_keys(documentOptions()) as $key) {
            if(isset($_FILES['dokumen']['name'][$key])) {
                $f=[];
                foreach(['name','type','tmp_name','error','size'] as $k)$f[$k]=$_FILES['dokumen'][$k][$key];
                try {
                    $new=Upload::simpan($f,(int)$u['idAkun']);
                    if($new)$docs[$key]=$new;
                }
                catch(DomainException $e) {
                    $errors[]=$key.': '.$e->getMessage();
                }
            }
        }
        if($action==='gunakanCV') {
            $m=Mahasiswa::getMahasiswa($this->idMahasiswa);
            if($m['idCV'])$docs['CV']=(int)$m['idCV'];
            else $errors[]='Belum ada CV pada profil.';
        }
        if(str_starts_with($action,'hapus:'))unset($docs[substr($action,6)]);
        $portfolio=post('portofolio');
        $letter=post('suratPengantar');
        if(!safeUrl($portfolio)||mb_strlen($portfolio)>250)$errors[]='Tautan portofolio harus URL http/https maksimal 250 karakter.';
        if(str_word_count($letter)>500||mb_strlen($letter)>6000)$errors[]='Surat pengantar maksimal 500 kata / 6.000 karakter.';
        // Berkas yang valid disimpan pada draf. Gagal kirim tidak menghilangkan lampiran.
        $data=['idLamaran'=>$draft? $draft['idLamaran']:0,'idMahasiswa'=>$this->idMahasiswa,'idLowongan'=>$id,'dokumenPersyaratan'=>$docs,'statusLamaran'=>'Draf','tanggalMelamar'=>null,'idTahapSaatIni'=>null,'portofolio'=>safeUrl($portfolio)?mb_substr($portfolio,0,250):'','suratPengantar'=>mb_substr($letter,0,6000)];
        Database::transaction(function()use($l,$data) {
            Database::query('SELECT idLowongan FROM LOWONGAN WHERE idLowongan=? FOR UPDATE',[$l['idLowongan']]);
            if(Lamaran::cekSudahMelamar($this->idMahasiswa,(int)$l['idLowongan']))throw new DomainException('Lamaran telah terkirim.');
            Lamaran::simpanLamaran(new Lamaran($data));
        }
        );
        if($errors) {
            http_response_code(422);
            FormPendaftaranLowonganPage::tampilkanPesan('Dokumen belum dapat diproses',$errors);
            return;
        }
        if($action==='kirim') {
            $this->mendaftarLowongan($this->idMahasiswa,$id,$docs);
            return;
        }
        flash('Draf dan dokumen berhasil disimpan.');
        go('lamaran/form',['id'=>$id]);
    }
    public function mendaftarLowongan(int $idMahasiswa,int $idLowongan,array $dokumen):void {
        $this->pemilik($idMahasiswa);
        if(Lamaran::cekSudahMelamar($idMahasiswa,$idLowongan)) {
            FormPendaftaranLowonganPage::tampilkanPesan('Anda telah Melamar Lowongan ini',[]);
            return;
        }
        $errors=$this->validasiLamaran($idMahasiswa,$idLowongan,$dokumen);
        if($errors) {
            http_response_code(422);
            FormPendaftaranLowonganPage::tampilkanPesan('Berkas Wajib Belum Lengkap',$errors);
            return;
        }
        $id=Database::transaction(function()use($idMahasiswa,$idLowongan,$dokumen) {
            Database::query('SELECT idLowongan FROM LOWONGAN WHERE idLowongan=? FOR UPDATE',[$idLowongan]);
            $l=$this->tersedia($idLowongan);
            if(Lamaran::cekSudahMelamar($idMahasiswa,$idLowongan))throw new DomainException('Anda sudah mendaftar pada lowongan ini.');
            $draft=Lamaran::getDraft($idMahasiswa,$idLowongan);
            $t=TahapSeleksi::getTahapByLowongan($idLowongan);
            if(!$t)throw new DomainException('Roadmap seleksi belum tersedia.');
            $data=$draft?$draft->toArray():[];
            $data=array_replace($data,['idMahasiswa'=>$idMahasiswa,'idLowongan'=>$idLowongan,'dokumenPersyaratan'=>$dokumen,'statusLamaran'=>'Lamaran Terkirim','tanggalMelamar'=>date('Y-m-d H:i:s'),'idTahapSaatIni'=>$t[0]['idTahap']]);
            $a=new Lamaran($data);
            Lamaran::simpanLamaran($a);
            $id=(int)$a['idLamaran'];
            Notifikasi::perusahaan((int)$l['idPerusahaan'],'Lamaran baru',Mahasiswa::getMahasiswa($idMahasiswa)['namaMahasiswa'].' melamar '.$l['namaLowongan'],url('seleksi/detail',['id'=>$id]));
            Notifikasi::kirimNotifikasi((int)Auth::user()['idAkun'],'Lamaran terkirim','Lamaran untuk '.$l['namaLowongan'].' berhasil diterima.',url('lamaran/detail',['id'=>$id]));
            return $id;
        }
        );
        flash('Lamaran Terkirim. Pantau perkembangannya di halaman ini.');
        go('lamaran/detail',['id'=>$id]);
    }
    private function validasiLamaran(int $idMahasiswa,int $idLowongan,array $dokumen):array {
        $l=$this->tersedia($idLowongan);
        $m=Mahasiswa::getMahasiswa($idMahasiswa);
        $draft=Lamaran::getDraft($idMahasiswa,$idLowongan);
        $errors=[];
        foreach($l['dokumenPersyaratan'] as $key) {
            if($key==='Portofolio'&&!empty($draft['portofolio']))continue;
            if(empty($dokumen[$key])||!Upload::milik((int)$dokumen[$key],(int)Auth::user()['idAkun']))$errors[]=documentOptions()[$key]??$key;
        }
        if(!post('persetujuan'))$errors[]='Centang pernyataan kebenaran data dan dokumen.';
        if((int)$m['semester']<(int)$l['tingkatSemesterMinimal'])$errors[]='Semester aktif belum memenuhi syarat minimal lowongan.';
        if(!in_array($m['jurusan'],$l['targetJurusan'],true))$errors[]='Program studi belum sesuai target jurusan lowongan.';
        if(!$m['telepon'])$errors[]='Lengkapi nomor telepon pada Profil.';
        return $errors;
    }
    public function getRiwayatLamaran(int $idMahasiswa):void {
        $this->pemilik($idMahasiswa);
        $rows=Lamaran::getLamaranByMahasiswa($idMahasiswa);
        $status=query('status');
        if($status!=='')$rows=array_values(array_filter($rows,fn($r)=>$r['statusLamaran']===$status));
        RiwayatLamaranPage::tampilkanDaftarLamaran($rows,$rows?'':'Riwayat lamaran masih kosong');
    }
    public function getDetailLamaran(int $id):void {
        $a=Lamaran::izin($id);
        View::render('lamaran/detail',['title'=>'Detail Lamaran','lamaran'=>$a,'tahapan'=>TahapSeleksi::getTahapByLowongan((int)$a['idLowongan']),'hasil'=>Lamaran::hasil($id),'jadwal'=>Kalender::jadwalLamaran($id),'dokumenInfo'=>Dokumen::informasi($a['dokumenPersyaratan'])]);
    }
    private function pemilik(int $id):void {
        if(Auth::mahasiswaId()!==$id)fail(403,'Identitas mahasiswa tidak sesuai sesi.');
    }
    private function tersedia(int $id):Lowongan {
        $l=Lowongan::getLowonganById($id);
        if(!$l||!$l->tersedia())fail(404,'Lowongan tidak tersedia, telah ditutup, atau melewati batas lamaran.');
        return $l;
    }
}
