<?php
// Copy to config/config.php and edit:  cp config/config.example.php config/config.php
return [
  'db' => [
    'host'    => 'localhost',            // 'localhost' = unix socket (default on Ubuntu). Use '127.0.0.1' for TCP.
    'name'    => 'lectura',
    'user'    => 'lectura',
    'pass'    => 'CHANGE_ME_TO_A_STRONG_PASSWORD',
    'charset' => 'utf8mb4',
  ],
  'base_url' => '',                      // '' = site served at the domain root (DocumentRoot = /var/www/lectura/public)
                                         // use '/lectura' only if you serve it from a sub-folder alias
  'timezone' => 'Asia/Karachi',
  'student_email_domain' => '',          // e.g. 'pu.edu.pk' to require a university email; '' = off
  'slots' => [1=>['08:15','10:15'], 2=>['10:15','12:15'], 3=>['12:15','14:15'], 4=>['14:15','16:15']],
  'friday_skip_slot' => 3,               // Jummah break
  'day_start' => '08:15',
  'day_end'   => '17:15',                // a 3-hour lab may start in slot 4
  'class_days'=> [1,2,3,4,5,6],          // Mon..Sat
];
