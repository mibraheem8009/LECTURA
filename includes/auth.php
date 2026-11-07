<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE)
  session_start();

function e($s): string
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function url(string $p = ''): string
{
  return rtrim(cfg('base_url'), '/') . '/' . ltrim($p, '/');
}
function redirect(string $p): never
{
  header('Location: ' . url($p));
  exit;
}
function role(): ?string
{
  return $_SESSION['role'] ?? null;
}
function uid(): ?int
{
  return $_SESSION['user_id'] ?? null;
}
function require_role(array $roles): void
{
  if (!in_array(role(), $roles, true))
    redirect('index.php');
}
function require_viewer(): void
{
  if (!role())
    redirect('index.php');
}   // student, teacher or admin

function csrf_token(): string
{
  return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
function csrf_field(): string
{
  return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_check(): void
{
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Invalid CSRF token. Go back and reload the page.');
  }
}
function flash(string $msg, string $type = 'ok'): void
{
  $_SESSION['flash'][] = [$type, $msg];
}
function flashes(): string
{
  $h = '';
  foreach ($_SESSION['flash'] ?? [] as [$t, $m])
    $h .= '<div class="flash ' . $t . '">' . e($m) . '</div>';
  unset($_SESSION['flash']);
  return $h;
}