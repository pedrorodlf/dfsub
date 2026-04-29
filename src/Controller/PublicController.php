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
        // Adicionado: min-height: 100vh; background-attachment: fixed; background-size: cover;
        $css = '@import url(\'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap\');body{font-family:\'Inter\',sans-serif;background:linear-gradient(135deg, #10182b 0%, #1f2f64 100%);color:#334155;margin:0;padding:0;line-height:1.6;min-height:100vh;background-attachment:fixed;background-size:cover;background-repeat:no-repeat}main{max-width:1000px;margin:40px auto;padding:40px;background:#ffffff;border-radius:12px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05)}.brand-header{text-align:center;margin-bottom:24px}.brand-header a{font-size:1.75rem;font-weight:800;color:#0f172a;text-decoration:none;letter-spacing:-0.5px}.brand-header a span{color:#2563eb}h1{font-size:1.75rem;margin-top:0;margin-bottom:24px;text-align:center;color:#0f172a;font-weight:700}h2{font-size:1.25rem;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-top:32px}input,select,button{font-family:inherit;font-size:1rem;padding:12px 16px;margin:8px 0 16px;width:100%;box-sizing:border-box;border-radius:8px;border:1px solid #cbd5e1;transition:all 0.2s ease;background:#fff}input:focus,select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.2)}input[readonly],input[disabled]{background:#f1f5f9;color:#64748b;cursor:not-allowed}label{display:block;margin-top:12px;font-weight:500;color:#475569;font-size:0.9rem}button{background:#2563eb;color:#fff;border:none;cursor:pointer;font-weight:600;padding:14px;margin-top:16px;box-shadow:0 4px 6px -1px rgba(37,99,235,0.2)}button:hover{background:#1d4ed8;transform:translateY(-1px);box-shadow:0 6px 8px -1px rgba(37,99,235,0.3)}button[value="delete"]{background:#ef4444!important}button[value="delete"]:hover{background:#dc2626!important}table{width:100%;border-collapse:separate;border-spacing:0;margin-top:24px;overflow:hidden;border-radius:8px;border:1px solid #e2e8f0;font-size:0.95rem}th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #e2e8f0}th{background:#f8fafc;font-weight:600;color:#475569}tr:last-child td{border-bottom:none}a{color:#2563eb;text-decoration:none;font-weight:500;transition:color 0.2s}a:hover{color:#1d4ed8}hr{border:0;height:1px;background:#e2e8f0;margin:32px 0}.status-Ativo{color:#15803d;background:#dcfce7;padding:4px 12px;border-radius:99px;font-size:0.85rem;display:inline-block;font-weight:600;white-space:nowrap;line-height:1.4}.status-Aguardando-verificação{color:#b45309;background:#fef3c7;padding:4px 12px;border-radius:99px;font-size:0.85rem;display:inline-block;font-weight:600;white-space:nowrap;line-height:1.4}.status-Vencido{color:#b91c1c;background:#fee2e2;padding:4px 12px;border-radius:99px;font-size:0.85rem;display:inline-block;font-weight:600;white-space:nowrap;line-height:1.4}.notice{background:#f0f9ff;border-left:4px solid #3b82f6;padding:16px;margin-bottom:24px;border-radius:0 8px 8px 0;color:#0369a1}.notice[style*="background-color: #eafbe6"]{background:#f0fdf4 !important;border-color:#22c55e !important;color:#166534 !important}.notice[style*="background:#ffefef"]{background:#fef2f2 !important;border-color:#ef4444 !important;color:#991b1b !important}@media (max-width:768px){main{margin:20px;padding:24px}table{display:block;overflow-x:auto;white-space:nowrap}}';
        
        // Favicons adicionados na head
        $favicons = '
        <link rel="icon" href="/var/assets/img/cropped-favicon-32x32.png" sizes="32x32" />
        <link rel="icon" href="/var/assets/img/cropped-favicon-192x192.png" sizes="192x192" />
        <link rel="apple-touch-icon" href="/var/assets/img/cropped-favicon-180x180.png" />';

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>' . $this->escape($title) . '</title><meta name="viewport" content="width=device-width, initial-scale=1">' . $favicons . '<style>' . $css . ' .brand-header img{max-height:64px;display:block;margin:0 auto 12px}</style></head><body><main><div class="brand-header"><img src="/var/assets/img/logo.png" onerror="this.style.display=\'none\'" alt="Logo"><br><a href="/">DF<span>SUB</span></a></div><h1>' . $this->escape($title) . '</h1>' . $content . '</main></body></html>';
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
        $fotoPath = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 160'%3E%3Crect width='120' height='160' fill='%23e9ecef'/%3E%3Ccircle cx='60' cy='55' r='30' fill='%23ccc'/%3E%3Cpath d='M20 160c0-35 15-55 40-55s40 20 40 55' fill='%23ccc'/%3E%3C/svg%3E";
        $uploadDir = __DIR__ . '/../../uploads/fotos';
        $fotoFiles = glob($uploadDir . '/user_' . $user['id'] . '.*');
        if ($fotoFiles) {
            $fotoPath = '/uploads/fotos/' . basename($fotoFiles[0]);
        }

        $dataEmissao = $user['data_emissao'] ? date('d/m/Y', strtotime($user['data_emissao'])) : 'Não definido';
        $dataVencimento = $user['data_vencimento'] ? date('d/m/Y', strtotime($user['data_vencimento'])) : 'Não definido';
        
        // Coloquei a classe status- em um <span> com vertical-align no <td>, idêntico ao que fizemos no painel admin!
        return '<section><h2>Associado encontrado</h2><div style="display:flex; gap: 20px; align-items:flex-start; flex-wrap: wrap; margin-bottom: 16px;"><div><img src="' . $this->escape($fotoPath) . '" alt="Foto 3x4" style="border:1px solid #ccc; padding:4px; background:#fff; border-radius:4px; width:120px; height:160px; object-fit: cover;"></div><table style="flex: 1; min-width: 280px; margin-top: 0;"><tr><th>Nome</th><td>' . $this->escape($user['nome']) . '</td></tr><tr><th>Matrícula</th><td>' . $this->escape($user['matricula']) . '</td></tr><tr><th>E-mail</th><td>' . $this->escape($user['email']) . '</td></tr><tr><th>Status</th><td style="vertical-align: middle;"><span class="' . $this->escape('status-' . str_replace(' ', '-', $user['status'])) . '">' . $this->escape($user['status']) . '</span></td></tr><tr><th>Emissão</th><td>' . $this->escape($dataEmissao) . '</td></tr><tr><th>Vencimento</th><td>' . $this->escape($dataVencimento) . '</td></tr></table></div><p>QR Code token: <strong>' . $this->escape($user['qr_token']) . '</strong></p></section>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
