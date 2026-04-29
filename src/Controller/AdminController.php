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
                $existingUser = $this->userService->getUserByEmail($email) ?: $this->userService->getUserByMatricula($matricula);

                if ($existingUser) {
                    $errors[] = 'Já existe um cadastro com este e-mail ou matrícula no sistema.';
                } else {
                    try {
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
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro do sistema (Banco de Dados): ' . $e->getMessage();
                    }
                }
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

            if ($request->request->get('action') === 'delete') {
                try {
                    $this->userService->deleteUser($id);
                    return new RedirectResponse('/admin');
                } catch (\Throwable $e) {
                    $errors[] = 'Erro do sistema (Banco de Dados): ' . $e->getMessage();
                }
            } elseif (empty($errors)) {
                try {
                    $this->userService->updateUser($id, $data);
                    $message = 'Dados do associado atualizados.';
                    $associate = $this->userService->getUserById($id);
                } catch (\Throwable $e) {
                    $errors[] = 'Erro do sistema (Banco de Dados): ' . $e->getMessage();
                }
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
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

private function renderPage(string $title, string $content): string
    {
        // Fundo #f8fafc mantido, com min-height para garantir a estabilidade
        $css = '@import url(\'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap\');body{font-family:\'Inter\',sans-serif;background:#f8fafc;color:#334155;margin:0;padding:0;line-height:1.6;min-height:100vh;background-attachment:fixed;background-size:cover;background-repeat:no-repeat}main{max-width:1000px;margin:40px auto;padding:40px;background:#ffffff;border-radius:12px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05)}.brand-header{text-align:center;margin-bottom:24px}.brand-header img{max-height:64px;display:block;margin:0 auto 12px}.brand-header a{font-size:1.75rem;font-weight:800;color:#0f172a;text-decoration:none;letter-spacing:-0.5px}.brand-header a span{color:#2563eb}h1{font-size:1.75rem;margin-top:0;margin-bottom:24px;text-align:center;color:#0f172a;font-weight:700}h2{font-size:1.25rem;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-top:32px}input,select,button{font-family:inherit;font-size:1rem;padding:12px 16px;margin:8px 0 16px;width:100%;box-sizing:border-box;border-radius:8px;border:1px solid #cbd5e1;transition:all 0.2s ease;background:#fff}input:focus,select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.2)}input[readonly],input[disabled]{background:#f1f5f9;color:#64748b;cursor:not-allowed}label{display:block;margin-top:12px;font-weight:500;color:#475569;font-size:0.9rem}button{background:#2563eb;color:#fff;border:none;cursor:pointer;font-weight:600;padding:14px;margin-top:16px;box-shadow:0 4px 6px -1px rgba(37,99,235,0.2)}button:hover{background:#1d4ed8;transform:translateY(-1px);box-shadow:0 6px 8px -1px rgba(37,99,235,0.3)}button[value="delete"]{background:#ef4444!important}button[value="delete"]:hover{background:#dc2626!important}table{width:100%;border-collapse:separate;border-spacing:0;margin-top:24px;overflow:hidden;border-radius:8px;border:1px solid #e2e8f0;font-size:0.95rem}th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;font-weight:600;color:#475569}tr:last-child td{border-bottom:none}a{color:#2563eb;text-decoration:none;font-weight:500;transition:color 0.2s}a:hover{color:#1d4ed8}hr{border:0;height:1px;background:#e2e8f0;margin:32px 0}.status-Ativo{color:#15803d;background:#dcfce7;padding:4px 12px;border-radius:99px;font-size:0.85rem;display:inline-block;font-weight:600;white-space:nowrap;line-height:1.4}.status-Aguardando-verificação{color:#b45309;background:#fef3c7;padding:4px 12px;border-radius:99px;font-size:0.85rem;display:inline-block;font-weight:600;white-space:nowrap;line-height:1.4}.status-Vencido{color:#b91c1c;background:#fee2e2;padding:4px 12px;border-radius:99px;font-size:0.85rem;display:inline-block;font-weight:600;white-space:nowrap;line-height:1.4}.notice{background:#f0f9ff;border-left:4px solid #3b82f6;padding:16px;margin-bottom:24px;border-radius:0 8px 8px 0;color:#0369a1}.notice[style*="background-color: #eafbe6"]{background:#f0fdf4 !important;border-color:#22c55e !important;color:#166534 !important}.notice[style*="background:#ffefef"]{background:#fef2f2 !important;border-color:#ef4444 !important;color:#991b1b !important}@media (max-width:768px){main{margin:20px;padding:24px}table{display:block;overflow-x:auto;white-space:nowrap}}';
        
        // Favicons adicionados na head
        $favicons = '
        <link rel="icon" href="/var/assets/img/cropped-favicon-32x32.png" sizes="32x32" />
        <link rel="icon" href="/var/assets/img/cropped-favicon-192x192.png" sizes="192x192" />
        <link rel="apple-touch-icon" href="/var/assets/img/cropped-favicon-180x180.png" />';

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' . $this->escape($title) . '</title><meta name="viewport" content="width=device-width, initial-scale=1">' . $favicons . '<style>' . $css . ' .brand-header img{max-height:64px;display:block;margin:0 auto 12px}</style></head><body><main><div class="brand-header"><img src="/var/assets/img/logo.png" onerror="this.style.display=\'none\'" alt="Logo"><br><a href="/">DF<span>SUB</span></a></div><h1>' . $this->escape($title) . '</h1>' . $content . '</main></body></html>';
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
            // A classe de status agora vai em um <span>, e o <td> fica responsável apenas por alinhar ao centro
            $rows .= '<tr>
                        <td>' . $this->escape($user['id']) . '</td>
                        <td style="white-space: nowrap;">' . $this->escape($user['nome']) . '</td>
                        <td style="white-space: nowrap;">' . $this->escape($user['email']) . '</td>
                        <td>' . $this->escape($user['matricula']) . '</td>
                        <td style="text-align: center; vertical-align: middle;">
                            <span class="' . $this->escape('status-' . str_replace(' ', '-', $user['status'])) . '">' . $this->escape($user['status']) . '</span>
                        </td>
                        <td style="white-space: nowrap;">' . $this->escape($user['data_emissao'] ?: '-') . '</td>
                        <td style="white-space: nowrap;">' . $this->escape($user['data_vencimento'] ?: '-') . '</td>
                        <td><a href="/admin/editar/' . $this->escape($user['id']) . '">Editar</a></td>
                      </tr>';
        }

        return $notice . '
        <section>
            <h2>Associados cadastrados</h2>
            <div style="overflow-x: auto; width: 100%; padding-bottom: 8px;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Matrícula</th>
                            <th style="text-align: center;">Status</th>
                            <th>Emissão</th>
                            <th>Vencimento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>
        
        <section>
            <h2>Adicionar novo associado</h2>
            <form method="post">
                <label>Nome completo<input type="text" name="nome" required></label>
                <label>Data de nascimento<input type="date" name="data_nascimento" required></label>
                <label>E-mail<input type="email" name="email" required></label>
                <label>Matrícula<input type="text" name="matricula" required></label>
                <label>Status
                    <select name="status">
                        <option>Aguardando verificação</option>
                        <option>Ativo</option>
                        <option>Vencido</option>
                    </select>
                </label>
                <label>Data de emissão<input type="date" name="data_emissao"></label>
                <label>Data de vencimento<input type="date" name="data_vencimento"></label>
                <button type="submit">Criar associado</button>
            </form>
        </section>
        <p><a href="/logout" style="color: #ef4444;">Sair</a></p>';
    }
    private function renderEditForm(array $user, ?string $message, array $errors): string
    {
        $notice = '';
        if (!empty($errors)) {
            $notice = '<div class="notice">' . implode('<br>', array_map([$this, 'escape'], $errors)) . '</div>';
        } elseif ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        return $notice . '<form method="post"><label>Nome completo<input type="text" name="nome" value="' . $this->escape($user['nome']) . '" required></label><label>Data de nascimento<input type="date" name="data_nascimento" value="' . $this->escape($user['data_nascimento']) . '" required></label><label>E-mail<input type="email" name="email" value="' . $this->escape($user['email']) . '" required></label><label>Matrícula<input type="text" name="matricula" value="' . $this->escape($user['matricula']) . '" required></label><label>Status<select name="status"><option' . ($user['status'] === 'Aguardando verificação' ? ' selected' : '') . '>Aguardando verificação</option><option' . ($user['status'] === 'Ativo' ? ' selected' : '') . '>Ativo</option><option' . ($user['status'] === 'Vencido' ? ' selected' : '') . '>Vencido</option></select></label><label>Data de emissão<input type="date" name="data_emissao" value="' . $this->escape($user['data_emissao'] ?: '') . '"></label><label>Data de vencimento<input type="date" name="data_vencimento" value="' . $this->escape($user['data_vencimento'] ?: '') . '"></label><button type="submit" name="action" value="save">Salvar alterações</button><button type="submit" name="action" value="delete" style="background:#dc3545; margin-top: 10px;" onclick="return confirm(\'Tem certeza que deseja excluir este associado permanentemente?\')">Excluir associado</button></form><p><a href="/admin">Voltar ao painel</a></p>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
