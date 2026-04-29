<?php

namespace App\Controller;

use App\Services\UserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use GuzzleHttp\Client;

class AuthController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function loginAction(Request $request): Response
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
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

    public function forgotPasswordAction(Request $request): Response
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $message = null;
        $errors = [];
        $showVerificationForm = false;

        if ($request->getMethod() === 'POST') {
            if ($request->request->has('verification_code')) {
                // Passo 2: Validar o código e salvar a nova senha
                $inputCode = trim($request->request->get('verification_code', ''));
                if (isset($_SESSION['reset_code']) && $inputCode === (string)$_SESSION['reset_code']) {
                    $resetData = $_SESSION['reset_data'] ?? [];
                    if (!empty($resetData)) {
                        try {
                            $user = $this->userService->getUserByEmail($resetData['email']);
                            if ($user) {
                                $this->userService->updateUser((int)$user['id'], [
                                    'senha' => $resetData['nova_senha']
                                ]);
                                $message = 'Senha redefinida com sucesso. Faça login com a nova senha.';
                                unset($_SESSION['reset_code'], $_SESSION['reset_data']);
                            }
                        } catch (\Throwable $e) {
                            $errors[] = 'Erro ao atualizar senha: ' . $e->getMessage();
                        }
                    }
                } else {
                    $errors[] = 'Código inválido.';
                    $showVerificationForm = true;
                }
            } else {
                // Passo 1: Validar e-mail/matrícula e enviar código
                $email = trim($request->request->get('email', ''));
                $matricula = trim($request->request->get('matricula', ''));
                $novaSenha = $request->request->get('password', '');
                $confirmSenha = $request->request->get('password_confirm', '');

                $user = $this->userService->getUserByEmail($email);
                
                if (!$user || $user['matricula'] !== $matricula) {
                    $errors[] = 'Dados não conferem com nossos registros.';
                } elseif ($novaSenha !== $confirmSenha) {
                    $errors[] = 'As senhas não coincidem.';
                }

                if (empty($errors)) {
                    $code = sprintf('%06d', mt_rand(0, 999999));
                    $_SESSION['reset_code'] = $code;
                    $_SESSION['reset_data'] = [
                        'email' => $email,
                        'nova_senha' => $novaSenha
                    ];

                    $subject = "Recuperacao de Senha - DFSUB";
                    $body = "Seu código para redefinir a senha é: $code";
                    $headers = "From: nao-responda@dfsub.com.br\r\nContent-Type: text/plain; charset=UTF-8";
                    @mail($email, $subject, $body, $headers);

                    $showVerificationForm = true;
                    $message = 'Código enviado para o seu e-mail.';
                }
            }
        }

        return new Response($this->renderPage('Recuperar Senha', $this->renderForgotPasswordForm($message, $errors, $showVerificationForm)));
    }

    private function renderForgotPasswordForm(?string $message, array $errors, bool $showVerificationForm): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice" style="border-color: #28a745; background-color: #eafbe6;">' . $this->escape($message) . '</div>';
        }

        if ($showVerificationForm) {
            return $notice . '<form method="post"><label>Código enviado por e-mail<input type="text" name="verification_code" required></label><button type="submit">Validar e Alterar Senha</button></form>';
        }

        return $notice . '<form method="post">
            <label>E-mail cadastrado<input type="email" name="email" required></label>
            <label>Matrícula<input type="text" name="matricula" required></label>
            <label>Nova Senha<input type="password" name="password" required></label>
            <label>Confirmar Nova Senha<input type="password" name="password_confirm" required></label>
            <button type="submit">Enviar Código de Verificação</button>
        </form><p><a href="/login">Voltar ao login</a></p>';
    }

    public function registerAction(Request $request): Response
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $message = null;
        $errors = [];
        $showVerificationForm = false;

        if ($request->getMethod() === 'POST') {
            if ($request->request->has('verification_code')) {
                $inputCode = trim($request->request->get('verification_code', ''));
                if (isset($_SESSION['verification_code']) && $inputCode === (string)$_SESSION['verification_code']) {
                    $regData = $_SESSION['registration_data'] ?? [];
                    if (!empty($regData)) {
                        $existingUser = $this->userService->getUserByEmail($regData['email']) ?: $this->userService->getUserByMatricula($regData['matricula']);
                        try {
                            if ($existingUser) {
                                $this->userService->updateUser((int)$existingUser['id'], [
                                    'nome' => $regData['nome'],
                                    'data_nascimento' => $regData['data_nascimento'],
                                    'email' => $regData['email'],
                                    'matricula' => $regData['matricula'],
                                    'senha' => $regData['senha'],
                                ]);
                                $message = 'Senha configurada com sucesso. Agora faça login.';
                            } else {
                                $this->userService->createUser([
                                    'nome' => $regData['nome'],
                                    'data_nascimento' => $regData['data_nascimento'],
                                    'email' => $regData['email'],
                                    'matricula' => $regData['matricula'],
                                    'senha' => $regData['senha'],
                                    'status' => 'Aguardando verificação',
                                    'data_emissao' => null,
                                    'data_vencimento' => null,
                                    'role' => 'associado',
                                ]);
                                $message = 'Cadastro realizado com sucesso. Faça login para acessar sua carteirinha.';
                            }
                            unset($_SESSION['verification_code'], $_SESSION['registration_data']);
                        } catch (\Throwable $e) {
                            $errors[] = 'Erro do sistema (Banco de Dados): ' . $e->getMessage();
                        }
                    } else {
                        $errors[] = 'Sessão expirada. Por favor, reinicie o cadastro.';
                    }
                } else {
                    $errors[] = 'Código de verificação inválido ou expirado.';
                    $showVerificationForm = true;
                }
            } else {
                $nome = trim($request->request->get('nome', ''));
                $dataNascimento = trim($request->request->get('data_nascimento', ''));
                $email = trim($request->request->get('email', ''));
                $emailConfirm = trim($request->request->get('email_confirm', ''));
                $matricula = trim($request->request->get('matricula', ''));
                $senha = $request->request->get('senha', '');
                $senhaConfirm = $request->request->get('senha_confirm', '');

                if ($nome === '' || $dataNascimento === '' || $email === '' || $emailConfirm === '' || $matricula === '' || $senha === '' || $senhaConfirm === '') {
                    $errors[] = 'Preencha todos os campos.';
                }

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'O formato do e-mail é inválido.';
                } elseif ($email !== '') {
                    $domain = substr(strrchr($email, "@"), 1);
                    if (!checkdnsrr($domain, "MX")) {
                        $errors[] = 'O domínio do e-mail não existe ou não recebe mensagens.';
                    }
                }

                if ($email !== '' && $emailConfirm !== '' && $email !== $emailConfirm) {
                    $errors[] = 'Os e-mails não coincidem.';
                }

                if ($senha !== '' && $senhaConfirm !== '' && $senha !== $senhaConfirm) {
                    $errors[] = 'As senhas não coincidem.';
                }

                if (empty($errors)) {
                    $existingUser = $this->userService->getUserByEmail($email) ?: $this->userService->getUserByMatricula($matricula);

                    if ($existingUser && !empty($existingUser['senha_hash'])) {
                        $errors[] = 'Já existe um cadastro com este e-mail ou matrícula.';
                    }

                    if (empty($errors)) {
                        $code = sprintf('%06d', mt_rand(0, 999999));
                        $_SESSION['verification_code'] = $code;
                        $_SESSION['registration_data'] = [
                            'nome' => $nome,
                            'data_nascimento' => $dataNascimento,
                            'email' => $email,
                            'matricula' => $matricula,
                            'senha' => $senha,
                        ];

                        $host = $_SERVER['HTTP_HOST'] ?? 'dfsub.com.br';
                        $subject = "Codigo de Confirmacao - DFSUB";
                        $body = "Olá, $nome.\n\nSeu código de confirmação para acesso à plataforma DFSUB é: $code\n\nSe você não solicitou este cadastro, por favor, ignore este e-mail.";
                        $headers = "From: nao-responda@" . $host . "\r\nReply-To: nao-responda@" . $host . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                        @mail($email, $subject, $body, $headers);

                        $showVerificationForm = true;
                        $message = 'Um código de 6 dígitos foi enviado para o seu e-mail. Verifique sua caixa de entrada (e spam) para continuar.';
                    }
                }
            }
        }

        return new Response($this->renderPage('Cadastro de Associado', $this->renderRegisterForm($message, $errors, $showVerificationForm)));
    }

    public function logoutAction(): RedirectResponse
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
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
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
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
                $uploadDir = __DIR__ . '/../../uploads/fotos';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if ($request->request->get('remove_foto') === '1') {
                    $oldFiles = glob($uploadDir . '/user_' . $user['id'] . '.*');
                    if ($oldFiles) {
                        foreach ($oldFiles as $oldFile) {
                            @unlink($oldFile);
                        }
                    }
                    $message = 'Foto removida com sucesso.';
                } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['foto']['tmp_name'];
                    $imgInfo = @getimagesize($tmpName);
                    
                    if ($imgInfo && in_array($imgInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
                        $oldFiles = glob($uploadDir . '/user_' . $user['id'] . '.*');
                        if ($oldFiles) {
                            foreach ($oldFiles as $oldFile) {
                                @unlink($oldFile);
                            }
                        }
                        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
                        if (!$ext) $ext = 'jpg';
                        $filename = 'user_' . $user['id'] . '.' . $ext;
                        move_uploaded_file($tmpName, $uploadDir . '/' . $filename);
                    } else {
                        $errors[] = 'A foto deve ser uma imagem válida (JPG, PNG ou WEBP).';
                    }
                } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Erro ao enviar a foto. Código: ' . $_FILES['foto']['error'];
                }
            }

            if (empty($errors)) {
                try {
                    $this->userService->updateUser((int)$user['id'], [
                        'nome' => $nome,
                        'data_nascimento' => $dataNascimento,
                        'email' => $email,
                        'matricula' => $matricula,
                    ]);
                    $message = 'Informações atualizadas com sucesso.';
                    $user = $this->userService->getUserById((int)$user['id']);
                } catch (\Throwable $e) {
                    $errors[] = 'Erro do sistema (Banco de Dados): ' . $e->getMessage();
                }
            }
        }

        return new Response($this->renderPage('Carteirinha do Associado', $this->renderDashboard($user, $message, $errors)));
    }

    public function downloadCardAction(Request $request): Response
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
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
        $css = '@import url(\'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap\');body{font-family:\'Inter\',sans-serif;background:linear-gradient(135deg, #10182b 0%, #1f2f64 100%);color:#334155;margin:0;padding:0;line-height:1.6;min-height:100vh;display:flex;align-items:center;justify-content:center}main{width:100%;max-width:800px;margin:40px 20px;padding:40px;background:#ffffff;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);box-sizing:border-box}.brand-header{display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:32px;padding-bottom:24px;border-bottom:1px solid #f1f5f9}.brand-header img{height:64px;object-fit:contain;margin:0}.brand-header a{font-size:2.5rem;font-weight:800;color:#0f172a;text-decoration:none;letter-spacing:-1px;line-height:1}.brand-header a span{color:#1f2f64}h1{font-size:1.75rem;margin-top:0;margin-bottom:24px;text-align:center;color:#0f172a;font-weight:700}h2{font-size:1.25rem;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-top:32px}input,select,button{font-family:inherit;font-size:1rem;padding:12px 16px;margin:8px 0 16px;width:100%;box-sizing:border-box;border-radius:8px;border:1px solid #cbd5e1;transition:all 0.2s ease;background:#fff}input[type="checkbox"]{width:auto;margin-right:8px;display:inline-block}input:focus,select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.2)}input[readonly],input[disabled]{background:#f1f5f9;color:#64748b;cursor:not-allowed}label{display:block;margin-top:12px;font-weight:500;color:#475569;font-size:0.9rem}button{background:#2563eb;color:#fff;border:none;cursor:pointer;font-weight:600;padding:14px;margin-top:16px;box-shadow:0 4px 6px -1px rgba(37,99,235,0.2)}button:hover{background:#1d4ed8;transform:translateY(-1px);box-shadow:0 6px 8px -1px rgba(37,99,235,0.3)}button[value="delete"]{background:#ef4444!important}button[value="delete"]:hover{background:#dc2626!important}table{width:100%;border-collapse:separate;border-spacing:0;margin-top:24px;overflow:hidden;border-radius:8px;border:1px solid #e2e8f0;font-size:0.95rem}th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;font-weight:600;color:#475569}tr:last-child td{border-bottom:none}a{color:#2563eb;text-decoration:none;font-weight:500;transition:color 0.2s ease}a:hover{color:#1d4ed8;text-decoration:underline}.status-Ativo{color:#16a34a;font-weight:600;background:#dcfce7;padding:4px 12px;border-radius:9999px;font-size:0.85rem;display:inline-block}.status-Aguardando-verificação{color:#d97706;font-weight:600;background:#fef3c7;padding:4px 12px;border-radius:9999px;font-size:0.85rem;display:inline-block}.status-Vencido{color:#dc2626;font-weight:600;background:#fee2e2;padding:4px 12px;border-radius:9999px;font-size:0.85rem;display:inline-block}.notice{background:#eff6ff;border-left:4px solid #3b82f6;padding:16px;margin-bottom:24px;border-radius:0 8px 8px 0;color:#1e3a8a;font-weight:500}@media(max-width:768px){main{padding:24px;margin:20px}.brand-header{flex-direction:column;text-align:center;gap:8px}table{display:block;overflow-x:auto;white-space:nowrap}}';
        
        return '<!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>' . $this->escape($title) . '</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="icon" href="/var/assets/img/cropped-favicon-32x32.png" sizes="32x32" />
            <link rel="icon" href="/var/assets/img/cropped-favicon-192x192.png" sizes="192x192" />
            <link rel="apple-touch-icon" href="/var/assets/img/cropped-favicon-180x180.png" />
            <style>' . $css . '</style>
        </head>
        <body>
            <main>
                <div class="brand-header">
                    <img src="/var/assets/img/logo.png" onerror="this.style.display=\'none\'" alt="Logo DFSUB">
                    <a href="/">DF<span>SUB</span></a>
                </div>
                <h1>' . $this->escape($title) . '</h1>
                ' . $content . '
            </main>
        </body>
        </html>';
    }

    private function renderLoginForm(?string $error = null): string
    {
        $notice = '';
        if ($error) {
            $notice = '<div class="notice">' . $this->escape($error) . '</div>';
        }
        return $notice . '<form method="post"><label>E-mail<input type="email" name="email" required></label><label>Senha<input type="password" name="password" required></label><button type="submit">Entrar</button></form><p><a href="/forgot-password">Esqueci minha senha</a></p><p>Não tem conta? <a href="/register">Cadastre-se</a></p><p>Consulta pública: <a href="/consulta">Pesquisar matrícula</a></p>';
    }

    private function renderRegisterForm(?string $message, array $errors, bool $showVerificationForm = false): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice" style="border-color: #28a745; background-color: #eafbe6;">' . $this->escape($message) . '</div>';
        }

        if ($showVerificationForm) {
            return $notice . '<form method="post"><label>Código de Verificação de 6 dígitos<input type="text" name="verification_code" maxlength="6" required placeholder="Ex: 123456"></label><button type="submit">Confirmar E-mail e Concluir</button></form><p><a href="/register">Cancelar e tentar novamente</a></p>';
        }

        return $notice . '<form method="post"><label>Nome completo<input type="text" name="nome" required></label><label>Data de nascimento<input type="date" name="data_nascimento" required></label><label>E-mail<input type="email" name="email" required></label><label>Confirmar e-mail<input type="email" name="email_confirm" required></label><label>Matrícula<input type="text" name="matricula" required></label><label>Senha<input type="password" name="senha" required></label><label>Confirmar senha<input type="password" name="senha_confirm" required></label><button type="submit">Criar conta / Definir senha</button></form><p>Já tem conta? <a href="/login">Faça login</a></p><p>Se o administrador já cadastrou seus dados, use o mesmo e-mail e matrícula para criar sua senha.</p>';
    }

    private function renderDashboard(array $user, ?string $message, array $errors): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'dfsub.com.br';
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $fullUrl = $scheme . '://' . $host . '/validar/' . $user['qr_token'];
        $qrCodeImgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($fullUrl);

        $statusClass = 'status-' . str_replace(' ', '-', $user['status']);
        $dataEmissao = $user['data_emissao'] ? date('d/m/Y', strtotime($user['data_emissao'])) : 'Não definido';
        $dataVencimento = $user['data_vencimento'] ? date('d/m/Y', strtotime($user['data_vencimento'])) : 'Não definido';
        $consultaUrl = '/validar/' . $this->escape($user['qr_token']);

        $fotoPath = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 160'%3E%3Crect width='120' height='160' fill='%23e9ecef'/%3E%3Ccircle cx='60' cy='55' r='30' fill='%23ccc'/%3E%3Cpath d='M20 160c0-35 15-55 40-55s40 20 40 55' fill='%23ccc'/%3E%3C/svg%3E";
        $uploadDir = __DIR__ . '/../../uploads/fotos';
        $fotoFiles = glob($uploadDir . '/user_' . $user['id'] . '.*');
        if ($fotoFiles) {
            $fotoPath = '/uploads/fotos/' . basename($fotoFiles[0]) . '?t=' . time();
        }

        return $notice . '
        <div style="display:flex; flex-direction:row; gap:20px; align-items:center; justify-content:space-between; background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:24px; flex-wrap:wrap;">
            <div style="flex:0 0 auto;">
                <img src="' . $this->escape($fotoPath) . '" alt="Foto" style="border:1px solid #cbd5e1; background:#fff; border-radius:8px; width:100px; height:133px; object-fit:cover;">
            </div>
            
            <div style="flex:1 1 250px; overflow-wrap:anywhere;">
                <div style="margin-bottom:8px;"><strong style="color:#475569;">Status:</strong> <span class="' . $this->escape($statusClass) . '">' . $this->escape($user['status']) . '</span></div>
                <div style="margin-bottom:8px;"><strong style="color:#475569;">Data de emissão:</strong> ' . $this->escape($dataEmissao) . '</div>
                <div style="margin-bottom:8px;"><strong style="color:#475569;">Data de vencimento:</strong> ' . $this->escape($dataVencimento) . '</div>
                <div><strong style="color:#475569;">QR público:</strong> <br><a href="' . $this->escape($consultaUrl) . '" style="word-break:break-all; font-size:0.9rem;">' . $this->escape($fullUrl) . '</a></div>
            </div>
            
            <div style="flex:0 0 auto; text-align:center;">
                <img src="' . $this->escape($qrCodeImgUrl) . '" alt="QR Code" style="border:1px solid #cbd5e1; padding:4px; background:#fff; border-radius:8px; width:100px; height:100px;">
            </div>
        </div>
        
        <form method="post" id="user-form" enctype="multipart/form-data">
            <label>Foto 3x4 (Opcional)<input type="file" name="foto" accept="image/png, image/jpeg, image/webp" disabled id="foto-input"></label>
            <label style="display:none;" id="remove-foto-label"><input type="checkbox" name="remove_foto" value="1" disabled id="remove-foto-input"> Remover foto atual</label>
            <label>Nome completo<input type="text" name="nome" value="' . $this->escape($user['nome']) . '" required readonly></label>
            <label>Data de nascimento<input type="date" name="data_nascimento" value="' . $this->escape($user['data_nascimento']) . '" required readonly></label>
            <label>E-mail<input type="email" name="email" value="' . $this->escape($user['email']) . '" required readonly></label>
            <label>Matrícula<input type="text" name="matricula" value="' . $this->escape($user['matricula']) . '" required readonly></label>
            <button type="button" id="edit-btn" onclick="document.getElementById(\'save-btn\').style.display=\'block\';this.style.display=\'none\';var ins=document.querySelectorAll(\'#user-form input\');for(var i=0;i<ins.length;i++){ins[i].removeAttribute(\'readonly\');ins[i].removeAttribute(\'disabled\');}document.getElementById(\'remove-foto-label\').style.display=\'block\';">Editar dados</button>
            <button type="submit" id="save-btn" style="display:none;">Salvar meus dados</button>
        </form>
        <p><a href="/dashboard/carteira.pdf">Baixar carteirinha em PDF</a></p>
        <p><a href="/logout" style="color: #ef4444;">Sair</a></p>';
    }

   private function buildPdf(array $user): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true); // Necessário para carregar a fonte Inter do Google e as imagens
        $options->set('defaultFont', 'Inter');
        $options->set('dpi', 96); 

        $dompdf = new Dompdf($options);

        $html = $this->renderPdfHtml($user);
        $dompdf->loadHtml($html);

        // Tamanho exato de 8cm x 12cm convertido para pontos (pt) do Dompdf
        // 8cm = ~227pt | 12cm = ~340pt
        $dompdf->setPaper([0, 0, 227, 340], 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function renderPdfHtml(array $user): string
    {
        // 1. Converter Foto para Base64
        $uploadDir = __DIR__ . '/../../uploads/fotos';
        $fotoFiles = glob($uploadDir . '/user_' . $user['id'] . '.*');
        $fotoBase64 = '';
        if ($fotoFiles && file_exists($fotoFiles[0])) {
            $type = pathinfo($fotoFiles[0], PATHINFO_EXTENSION);
            $data = file_get_contents($fotoFiles[0]);
            $fotoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // 2. Converter Logo para Base64
        $logoPath = __DIR__ . '/../../var/assets/img/logo.png'; 
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // 3. QR Code Base64
        $host = $_SERVER['HTTP_HOST'] ?? 'dfsub.com.br';
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $fullUrl = $scheme . '://' . $host . '/validar/' . $user['qr_token'];
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=' . urlencode($fullUrl);
        
        $qrBase64 = '';
        try {
            $client = new Client();
            $res = $client->request('GET', $qrUrl, ['verify' => false]);
            $qrData = $res->getBody()->getContents();
            $qrBase64 = 'data:image/png;base64,' . base64_encode($qrData);
        } catch (\Throwable $e) {}

        // 4. Datas
        $dataNascimento = $user['data_nascimento'] ? date('d/m/Y', strtotime($user['data_nascimento'])) : '---';
        $dataEmissao = $user['data_emissao'] ? date('d/m/Y', strtotime($user['data_emissao'])) : '---';
        $validade = $user['data_vencimento'] ? date('m/y', strtotime($user['data_vencimento'])) : '---';

        return '<!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&display=swap");
                
                @page { margin: 0px; size: 227pt 340pt; }
                body { 
                    margin: 0px; 
                    padding: 0px; 
                    font-family: "Oswald", "Helvetica", Arial, sans-serif; 
                    background-color: #1f2f64; 
                    color: #ffffff; 
                    width: 227pt; 
                    height: 340pt; 
                    overflow: hidden; 
                }
                .header { background-color: #5591c2; height: 75px; width: 100%; position: absolute; top: 0; left: 0; }
                
                .foto-container { 
                    position: absolute; top: 12px; left: 15px; 
                    width: 50px; height: 50px; 
                    border-radius: 25px; 
                    background-color: #a0aec0; 
                    overflow: hidden; 
                }
                .foto-container img { width: 50px; }
                
                .title { position: absolute; top: 16px; left: 0; width: 100%; text-align: center; font-size: 24px; color: #ffffff; font-weight: 500; letter-spacing: 0.5px; margin: 0; padding: 0; line-height: 1;}
                
                .logo { position: absolute; top: 12px; right: 15px; width: 50px; height: 50px; background-color: #ffffff; border-radius: 4px; text-align: center; }
                .logo img { max-width: 44px; max-height: 44px; margin-top: 3px; }
                
                .content { position: absolute; top: 90px; left: 15px; width: 197pt; }
                
                /* Margem de respiro entre os blocos (MANTIDA) */
                .field-group { margin-bottom: 12px; }
                
                .row-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
                .row-table td { padding: 0; vertical-align: top; }
                
                .label { 
                    font-size: 13px; 
                    color: #cbd5e1; 
                    font-weight: 300; 
                    margin: 0; 
                    padding: 0;
                    line-height: 1;
                }
                
                /* Margem negativa para puxar o valor e colar no título */
                .value { 
                    font-size: 17px; 
                    font-weight: 500; 
                    color: #ffffff; 
                    margin: 0; 
                    padding: 0;
                    margin-top: -3px; 
                    line-height: 1.1;
                }
                .value-large { 
                    font-size: 21px; 
                    font-weight: 600; 
                    color: #ffffff; 
                    margin: 0; 
                    padding: 0;
                    margin-top: -4px; 
                    line-height: 0.8;
                }
                
                .qr-container { position: absolute; bottom: 15px; right: 15px; text-align: center; width: 66px; }
                .qr { width: 66px; height: 66px; background-color: #ffffff; padding: 2px; box-sizing: border-box; }
                .qr img { width: 100%; height: 100%; }
                .validade { font-size: 11px; color: #cbd5e1; margin-top: 4px; font-weight: 300; margin-bottom: 0; }
            </style>
        </head>
        <body>
            <div class="header"></div>
            
            <div class="foto-container">
                ' . ($fotoBase64 ? '<img src="' . $fotoBase64 . '">' : '') . '
            </div>
            
            <div class="title">Associado</div>
            
            <div class="logo">
                ' . ($logoBase64 ? '<img src="' . $logoBase64 . '">' : '') . '
            </div>
            
            <div class="content">
                
                <div class="field-group">
                    <p class="label">titular</p>
                    <p class="value-large">' . $this->escape($user['nome']) . '</p>
                </div>
                
                <table class="row-table">
                    <tr>
                        <td style="width: 55%;">
                            <p class="label">matrícula</p>
                            <p class="value">' . $this->escape($user['matricula']) . '</p>
                        </td>
                        <td style="width: 45%;">
                            <p class="label">data de nascimento</p>
                            <p class="value">' . $dataNascimento . '</p>
                        </td>
                    </tr>
                </table>
                
                <div class="field-group">
                    <p class="label">email</p>
                    <p class="value">' . $this->escape($user['email']) . '</p>
                </div>
                
                <div class="field-group">
                    <p class="label">data de emissão</p>
                    <p class="value">' . $dataEmissao . '</p>
                </div>
                
            </div>
            
            <div class="qr-container">
                <div class="qr">
                    ' . ($qrBase64 ? '<img src="' . $qrBase64 . '">' : '') . '
                </div>
                <p class="validade">Validade ' . $validade . '</p>
            </div>
        </body>
        </html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapePdfText(string $value): string
    {
        $value = mb_convert_encoding($value, 'windows-1252', 'UTF-8');
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
