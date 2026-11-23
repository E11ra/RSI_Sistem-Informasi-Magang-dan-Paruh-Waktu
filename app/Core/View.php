<?php
declare(strict_types=1);
final class View  {
    public static function render(string $template,array $viewData=[]):void {
        extract($viewData,EXTR_SKIP);
        $title=$title??'FILKOM Connect';
        $currentUser=Auth::user();
        $unread=$currentUser?Notifikasi::belumDibaca((int)$currentUser['idAkun']):0;
        require ROOT.'/app/Views/layout/header.php';
        require ROOT.'/app/Views/'.$template.'.php';
        require ROOT.'/app/Views/layout/footer.php';
    }
}
