<?php

namespace App\Controller;

use App\Services\UserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function loginAction(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if ($request->getMethod() === 'POST') {
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');

            if ($email === '' || $password === '') {
                $error = 'Preencha e-mail e senha.';
            } else {
                $user = $this->userService->getUserByEmail($email);
                if (!$user) {
                    $error = 'Usuário ou senha inválidos.';
                } elseif (!$this->userService->verifyPassword($password, $user['senha_hash'])) {
                    $error = 'Usuário ou senha inválidos.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'admin') {
                        return new RedirectResponse('/admin');
                    }

                    return new RedirectResponse('/dashboard');
                }
            }
        }

        return new Response($this->renderPage('Login de Associado / Administrador', $this->renderLoginForm($error ?? null)));
    }

    public function registerAction(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $existingUser = null;
        $message = null;
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $nome = trim($request->request->get('nome', ''));
            $dataNascimento = trim($request->request->get('data_nascimento', ''));
            $email = trim($request->request->get('email', ''));
            $matricula = trim($request->request->get('matricula', ''));
            $senha = $request->request->get('senha', '');
            $senhaConfirm = $request->request->get('senha_confirm', '');

            if ($nome === '' || $dataNascimento === '' || $email === '' || $matricula === '' || $senha === '' || $senhaConfirm === '') {
                $errors[] = 'Preencha todos os campos.';
            }

            if ($senha !== $senhaConfirm) {
                $errors[] = 'As senhas não coincidem.';
            }

            if (empty($errors)) {
                $existingUser = $this->userService->getUserByEmail($email) ?: $this->userService->getUserByMatricula($matricula);

                if ($existingUser && !empty($existingUser['senha_hash'])) {
                    $errors[] = 'Já existe um cadastro com este e-mail ou matrícula.';
                }

                if (empty($errors)) {
                    $data = [
                        'nome' => $nome,
                        'data_nascimento' => $dataNascimento,
                        'email' => $email,
                        'matricula' => $matricula,
                        'senha' => $senha,
                        'status' => 'Aguardando verificação',
                        'data_emissao' => null,
                        'data_vencimento' => null,
                        'role' => 'associado',
                    ];

                    if ($existingUser) {
                        $this->userService->updateUser((int)$existingUser['id'], $data);
                        $message = 'Senha configurada com sucesso. Agora faça login.';
                    } else {
                        $this->userService->createUser($data);
                        $message = 'Cadastro realizado com sucesso. Faça login para acessar sua carteirinha.';
                    }
                }
            }
        }

        return new Response($this->renderPage('Cadastro de Associado', $this->renderRegisterForm($message, $errors)));
    }

    public function logoutAction(): RedirectResponse
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();

        return new RedirectResponse('/login');
    }

    public function dashboardAction(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $this->getSessionUser();
        if (!$user || $user['role'] !== 'associado') {
            return new RedirectResponse('/login');
        }

        $message = null;
        $errors = [];
        if ($request->getMethod() === 'POST') {
            $nome = trim($request->request->get('nome', ''));
            $dataNascimento = trim($request->request->get('data_nascimento', ''));
            $email = trim($request->request->get('email', ''));
            $matricula = trim($request->request->get('matricula', ''));

            if ($nome === '' || $dataNascimento === '' || $email === '' || $matricula === '') {
                $errors[] = 'Preencha todos os campos.';
            }

            if (empty($errors)) {
                $this->userService->updateUser((int)$user['id'], [
                    'nome' => $nome,
                    'data_nascimento' => $dataNascimento,
                    'email' => $email,
                    'matricula' => $matricula,
                ]);
                $message = 'Informações atualizadas com sucesso.';
                $user = $this->userService->getUserById((int)$user['id']);
            }
        }

        return new Response($this->renderPage('Carteirinha do Associado', $this->renderDashboard($user, $message, $errors)));
    }

    public function downloadCardAction(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $this->getSessionUser();
        if (!$user || $user['role'] !== 'associado') {
            return new RedirectResponse('/login');
        }

        $pdf = $this->buildPdf($user);
        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="carteirinha-' . $this->slugify($user['nome']) . '.pdf"');

        return $response;
    }

    private function getSessionUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $this->userService->getUserById((int)$_SESSION['user_id']);
    }

    private function renderPage(string $title, string $content): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' . $this->escape($title) . '</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;color:#222;margin:0;padding:0}main{max-width:900px;margin:40px auto;padding:24px;background:#fff;box-shadow:0 0 24px rgba(0,0,0,.08)}h1{margin-top:0}input,select,button{font-size:1rem;padding:10px;margin:6px 0;width:100%;box-sizing:border-box}label{display:block;margin-top:12px}button{background:#0052cc;color:#fff;border:none;cursor:pointer}button:hover{background:#003d99}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{padding:10px;border:1px solid #ddd;text-align:left}a{color:#0052cc;text-decoration:none}a:hover{text-decoration:underline}.status-Ativo{color:green;font-weight:bold}.status-Aguardando verificação{color:orange;font-weight:bold}.status-Vencido{color:red;font-weight:bold}.notice{background:#eef9ff;border-left:4px solid #72b5ff;padding:12px;margin-bottom:16px}</style></head><body><main><h1>' . $this->escape($title) . '</h1>' . $content . '</main></body></html>';
    }

    private function renderLoginForm(?string $error = null): string
    {
        $notice = '';
        if ($error) {
            $notice = '<div class="notice">' . $this->escape($error) . '</div>';
        }
        return $notice . '<form method="post"><label>E-mail<input type="email" name="email" required></label><label>Senha<input type="password" name="password" required></label><button type="submit">Entrar</button></form><p>Não tem conta? <a href="/register">Cadastre-se</a></p><p>Consulta pública: <a href="/consulta">Pesquisar matrícula</a></p>';
    }

    private function renderRegisterForm(?string $message, array $errors): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        return $notice . '<form method="post"><label>Nome completo<input type="text" name="nome" required></label><label>Data de nascimento<input type="date" name="data_nascimento" required></label><label>E-mail<input type="email" name="email" required></label><label>Matrícula<input type="text" name="matricula" required></label><label>Senha<input type="password" name="senha" required></label><label>Confirmar senha<input type="password" name="senha_confirm" required></label><button type="submit">Criar conta / Definir senha</button></form><p>Já tem conta? <a href="/login">Faça login</a></p><p>Se o administrador já cadastrou seus dados, use o mesmo e-mail e matrícula para criar sua senha.</p>';
    }

    private function renderDashboard(array $user, ?string $message, array $errors): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        $statusClass = 'status-' . str_replace(' ', '-', $user['status']);
        $dataEmissao = $user['data_emissao'] ?: 'Não definido';
        $dataVencimento = $user['data_vencimento'] ?: 'Não definido';
        $consultaUrl = '/validar/' . $this->escape($user['qr_token']);

        return $notice . '<div><strong>Status:</strong> <span class="' . $this->escape($statusClass) . '">' . $this->escape($user['status']) . '</span><br><strong>Data de emissão:</strong> ' . $this->escape($dataEmissao) . '<br><strong>Data de vencimento:</strong> ' . $this->escape($dataVencimento) . '<br><strong>QR público:</strong> <a href="' . $this->escape($consultaUrl) . '">/validar/' . $this->escape($user['qr_token']) . '</a></div><hr><form method="post"><label>Nome completo<input type="text" name="nome" value="' . $this->escape($user['nome']) . '" required></label><label>Data de nascimento<input type="date" name="data_nascimento" value="' . $this->escape($user['data_nascimento']) . '" required></label><label>E-mail<input type="email" name="email" value="' . $this->escape($user['email']) . '" required></label><label>Matrícula<input type="text" name="matricula" value="' . $this->escape($user['matricula']) . '" required></label><button type="submit">Salvar meus dados</button></form><p><a href="/dashboard/carteira.pdf">Baixar carteirinha em PDF</a></p><p><a href="/logout">Sair</a></p>';
    }

    private function buildPdf(array $user): string
    {
        $lines = [
            'BT',
            '/F1 22 Tf',
            '50 760 Td (' . $this->escapePdfText('CARTEIRINHA DFSUB') . ') Tj',
            '/F1 14 Tf',
            '50 720 Td (' . $this->escapePdfText('Nome: ' . $user['nome']) . ') Tj',
            '50 690 Td (' . $this->escapePdfText('Data de nascimento: ' . $user['data_nascimento']) . ') Tj',
            '50 660 Td (' . $this->escapePdfText('Email: ' . $user['email']) . ') Tj',
            '50 630 Td (' . $this->escapePdfText('Matrícula: ' . $user['matricula']) . ') Tj',
            '50 600 Td (' . $this->escapePdfText('Status: ' . $user['status']) . ') Tj',
            '50 570 Td (' . $this->escapePdfText('Emissão: ' . ($user['data_emissao'] ?: '---')) . ') Tj',
            '50 540 Td (' . $this->escapePdfText('Vencimento: ' . ($user['data_vencimento'] ?: '---')) . ') Tj',
            '50 500 Td (' . $this->escapePdfText('Valide via: /validar/' . $user['qr_token']) . ') Tj',
            'ET',
        ];

        $stream = implode("\n", $lines);
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> >> /Contents 4 0 R >>\nendobj",
            "4 0 obj\n<< /Length %d >>\nstream\n%s\nendstream\nendobj",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj",
        ];

        $objects[3] = sprintf($objects[3], strlen($stream), $stream);

        $document = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $obj) {
            $offsets[] = strlen($document);
            $document .= $obj . "\n";
        }

        $xrefStart = strlen($document);
        $document .= "xref\n0 " . (count($objects) + 1) . "\n";
        $document .= sprintf('%010d 65535 f \n', 0);
        foreach ($offsets as $offset) {
            $document .= sprintf('%010d 00000 n \n', $offset);
        }
        $document .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefStart . "\n%%EOF";

        return $document;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\\pL0-9]+~u', '-', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        if (empty($text)) {
            return 'carteirinha';
        }
        return $text;
    }
}
