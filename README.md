# wordpress-brute-force-telegram-bildirim
wordpress sitenize girmeye çalışanları bildirir. kullanıcı adı, e-posta adresi, şifre yanlış girildiğinde telegram üzerinden bilgilendirir

## Kurulum
`wp-brute-force-telegram` klasörünü `wp-content/plugins/` içine kopyalayıp etkinleştirin. Her yönetici, **Kullanıcılar > Profil** sayfasında kendi Telegram bot token ve chat ID bilgisini girmelidir (zorunlu). Hatalı giriş denemelerinde (yanlış kullanıcı adı, e-posta veya şifre) bilgisi girilmiş tüm yöneticilere anında bildirim gider; denenen şifre bildirime eklenmez. Alanlar etiketli, `aria` öznitelikli ve ekran okuyucu uyumludur.
