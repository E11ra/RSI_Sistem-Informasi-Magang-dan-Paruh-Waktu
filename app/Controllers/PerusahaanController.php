<?php
declare(strict_types=1);
final class PerusahaanController  {
    public function getDaftarPerusahaan():void {
        Auth::require(['Admin']);
        $rows=Perusahaan::getDaftarPerusahaan(query('status'));
        $q=query('keyword');
        if($q!=='')$rows=array_values(array_filter($rows,fn($p)=>mb_stripos($p['namaPerusahaan'].' '.$p['alamat'],$q)!==false));
        View::render('perusahaan/index',['title'=>'Perusahaan','perusahaan'=>$rows]);
    }
    private function perusahaan(int $id):Perusahaan {
        Auth::require(['Admin']);
        $p=Perusahaan::getPerusahaan($id);
        if(!$p||$p['dihapusPada'])fail(404,'Perusahaan tidak ditemukan.');
        return $p;
    }
    public function getDetailPerusahaan(int $id):void {
        $p=$this->perusahaan($id);
        View::render('perusahaan/detail',['title'=>'Verifikasi Perusahaan','perusahaan'=>$p,'dokumen'=>Perusahaan::dokumen($id),'riwayat'=>Database::all('SELECT v.*,a.namaPengguna FROM RIWAYAT_VERIFIKASI v JOIN AKUN a ON a.idAkun=v.idAdmin WHERE v.idPerusahaan=? ORDER BY v.idVerifikasi DESC',[$id]),'lowongan'=>Lowongan::getDaftarLowongan($id)]);
    }
    public function periksaDokumen():void {
        $id=number('idPerusahaan');
        $this->perusahaan($id);
        $doc=number('idDokumenPerusahaan');
        $result=post('hasil');
        if(!in_array($result,['Sesuai','Tidak sesuai','Bermasalah'],true)||mb_strlen(post('catatan'))>2000)throw new DomainException('Hasil pemeriksaan atau catatan tidak valid.');
        if(!Database::value('SELECT 1 FROM DOKUMEN_PERUSAHAAN WHERE id=? AND idPerusahaan=?',[$doc,$id]))fail(404,'Dokumen perusahaan tidak ditemukan.');
        Database::query('UPDATE DOKUMEN_PERUSAHAAN SET hasilPemeriksaan=?,catatan=?,idPemeriksa=? WHERE id=? AND idPerusahaan=?',[$result,post('catatan'),Auth::user()['idAkun'],$doc,$id]);
        flash('Hasil pemeriksaan dokumen disimpan.');
        go('perusahaan/detail',['id'=>$id]);
    }
    public function verifikasiPerusahaan():void {
        $id=number('idPerusahaan');
        $this->perusahaan($id);
        $result=post('hasil');
        $note=post('catatan');
        if(!in_array($result,['Layak','Tidak Layak','Perlu Perbaikan'],true)||!$note||mb_strlen($note)>3000)throw new DomainException('Pilih hasil verifikasi dan isi catatan maksimal 3.000 karakter.');
        if($result==='Layak') {
            $docs=Perusahaan::dokumen($id);
            if(!$docs||array_filter($docs,fn($d)=>$d['hasilPemeriksaan']!=='Sesuai'))throw new DomainException('Periksa seluruh dokumen dan tandai Sesuai sebelum menyatakan perusahaan Layak.');
        }
        Database::query('INSERT INTO RIWAYAT_VERIFIKASI(idPerusahaan,idAdmin,hasil,catatan) VALUES (?,?,?,?)',[$id,Auth::user()['idAkun'],$result,$note]);
        flash('Kesimpulan verifikasi tersimpan. Keputusan persetujuan dapat dilakukan sekarang.');
        go('perusahaan/detail',['id'=>$id]);
    }
    public function putuskanPerusahaan():void {
        $id=number('idPerusahaan');
        $this->perusahaan($id);
        $choice=post('keputusan');
        $note=post('alasan');
        if(!in_array($choice,['setujui','tolak'],true)||mb_strlen($note)>3000)throw new DomainException('Keputusan tidak valid.');
        if($choice==='tolak'&&!$note)throw new DomainException('Alasan penolakan wajib diisi.');
        Database::transaction(function()use($id,$choice,$note) {
            $p=Database::one('SELECT * FROM PERUSAHAAN WHERE idPerusahaan=? FOR UPDATE',[$id]);
            if(!$p||$p['dihapusPada'])throw new DomainException('Perusahaan tidak tersedia.');
            if($choice==='setujui') {
                $last=Database::one('SELECT hasil,dibuatPada FROM RIWAYAT_VERIFIKASI WHERE idPerusahaan=? ORDER BY idVerifikasi DESC LIMIT 1',[$id]);
                $docs=Perusahaan::dokumen($id);
                if(!$last||$last['hasil']!=='Layak'||!$docs||array_filter($docs,fn($d)=>$d['hasilPemeriksaan']!=='Sesuai'))throw new DomainException('Dokumen belum dinyatakan sesuai atau kesimpulan terakhir belum Layak.');
            }
            $status=$choice==='setujui'?'Terverifikasi':'Ditolak';
            $message=$note?:'Perusahaan memenuhi persyaratan verifikasi.';
            Database::query('UPDATE PERUSAHAAN SET statusVerifikasi=?,catatanVerifikasi=? WHERE idPerusahaan=?',[$status,$message,$id]);
            Database::query('INSERT INTO RIWAYAT_VERIFIKASI(idPerusahaan,idAdmin,hasil,catatan) VALUES (?,?,?,?)',[$id,Auth::user()['idAkun'],$status,$message]);
            Notifikasi::perusahaan($id,'Hasil verifikasi: '.$status,$message,url('profil'));
        }
        );
        flash('Keputusan perusahaan tersimpan dan rekruter menerima notifikasi.');
        go('perusahaan/detail',['id'=>$id]);
    }
    public function hapusPerusahaan():void {
        $id=number('idPerusahaan');
        $p=$this->perusahaan($id);
        $note=post('alasan');
        if(!isset($_POST['konfirmasi'])) {
            View::render('confirm',['title'=>'Hapus perusahaan ini?','message'=>$p['namaPerusahaan'].' dan seluruh lowongannya akan disembunyikan. Akun rekruter dinonaktifkan. Riwayat lamaran mahasiswa dipertahankan.','route'=>'perusahaan/hapus','fields'=>['idPerusahaan'=>$id,'alasan'=>$note],'back'=>url('perusahaan/detail',['id'=>$id])]);
            return;
        }
        if(!$note||mb_strlen($note)>3000)throw new DomainException('Tuliskan alasan/bukti pelanggaran sebelum menghapus perusahaan.');
        Database::transaction(function()use($id,$note) {
            Database::query("UPDATE PERUSAHAAN SET dihapusPada=NOW(),statusVerifikasi='Dihapus',catatanVerifikasi=? WHERE idPerusahaan=?",[$note,$id]);
            Database::query("UPDATE LOWONGAN SET dihapusPada=NOW(),statusLowongan='Ditutup' WHERE idPerusahaan=?",[$id]);
            Database::query('UPDATE AKUN SET aktif=0 WHERE idPerusahaan=?',[$id]);
            Database::query('INSERT INTO RIWAYAT_VERIFIKASI(idPerusahaan,idAdmin,hasil,catatan) VALUES (?,?,?,?)',[$id,Auth::user()['idAkun'],'Dihapus',$note]);
            $students=Database::all("SELECT DISTINCT a.idMahasiswa FROM LAMARAN a JOIN LOWONGAN l ON l.idLowongan=a.idLowongan WHERE l.idPerusahaan=? AND a.statusLamaran<>'Draf'",[$id]);
            foreach($students as $m)Notifikasi::kirimNotifikasi(Akun::mahasiswa((int)$m['idMahasiswa']),'Perusahaan tidak lagi tersedia','Lowongan perusahaan terkait dinonaktifkan oleh admin. Riwayat lamaran tetap dapat diakses.',url('lamaran'));
        }
        );
        flash('Perusahaan dan lowongannya dihapus dari daftar aktif.');
        go('perusahaan');
    }
}
