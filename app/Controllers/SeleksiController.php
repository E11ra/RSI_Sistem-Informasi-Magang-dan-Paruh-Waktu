<?php
declare(strict_types=1);
final class SeleksiController  {
    public function getDaftarPendaftar():void {
        $company=Auth::perusahaanId();
        $id=number('lowongan');
        if($id) {
            $l=Lowongan::getLowonganById($id);
            if(!$l||(int)$l['idPerusahaan']!==$company)fail(403,'Lowongan bukan milik perusahaan Anda.');
        }
        $rows=Lamaran::daftarPendaftar($company,$id);
        $status=query('status');
        $keyword=query('keyword');
        if($status)$rows=array_values(array_filter($rows,fn($a)=>$a['statusLamaran']===$status));
        if($keyword)$rows=array_values(array_filter($rows,fn($a)=>mb_stripos($a['namaMahasiswa'].' '.$a['NIM'].' '.$a['namaLowongan'],$keyword)!==false));
        View::render('seleksi/index',['title'=>'Pendaftar','pendaftar'=>$rows,'lowongan'=>Lowongan::getDaftarLowongan($company)]);
    }
    public function getProfilPendaftar(int $idLamaran):void {
        Auth::require(['Rekruter']);
        (new LamaranController())->getDetailLamaran($idLamaran);
    }
    public function perbaruiStatusSeleksi():void {
        $company=Auth::verified();
        $id=number('idLamaran');
        $a=Lamaran::izin($id);
        $choice=post('hasil');
        $note=post('catatan');
        if(!in_array($choice,['Diproses','Lolos','Tidak Lolos'],true))throw new DomainException('Pilih hasil seleksi yang valid.');
        if(mb_strlen($note)>2000)throw new DomainException('Catatan maksimal 2.000 karakter.');
        $idTahap=number('idTahap');
        Database::transaction(function()use($a,$id,$choice,$note,$idTahap,$company) {
            $job=Database::one('SELECT * FROM LOWONGAN WHERE idLowongan=? AND idPerusahaan=? FOR UPDATE',[$a['idLowongan'],$company]);
            $row=Database::one('SELECT * FROM LAMARAN WHERE idLamaran=? FOR UPDATE',[$id]);
            if(!$job||$job['dihapusPada'])throw new DomainException('Lowongan tidak lagi tersedia untuk seleksi.');
            if(in_array($row['statusLamaran'],['Draf','Diterima','Ditolak'],true))throw new DomainException('Lamaran ini tidak memiliki tahap aktif yang dapat diperbarui.');
            if((int)$row['idTahapSaatIni']!==$idTahap)throw new DomainException('Tahap sudah berubah. Muat ulang halaman sebelum menilai.');
            $stages=TahapSeleksi::getTahapByLowongan((int)$a['idLowongan']);
            $pos=null;
            foreach($stages as $i=>$t)if((int)$t['idTahap']===$idTahap)$pos=$i;
            if($pos===null)throw new DomainException('Tahap seleksi tidak ditemukan.');
            $next=$stages[$pos+1]??null;
            $status='Diproses';
            $active=$idTahap;
            $adminLolos=(int)$row['administrasiLolos'];
            if($choice==='Tidak Lolos')$status='Ditolak';
            elseif($choice==='Lolos') {
                if($pos===0)$adminLolos=1;
                if($next) {
                    $active=(int)$next['idTahap'];
                    $status=mb_stripos($next['namaTahap'],'wawancara')!==false?'Wawancara':'Diproses';
                }
                else {
                    $accepted=(int)Database::value("SELECT COUNT(*) FROM LAMARAN WHERE idLowongan=? AND statusLamaran='Diterima'",[$a['idLowongan']]);
                    if($accepted>=(int)$job['kuota'])throw new DomainException('Kuota penerimaan lowongan telah terpenuhi.');
                    $status='Diterima';
                }
            }
            elseif(mb_stripos($stages[$pos]['namaTahap'],'wawancara')!==false)$status='Wawancara';
            Database::query('INSERT INTO HASIL_SELEKSI(idLamaran,idTahap,idLowongan,hasil,catatan,idPenilai) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE hasil=VALUES(hasil),catatan=VALUES(catatan),idPenilai=VALUES(idPenilai),diperbaruiPada=NOW()',[$id,$idTahap,$a['idLowongan'],$choice,$note,Auth::user()['idAkun']]);
            Database::query('UPDATE LAMARAN SET statusLamaran=?,idTahapSaatIni=?,administrasiLolos=? WHERE idLamaran=?',[$status,$active,$adminLolos,$id]);
            Notifikasi::kirimNotifikasi(Akun::mahasiswa((int)$a['idMahasiswa']),'Pembaruan seleksi',$a['namaLowongan'].': '.$stages[$pos]['namaTahap'].' — '.$choice.($note?' · '.$note:''),url('lamaran/detail',['id'=>$id]));
        }
        );
        flash('Status seleksi dan notifikasi mahasiswa berhasil diperbarui.');
        go('seleksi/detail',['id'=>$id]);
    }
    public function simpanJadwal():void {
        Auth::verified();
        $id=number('idLamaran');
        $a=Lamaran::izin($id);
        $tahap=number('idTahap');
        $time=post('waktu');
        $link=post('tautan');
        if(in_array($a['statusLamaran'],['Draf','Ditolak','Diterima'],true)||$a['lowonganDihapus'])throw new DomainException('Jadwal hanya dapat dibuat untuk lamaran yang masih diproses.');
        if(!Database::value('SELECT 1 FROM `TAHAP SELEKSI` WHERE idTahap=? AND idLowongan=?',[$tahap,$a['idLowongan']]))throw new DomainException('Tahap jadwal tidak sesuai lowongan.');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$time)||strtotime($time)===false||strtotime($time)<=time())throw new DomainException('Pilih waktu seleksi setelah waktu sekarang.');
        if(!post('judul')||mb_strlen(post('judul'))>150||!safeUrl($link)||mb_strlen($link)>250||mb_strlen(post('lokasi'))>150||mb_strlen(post('catatan'))>2000)throw new DomainException('Periksa judul, lokasi, catatan, dan tautan http/https jadwal.');
        Database::transaction(function()use($id,$a,$tahap,$time,$link) {
            Database::query('INSERT INTO JADWAL_SELEKSI(idLamaran,idTahap,idLowongan,judul,waktu,tautan,lokasi,catatan) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE judul=VALUES(judul),waktu=VALUES(waktu),tautan=VALUES(tautan),lokasi=VALUES(lokasi),catatan=VALUES(catatan)',[$id,$tahap,$a['idLowongan'],post('judul'),str_replace('T',' ',$time).':00',$link,post('lokasi'),post('catatan')]);
            Notifikasi::kirimNotifikasi(Akun::mahasiswa((int)$a['idMahasiswa']),'Jadwal seleksi baru',post('judul').' · '.tanggal($time,'d M Y H:i').' WIB',url('kalender'));
        }
        );
        flash('Jadwal tersimpan di kalender mahasiswa.');
        go('seleksi/detail',['id'=>$id]);
    }
}
