<?php
declare(strict_types=1);
final class ProfilController  {
    public function tampilkanProfil():void {
        $u=Auth::require();
        $profil=match($u['role']) {
            'Mahasiswa'=>Mahasiswa::getMahasiswa((int)$u['idMahasiswa']),'Rekruter'=>Perusahaan::getPerusahaan((int)$u['idPerusahaan']),default=>$u
        }
        ;
        View::render('profil',['title'=>'Profil','profil'=>$profil,'errors'=>[],'legalitas'=>$u['role']==='Rekruter'?Perusahaan::dokumen((int)$u['idPerusahaan']):[]]);
    }
    public function simpanProfil():void {
        $u=Auth::require(['Mahasiswa','Rekruter']);
        $d=$_POST;
        $errors=[];
        if($u['role']==='Mahasiswa') {
            $old=Mahasiswa::getMahasiswa((int)$u['idMahasiswa']);
            $d['idCV']=$old['idCV'];
            foreach(['namaMahasiswa','email','jurusan','telepon','portofolio','bio','semester','IPK'] as $k)$d[$k]=post($k);
            if(!$d['namaMahasiswa']||mb_strlen($d['namaMahasiswa'])>100)$errors[]='Nama wajib diisi, maksimal 100 karakter.';
            if(!filter_var($d['email'],FILTER_VALIDATE_EMAIL)||mb_strlen($d['email'])>100)$errors[]='Email tidak valid.';
            if(!in_array($d['jurusan'],jurusanOptions(),true))$errors[]='Program studi tidak valid.';
            if(filter_var($d['semester'],FILTER_VALIDATE_INT)===false||(int)$d['semester']<1||(int)$d['semester']>14)$errors[]='Semester harus 1–14.';
            if(!is_numeric($d['IPK'])||(float)$d['IPK']<0||(float)$d['IPK']>4)$errors[]='IPK harus 0–4.';
            if(!safeUrl($d['portofolio'])||mb_strlen($d['portofolio'])>250)$errors[]='Tautan portofolio tidak valid.';
            if(mb_strlen($d['telepon'])>30||mb_strlen($d['bio'])>2000)$errors[]='Telepon maksimal 30 karakter dan bio maksimal 2.000 karakter.';
            if(Database::value('SELECT 1 FROM AKUN WHERE email=? AND idAkun<>?',[$d['email'],$u['idAkun']])||Database::value('SELECT 1 FROM MAHASISWA WHERE email=? AND idMahasiswa<>?',[$d['email'],$u['idMahasiswa']]))$errors[]='Email sudah digunakan akun lain.';
            if(!$errors) {
                try {
                    $new=Upload::simpan($_FILES['cv']??[],(int)$u['idAkun']);
                    if($new)$d['idCV']=$new;
                }
                catch(DomainException $e) {
                    $errors[]=$e->getMessage();
                }
            }
            if(!$errors) {
                Database::transaction(fn()=>Mahasiswa::simpanProfil((int)$u['idMahasiswa'],$d));
                flash('Profil mahasiswa diperbarui.');
                go('profil');
            }
            $profil=new Mahasiswa(array_replace($old->toArray(),$d));
        }
        else {
            $old=Perusahaan::getPerusahaan((int)$u['idPerusahaan']);
            foreach(['namaPerusahaan','emailPerusahaan','alamat','bidangUsaha','kontak','deskripsi','website'] as $k)$d[$k]=post($k);
            foreach(['namaPerusahaan'=>100,'alamat'=>2000,'bidangUsaha'=>100,'kontak'=>30,'deskripsi'=>5000,'website'=>250] as $k=>$max)if(mb_strlen($d[$k])>$max||in_array($k,['namaPerusahaan','alamat'],true)&&!$d[$k])$errors[]="$k wajib/terlalu panjang.";
            if(!filter_var($d['emailPerusahaan'],FILTER_VALIDATE_EMAIL)||mb_strlen($d['emailPerusahaan'])>100||!safeUrl($d['website']))$errors[]='Email atau website perusahaan tidak valid.';
            if(Database::value('SELECT 1 FROM PERUSAHAAN WHERE emailPerusahaan=? AND idPerusahaan<>?',[$d['emailPerusahaan'],$u['idPerusahaan']]))$errors[]='Email perusahaan sudah digunakan.';
            if(!$errors) {
                Perusahaan::simpanProfil((int)$u['idPerusahaan'],$d);
                flash('Profil perusahaan diperbarui.');
                go('profil');
            }
            $profil=new Perusahaan(array_replace($old->toArray(),$d));
        }
        http_response_code(422);
        View::render('profil',['title'=>'Profil','profil'=>$profil,'errors'=>$errors,'legalitas'=>$u['role']==='Rekruter'?Perusahaan::dokumen((int)$u['idPerusahaan']):[]]);
    }
    public function unggahLegalitas():void {
        $u=Auth::require(['Rekruter']);
        $type=post('namaDokumen');
        if(!in_array($type,['NIB','Akta Pendirian','Izin Usaha'],true))throw new DomainException('Pilih jenis dokumen legalitas.');
        $file=Upload::simpan($_FILES['legalitas']??[],(int)$u['idAkun']);
        if(!$file)throw new DomainException('Pilih berkas legalitas PDF.');
        Database::transaction(function()use($u,$type,$file) {
            Database::query('SELECT idPerusahaan FROM PERUSAHAAN WHERE idPerusahaan=? FOR UPDATE',[$u['idPerusahaan']]);
            Database::query('DELETE FROM DOKUMEN_PERUSAHAAN WHERE idPerusahaan=? AND namaDokumen=?',[$u['idPerusahaan'],$type]);
            Database::query('INSERT INTO DOKUMEN_PERUSAHAAN(idPerusahaan,idDokumen,namaDokumen) VALUES (?,?,?)',[$u['idPerusahaan'],$file,$type]);
            Database::query("UPDATE PERUSAHAAN SET statusVerifikasi='Menunggu',catatanVerifikasi='Dokumen baru menunggu pemeriksaan ulang.' WHERE idPerusahaan=?",[$u['idPerusahaan']]);
            foreach(Database::all("SELECT idAkun FROM AKUN WHERE role='Admin' AND aktif=1") as $admin)Notifikasi::kirimNotifikasi((int)$admin['idAkun'],'Dokumen perusahaan baru',$u['namaPerusahaan'].' mengunggah '.$type,url('perusahaan/detail',['id'=>$u['idPerusahaan']]));
        }
        );
        flash('Dokumen tersimpan. Perusahaan menunggu pemeriksaan admin.');
        go('profil');
    }
}
