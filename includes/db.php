<?php
function cfg(?string $k = null)
{
  static $c;
  $c ??= require __DIR__ . '/../config/config.php';
  return $k === null ? $c : $c[$k];
}
function db(): PDO
{
  static $pdo;
  if (!$pdo) {
    $d = cfg('db');
    $pdo = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}", $d['user'], $d['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    date_default_timezone_set(cfg('timezone'));
  }
  return $pdo;
}