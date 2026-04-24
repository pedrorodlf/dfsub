<?php

namespace App\Controller;

use App\Services\UserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function indexAction(Request $request): Response
    {
        $this->ensureSession();
        $user = $this->getSessionUser();
        if (!$user || $user['role'] !== 'admin') {
            return new RedirectResponse('/login');
        }

        $message = null;
        $errors = [];
        if ($request->getMethod() === 'POST') {
            $nome = trim($request->request->get('nome', ''));
            $dataNascimento = trim($request->request->get('data_nascimento', ''));
            $email = trim($request->request->get('email', ''));
            $matricula = trim($request->request->get('matricula', ''));
            $status = $request->request->get('status', 'Aguardando verificação');
            $dataEmissao = $request->request->get('data_emissao', '');
            $dataVencimento = $request->request->get('data_vencimento', '');

            if ($nome === '' || $dataNascimento === '' || $email === '' || $matricula === '') {
                $errors[] = 'Preencha todos os campos do novo associado.';
            }

            if (empty($errors)) {
                $this->userService->createUser([
                    'nome' => $nome,
                    'data_nascimento' => $dataNascimento,
                    'email' => $email,
                    'matricula' => $matricula,
                    'senha' => null,
                    'status' => $status,
                    'data_emissao' => $dataEmissao ?: null,
                    'data_vencimento' => $dataVencimento ?: null,
                    'role' => 'associado',
                ]);
                $message = 'Associado criado com sucesso. Instrua-o a acessar /register para criar a senha.';
            }
        }

        $users = $this->userService->listAllUsers();
        return new Response($this->renderPage('Painel do Administrador', $this->renderAdminPage($users, $message, $errors)));
    }

    public function editAction(Request $request, int $id): Response
    {
        $this->ensureSession();
        $user = $this->getSessionUser();
        if (!$user || $user['role'] !== 'admin') {
            return new RedirectResponse('/login');
        }

        $associate = $this->userService->getUserById($id);
        if (!$associate) {
            return new Response($this->renderPage('Editar Associado', '<div class="notice">Associado não encontrado.</div>'));
        }

        $message = null;
        $errors = [];
        if ($request->getMethod() === 'POST') {
            $data = [
                'nome' => trim($request->request->get('nome', '')),
                'data_nascimento' => trim($request->request->get('data_nascimento', '')),
                'email' => trim($request->request->get('email', '')),
                'matricula' => trim($request->request->get('matricula', '')),
                'status' => $request->request->get('status', $associate['status']),
                'data_emissao' => $request->request->get('data_emissao', ''),
                'data_vencimento' => $request->request->get('data_vencimento', ''),
            ];

            if ($data['nome'] === '' || $data['data_nascimento'] === '' || $data['email'] === '' || $data['matricula'] === '') {
                $errors[] = 'Preencha todos os campos obrigatórios.';
            }

            if (empty($errors)) {
                $this->userService->updateUser($id, $data);
                $message = 'Dados do associado atualizados.';
                $associate = $this->userService->getUserById($id);
            }
        }

        return new Response($this->renderPage('Editar Associado', $this->renderEditForm($associate, $message, $errors)));
    }

    private function getSessionUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $this->userService->getUserById((int)$_SESSION['user_id']);
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function renderPage(string $title, string $content): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' . $this->escape($title) . '</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font-family:Arial,Helvetica,sans-serif;background:#f7f7f7;color:#222;margin:0;padding:0}main{max-width:1000px;margin:40px auto;padding:24px;background:#fff;box-shadow:0 0 24px rgba(0,0,0,.08)}h1{margin-top:0}input,select,button{font-size:1rem;padding:10px;margin:6px 0;width:100%;box-sizing:border-box}label{display:block;margin-top:12px}button{background:#111;color:#fff;border:none;cursor:pointer}button:hover{background:#333}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{padding:10px;border:1px solid #ddd;text-align:left}a{color:#0052cc;text-decoration:none}a:hover{text-decoration:underline}.status-Ativo{color:green;font-weight:bold}.status-Aguardando verificação{color:orange;font-weight:bold}.status-Vencido{color:red;font-weight:bold}.notice{background:#eef9ff;border-left:4px solid #72b5ff;padding:12px;margin-bottom:16px}</style></head><body><main><h1>' . $this->escape($title) . '</h1>' . $content . '</main></body></html>';
    }

    private function renderAdminPage(array $users, ?string $message, array $errors): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        $rows = '';
        foreach ($users as $user) {
            $rows .= '<tr><td>' . $this->escape($user['id']) . '</td><td>' . $this->escape($user['nome']) . '</td><td>' . $this->escape($user['email']) . '</td><td>' . $this->escape($user['matricula']) . '</td><td class="' . $this->escape('status-' . str_replace(' ', '-', $user['status'])) . '">' . $this->escape($user['status']) . '</td><td>' . $this->escape($user['data_emissao'] ?: '-') . '</td><td>' . $this->escape($user['data_vencimento'] ?: '-') . '</td><td><a href="/admin/editar/' . $this->escape($user['id']) . '">Editar</a></td></tr>';
        }

        return $notice . '<section><h2>Adicionar novo associado</h2><form method="post"><label>Nome completo<input type="text" name="nome" required></label><label>Data de nascimento<input type="date" name="data_nascimento" required></label><label>E-mail<input type="email" name="email" required></label><label>Matrícula<input type="text" name="matricula" required></label><label>Status<select name="status"><option>Aguardando verificação</option><option>Ativo</option><option>Vencido</option></select></label><label>Data de emissão<input type="date" name="data_emissao"></label><label>Data de vencimento<input type="date" name="data_vencimento"></label><button type="submit">Criar associado</button></form></section><section><h2>Associados cadastrados</h2><table><thead><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Matrícula</th><th>Status</th><th>Emissão</th><th>Vencimento</th><th>Ações</th></tr></thead><tbody>' . $rows . '</tbody></table><p><a href="/logout">Sair</a></p></section>';
    }

    private function renderEditForm(array $user, ?string $message, array $errors): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        return $notice . '<form method="post"><label>Nome completo<input type="text" name="nome" value="' . $this->escape($user['nome']) . '" required></label><label>Data de nascimento<input type="date" name="data_nascimento" value="' . $this->escape($user['data_nascimento']) . '" required></label><label>E-mail<input type="email" name="email" value="' . $this->escape($user['email']) . '" required></label><label>Matrícula<input type="text" name="matricula" value="' . $this->escape($user['matricula']) . '" required></label><label>Status<select name="status"><option' . ($user['status'] === 'Aguardando verificação' ? ' selected' : '') . '>Aguardando verificação</option><option' . ($user['status'] === 'Ativo' ? ' selected' : '') . '>Ativo</option><option' . ($user['status'] === 'Vencido' ? ' selected' : '') . '>Vencido</option></select></label><label>Data de emissão<input type="date" name="data_emissao" value="' . $this->escape($user['data_emissao'] ?: '') . '"></label><label>Data de vencimento<input type="date" name="data_vencimento" value="' . $this->escape($user['data_vencimento'] ?: '') . '"></label><button type="submit">Salvar alterações</button></form><p><a href="/admin">Voltar ao painel</a></p>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
