<?php

namespace App\Controller;

use App\Services\UserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicController
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function consultaAction(Request $request): Response
    {
        $matricula = trim($request->query->get('matricula', ''));
        $user = null;
        $message = null;

        if ($matricula !== '') {
            $user = $this->userService->getUserByMatricula($matricula);
            if (!$user) {
                $message = 'Nenhum associado encontrado para esta matrícula.';
            }
        }

        return new Response($this->renderPage('Consulta por Matrícula', $this->renderConsulta($user, $message, $matricula)));
    }

    public function validateAction(string $token): Response
    {
        $user = $this->userService->getUserByToken($token);
        if (!$user) {
            return new Response($this->renderPage('Consulta por QR Code', '<div class="notice">QR Code inválido ou associado não encontrado.</div>'));
        }

        return new Response($this->renderPage('Validação de Associado', $this->renderValidationCard($user)));
    }

    private function renderPage(string $title, string $content): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' . $this->escape($title) . '</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f4;color:#222;margin:0;padding:0}main{max-width:880px;margin:40px auto;padding:24px;background:#fff;box-shadow:0 0 24px rgba(0,0,0,.08)}h1{margin-top:0}input,button{font-size:1rem;padding:10px;margin:6px 0;width:100%;box-sizing:border-box}label{display:block;margin-top:12px}button{background:#0080ff;color:#fff;border:none;cursor:pointer}button:hover{background:#005bb5}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{padding:10px;border:1px solid #ddd;text-align:left}a{color:#0052cc;text-decoration:none}a:hover{text-decoration:underline}.status-Ativo{color:green;font-weight:bold}.status-Aguardando verificação{color:orange;font-weight:bold}.status-Vencido{color:red;font-weight:bold}.notice{background:#ffefef;border-left:4px solid #f36d6d;padding:12px;margin-bottom:16px}</style></head><body><main><h1>' . $this->escape($title) . '</h1>' . $content . '</main></body></html>';
    }

    private function renderConsulta(?array $user, ?string $message, string $matricula): string
    {
        $notice = '';
        if ($message) {
            $notice = '<div class="notice">' . $this->escape($message) . '</div>';
        }

        $form = '<form method="get"><label>Pesquisar por matrícula<input type="text" name="matricula" value="' . $this->escape($matricula) . '" required></label><button type="submit">Buscar</button></form>';

        if (!$user) {
            return $notice . $form;
        }

        return $notice . $form . $this->renderValidationCard($user);
    }

    private function renderValidationCard(array $user): string
    {
        $dataEmissao = $user['data_emissao'] ?: 'Não definido';
        $dataVencimento = $user['data_vencimento'] ?: 'Não definido';
        return '<section><h2>Associado encontrado</h2><table><tr><th>Nome</th><td>' . $this->escape($user['nome']) . '</td></tr><tr><th>Matrícula</th><td>' . $this->escape($user['matricula']) . '</td></tr><tr><th>E-mail</th><td>' . $this->escape($user['email']) . '</td></tr><tr><th>Status</th><td class="' . $this->escape('status-' . str_replace(' ', '-', $user['status'])) . '">' . $this->escape($user['status']) . '</td></tr><tr><th>Emissão</th><td>' . $this->escape($dataEmissao) . '</td></tr><tr><th>Vencimento</th><td>' . $this->escape($dataVencimento) . '</td></tr></table><p>QR Code token: <strong>' . $this->escape($user['qr_token']) . '</strong></p></section>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
