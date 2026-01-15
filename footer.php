<?php
// footer.php

$current_page = basename($_SERVER['PHP_SELF']);
$is_login_page = ($current_page === 'login.php');

// Halaman Dashboard (non-login)
if(!$is_login_page):
?>
    </div> 
        </section>
    </div> 
    
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">Afshin APP</div>
        <strong>&copy; MRgoesray 2025</strong>
    </footer>
</div> <?php 
// GUNAKAN ENDIF di sini untuk menutup blok if(!$is_login_page):
endif; 

// Tambahkan beberapa baris kode di bawah ini, jika Anda ingin menyertakan skrip JavaScript
// yang diperlukan di akhir body (meskipun AdminLTE seharusnya sudah ada di header.php)

// SCRIPT JAVASCRIPT DAPAT DITEMPATKAN DI SINI JIKA DIPERLUKAN

?>

</body>
</html>