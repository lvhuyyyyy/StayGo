<?php
// File này chứa các API key nhạy cảm.
// KHÔNG chia sẻ, KHÔNG commit lên git công khai.
// Trên Railway: thêm các biến này vào Variables.

define('OPENAI_API_KEY',      getenv('OPENAI_API_KEY')      ?: '');
define('GOOGLE_CLIENT_ID',    getenv('GOOGLE_CLIENT_ID')    ?: '');
define('GOOGLE_CLIENT_SECRET',getenv('GOOGLE_CLIENT_SECRET')?:'');
define('GOOGLE_REDIRECT_URI',  getenv('GOOGLE_REDIRECT_URI')  ?: '');
define('FB_APP_ID',            getenv('FB_APP_ID')            ?: '');
define('FB_APP_SECRET',        getenv('FB_APP_SECRET')        ?: '');
define('FB_REDIRECT_URI',      getenv('FB_REDIRECT_URI')      ?: '');
