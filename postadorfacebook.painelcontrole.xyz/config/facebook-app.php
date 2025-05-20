<?php
// Configurações da API do Facebook
define('FB_APP_ID', 'SEU_APP_ID_FACEBOOK');
define('FB_APP_SECRET', 'SEU_APP_SECRET_FACEBOOK');
define('FB_APP_VERSION', 'v18.0');
define('FB_REDIRECT_URI', 'https://seusite.com/api/facebook-callback.php');
define('FB_PERMISSIONS', ['public_profile', 'email', 'groups_access_member_info', 'publish_to_groups']);
?>